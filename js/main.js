// in file: htdocs/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');
    const countBadge = document.getElementById('notification-count');
    const notificationList = document.getElementById('notification-list');

    if (!bell) return; // Don't run if the bell isn't on the page (e.g., login page)

    function fetchNotifications() {
        fetch('/api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationUI(data.unread_count, data.notifications);
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    function updateNotificationUI(unreadCount, notifications) {
        // Update badge
        if (unreadCount > 0) {
            countBadge.textContent = unreadCount;
            countBadge.style.display = 'block';
        } else {
            countBadge.style.display = 'none';
        }

        // Update dropdown list
        notificationList.innerHTML = ''; // Clear old list
        if (notifications.length === 0) {
            const noNotif = document.createElement('a');
            noNotif.href = '#';
            noNotif.innerHTML = '<div class="notification-item">No notifications.</div>';
            notificationList.appendChild(noNotif);
        } else {
            notifications.forEach(notif => {
                const item = document.createElement('a');
                item.href = notif.request_id ? `/requests/view.php?id=${notif.request_id}` : '#';
                item.innerHTML = `
                    <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}">
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${timeAgo(notif.created_at)}</div>
                    </div>
                `;
                notificationList.appendChild(item);
            });
        }
    }

    bell.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';

        if (!isVisible && countBadge.style.display === 'block') { // If we just opened it and there were unread notifications
            markNotificationsAsRead();
        }
    });

    function markNotificationsAsRead() {
        // Optimistically update UI
        const currentCount = parseInt(countBadge.textContent, 10);
        if(currentCount > 0) {
            countBadge.style.display = 'none';
        
            fetch('/api/mark_notifications_read.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        // If it failed, maybe refetch to get the real count
                        fetchNotifications();
                    }
                });
        }
    }

    // Close dropdown if clicking outside
    document.addEventListener('click', function(event) {
        if (dropdown.style.display === 'block' && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    // Helper to format time
    function timeAgo(dateString) {
        const date = new Date(dateString + " UTC"); // Treat date from DB as UTC
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        if (seconds < 10) return "just now";
        return Math.floor(seconds) + " seconds ago";
    }

    // Initial fetch and periodic polling
    fetchNotifications();
    setInterval(fetchNotifications, 15000); // Check for new notifications every 15 seconds
});