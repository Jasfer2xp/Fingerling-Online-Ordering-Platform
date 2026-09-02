/**
 * Countdown Timer
 * // updated countdown to use dynamic deadline
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find all countdown elements
    const countdowns = document.querySelectorAll('.countdown-timer');
    
    countdowns.forEach(element => {
        // Read the deadline from data attribute
        const deadlineStr = element.getAttribute('data-deadline');
        if (!deadlineStr) return;
        
        const deadline = new Date(deadlineStr).getTime();
        
        // Update functionality
        const updateTimer = () => {
            const now = new Date().getTime();
            const distance = deadline - now;
            
            // If expired
            if (distance < 0) {
                element.innerHTML = '<span class="text-danger">Expired</span>';
                element.classList.add('expired');
                clearInterval(interval);
                return;
            }
            
            // Calculate time parts
            // 2-minute window is short, so we focus on MM:SS if under an hour, or full format otherwise
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Format output (mm:ss)
            let output = '';
            
            if (days > 0) {
                output += days + 'd ';
            }
            
            if (hours > 0 || days > 0) {
                output += String(hours).padStart(2, '0') + ':';
            }
            
            output += String(minutes).padStart(2, '0') + ':';
            output += String(seconds).padStart(2, '0');
            
            // Display
            element.innerText = output;
            
            // Add urgency visual if under 1 minute for 2-minute windows
            if (distance < 60000) { // Less than 1 minute
                element.classList.add('text-danger');
                element.classList.add('fw-bold');
            }
        };
        
        // Run immediately and then interval
        updateTimer();
        const interval = setInterval(updateTimer, 1000);
    });
});
