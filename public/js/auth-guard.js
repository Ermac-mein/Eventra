/**
 * Eventra Auth Guard
 * Protects routes based on user role and authentication status.
 */

(function() {
    // 1. Identify required role from context (URL or body attribute)
    const currentPath = window.location.pathname;
    let requiredRole = null;

    if (currentPath.includes('/admin/')) {
        requiredRole = 'admin';
    } else if (currentPath.includes('/client/')) {
        requiredRole = 'client';
    }

    if (!requiredRole) return; // Not a protected area

    // 2. Check Storage
    const storageKey = requiredRole === 'admin' ? 'admin_user' : 'client_user';
    const userStr = localStorage.getItem(storageKey); 
    
    let user = null;
    try {
        const stored = localStorage.getItem(storageKey);
        user = stored ? JSON.parse(stored) : null;
    } catch (e) {
        console.error('Auth Guard: Failed to parse user storage', e);
    }

    if (!user || user.role !== requiredRole) {
        console.warn(`Auth Guard: Unauthorized access to ${requiredRole} area. Redirecting...`);
        const basePath = currentPath.includes('/pages/') ? '../../' : '../';
        
        if (requiredRole === 'admin') {
            window.location.href = basePath + 'admin/pages/adminLogin.html';
        } else {
            window.location.href = basePath + 'client/pages/clientLogin.html';
        }
        return;
    }

    //console.log(`Auth Guard: Successfully authenticated as ${requiredRole}`);
})();
