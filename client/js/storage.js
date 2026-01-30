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
                localStorage.clear();
                return true;
            } catch (error) {
                console.error('Error clearing storage:', error);
                return false;
            }
        }
    };
}

