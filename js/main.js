// in file: htdocs/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    console.log("main.js loaded and DOMContentLoaded fired.");

    // --- Sidebar Toggle Functionality ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarWrapper = document.getElementById('sidebar-wrapper');

    if (sidebarToggle && sidebarWrapper) {
        sidebarToggle.addEventListener('click', function() {
            console.log("Sidebar toggle clicked.");
            sidebarWrapper.classList.toggle('show');
        });
    }

    // --- Notification Dropdown Fetch ---
    const notificationBell = document.getElementById('notification-bell');
    const notificationList = document.getElementById('notification-list');
    const notificationCount = document.getElementById('notification-count');

    if (notificationBell && notificationList && notificationCount) {
        // Function to fetch notifications
        const fetchNotifications = async () => {
            console.log("Fetching notifications...");
            try {
                const response = await fetch('/api/get_notifications.php');
                const data = await response.json();
                console.log("Notifications API response:", data);

                if (data.success) {
                    notificationList.innerHTML = ''; // Clear previous notifications
                    if (data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationItem = document.createElement('li');
                            notificationItem.innerHTML = `
                                <a class="dropdown-item ${notification.is_read == 0 ? 'unread' : ''}" href="/notifications.php?id=${notification.id}">
                                    <span class="notification-message">${notification.message}</span>
                                    <small class="notification-time">${new Date(notification.created_at + ' UTC').toLocaleString()}</small>
                                </a>
                            `;
                            notificationList.appendChild(notificationItem);
                        });
                        const unreadCount = data.notifications.filter(n => n.is_read == 0).length;
                        if (unreadCount > 0) {
                            notificationCount.textContent = unreadCount;
                            notificationCount.style.display = 'block';
                        } else {
                            notificationCount.style.display = 'none';
                        }
                    } else {
                        notificationList.innerHTML = '<li><span class="dropdown-item text-muted">No new notifications.</span></li>';
                        notificationCount.style.display = 'none';
                    }
                } else {
                    console.error('Notifications API reported failure:', data.message);
                }
            } catch (error) {
                console.error('Error fetching notifications:', error);
                notificationList.innerHTML = '<li><span class="dropdown-item text-danger">Failed to load notifications.</span></li>';
            }
        };

        notificationBell.addEventListener('click', fetchNotifications);
        fetchNotifications(); // Initial fetch on page load
    }

    // --- Dashboard Enhancements ---

    // Function to fetch and update dashboard stats
    const updateDashboardStats = async () => {
        const dashboardStatsDiv = document.getElementById('dashboard-stats');
        if (!dashboardStatsDiv) {
            console.log("Dashboard stats div not found. Not on dashboard page.");
            return;
        }
        console.log("Dashboard stats div found. Attempting to fetch stats...");

        try {
            const response = await fetch('/api/get_dashboard_stats.php');
            const stats = await response.json();
            console.log("Dashboard Stats API response:", stats);

            if (stats.success) {
                console.log("Stats API reported success.");
                // Assuming the structure within dashboard-stats has .stat-number and .stat-label
                const statCards = dashboardStatsDiv.querySelectorAll('.stat-card');
                console.log("Found stat cards:", statCards.length);

                if (statCards.length === 3) {
                    const pendingRequestsElement = statCards[0].querySelector('.stat-number');
                    const approvedThisMonthElement = statCards[1].querySelector('.stat-number');
                    const teamMembersElement = statCards[2].querySelector('.stat-number');

                    if (pendingRequestsElement) {
                        pendingRequestsElement.textContent = stats.stats.pendingRequests;
                        console.log("Updated Pending Requests:", stats.stats.pendingRequests);
                    } else {
                        console.warn("Pending Requests .stat-number element not found.");
                    }
                    if (approvedThisMonthElement) {
                        approvedThisMonthElement.textContent = stats.stats.approvedThisMonth;
                        console.log("Updated Approved This Month:", stats.stats.approvedThisMonth);
                    } else {
                        console.warn("Approved This Month .stat-number element not found.");
                    }
                    if (teamMembersElement) {
                        teamMembersElement.textContent = stats.stats.teamSize;
                        console.log("Updated Team Members:", stats.stats.teamSize);
                    } else {
                        console.warn("Team Members .stat-number element not found.");
                    }

                } else {
                    console.warn("Expected 3 stat cards but found:", statCards.length);
                }
            } else {
                console.error('Dashboard Stats API reported failure:', stats.message);
            }
        } catch (error) {
            console.error('Error fetching dashboard stats:', error);
        }
    };

    // Function to render the leave calendar
    const renderLeaveCalendar = async () => {
        const calendarWidget = document.getElementById('calendar-widget');
        if (!calendarWidget) {
            console.log("Calendar widget not found. Not rendering calendar.");
            return;
        }
        console.log("Calendar widget found. Attempting to render calendar...");

        const calendarGrid = calendarWidget.querySelector('.calendar-grid');
        if (!calendarGrid) {
            console.error("Calendar grid element not found inside calendar widget.");
            return;
        }

        // Clear existing content except for headers
        calendarGrid.innerHTML = `
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>
        `;
        console.log("Calendar grid headers re-created.");

        try {
            const response = await fetch('/api/get_leave_calendar.php');
            const data = await response.json();
            console.log("Leave Calendar API response:", data);

            if (data.success) {
                console.log("Leave Calendar API reported success.");
                const currentMonth = data.current_month;
                const currentYear = data.current_year;
                const leaveEvents = data.leave_events; // Use the correct key

                console.log(`Rendering calendar for: ${currentMonth}/${currentYear}`);
                console.log("Leave events to process:", leaveEvents);

                const firstDayOfMonth = new Date(currentYear, currentMonth - 1, 1);
                const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
                const startDay = firstDayOfMonth.getDay(); // 0 for Sunday, 1 for Monday, etc.

                // Update calendar header
                const monthName = new Date(currentYear, currentMonth - 1).toLocaleString('default', { month: 'long' });
                const calendarHeader = calendarWidget.querySelector('.calendar-header h3');
                if (calendarHeader) {
                    calendarHeader.textContent = `Team Leave Calendar - ${monthName} ${currentYear}`;
                } else {
                    console.warn("Calendar header h3 not found.");
                }

                // Add empty days for the start of the month
                for (let i = 0; i < startDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.classList.add('calendar-day', 'empty');
                    calendarGrid.appendChild(emptyDay);
                }

                // Add days of the month
                for (let day = 1; day <= daysInMonth; day++) {
                    const date = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const leaveForDay = leaveEvents.filter(event => 
                        date >= event.start_date && date <= event.end_date
                    );

                    const calendarDay = document.createElement('div');
                    calendarDay.classList.add('calendar-day');
                    
                    const dayNumber = document.createElement('span');
                    dayNumber.classList.add('day-number');
                    dayNumber.textContent = day;
                    calendarDay.appendChild(dayNumber);

                    // Add weekend class
                    const dateObj = new Date(currentYear, currentMonth - 1, day);
                    if (dateObj.getDay() === 0 || dateObj.getDay() === 6) { // Sunday (0) or Saturday (6)
                        calendarDay.classList.add('weekend');
                    }

                    if (leaveForDay.length > 0) {
                        calendarDay.classList.add('has-leave');
                        const leaveEntriesDiv = document.createElement('div');
                        leaveEntriesDiv.classList.add('leave-entries');
                        leaveForDay.forEach(leave => {
                            const leaveEntry = document.createElement('div');
                            leaveEntry.classList.add('leave-entry');
                            // Use leave.user_name as per PHP change
                            leaveEntry.textContent = leave.user_name; 
                            leaveEntriesDiv.appendChild(leaveEntry);
                        });
                        calendarDay.appendChild(leaveEntriesDiv);
                    }
                    calendarGrid.appendChild(calendarDay);
                }
            } else {
                console.error('Failed to load leave calendar from API:', data.message);
                calendarGrid.innerHTML = '<div class="col-12 text-center text-muted">Failed to load calendar. Check API response.</div>';
            }
        } catch (error) {
            console.error('Error fetching or processing leave calendar:', error);
            calendarGrid.innerHTML = '<div class="col-12 text-center text-muted">Error loading calendar data. See console for details.</div>';
        }
    };

    // Execute dashboard enhancements if on the dashboard page
    if (document.getElementById('dashboard-stats') || document.getElementById('calendar-widget')) {
        console.log("Dashboard elements found. Initiating stats and calendar updates.");
        updateDashboardStats();
        renderLeaveCalendar();
    } else {
        console.log("Not on dashboard page. Skipping dashboard enhancements.");
    }
});