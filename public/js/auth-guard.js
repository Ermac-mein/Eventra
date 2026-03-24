/**
 * Eventra Auth Guard
 * Protects routes based on user role and authentication status.
 * Optimized for robustness: Waits for AuthController initialization before evaluating.
 */

(async function() {
    const currentPath = window.location.pathname;
    
    // 1. Skip protection for login, signup, and homepage to prevent redirect loops
    const publicPages = ['adminLogin.html', 'clientLogin.html', 'signup.html', 'index.html', '/public/pages/'];
    if (publicPages.some(page => currentPath.endsWith(page) || currentPath === '/')) {
        // If it's index.html but it's NOT index.html?trigger=login, we still might want to allow it as a guest area
        // Protected areas are explicitly /admin/ or /client/
        if (!currentPath.includes('/admin/') && !currentPath.includes('/client/')) {
            return;
        }
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
        const basePath = getBasePath();
        
        // Store the current URL to redirect back after login
        if (window.storage) {
            window.storage.set('redirect_after_login', window.location.href);
        }

        const origin = window.location.origin;
        if (requiredRole === 'admin') {
            window.location.replace(origin + '/admin/pages/adminLogin.html');
        } else {
            window.location.replace(origin + '/client/pages/clientLogin.html');
        }
        return;
    }

    // 2. Show Premium Loading Overlay
    const showOverlay = () => {
        if (!document.body) {
            console.warn('[Auth Guard] document.body not available yet. Retrying...');
            setTimeout(showOverlay, 50);
            return;
        }
        
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'auth-guard-loading';
        loadingOverlay.className = 'auth-loading-screen';
        loadingOverlay.innerHTML = `
            <div class="auth-spinner-container">
                <div class="auth-spinner-ring"></div>
                <div class="auth-spinner-active"></div>
            </div>
            <div class="auth-loading-text">Verifying Session...</div>
        `;
        document.body.appendChild(loadingOverlay);
        return loadingOverlay;
    };

    const loadingOverlay = showOverlay();

    try {
        // 3. Wait for AuthController to finish its server-side handshake
        console.log('[Auth Guard] Waiting for AuthController...');
        
        if (!window.authController) {
             throw new Error('AuthController not initialized');
        }

        // 3.5 Ensure AuthController is initialized
        if (!window.authController.settled && !window.authController.isSyncing) {
            console.log('[Auth Guard] Triggering AuthController.init()...');
            window.authController.init();
        }

        const authState = await window.authController.ready;
        console.log('[Auth Guard] Auth settled state:', authState);

        const user = window.authController.user;
        
        // 4. Final Evaluation
        if (authState !== 'authenticated' || !user || user.role !== requiredRole) {
            console.warn('[Auth Guard] Access denied. Redirecting to login.');
            
            // Store redirect URL
            if (window.storage) {
                window.storage.set('redirect_after_login', window.location.href);
            }

            const basePath = getBasePath();
            const origin = window.location.origin;
            if (requiredRole === 'admin') {
                window.location.href = origin + '/admin/pages/adminLogin.html';
            } else {
                window.location.href = origin + '/client/pages/clientLogin.html';
            }
            return;
        }

        // 5. Success! Hide Loading Overlay
        console.log(`[Auth Guard] Authorized as ${requiredRole}`);
        loadingOverlay.classList.add('hidden');
        setTimeout(() => loadingOverlay.remove(), 600);

    } catch (error) {
        console.error('[Auth Guard] Error during validation:', error);
        // On critical error, fallback to safety: redirect to home or role-specific login
        const basePath = getBasePath();
        if (requiredRole === 'admin') {
            window.location.href = basePath + 'admin/login';
        } else if (requiredRole === 'client') {
            window.location.href = basePath + 'client/login';
        } else {
            window.location.href = basePath + 'index.html';
        }
    }
})();
