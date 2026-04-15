/**
 * Shared Client JavaScript
 * Common functionality across all client pages
 */

// Initialize logout functionality
document.addEventListener('DOMContentLoaded', () => {
    initLogout();
    // initNotifications(); // Handled by drawer-system.js
    initProfileClick();
    loadGlobalProfile();
});

/**
 * Loads the user profile globally to update header avatar
 */
async function loadGlobalProfile() {
    try {
        const user = storage.getUser();
        if (user) {
            updateGlobalAvatar(user);
            updateClientNameDisplay(user);
        }

        // If auth controller is already synced, we don't need to fetch again immediately
        if (window.authController && window.authController.settled && window.authController.state === 'authenticated') {
            return;
        }

        // Fetch fresh data if not settled or on explicit request
        const response = await apiFetch('/api/users/get-profile.php');
        const result = await response.json();

        if (result.success) {
            storage.setUser(result.user);
            updateGlobalAvatar(result.user);
            updateClientNameDisplay(result.user);
        }
    } catch (error) {
    }
}

// Listen for auth sync to update profile
document.addEventListener('auth:sync', (e) => {
    if (e.detail.success && e.detail.user) {
        updateGlobalAvatar(e.detail.user);
        updateClientNameDisplay(e.detail.user);
    }
});

function updateGlobalAvatar(user) {
    const avatars = document.querySelectorAll('.user-avatar');
    avatars.forEach(avatar => {
        // Ensure parent has avatar-wrapper class for absolute positioning of badge
        const parent = avatar.parentElement;
        if (parent && !parent.classList.contains('avatar-wrapper')) {
            parent.classList.add('avatar-wrapper');
        }

        const name = user.name || user.business_name || 'User';
        const profileUrl = typeof getProfileImg === 'function' 
            ? getProfileImg(user.profile_pic, name)
            : (user.profile_pic || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name));
            
        avatar.style.backgroundImage = `url(${profileUrl})`;
        avatar.style.backgroundSize = 'cover';
        avatar.style.backgroundPosition = 'center';

        // Add/Update Verification Badge
        if (parent && typeof getVerificationBadge === 'function') {
            const existingBadge = parent.querySelector('.verification-badge');
            if (existingBadge) existingBadge.remove();
            parent.insertAdjacentHTML('beforeend', getVerificationBadge(user.verification_status));
            // Re-init icons
            if (window.lucide) window.lucide.createIcons();
        }
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
        await apiFetch('/api/auth/logout.php');

        // Stop notification polling
        if (window.notificationManager) {
            window.notificationManager.stopPolling();
        }

        // Clear ONLY role-specific storage
        const keys = storage.getRoleKeys();
        storage.remove(keys.user);
        storage.remove(keys.token);
        sessionStorage.clear();

        // Hard redirect to login
        const loginPage = keys.user === 'admin_user' ? '../../admin/pages/adminLogin.html' : '../../client/pages/clientLogin.html';
        window.location.href = loginPage;
    } catch (error) {
        // Clean up and redirect anyway
        const keys = storage.getRoleKeys();
        storage.remove(keys.user);
        storage.remove(keys.token);
        const loginPage = keys.user === 'admin_user' ? '../../admin/pages/adminLogin.html' : '../../client/pages/clientLogin.html';
        window.location.href = loginPage;
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

    // Centralized Global Listeners (Export, Notifications, Profile)
    document.addEventListener('click', (e) => {
        // Global Export Button
        const exportBtn = e.target.closest('#globalExportBtn');
        if (exportBtn) {
            const path = window.location.pathname;
            let dataType = 'events';
            if (path.includes('tickets.html')) dataType = 'tickets';
            else if (path.includes('payments.html')) dataType = 'payments';
            else if (path.includes('users.html')) dataType = 'users';
            else if (path.includes('media.html')) dataType = 'media';

            if (typeof window.showExportModal === 'function') {
                window.showExportModal(dataType);
            }
        }

        // Global Notifications
        const notificationIcon = e.target.closest('[data-drawer="notifications"]');
        if (notificationIcon) {
            if (window.drawerSystem && typeof window.drawerSystem.open === 'function') {
                window.drawerSystem.open('notifications');
            }
        }

        // Global Profile Click
        const profileAvatar = e.target.closest('.user-profile') || e.target.closest('.user-avatar');
        if (profileAvatar) {
            if (typeof window.showProfileEditModal === 'function') {
                window.showProfileEditModal();
            }
        }
    });
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

/**
 * Mobile Sidebar Toggle Functionality
 * Handles showing/hiding sidebar on mobile devices
 */
document.addEventListener('DOMContentLoaded', () => {
    initMobileSidebar();
    initDesktopSidebar();
});

/**
 * Desktop Sidebar Toggle
 */
function initDesktopSidebar() {
    const header = document.querySelector('.header');
    const sidebar = document.querySelector('.sidebar');
    const mainLayout = document.querySelector('.main-layout');

    if (!header || !sidebar || !mainLayout) return;

    // 1. Create Toggle Button
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'sidebarToggle';
    toggleBtn.className = 'sidebar-toggle-btn';
    toggleBtn.innerHTML = '<i data-lucide="menu"></i>';
    toggleBtn.style.cssText = `
        background: none;
        border: none;
        color: var(--client-text-main);
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0.5rem;
        display: flex;
        align-items: center;
        margin-right: 1.5rem;
        transition: transform 0.3s ease;
    `;

    // 2. Insert Toggle Button BEFORE the search bar
    const searchBar = header.querySelector('.header-search');
    if (searchBar) {
        header.insertBefore(toggleBtn, searchBar);
    } else {
        header.prepend(toggleBtn);
    }

    // 3. Handle Initial State from LocalStorage
    const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
    if (isCollapsed && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        mainLayout.classList.add('collapsed');
    }

    // 4. Toggle Event
    toggleBtn.addEventListener('click', () => {
        const nowCollapsed = sidebar.classList.toggle('collapsed');
        mainLayout.classList.toggle('collapsed');
        localStorage.setItem('sidebar_collapsed', nowCollapsed);
        
        // Rotate icon or change if needed
        toggleBtn.style.transform = nowCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
    });

    // Re-init icons
    if (window.lucide) window.lucide.createIcons();
}

/**
 * Mobile Sidebar Toggle Functionality
 */
function initMobileSidebar() {
    // Check if we're on a mobile device
    function isMobile() {
        return window.innerWidth <= 767;
    }

    // Create hamburger button if on mobile
    if (isMobile()) {
        createMobileMenuButton();
    }

    // Handle window resize to add/remove hamburger button
    window.addEventListener('resize', debounce(() => {
        const hamburger = document.getElementById('mobileMenuToggle');
        if (isMobile() && !hamburger) {
            createMobileMenuButton();
        } else if (!isMobile() && hamburger) {
            hamburger.remove();
            closeMobileSidebar();
        }
    }, 250));

    // Close sidebar when clicking outside
    document.addEventListener('click', (e) => {
        const sidebar = document.querySelector('.sidebar');
        const hamburger = document.getElementById('mobileMenuToggle');
        if (sidebar && hamburger && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                closeMobileSidebar();
            }
        }
    });

    // Close sidebar when clicking on a menu item (navigation)
    const menuItems = document.querySelectorAll('.menu-item a');
    menuItems.forEach(item => {
        // Mark navigation initiated from sidebar to avoid immediate auth-guard redirect loop
        item.addEventListener('click', () => {
            // Mark navigation initiated from sidebar to avoid immediate auth-guard redirect loop
            try { sessionStorage.setItem('skip_auth_redirect', '1'); } catch (err) {}
            try { localStorage.setItem('skip_auth_redirect', Date.now().toString()); } catch (err) {}
            if (isMobile()) {
                closeMobileSidebar();
            }
        });
    });
}

function createMobileMenuButton() {
    const header = document.querySelector('.header');
    if (!header || document.getElementById('mobileMenuToggle')) return;

    const hamburger = document.createElement('button');
    hamburger.id = 'mobileMenuToggle';
    hamburger.className = 'mobile-menu-toggle';
    hamburger.innerHTML = '<i data-lucide="menu" style="width: 24px; height: 24px;"></i>';
    hamburger.style.cssText = `
        background: none;
        border: none;
        color: var(--client-text-main);
        cursor: pointer;
        font-size: 1.5rem;
        padding: 0.5rem;
        display: flex;
        align-items: center;
        margin-left: 1rem;
    `;

    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMobileSidebar();
    });

    // Insert at the beginning of header (before search)
    const headerSearch = header.querySelector('.header-search');
    if (headerSearch) {
        header.insertBefore(hamburger, headerSearch);
    } else {
        header.insertBefore(hamburger, header.firstChild);
    }

    // Reinitialize lucide icons
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
}

function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

function closeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.remove('active');
    }
}

window.toggleMobileSidebar = toggleMobileSidebar;
window.closeMobileSidebar = closeMobileSidebar;
