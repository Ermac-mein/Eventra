/**
 * Schedule Notification Checker for Client Dashboard
 * Checks for upcoming events and triggers browser notifications
 */

class ScheduleNotificationChecker {
    constructor() {
        this.checkInterval = null;
        this.checkDelay = 60000; // Check every 1 minute
        this.notifiedEvents = new Set(); // Track already notified events
        this.requestPermission();
    }

    requestPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        }
    }

    async checkUpcomingEvents() {
        try {
            const response = await apiFetch('../../api/events/get-upcoming-events.php');

            const result = await response.json();
            
            if (result.success && result.events) {
                this.processEvents(result.events);
            }
        } catch (error) {
            console.error('Error checking upcoming events:', error);
        }
    }

    processEvents(events) {
        const now = new Date();
        const notificationWindow = 15 * 60 * 1000; // 15 minutes before event

        events.forEach(event => {
            const eventDateTime = new Date(`${event.event_date} ${event.event_time}`);
            const timeDiff = eventDateTime - now;

            // Notify if event is within 15 minutes and hasn't been notified yet
            if (timeDiff > 0 && timeDiff <= notificationWindow && !this.notifiedEvents.has(event.id)) {
                this.showNotification(event, timeDiff);
                this.notifiedEvents.add(event.id);
            }

            // Also notify when event time is reached (within 1 minute)
            if (Math.abs(timeDiff) <= 60000 && !this.notifiedEvents.has(`${event.id}-started`)) {
                this.showEventStartNotification(event);
                this.notifiedEvents.add(`${event.id}-started`);
            }
        });
    }

    showNotification(event, timeDiff) {
        const minutesUntil = Math.floor(timeDiff / 60000);
        
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification('Upcoming Event Reminder', {
                body: `${event.event_name} starts in ${minutesUntil} minutes!\nLocation: ${event.state}`,
                icon: event.image_path || '/public/assets/logo.png',
                badge: '/public/assets/logo.png',
                tag: `event-${event.id}`,
                requireInteraction: true
            });

            notification.onclick = () => {
                window.focus();
                window.location.href = `events.html?event=${event.id}`;
                notification.close();
            };
        }

        // Also show in-app notification
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Upcoming Event!',
                html: `<strong>${event.event_name}</strong><br>Starts in ${minutesUntil} minutes<br>📍 ${event.state}`,
                icon: 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true
            });
        }
    }

    showEventStartNotification(event) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification('Event Starting Now!', {
                body: `${event.event_name} is starting now!\nLocation: ${event.state}`,
                icon: event.image_path || '/public/assets/logo.png',
                badge: '/public/assets/logo.png',
                tag: `event-start-${event.id}`,
                requireInteraction: true
            });

            notification.onclick = () => {
                window.focus();
                window.location.href = `events.html?event=${event.id}`;
                notification.close();
            };
        }

        // Also show in-app notification
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Event Starting Now!',
                html: `<strong>${event.event_name}</strong><br>The event is starting now!<br>📍 ${event.state}`,
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 7000,
                timerProgressBar: true
            });
        }
    }

    startChecking() {
        // Initial check
        this.checkUpcomingEvents();

        // Set up periodic checking
        this.checkInterval = setInterval(() => {
            this.checkUpcomingEvents();
        }, this.checkDelay);

        //console.log('Schedule notification checker started');
    }

    stopChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
          //  console.log('Schedule notification checker stopped');
        }
    }
}

// Create global instance
window.scheduleNotificationChecker = new ScheduleNotificationChecker();

// Auto-start checking when page loads (only for client dashboard)
document.addEventListener('DOMContentLoaded', () => {
    // Only run on client dashboard pages
    if (window.location.pathname.includes('/client/')) {
        if (window.scheduleNotificationChecker) {
            window.scheduleNotificationChecker.startChecking();
        }
    }
});
