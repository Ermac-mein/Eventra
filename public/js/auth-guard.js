/**
 * Eventra Auth Guard
 * Protects routes based on user role and authentication status.
 * Optimized for robustness: Waits for AuthController initialization before evaluating.
 */

(async function() {
    const currentPath = window.location.pathname;
    
    // 1. Skip protection for login and signup pages to prevent redirect loops
    const loginPages = ['adminLogin.html', 'clientLogin.html', 'signup.html', 'index.html'];
    if (loginPages.some(page => currentPath.endsWith(page)) && !currentPath.includes('/admin/') && !currentPath.includes('/client/')) {
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

    // 2. Show Premium Loading Overlay
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

    try {
        // 3. Wait for AuthController to finish its server-side handshake
        console.log('[Auth Guard] Waiting for AuthController...');
        
        if (!window.authController) {
             throw new Error('AuthController not initialized');
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
            if (requiredRole === 'admin') {
                window.location.href = basePath + 'admin/pages/adminLogin.html';
            } else {
                window.location.href = basePath + 'client/pages/clientLogin.html';
            }
            return;
        }

        // 5. Success! Hide Loading Overlay
        console.log(`[Auth Guard] Authorized as ${requiredRole}`);
        loadingOverlay.classList.add('hidden');
        setTimeout(() => loadingOverlay.remove(), 600);

    } catch (error) {
        console.error('[Auth Guard] Error during validation:', error);
        // On critical error, fallback to safety: redirect to home or login
        window.location.href = getBasePath() + 'public/pages/index.html';
    }
})();
