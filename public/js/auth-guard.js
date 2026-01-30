
(function() {
    const user = storage.get('user');
    const token = storage.get('auth_token');
    const currentPath = window.location.pathname;

    // Determine required role based on path
    let requiredRole = null;
    if (currentPath.includes('/admin/')) {
        requiredRole = 'admin';
    } else if (currentPath.includes('/client/')) {
        requiredRole = 'client';
    }

    // Basic client-side check
    if (!user || !token || (requiredRole && user.role !== requiredRole)) {
        console.warn('Unauthorized access attempt or invalid role.');
        storage.remove('user');
        storage.remove('auth_token');
        window.location.href = '/public/pages/login.html';
        return;
    }

    // We can do this periodically or on every page load
    async function verifySession() {
        try {
            const response = await fetch('/api/notifications/realtime.php', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            if (response.status === 401 || response.status === 403) {
                storage.remove('user');
                storage.remove('auth_token');
                window.location.href = '/public/pages/login.html';
            }
        } catch (error) {
            console.error('Session verification failed:', error);
        }
    }

    // verifySession(); // Enabled for real-time check

    // Make logout globally available
    window.logout = async function() {
        try {
            const response = await fetch('/api/auth/logout.php', {
                method: 'POST'
            });
            const result = await response.json();
            if (result.success) {
                storage.remove('user');
                storage.remove('auth_token');
                window.location.href = '/public/pages/login.html';
            } else {
                alert('Logout failed: ' + result.message);
            }
        } catch (error) {
            console.error('Logout error:', error);
            // Fallback: clear local storage anyway
            storage.remove('user');
            storage.remove('auth_token');
            window.location.href = '/public/pages/login.html';
        }
    };

    // Update UI with user info if elements exist
    document.addEventListener('DOMContentLoaded', () => {
        const userNameDisplays = document.querySelectorAll('.user-name-display');
        const userAvatarDisplays = document.querySelectorAll('.user-avatar-display');

        userNameDisplays.forEach(el => el.textContent = user.name);
        userAvatarDisplays.forEach(el => {
            if (user.profile_pic) {
                el.style.backgroundImage = `url(${user.profile_pic})`;
                el.style.backgroundSize = 'cover';
            }
        });
    });
})();
