/**
 * Shared Client JavaScript
 * Common functionality across all client pages
 */

// Initialize logout functionality
document.addEventListener('DOMContentLoaded', () => {
    initLogout();
    initNotifications();
    initSearch();
    initProfileClick();
});

/**
 * Global logout function
 * Clears all storage, stops polling, and redirects to login
 */
async function logout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }

    try {
        // Call server-side logout
        await fetch('../../api/auth/logout.php');

        // Stop notification polling
        if (window.notificationManager) {
            window.notificationManager.stopPolling();
        }

        // Clear all storage
        localStorage.clear();
        sessionStorage.clear();

        // Hard redirect to login
        window.location.href = '../../public/pages/login.html';
    } catch (error) {
        console.error('Logout error:', error);
        // Force clear and redirect anyway
        localStorage.clear();
        sessionStorage.clear();
        window.location.href = '../../public/pages/login.html';
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
            const page = window.location.pathname.split('/').pop();
            if (page.includes('dashboard')) {
                if (typeof exportEventsToPDF === 'function') exportEventsToPDF();
            } else if (page.includes('events')) {
                if (typeof exportEventsToPDF === 'function') exportEventsToPDF();
            } else if (page.includes('tickets')) {
                if (typeof exportTicketsToPDF === 'function') exportTicketsToPDF();
            } else if (page.includes('users')) {
                if (typeof exportUsersToPDF === 'function') exportUsersToPDF();
            } else if (page.includes('media')) {
                if (typeof exportMediaToPDF === 'function') exportMediaToPDF();
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

function initNotifications() {
    const notificationIcon = document.querySelector('[data-drawer="notifications"]');
    if (notificationIcon) {
        notificationIcon.addEventListener('click', async () => {
            await loadNotifications();
        });
    }
}

async function loadNotifications() {
    try {
        const response = await fetch('../../api/notifications/get-notifications.php');
        const result = await response.json();

        if (result.success) {
            // Update notification badge if exists
            const badge = document.querySelector('.notification-badge');
            if (badge && result.unread_count > 0) {
                badge.textContent = result.unread_count;
                badge.style.display = 'block';
            }

            // Display notifications (implement drawer/modal as needed)
            console.log('Notifications:', result.notifications);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
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
