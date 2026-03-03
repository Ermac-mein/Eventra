/**
 * Eventra Auth Controller
 * Centralized state machine for authentication and Google Sign-In lifecycle.
 */
class AuthController {
    constructor() {
        this.states = {
            INITIALIZING: 'initializing',
            UNAUTHENTICATED: 'unauthenticated',
            AUTHENTICATING: 'authenticating',
            AUTHENTICATED: 'authenticated',
            ERROR: 'error'
        };
        this.state = this.states.INITIALIZING;
        this.user = null;
        this.googleInitialized = false;
        this.isRedirecting = false;
        
        // Promise that resolves when the first sync is complete
        this._readyResolve = null;
        this.ready = new Promise((resolve) => {
            this._readyResolve = resolve;
        });
    }

    /**
     * Initialize Auth Controller
     */
    async init() {
        console.log('[AuthController] Initializing...');
        
        // 1. Initial State from Storage (Optimistic)
        const storedUser = window.storage ? window.storage.getUser() : null;
        const storedToken = window.storage ? window.storage.getToken() : null;
        
        if (storedUser && storedToken) {
            this.user = storedUser;
            this.setState(this.states.AUTHENTICATED);
        }

        // 2. Perform server-side validation
        try {
            await this.syncSession();
        } finally {
            // Ensure ready promise resolves even on error
            if (this._readyResolve) {
                this._readyResolve(this.state);
                this._readyResolve = null;
            }
        }
        
        return this.state;
    }

    /**
     * Synchronize session with backend
     */
    async syncSession() {
        if (this.isRedirecting) return;
        
        try {
            const basePath = getBasePath();
            const path = window.location.pathname;
            
            // Skip sync for portal/login pages to avoid loops, but still resolve ready
            // Updated to be more robust for different environments
            if (path.includes('Login.html') || path.includes('index.html')) {
                // If we are on index.html, we only skip if trigger=login is present or if we are clearly in guest mode
                const urlParams = new URLSearchParams(window.location.search);
                if (path.includes('Login.html') || urlParams.get('trigger') === 'login') {
                    this.setState(this.states.UNAUTHENTICATED);
                    return;
                }
            }

            const response = await apiFetch(basePath + 'api/auth/check-session.php', {
                cache: 'no-store'
            });
            
            if (!response) {
                this.clearLocalState();
                this.setState(this.states.UNAUTHENTICATED);
                return;
            }

            const result = await response.json();
            if (result.success) {
                this.user = result.user;
                if (window.storage) window.storage.setUser(result.user);
                this.setState(this.states.AUTHENTICATED);
                window.dispatchEvent(new CustomEvent('auth:sync', { detail: { success: true, user: result.user } }));
            } else {
                this.clearLocalState();
                this.setState(this.states.UNAUTHENTICATED);
            }
        } catch (error) {
            console.error('[AuthController] Session sync failed:', error);
            // If we have local data but sync failed (network error?), keep current state but log error
            if (this.state === this.states.INITIALIZING) {
                this.setState(this.states.UNAUTHENTICATED);
            }
        }
    }

    /**
     * State Machine Transition
     */
    setState(newState) {
        if (this.state === newState) return;
        console.log(`[AuthController] State change: ${this.state} -> ${newState}`);
        this.state = newState;
        window.dispatchEvent(new CustomEvent('auth:stateChange', { detail: { state: newState, user: this.user } }));
        
        // Global events for specific states
        if (newState === this.states.AUTHENTICATED) {
            window.dispatchEvent(new CustomEvent('auth:authenticated', { detail: { user: this.user } }));
        } else if (newState === this.states.UNAUTHENTICATED) {
            window.dispatchEvent(new CustomEvent('auth:unauthenticated'));
        }
    }

    /**
     * Clear only local auth data
     */
    clearLocalState() {
        if (window.storage) window.storage.clearRoleSessions();
        this.user = null;
        this.setState(this.states.UNAUTHENTICATED);
    }

    /**
     * Hard Reset Storage & State
     */
    clearSession() {
        console.log('[AuthController] Performing hard reset...');
        this.clearLocalState();
        window.storage.remove('redirect_after_login');
        this.setState(this.states.UNAUTHENTICATED);
        
        // Force Google SDK reset
        if (typeof google !== 'undefined') {
            google.accounts.id.disableAutoSelect();
        }
    }

