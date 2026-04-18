document.addEventListener('DOMContentLoaded', () => {
    const basePath = getBasePath();
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const rememberMeInput = document.getElementById('rememberMe');
    const togglePassword = document.getElementById('togglePassword');
    const loginButton = document.getElementById('loginButton');
    const successMessage = document.getElementById('successMessage');
    const forgotPasswordLink = document.querySelector('.forgot-password');

    // Role Context (Detected from URL role/intent or body data-intent)
    const urlParams = new URLSearchParams(window.location.search);
    const roleParam = urlParams.get('role');
    const intentParam = urlParams.get('intent');
    const trigger = urlParams.get('trigger');
    
    // Final intent resolution
    let intent = roleParam || intentParam || document.body.getAttribute('data-intent') || 'client';

    // Special case: if we are clearly in the public scope and no intent is forced, 
    // we might want to default to 'user' for discovery, but for this login page, 'client' is the standard.
    const isHomepageFlow = intent === 'user' || trigger === 'google';
    if (isHomepageFlow) intent = 'user';


    // Role-Specific UI Adjustments
    // Role-Specific UI Adjustments
    if (intent === 'client') {
        document.title = "Client Login - Eventra";
        const sliderText = document.querySelector('.slider-text');
        if (sliderText) sliderText.style.display = 'none';
    } else if (intent === 'user') {
        // Users should only use Google Sign-in via the homepage modal
        window.location.href = '../../public/pages/index.html';
        return;
    }

    // Check for session timeout error
    if (urlParams.get('error') === 'session_timeout') {
        setTimeout(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Session Expired',
                    text: 'Your session has timed out. Please log in again to continue.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true,
                    background: '#1e293b',
                    color: '#fff'
                });
            } else {
                showNotification('Your session has expired. Please log in again.', 'error');
            }
        }, 500);
    }

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            const type = isPassword ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Update Icon
            togglePassword.innerHTML = isPassword ? 
                '<i data-lucide="eye-off" style="width: 18px; height: 18px;"></i>' : 
                '<i data-lucide="eye" style="width: 18px; height: 18px;"></i>';
            
            // Re-create icons for the new element
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }

    function showError(elementId, message) {
        const errorElement = document.getElementById(elementId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
        const inputId = elementId.replace('Error', '');
        const inputElement = document.getElementById(inputId);
        if (inputElement) inputElement.classList.add('error');
    }

    function resetErrors() {
        document.querySelectorAll('.error-message').forEach(err => err.style.display = 'none');
        document.querySelectorAll('.form-input').forEach(input => input.classList.remove('error'));
    }

    // Helper to detect project root depth
    function getBasePath() {
        const path = window.location.pathname;
        // If we are in /public/pages/ or similar depth 2 path
        if (path.includes('/pages/')) return '../../';
        // If we are in /admin/ or /client/ (depth 1)
        return '../';
    }


    // Add form submission listener
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleLogin();
        });
    }

    // Forgot Password Link Handler
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            handleForgotPassword();
        });
    }

    async function handleLogin() {
        const originalBtnText = loginButton.innerHTML;
        loginButton.disabled = true;
        loginButton.innerHTML = '<span class="spinner"></span> Logging in...';

        try {
            const response = await apiFetch('/api/clients/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: emailInput.value,
                    password: passwordInput.value,
                    remember_me: rememberMeInput?.checked || false,
                    intent: intent
                })
            });

            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                throw new Error("Server returned non-JSON response. Status: " + response.status);
            }

            const result = await response.json();

            if (result.success && (result.otp_required || result.next_step === 'otp_verification')) {
                // --- CUSTOM OTP MODAL FLOW ---
                loginButton.innerHTML = originalBtnText;
                loginButton.disabled = false;

                const otpModal = document.getElementById('otpModal');
                const otpForm = document.getElementById('otpForm');
                const otpCodeInput = document.getElementById('otpCode');
                const otpError = document.getElementById('otpError');
                const cancelBtn = document.getElementById('cancelOtpButton');

                if (otpModal) {
                    otpModal.style.display = 'flex';
                    otpCodeInput.focus();

                    // Handle Cancellation
                    cancelBtn.onclick = () => {
                        otpModal.style.display = 'none';
                        otpForm.reset();
                    };

                    // Handle Submission
                    otpForm.onsubmit = async (e) => {
                        e.preventDefault();
                        const verifyBtn = document.getElementById('verifyOtpButton');
                        const originalVerifyText = verifyBtn.innerHTML;
                        
                        verifyBtn.disabled = true;
                        verifyBtn.innerHTML = '<span class="spinner"></span> Verifying...';
                        otpError.style.display = 'none';

                        try {
                            const verifyRes = await apiFetch('/api/auth/verify-otp.php', {
                                method: 'POST',
                                body: JSON.stringify({
                                    identity: emailInput.value,
                                    auth_id: result.auth_id,
                                    otp: otpCodeInput.value,
                                    intent: 'client_login_otp'
                                })
                            });

                            const verifyResult = await verifyRes.json();

                            if (verifyResult.success) {
                                otpModal.style.display = 'none';
                                completeLoginSession(verifyResult);
                            } else {
                                otpError.textContent = verifyResult.message || 'Invalid code.';
                                otpError.style.display = 'block';
                                verifyBtn.disabled = false;
                                verifyBtn.innerHTML = originalVerifyText;
                            }
                        } catch (err) {
                            otpError.textContent = 'Verification failed. Try again.';
                            otpError.style.display = 'block';
                            verifyBtn.disabled = false;
                            verifyBtn.innerHTML = originalVerifyText;
                        }
                    };
                }
                return;
            }

            if (result.success) {
                completeLoginSession(result);
            } else {
                // Clear any stale state on failure
                if (window.authController) window.authController.clearLocalState();
                
                // If the message contains "Email", show it there, otherwise show at password
                const errorElement = result.message?.toLowerCase().includes('email') ? 'emailError' : 'passwordError';
                showError(errorElement, result.message || 'Invalid email or password');
                loginButton.disabled = false;
                loginButton.innerHTML = originalBtnText;
            }
        } catch (error) {
            showError('passwordError', 'An error occurred. Please try again later.');
            loginButton.disabled = false;
            loginButton.innerHTML = originalBtnText;
        }
    }

    /**
     * Shared logic to finalize session after successful password or OTP verification
     */
    function completeLoginSession(result) {
        // Show Success SweetAlert
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Login Successful',
                text: 'Welcome back!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                background: '#1e293b',
                color: '#fff'
            });
        }

        // Isolate session storage by role - store BOTH user and token
        if (window.storage && typeof window.storage.setToken === 'function') {
            window.storage.setUser(result.user);
            if (result.user.token) {
                window.storage.setToken(result.user.token);
            }
        } else {
            // Fallback: store directly to localStorage if storage manager not ready
            try {
                localStorage.setItem('client_auth_token', result.user.token || '');
                localStorage.setItem('client_user', JSON.stringify(result.user));
            } catch (e) {}
        }

        // Signal a fresh login to help the auth-guard be more patient
        sessionStorage.setItem('just_logged_in', 'true');
        
        setTimeout(() => {
            const redirectUrl = result.redirect || '/client/pages/clientDashboard.html';
            
            // Use unified redirect handler if available for consistency
            if (window.authController && typeof window.authController.handleRedirect === 'function') {
                window.authController.handleRedirect(redirectUrl);
            } else {
                window.location.href = redirectUrl;
            }
        }, 1600);
    }


    // Unified Google Init (Handled by AuthController)
    async function initGoogleAuth() {
        try {
            const response = await apiFetch('/api/config/get-google-config.php');
            const data = await response.json();
            
            if (data.success && data.client_id) {
                // Wait for Google SDK to load
                let attempts = 0;
                const checkGoogle = setInterval(() => {
                    attempts++;
                    if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                        clearInterval(checkGoogle);
                        // Initialize Google SDK in background mode to preserve custom button UI
                        authController.initGoogle(data.client_id, 'none'); 
                    } else if (attempts > 50) {
                        clearInterval(checkGoogle);
                    }
                }, 100);
            }
        } catch (error) {
        }
    }

    // Google Login button click handler
    const googleSignInBtn = document.getElementById('googleSignIn');
    if (googleSignInBtn) {
        googleSignInBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.authController) {
                window.authController.handleGoogleLoginManual();
            }
        });
    }

    // Initialize Google Auth
    initGoogleAuth();

    // handleCredentialResponse is now handled by AuthController.handleGoogleResponse

    function parseJwt(token) {
        var base64Url = token.split('.')[1];
        var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
        return JSON.parse(jsonPayload);
    };

    // Event Image Slider Logic - RESTRICTED: Only show when user is authenticated to prevent data leakage
    async function initSlider() {
        const sliderContainer = document.querySelector('.slider-images');
        
        if (!sliderContainer) return;

        // Check if user is already authenticated
        const isAuthenticated = window.storage && typeof window.storage.getUser === 'function' && window.storage.getUser();
        
        // Don't fetch or display events on login page when user is not authenticated
        // This prevents showing other clients' events to unauthenticated users
        if (!isAuthenticated) {
            // Show placeholder or empty state instead
            sliderContainer.innerHTML = '';
            return;
        }

        try {
            const response = await apiFetch('/api/events/get-events.php?status=published&limit=10');
            const data = await response.json();

            if (data.success && data.events.length > 0) {
                const events = data.events.filter(e => e.image_path);
                if (events.length === 0) return;

                // Inject images (using high quality placeholder or actual path)
                sliderContainer.innerHTML = events.map((event, index) => `
                    <img src="/${event.image_path}" 
                         alt="${event.event_name}" 
                         class="slider-img ${index === 0 ? 'active' : ''}" 
                         data-index="${index}">
                `).join('');

                let currentIndex = 0;
                
                const updateSlider = () => {
                    const images = document.querySelectorAll('.slider-img');
                    if (images.length === 0) return;
                    
                    images[currentIndex].classList.remove('active');
                    currentIndex = (currentIndex + 1) % images.length;
                    images[currentIndex].classList.add('active');
                };

                // Cycle every 5 seconds
                setInterval(updateSlider, 5000);
            }
        } catch (error) {
        }
    }

    initSlider();
});

