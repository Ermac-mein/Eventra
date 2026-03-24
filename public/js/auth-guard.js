/**
 * Eventra Auth Guard
 * Protects routes based on user role and authentication status.
 * Optimized for robustness: Calls AuthController.init() then waits for it to settle.
 */

(async function() {
    const currentPath = window.location.pathname;
    const origin = window.location.origin;

    // 1. Skip protection for login, signup, and public pages to prevent redirect loops
    const publicPages = ['adminLogin.html', 'clientLogin.html', 'signup.html', 'index.html', '/public/pages/'];
    if (publicPages.some(page => currentPath.endsWith(page) || currentPath === '/')) {
        // ALWAYS skip for explicitly public pages like Login/Signup
        return;
    }

    // Determine required role based on path
    let requiredRole = null;
    if (currentPath.includes('/admin/')) {
        requiredRole = 'admin';
    } else if (currentPath.includes('/client/')) {
        requiredRole = 'client';
    }

    if (!requiredRole) return; // Not a protected area

    // FAST synchronous check before rendering body
    const hasLocalAuth = window.storage && window.storage.getUser() && window.storage.getToken();
    if (!hasLocalAuth) {
        if (requiredRole === 'admin') {
            window.location.replace(origin + '/admin/pages/adminLogin.html');
        } else {
            window.location.replace(origin + '/client/pages/clientLogin.html');
        }
        return;
    }

    // 2. Visual loading overlay removed so users can proceed to dashboards instantly.
    const loadingOverlay = null;

    try {
        // 3. Ensure AuthController exists and is initialized
        if (!window.authController) {
            console.error('[Auth Guard] authController not found on window. Check script load order.');
            throw new Error('AuthController not found');
        }

        // Always kick off init() — it guards against double-calls internally
        if (!window.authController.settled && !window.authController.isSyncing) {
            console.log('[Auth Guard] Triggering AuthController.init()...');
            window.authController.init();
        }

        // 4. Wait for AuthController to complete server-side handshake
        const authState = await window.authController.ready;
        console.log('[Auth Guard] Auth settled state:', authState);

        const user = window.authController.user;

        // 5. Final Evaluation
        if (authState !== 'authenticated' || !user || user.role !== requiredRole) {
            console.warn('[Auth Guard] Access denied. Redirecting to login.');

            if (window.storage) {
                window.storage.set('redirect_after_login', window.location.href);
            }

            if (requiredRole === 'admin') {
                window.location.href = origin + '/admin/pages/adminLogin.html';
            } else {
                window.location.href = origin + '/client/pages/clientLogin.html';
            }
            return;
        }

        // 6. Success — hide loading overlay
        console.log(`[Auth Guard] Authorized as ${requiredRole}`);
        if (loadingOverlay) {
            loadingOverlay.classList.add('hidden');
            setTimeout(() => loadingOverlay.remove(), 600);
        }

    } catch (error) {
        console.error('[Auth Guard] Error during validation:', error);
        
        // Ensure overlay is hidden even on error
        if (loadingOverlay) {
            loadingOverlay.classList.add('hidden');
            setTimeout(() => loadingOverlay.remove(), 600);
        }

        // On critical error OR sync failure, redirect to the role-specific login
        if (requiredRole === 'admin') {
            window.location.replace(origin + '/admin/pages/adminLogin.html' + (window.location.search || '?error=auth_failed'));
        } else if (requiredRole === 'client') {
            window.location.replace(origin + '/client/pages/clientLogin.html' + (window.location.search || '?error=auth_failed'));
        } else {
            window.location.replace(origin + '/public/pages/index.html?trigger=login');
        }
    }
})();