    /**
     * Initialize Google SDK
     * @param {string} clientId 
     * @param {string} containerId 
     */
    initGoogle(clientId, containerId = 'googleSignInContainer') {
        if (!clientId) return;

        try {
            google.accounts.id.initialize({
                client_id: clientId,
                callback: (res) => this.handleGoogleResponse(res),
                auto_select: false,
                cancel_on_tap_outside: true,
                itp_support: true
            });

            this.googleInitialized = true;
            this.renderGoogleButton(containerId);
        } catch (error) {
            console.error('[AuthController] Google Init Error:', error);
            this.setState(this.states.ERROR);
        }
    }

    /**
     * Render Google Sign-In Button
     */
    renderGoogleButton(containerId) {
        const container = document.getElementById(containerId);
        if (!container || !this.googleInitialized) return;

        google.accounts.id.renderButton(container, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            text: 'signin_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: '320'
        });
    }

    /**
     * Handle Google Credential Response
     */
    async handleGoogleResponse(response) {
        if (this.isRedirecting) return;
        
        this.setState(this.states.AUTHENTICATING);
        
        showNotification('Verifying with Google...', 'info');

        // Transition container to loading state
        const container = document.getElementById('googleSignInContainer');
        if (container) {
            container.innerHTML = `
                <div class="auth-loading-spinner" style="display: flex; align-items: center; justify-content: center; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                    <span class="spinner" style="margin-right: 10px; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite;"></span>
                    <span style="color: white; font-size: 0.9rem;">Authenticating...</span>
                </div>
            `;
        }

        try {
            const basePath = getBasePath();
            const res = await apiFetch(basePath + 'api/auth/google-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    credential: response.credential,
                    intent: this.getPortalIntent()
                }),
                cache: 'no-store'
            });

            const result = await res.json();

            if (result.success) {
                this.user = result.user;
                if (window.storage) window.storage.setUser(result.user);
                this.setState(this.states.AUTHENTICATED);
                
                showNotification('Welcome to Eventra!', 'success');
                
                this.isRedirecting = true;
                setTimeout(() => {
                    this.handleRedirect(result.redirect);
                }, 1500);
            } else {
                throw new Error(result.message || 'Authentication failed');
            }
        } catch (error) {
            console.error('[AuthController] Google Login Error:', error);
            showNotification(error.message, 'error');
            this.setState(this.states.ERROR);
            
            setTimeout(() => {
                this.syncSession(); 
                const container = document.getElementById('googleSignInContainer');
                if (container) this.renderGoogleButton('googleSignInContainer');
            }, 2000);
        }
    }

    /**
     * Helper to get portal intent
     */
    getPortalIntent() {
        const path = window.location.pathname;
        if (path.includes('/admin/')) return 'admin';
        if (path.includes('/client/')) return 'client';
        return 'user';
    }

    /**
     * Unified Redirect Handler
     */
    handleRedirect(target) {
        if (!target) {
            const basePath = getBasePath();
            const role = this.user ? this.user.role : 'user';
            
            if (role === 'admin') target = basePath + 'admin/pages/adminDashboard.html';
            else if (role === 'client') target = basePath + 'client/pages/clientDashboard.html';
            else target = basePath + 'public/pages/index.html';
        }

        // Check if there was a pending redirect
        const pending = window.storage ? window.storage.get('redirect_after_login') : null;
        if (pending) {
            if (window.storage) window.storage.remove('redirect_after_login');
            window.location.href = pending;
            return;
        }

        const finalUrl = target.includes('://') ? target : getBasePath() + target.replace(/^\//, '');
        
        window.location.href = finalUrl;
    }

    /**
     * Unified Logout
     */
    async logout(shouldRedirect = true) {
        try {
            await apiFetch(getBasePath() + 'api/auth/logout.php');
        } catch (e) {}

        this.clearSession();
        
        if (shouldRedirect) {
            window.location.href = getBasePath() + 'public/pages/index.html';
        }
    }
}

// Global Singleton
window.authController = new AuthController();
