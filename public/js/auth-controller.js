/**
 * Eventra Auth Controller
 * Centralized state machine for authentication and Google Sign-In lifecycle.
 */
class AuthController {
    constructor() {
        this.states = {
            UNAUTHENTICATED: 'unauthenticated',
            AUTHENTICATING: 'authenticating',
            AUTHENTICATED: 'authenticated',
            ERROR: 'error'
        };
        this.state = this.states.UNAUTHENTICATED;
        this.user = null;
        this.googleInitialized = false;
        this.isRedirecting = false;
    }

    /**
     * Initialize Auth Controller
     */
    async init() {
        console.log('[AuthController] Initializing...');
        
        // 1. Initial State from Storage (Optimistic)
        if (typeof isAuthenticated === 'function' && isAuthenticated()) {
            this.user = storage.getUser();
            this.state = this.states.AUTHENTICATED;
        }

        // 2. Clear Google prompt state on load to ensure clean start
        if (typeof google !== 'undefined') {
            google.accounts.id.cancel();
        }

        // 3. Perform server-side validation
        await this.syncSession();
        
        return this.state;
    }

    /**
     * Synchronize session with backend
     */
    async syncSession() {
        try {
            const basePath = getBasePath();
            // Skip sync for login pages to avoid loops
            if (window.location.pathname.includes('Login.html')) return;

            const response = await apiFetch(basePath + 'api/auth/check-session.php', {
                cache: 'no-store'
            });
            
            if (!response) {
                this.setState(this.states.UNAUTHENTICATED);
                return;
            }

            const result = await response.json();
            if (result.success) {
                this.user = result.user;
                storage.setUser(result.user);
                this.setState(this.states.AUTHENTICATED);
                window.dispatchEvent(new CustomEvent('auth:sync', { detail: { success: true, user: result.user } }));
            } else {
                if (this.state === this.states.AUTHENTICATED) {
                    this.logout(false); // Silent logout if session expired
                }
                this.setState(this.states.UNAUTHENTICATED);
            }
        } catch (error) {
            console.error('[AuthController] Session sync failed:', error);
            this.setState(this.states.ERROR);
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
    }

    /**
     * Hard Reset Storage & State
     */
    clearSession() {
        console.log('[AuthController] Performing hard reset...');
        storage.clearRoleSessions();
        storage.remove('redirect_after_login');
        this.user = null;
        this.setState(this.states.UNAUTHENTICATED);
        
        // Force Google SDK reset
        if (typeof google !== 'undefined') {
            google.accounts.id.disableAutoSelect();
            google.accounts.id.cancel();
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
        
        // UI Requirement: Immediately show toast
        showNotification('Verifying with Google...', 'info');

        // UI Requirement: Hide selector/prompt within 2s
        setTimeout(() => {
            if (typeof google !== 'undefined') {
                google.accounts.id.cancel();
            }
            // Transition container to loading state
            const container = document.getElementById('googleSignInContainer');
            if (container) {
                container.innerHTML = `
                    <div class="auth-loading-spinner" style="display: flex; align-items: center; justify-content: center; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <span class="spinner" style="margin-right: 10px;"></span>
                        <span>Authenticating...</span>
                    </div>
                `;
            }
        }, 500);

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
                storage.setUser(result.user);
                this.setState(this.states.AUTHENTICATED);
                
                showNotification('Welcome to Eventra!', 'success');
                
                // UI Requirement: 2s delay BEFORE redirect
                this.isRedirecting = true;
                setTimeout(() => {
                    this.handleRedirect(result.redirect);
                }, 2000);
            } else {
                throw new Error(result.message || 'Authentication failed');
            }
        } catch (error) {
            console.error('[AuthController] Google Login Error:', error);
            showNotification(error.message, 'error');
            this.setState(this.states.ERROR);
            
            // UI Requirement: After 2s, reset button
            setTimeout(() => {
                this.syncSession(); // Reset state
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
    handleRedirect(backendRedirect) {
        const storedRedirect = sessionStorage.getItem('redirect_after_login');
        sessionStorage.removeItem('redirect_after_login');
        
        const target = storedRedirect || backendRedirect || 'public/pages/index.html';
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
