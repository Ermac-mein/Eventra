// Notification Manager for Admin
class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.pollingInterval = null;
        this.pollingDelay = 10000; // 10 seconds
    }

    async fetchNotifications() {
        try {
            const response = await fetch('../../api/notifications/get-admin-notifications.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Failed to fetch notifications');
            }

            const result = await response.json();
            
            if (result.success) {
                this.notifications = result.notifications;
                this.unreadCount = result.unread_count;
                this.updateNotificationUI();
                return result;
            } else {
                console.error('Failed to fetch notifications:', result.message);
                return null;
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return null;
        }
    }

    updateNotificationUI() {
        // Update badge
        this.updateNotificationBadge();
        
        // Update drawer content
        this.renderNotifications();
    }

    updateNotificationBadge() {
        const bellIcon = document.querySelector('.action-icon:first-child');
        if (!bellIcon) return;

        // Remove existing badge
        const existingBadge = bellIcon.querySelector('.notification-badge');
        if (existingBadge) {
            existingBadge.remove();
        }

        // Add badge if there are unread notifications
        if (this.unreadCount > 0) {
            const badge = document.createElement('span');
            badge.className = 'notification-badge';
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            bellIcon.style.position = 'relative';
            bellIcon.appendChild(badge);
        }
    }

    renderNotifications() {
        const notifDrawer = document.getElementById('notificationsDrawer');
        if (!notifDrawer) return;

        const drawerContent = notifDrawer.querySelector('.drawer-content');
        if (!drawerContent) return;

        // Clear existing content
        drawerContent.innerHTML = '';

        if (this.notifications.length === 0) {
            drawerContent.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #7f8c8d;">
                    <span style="font-size: 3rem;">🔔</span>
                    <p style="margin-top: 1rem;">No notifications yet</p>
                </div>
            `;
            return;
        }

        // Add Clear All Button
        const headerActions = document.createElement('div');
        headerActions.style.cssText = 'padding: 1rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: flex-end;';
        headerActions.innerHTML = `<button onclick="window.notificationManager.clearAll()" style="color: #e74c3c; background: none; border: 1px solid #e74c3c; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">Clear All</button>`;
        drawerContent.appendChild(headerActions);

        // Container for notifications
        const listContainer = document.createElement('div');
        listContainer.className = 'notif-list-container';
        drawerContent.appendChild(listContainer);

        // Render each notification
        this.notifications.forEach(notif => {
            const notifItem = this.createNotificationElement(notif);
            listContainer.appendChild(notifItem);
        });
    }

    createNotificationElement(notif) {
        const notifItem = document.createElement('div');
        notifItem.className = 'notif-item';
        if (!notif.is_read) {
            notifItem.classList.add('unread');
        }

        // Format date and time
        const date = new Date(notif.created_at);
        const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const dateStr = date.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });

        // Icon based on notification type
        const icons = {
            info: '🔔',
            success: '✓',
            warning: '⚠',
            error: '✕'
        };

        notifItem.innerHTML = `
            <div class="notif-icon">${icons[notif.type] || icons.info}</div>
            <div class="notif-body">
                <p class="notif-text">${notif.message}</p>
                <div class="notif-meta">
                    <span>${timeStr}</span>
                    <span>${dateStr}</span>
                </div>
            </div>
        `;

        return notifItem;
    }

    async markAsRead(notificationId = null) {
        try {
            const body = notificationId 
                ? { notification_id: notificationId }
                : { mark_all: true };

            const response = await fetch('../../api/notifications/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(body)
            });

            const result = await response.json();
            
            if (result.success) {
                // Refresh notifications
                await this.fetchNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async clearAll() {
        const result = await Swal.fire({
            title: 'Clear All Notifications?',
            text: 'Are you sure you want to delete all your notifications? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Yes, Clear All',
            cancelButtonText: 'Cancel'
        });

        if (!result.isConfirmed) return;
        
        try {
            const response = await fetch('../../api/notifications/clear-all.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            if (result.success) {
                this.notifications = [];
                this.unreadCount = 0;
                this.updateNotificationUI();
                if (typeof showToast === 'function') showToast('All notifications cleared', 'success');
            }
        } catch (error) {
            console.error('Error clearing notifications:', error);
        }
    }

    startPolling() {
        // Initial fetch
        this.fetchNotifications();

        // Set up polling
        this.pollingInterval = setInterval(() => {
            this.fetchNotifications();
        }, this.pollingDelay);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }
}

// Create global instance
window.notificationManager = new NotificationManager();

// Auto-start polling when page loads
document.addEventListener('DOMContentLoaded', () => {
    if (window.notificationManager) {
        window.notificationManager.startPolling();
    }
});
