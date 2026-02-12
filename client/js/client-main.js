/**
 * Shared Client JavaScript
 * Common functionality across all client pages
 */

// Initialize logout functionality
document.addEventListener('DOMContentLoaded', () => {
    initLogout();
    // initNotifications(); // Handled by drawer-system.js
    initSearch();
    initProfileClick();
    loadGlobalProfile();
});

/**
 * Loads the user profile globally to update header avatar
 */
async function loadGlobalProfile() {
    try {
        // Detect role from path
        const isClient = window.location.pathname.includes('/client/');
        const isAdmin = window.location.pathname.includes('/admin/');
        
        const keys = {
            user: isClient ? 'client_user' : (isAdmin ? 'admin_user' : 'user'),
            token: isClient ? 'client_auth_token' : (isAdmin ? 'admin_auth_token' : 'auth_token')
        };

        // Check if we have user data in storage first for immediate display
        const storedUser = storage.get(keys.user);
        if (storedUser) {
            updateGlobalAvatar(storedUser);
            updateClientNameDisplay(storedUser);
        }

        // Fetch fresh data
        const response = await fetch('../../api/users/get-profile.php', {
            credentials: 'include'
        });
        
        if (response.status === 401) {
            window.location.href = '../../client/pages/clientLogin.html';
            return;
        }

        const result = await response.json();

        if (result.success) {
            const keys = typeof getRoleKeys === 'function' ? getRoleKeys() : { user: 'user', token: 'auth_token' };
            storage.set(keys.user, result.user);
            updateGlobalAvatar(result.user);
        }
    } catch (error) {
        console.error('Error loading global profile:', error);
    }
}

function updateGlobalAvatar(user) {
    const avatars = document.querySelectorAll('.user-avatar');
    avatars.forEach(avatar => {
        if (user.profile_pic) {
            avatar.style.backgroundImage = `url(${user.profile_pic})`;
        } else {
            // Fallback to UI Avatars
            const name = user.name || user.business_name || 'User';
            const defaultAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&color=fff`;
            avatar.style.backgroundImage = `url(${defaultAvatar})`;
        }
        avatar.style.backgroundSize = 'cover';
        avatar.style.backgroundPosition = 'center';
    });
}

/**
 * Global logout function
 * Clears all storage, stops polling, and redirects to login
 */
async function logout() {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of your session!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'Yes, logout!'
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        // Call server-side logout
        await fetch('../../api/auth/logout.php');

        // Stop notification polling
        if (window.notificationManager) {
            window.notificationManager.stopPolling();
        }

        // Clear ONLY client storage
        storage.remove('client_user');
        storage.remove('client_auth_token');
        sessionStorage.clear();

        // Hard redirect to login
        window.location.href = '../../client/pages/clientLogin.html';
    } catch (error) {
        console.error('Logout error:', error);
        // Clean up and redirect anyway
        storage.remove('client_user');
        storage.remove('client_auth_token');
        window.location.href = '../../client/pages/clientLogin.html';
    }
}

// Make logout globally accessible
window.logout = logout;

function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });
    }

    // Also attach to any logout links
    document.querySelectorAll('.logout-link, [href*="logout"]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });
    });

    // Centralized Global Listeners (Export, Profile)
    document.addEventListener('click', (e) => {
        // Global Export Button
        if (e.target.closest('#globalExportBtn')) {
            const path = window.location.pathname;
            let dataType = 'events'; // default
            if (path.includes('tickets.html')) dataType = 'tickets';
            else if (path.includes('users.html')) dataType = 'users';
            else if (path.includes('media.html')) dataType = 'media';

            if (typeof showExportModal === 'function') {
                showExportModal(dataType);
            }
        }

        // Global Profile Click
        if (e.target.closest('.user-avatar')) {
            if (typeof showProfileEditModal === 'function') {
                showProfileEditModal();
            }
        }
    });
}


function initSearch() {
    const searchInput = document.querySelector('.header-search input');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(handleSearch, 300));
    }
}

async function handleSearch(e) {
    const query = e.target.value.trim();
    if (query.length < 2) return;

    // Implement search based on current page
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('events.html')) {
        await searchEvents(query);
    } else if (currentPage.includes('tickets.html')) {
        await searchTickets(query);
    } else if (currentPage.includes('users.html')) {
        await searchUsers(query);
    }
}

async function searchEvents(query) {
    try {
        const response = await fetch(`../../api/events/search-events.php?query=${encodeURIComponent(query)}`);
        const result = await response.json();

        if (result.success) {
            // Update events display
            console.log('Search results:', result.events);
            // TODO: Update UI with search results
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

async function searchTickets(query) {
    // TODO: Implement ticket search
    console.log('Searching tickets:', query);
}

async function searchUsers(query) {
    // TODO: Implement user search
    console.log('Searching users:', query);
}

function initProfileClick() {
    // Make user avatar clickable to open profile modal
    const userAvatar = document.querySelector('.user-avatar');
    if (userAvatar) {
        userAvatar.style.cursor = 'pointer';
        userAvatar.title = 'Click to edit profile';
        userAvatar.addEventListener('click', () => {
            if (typeof showProfileEditModal === 'function') {
                showProfileEditModal();
            }
        });
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

window.copyToClipboard = function(text, successMsg) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        if (typeof showNotification === 'function') {
            showNotification(successMsg, 'success');
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: successMsg,
                showConfirmButton: false,
                timer: 2000
            });
        }
    }).catch(err => {
        console.error('Failed to copy:', err);
        Swal.fire('Error', 'Failed to copy to clipboard', 'error');
    });
};

/**
 * Updates any elements showing the client name to avoid "undefined"
 */
function updateClientNameDisplay(user) {
    if (!user) return;
    const name = user.name || user.business_name || 'Client';
    
    // Update elements with class 'client-name' or 'profile-name'
    document.querySelectorAll('.client-name, #profileName').forEach(el => {
        el.textContent = name;
    });

    // Update greeting if it exists
    const greeting = document.querySelector('.greeting-text');
    if (greeting) {
        greeting.textContent = `Welcome, ${name}`;
    }
}
window.updateClientNameDisplay = updateClientNameDisplay;