// Password Recovery Flow
async function handleForgotPassword() {
    const { value: identity } = await Swal.fire({
        title: 'Forgot Password?',
        text: 'Enter your registered email address to receive an OTP.',
        input: 'text',
        inputPlaceholder: 'Email Address',
        showCancelButton: true,
        confirmButtonText: 'Send OTP',
        background: '#1e293b',
        color: '#fff',
        confirmButtonColor: '#2ecc71'
    });

    if (!identity) return;

    Swal.showLoading();

    try {
        const response = await apiFetch('/api/auth/forgot-password.php', {
            method: 'POST',
            body: JSON.stringify({ identity })
        });
        const result = await response.json();

        if (result.success) {
            // Step 2: Prompt for OTP
            const { value: otp } = await Swal.fire({
                title: 'Verify OTP',
                text: result.message,
                input: 'text',
                inputPlaceholder: 'Enter 6-digit OTP',
                showCancelButton: true,
                confirmButtonText: 'Verify',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#2ecc71',
                inputAttributes: {
                    maxlength: 6,
                    autocapitalize: 'off',
                    autocorrect: 'off'
                }
            });

            if (!otp) return;

            Swal.showLoading();
            const verifyRes = await apiFetch('/api/auth/verify-otp.php', {
                method: 'POST',
                body: JSON.stringify({ identity, otp })
            });
            const verifyResult = await verifyRes.json();

            if (verifyResult.success) {
                // Step 3: Prompt for New Password
                const { value: password } = await Swal.fire({
                    title: 'Reset Password',
                    text: 'Enter your new password (min. 8 characters, one uppercase, one digit, and one special character).',
                    input: 'password',
                    inputPlaceholder: 'New Password',
                    showCancelButton: true,
                    confirmButtonText: 'Reset Password',
                    background: '#1e293b',
                    color: '#fff',
                    confirmButtonColor: '#2ecc71',
                    inputValidator: (value) => {
                        const passwordRegex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/;
                        if (!value) {
                            return 'You need to write something!';
                        }
                        if (!passwordRegex.test(value)) {
                            return 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character.';
                        }
                    }
                });

                if (!password) return;

                Swal.showLoading();
                const resetRes = await apiFetch('/api/auth/reset-password.php', {
                    method: 'POST',
                    body: JSON.stringify({ 
                        reset_token: verifyResult.reset_token, 
                        new_password: password 
                    })
                });
                const resetResult = await resetRes.json();

                if (resetResult.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: resetResult.message,
                        background: '#1e293b',
                        color: '#fff'
                    });
                } else {
                    Swal.fire('Error', resetResult.message, 'error');
                }
            } else {
                Swal.fire('Error', verifyResult.message, 'error');
            }
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'An unexpected error occurred.', 'error');
    }
}
