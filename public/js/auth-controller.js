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
        this.isSyncing = false;
        this.settled = false;
        
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
        if (this.settled || this.isSyncing) return this.ready;
        // console.log('[AuthController] Initializing...');
        
        // 1. Initial State from Storage (Optimistic)
        let storedUser = window.storage ? window.storage.getUser() : null;
        let storedToken = window.storage ? window.storage.getToken() : null;
        
        if (storedUser && storedToken) {
            this.user = storedUser;
            this.setState(this.states.AUTHENTICATED);
        }

        // 2. Perform server-side validation
        try {
            this.isSyncing = true;
            await this.syncSession();
        } finally {
            this.isSyncing = false;
            this.settled = true;
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

            const role = this.getPortalIntent();
            const endpoint = `${basePath}api/auth/check-session.php`; // Use centralized endpoint directly

            const response = await apiFetch(endpoint, {
                cache: 'no-store'
            });
            
            if (!response) {
                this.clearLocalState();
                this.setState(this.states.UNAUTHENTICATED);
                return;
            }

            const result = await response.json();
            if (result.success) {
                // Merge data to preserve any local-only fields if necessary, 
                // but usually server is source of truth.
                const updatedUser = { ...this.user, ...result.user };
                this.user = updatedUser;
                
                if (window.storage) window.storage.setUser(updatedUser);
                this.setState(this.states.AUTHENTICATED);
                window.dispatchEvent(new CustomEvent('auth:sync', { detail: { success: true, user: updatedUser } }));
            } else {
                console.warn('[AuthController] Session invalid according to server:', result.message);
                this.clearLocalState();
            }
        } catch (error) {
            console.error('[AuthController] Session sync failed:', error);
            
            // CRITICAL: If sync failed, we cannot trust the optimistic state.
            // Only keep 'authenticated' if there was a network-level abort and we already had data, 
            // but for a "full fix", any sync failure should probably drop us to unauthenticated 
            // to be safe, especially if the error is a parsing error (invalid JSON from server).
            
            this.clearLocalState();
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
        if (!clientId) {
            console.error('[AuthController] No client ID provided for Google initialization');
            return;
        }

        try {
            console.log('[AuthController] Initializing Google with clientId:', clientId.substring(0, 20) + '...');
            
            google.accounts.id.initialize({
                client_id: clientId,
                callback: (res) => this.handleGoogleResponse(res),
                auto_select: false,
                prompt: 'select_account',
                cancel_on_tap_outside: true,
                itp_support: true
            });

            this.googleInitialized = true;
            console.log('[AuthController] Google initialized successfully, rendering button');
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
        if (!container) {
            console.warn('[AuthController] Container not found:', containerId);
            return;
        }
        if (!this.googleInitialized) {
            console.warn('[AuthController] Google not initialized yet');
            return;
        }

        try {
            console.log('[AuthController] Starting button render process...');
            console.log('[AuthController] Container element:', container);
            console.log('[AuthController] Container visibility:', {
                display: window.getComputedStyle(container).display,
                visibility: window.getComputedStyle(container).visibility,
                opacity: window.getComputedStyle(container).opacity,
                width: container.offsetWidth,
                height: container.offsetHeight
            });
            
            // Clear any existing content EXCEPT if it contains rendered content already
            const hasExistingButton = container.querySelector('[data-testid="button"]') || container.querySelector('.gis-button');
            if (!hasExistingButton) {
                container.innerHTML = '';
            }
            
            console.log('[AuthController] Container cleared, ready for button render');
            
            // Render the button
            google.accounts.id.renderButton(container, {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                text: 'signin_with',
                shape: 'rectangular',
                logo_alignment: 'left'
            });
            
            // Wait a bit for the button to be rendered and check
            setTimeout(() => {
                console.log('[AuthController] Google button render call completed');
                console.log('[AuthController] Container after render:', {
                    innerHTML: container.innerHTML.substring(0, 100),
                    children: container.children.length,
                    display: window.getComputedStyle(container).display,
                    width: container.offsetWidth,
                    height: container.offsetHeight,
                    hasButton: container.querySelector('button') !== null,
                    hasIframe: container.querySelector('iframe') !== null
                });
                
                // If button didn't render, try the prompt method as fallback
                if (!container.querySelector('button') && !container.querySelector('iframe')) {
                    console.warn('[AuthController] Button did not render via renderButton, trying prompt instead');
                    try {
                        google.accounts.id.prompt((notification) => {
                            console.log('[AuthController] Prompt notification:', notification);
                        });
                    } catch (err) {
                        console.error('[AuthController] Prompt fallback error:', err);
                    }
                }
            }, 100);
        } catch (error) {
            console.error('[AuthController] Error rendering Google button:', error);
        }
    }

    /**
     * Trigger Google Login Prompt manually
     */
    handleGoogleLoginManual() {
        if (!this.googleInitialized) {
            console.error('[AuthController] Google SDK not initialized');
            return;
        }
        google.accounts.id.prompt();
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
            const role = this.getPortalIntent();
            const endpoint = `${basePath}api/${role}/auth/google-login.php`;
            
            const res = await apiFetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    credential: response.credential,
                    client_id: google.accounts.id.client_id // Useful for verification if needed
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
        const basePath = getBasePath();
        
        // 1. Resolve Default Target if not provided
        if (!target) {
            const role = this.user ? this.user.role : 'user';
            if (role === 'admin') target = '/admin/pages/adminDashboard.html';
            else if (role === 'client') target = '/client/pages/clientDashboard.html';
            else target = '/public/pages/index.html';
        }

        // 2. Priority: redirect_after_login (if deep/specific)
        let pending = window.storage ? window.storage.get('redirect_after_login') : null;
        
        // Sanitize pending redirect - ignore if it's just the homepage/root and we have a specific dashboard target
        if (pending) {
            const isWeakRedirect = pending.endsWith('/') || pending.endsWith('index.html') || pending.includes('?trigger=login');
            const targetIsDashboard = target.includes('Dashboard.html');
            
            if (isWeakRedirect && targetIsDashboard) {
                console.log('[AuthController] Ignoring weak pending redirect in favor of dashboard:', pending);
                pending = null;
                if (window.storage) window.storage.remove('redirect_after_login');
            }
        }

        if (pending) {
            if (window.storage) window.storage.remove('redirect_after_login');
            console.log('[AuthController] Using pending redirect:', pending);
            window.location.href = pending;
            return;
        }

        // 3. Final URL Resolution
        // Normalize: remove leading slash to prevent double slash with basePath
        const normalizedTarget = target.replace(/^\//, '');
        const finalUrl = target.includes('://') ? target : basePath + normalizedTarget;
        
        console.log('[AuthController] Redirecting to:', finalUrl);
        window.location.href = finalUrl;
    }

    /**
     * Unified Logout
     */
    async logout(shouldRedirect = true) {
        try {
            const role = this.getPortalIntent();
            await apiFetch('/api/auth/logout.php', { method: 'POST' });
        } catch (e) {}

        this.clearSession();
        
        if (shouldRedirect) {
            const role = this.getPortalIntent();
            const origin = window.location.origin;
            if (role === 'admin') {
                window.location.href = origin + '/admin/pages/adminLogin.html';
            } else if (role === 'client') {
                window.location.href = origin + '/client/pages/clientLogin.html';
            } else {
                window.location.href = origin + '/public/pages/index.html?trigger=login';
            }
        }
    }

}

// Global Singleton
window.authController = new AuthController();

// Auto-initialize: begin server-side session handshake immediately.
// auth-guard.js awaits authController.ready — this ensures it always resolves.
window.authController.init();
