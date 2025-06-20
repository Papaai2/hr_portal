// Enhanced dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard widgets
    initializeCalendarWidget();
    initializeLeaveBalanceProgress();
    initializeQuickStats();
    
    function initializeCalendarWidget() {
        const calendarContainer = document.getElementById('calendar-widget');
        if (!calendarContainer) return;
        
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        renderCalendar(currentYear, currentMonth);
        loadLeaveData(currentYear, currentMonth + 1); // JS months are 0-indexed
    }
    
    function renderCalendar(year, month) {
        const calendarGrid = document.querySelector('.calendar-grid');
        if (!calendarGrid) return;
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();
        
        calendarGrid.innerHTML = '';
        
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = day;
            calendarGrid.appendChild(header);
        });
        
        for (let i = 0; i < startingDayOfWeek; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day empty';
            calendarGrid.appendChild(emptyDay);
        }
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            const leaveEntries = document.createElement('div');
            leaveEntries.className = 'leave-entries';
            dayElement.appendChild(leaveEntries);
            
            const dayOfWeek = new Date(year, month, day).getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                dayElement.classList.add('weekend');
            }
            
            calendarGrid.appendChild(dayElement);
        }
    }
    
    function loadLeaveData(year, month) {
        fetch(`/api/get_leave_calendar.php?year=${year}&month=${month}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    markLeavedays(data.leaveDays);
                }
            })
            .catch(error => console.error('Error loading leave data:', error));
    }
    
    function markLeavedays(leaveDays) {
        const userRole = document.body.dataset.userRole || 'user';
        const canSeeNames = ['admin', 'hr_manager', 'manager'].includes(userRole);

        const leavesByDate = leaveDays.reduce((acc, leave) => {
            if (!acc[leave.date]) {
                acc[leave.date] = [];
            }
            acc[leave.date].push(leave);
            return acc;
        }, {});

        document.querySelectorAll('.leave-entries').forEach(e => e.innerHTML = '');
        document.querySelectorAll('.calendar-day.has-leave').forEach(d => d.classList.remove('has-leave'));

        for (const date in leavesByDate) {
            const dayElement = document.querySelector(`[data-date="${date}"]`);
            if (dayElement) {
                const leaves = leavesByDate[date];
                const hoverTitle = leaves.map(l => `${l.employee}: ${l.type}`).join('\n');
                
                dayElement.classList.add('has-leave');
                dayElement.title = hoverTitle;

                if (canSeeNames) {
                    const entriesContainer = dayElement.querySelector('.leave-entries');
                    if (entriesContainer) {
                        leaves.forEach(leave => {
                            const entry = document.createElement('div');
                            entry.className = 'leave-entry';
                            entry.textContent = leave.employee;
                            entriesContainer.appendChild(entry);
                        });
                    }
                }
            }
        }
    }
    
    function initializeLeaveBalanceProgress() {
        const balanceItems = document.querySelectorAll('.list-group-item');
        balanceItems.forEach(item => {
            const balanceText = item.querySelector('.badge');
            if (balanceText) {
                const balance = parseFloat(balanceText.textContent);
                const maxBalance = 30;
                const percentage = Math.min((balance / maxBalance) * 100, 100);
                
                const progressBar = document.createElement('div');
                progressBar.className = 'balance-progress';
                progressBar.innerHTML = `<div class="balance-progress-bar" style="width: ${percentage}%"></div>`;
                
                item.appendChild(progressBar);
            }
        });
    }
    
    function initializeQuickStats() {
        fetch('/api/get_dashboard_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardStats(data.stats);
                }
            })
            .catch(error => console.error('Error loading dashboard stats:', error));
    }
    
    function updateDashboardStats(stats) {
        const statsContainer = document.getElementById('dashboard-stats');
        if (!statsContainer) return;
        
        statsContainer.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">${stats.pendingRequests || 0}</div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">${stats.approvedThisMonth || 0}</div>
                        <div class="stat-label">Approved This Month</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">${stats.teamSize || 0}</div>
                        <div class="stat-label">Team Members</div>
                    </div>
                </div>
            </div>
        `;
    }
});// Enhanced dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard widgets
    initializeCalendarWidget();
    initializeLeaveBalanceProgress();
    initializeQuickStats();
    
    function initializeCalendarWidget() {
        const calendarContainer = document.getElementById('calendar-widget');
        if (!calendarContainer) return;
        
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        renderCalendar(currentYear, currentMonth);
        loadLeaveData(currentYear, currentMonth + 1); // JS months are 0-indexed
    }
    
    function renderCalendar(year, month) {
        const calendarGrid = document.querySelector('.calendar-grid');
        if (!calendarGrid) return;
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();
        
        calendarGrid.innerHTML = '';
        
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = day;
            calendarGrid.appendChild(header);
        });
        
        for (let i = 0; i < startingDayOfWeek; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day empty';
            calendarGrid.appendChild(emptyDay);
        }
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            const leaveEntries = document.createElement('div');
            leaveEntries.className = 'leave-entries';
            dayElement.appendChild(leaveEntries);
            
            const dayOfWeek = new Date(year, month, day).getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                dayElement.classList.add('weekend');
            }
            
            calendarGrid.appendChild(dayElement);
        }
    }
    
    function loadLeaveData(year, month) {
        fetch(`/api/get_leave_calendar.php?year=${year}&month=${month}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    markLeavedays(data.leaveDays);
                }
            })
            .catch(error => console.error('Error loading leave data:', error));
    }
    
    function markLeavedays(leaveDays) {
        const userRole = document.body.dataset.userRole || 'user';
        const canSeeNames = ['admin', 'hr_manager', 'manager'].includes(userRole);

        const leavesByDate = leaveDays.reduce((acc, leave) => {
            if (!acc[leave.date]) {
                acc[leave.date] = [];
            }
            acc[leave.date].push(leave);
            return acc;
        }, {});

        document.querySelectorAll('.leave-entries').forEach(e => e.innerHTML = '');
        document.querySelectorAll('.calendar-day.has-leave').forEach(d => d.classList.remove('has-leave'));

        for (const date in leavesByDate) {
            const dayElement = document.querySelector(`[data-date="${date}"]`);
            if (dayElement) {
                const leaves = leavesByDate[date];
                const hoverTitle = leaves.map(l => `${l.employee}: ${l.type}`).join('\n');
                
                dayElement.classList.add('has-leave');
                dayElement.title = hoverTitle;

                if (canSeeNames) {
                    const entriesContainer = dayElement.querySelector('.leave-entries');
                    if (entriesContainer) {
                        leaves.forEach(leave => {
                            const entry = document.createElement('div');
                            entry.className = 'leave-entry';
                            entry.textContent = leave.employee;
                            entriesContainer.appendChild(entry);
                        });
                    }
                }
            }
        }
    }
    
    function initializeLeaveBalanceProgress() {
        const balanceItems = document.querySelectorAll('.list-group-item');
        balanceItems.forEach(item => {
            const balanceText = item.querySelector('.badge');
            if (balanceText) {
                const balance = parseFloat(balanceText.textContent);
                const maxBalance = 30;
                const percentage = Math.min((balance / maxBalance) * 100, 100);
                
                const progressBar = document.createElement('div');
                progressBar.className = 'balance-progress';
                progressBar.innerHTML = `<div class="balance-progress-bar" style="width: ${percentage}%"></div>`;
                
                item.appendChild(progressBar);
            }
        });
    }
    
    function initializeQuickStats() {
        fetch('/api/get_dashboard_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardStats(data.stats);
                }
            })
            .catch(error => console.error('Error loading dashboard stats:', error));
    }
    
    function updateDashboardStats(stats) {
        const statsContainer = document.getElementById('dashboard-stats');
        if (!statsContainer) return;
        
        statsContainer.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">${stats.pendingRequests || 0}</div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">${stats.approvedThisMonth || 0}</div>
                        <div class="stat-label">Approved This Month</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">${stats.teamSize || 0}</div>
                        <div class="stat-label">Team Members</div>
                    </div>
                </div>
            </div>
        `;
    }
});