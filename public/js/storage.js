/**
 * Local Storage Utility
 * Simple wrapper for localStorage with JSON support
 */

if (typeof storage === 'undefined') {
    window.storage = {
        set: function(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (error) {
                console.error('Error saving to storage:', error);
                return false;
            }
        },

        get: function(key) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : null;
            } catch (error) {
                console.error('Error reading from storage:', error);
                return null;
            }
        },

        remove: function(key) {
            try {
                localStorage.removeItem(key);
                return true;
            } catch (error) {
                console.error('Error removing from storage:', error);
                return false;
            }
        },

        clear: function() {
            try {
                // Non-destructive clear (Only client-side keys)
                localStorage.removeItem('client_user');
                localStorage.removeItem('client_auth_token');
                localStorage.removeItem('redirect_after_login');
                return true;
            } catch (error) {
                console.error('Error clearing storage:', error);
                return false;
            }
        },

        getRoleKeys: function() {
            const currentPath = window.location.pathname;
            if (currentPath.includes('/admin/')) {
                return { user: 'admin_user', token: 'admin_auth_token' };
            } else if (currentPath.includes('/client/')) {
                return { user: 'client_user', token: 'client_auth_token' };
            }
            return { user: 'user', token: 'auth_token' };
        },

        getUser: function() {
            const keys = this.getRoleKeys();
            return this.get(keys.user);
        },

        setUser: function(userData) {
            const keys = this.getRoleKeys();
            if (userData.token) {
                this.set(keys.token, userData.token);
            }
            return this.set(keys.user, userData);
        }
    };
}

// Global helper for role-specific keys
if (typeof getRoleKeys === 'undefined') {
    window.getRoleKeys = function() {
        return window.storage.getRoleKeys();
    };
}

