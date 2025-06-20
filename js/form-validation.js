// Enhanced client-side validation for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Date validation for vacation requests
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        startDateInput.min = today;
        endDateInput.min = today;
        
        startDateInput.addEventListener('change', function() {
            endDateInput.min = this.value;
            if (endDateInput.value && endDateInput.value < this.value) {
                endDateInput.value = this.value;
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (this.value < startDateInput.value) {
                this.value = startDateInput.value;
            }
        });
    }
    
    // Real-time leave balance calculation
    const leaveTypeSelect = document.getElementById('leave_type_id');
    if (leaveTypeSelect) {
        leaveTypeSelect.addEventListener('change', updateLeaveCalculation);
        startDateInput?.addEventListener('change', updateLeaveCalculation);
        endDateInput?.addEventListener('change', updateLeaveCalculation);
    }
    
    function updateLeaveCalculation() {
        const startDate = startDateInput?.value;
        const endDate = endDateInput?.value;
        const leaveType = leaveTypeSelect?.value;
        
        if (startDate && endDate && leaveType) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            // Show calculation preview
            showLeaveCalculation(diffDays, leaveType);
        }
    }
    
    function showLeaveCalculation(days, leaveTypeId) {
        let calculationDiv = document.getElementById('leave-calculation');
        if (!calculationDiv) {
            calculationDiv = document.createElement('div');
            calculationDiv.id = 'leave-calculation';
            calculationDiv.className = 'alert alert-info mt-3';
            leaveTypeSelect.parentNode.appendChild(calculationDiv);
        }
        
        // Get balance from select option text
        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
        const balanceMatch = selectedOption.text.match(/Balance: ([\d.]+)/);
        const currentBalance = balanceMatch ? parseFloat(balanceMatch[1]) : 0;
        
        const remainingBalance = currentBalance - days;
        const statusClass = remainingBalance >= 0 ? 'alert-info' : 'alert-warning';
        
        calculationDiv.className = `alert ${statusClass} mt-3`;
        calculationDiv.innerHTML = `
            <strong>Leave Calculation:</strong><br>
            Days requested: ${days}<br>
            Current balance: ${currentBalance} days<br>
            Remaining balance: ${remainingBalance} days
            ${remainingBalance < 0 ? '<br><strong>⚠️ Insufficient balance!</strong>' : ''}
        `;
    }
});