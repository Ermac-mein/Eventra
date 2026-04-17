document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.getElementById('signupForm');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const businessNameInput = document.getElementById('businessName');
    const businessNameGroup = document.getElementById('businessNameGroup');
    const signupButton = document.getElementById('signupButton');
    const successMessage = document.getElementById('successMessage');
    const togglePassword = document.getElementById('togglePassword');
    const googleSignUp = document.getElementById('googleSignUp');
    const signupTitle = document.getElementById('signupTitle');
    const loginLink = document.getElementById('loginLink');

    // Role Context (Detected from URL role/intent or body data-intent)
    const urlParams = new URLSearchParams(window.location.search);
    const roleParam = urlParams.get('role');
    const intentParam = urlParams.get('intent');
    const intent = roleParam || intentParam || document.body.getAttribute('data-intent') || 'client';

    // Role-Specific UI Adjustments
    if (intent === 'admin') {
        document.title = "Admin Registration - Eventra";
        if (googleSignUp) {
            const googleContainer = document.getElementById('googleContainer');
            const authDivider = document.getElementById('authDivider');
            if (googleContainer) googleContainer.style.display = 'none';
            if (authDivider) authDivider.style.display = 'none';
        }
        if (signupTitle) signupTitle.textContent = 'Admin Registration';
        if (signupButton) signupButton.textContent = 'Create Admin Account';
        if (loginLink) loginLink.href = `clientLogin.html?role=admin`;
        
    } else {
        if (intent === 'user') {
            window.location.href = '../../public/pages/index.html';
            return;
        }
        document.title = (intent === 'client' ? "Client" : "User") + " Registration - Eventra";
        if (signupTitle) signupTitle.textContent = (intent === 'client') ? 'Client Registration' : 'Create Account';
        if (signupButton) signupButton.textContent = (intent === 'client') ? 'Create Client Account' : 'Sign Up';
        if (loginLink) loginLink.href = `clientLogin.html?role=${intent}`;
    }

    // Handle Business Name Visibility (client only)
    if (businessNameGroup) {
        businessNameGroup.style.display = (intent === 'client') ? 'block' : 'none';
        if (businessNameInput && intent === 'client') {
            businessNameInput.required = true;
        }
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
            
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    }

    // Google Sign Up
    if (googleSignUp) {
        googleSignUp.addEventListener('click', () => {
            handleGoogleSignUp();
        });
    }

    // Form submission
    if (signupForm) {
        // Add persistence: save on input
        signupForm.addEventListener('input', () => saveFormState('signupForm'));
        signupForm.addEventListener('change', () => saveFormState('signupForm'));

        // Restore saved state
        restoreFormState('signupForm');

        // NO AUTO-SYNC between full name and business name – they are separate fields

        signupForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Basic validation
            let isValid = true;
            resetErrors();

            if (fullNameInput.value.trim().length < 2) {
                showError('fullNameError', 'Please enter your full name');
                isValid = false;
            }

            if (!validateEmail(emailInput.value)) {
                showError('emailError', 'Please enter a valid email address');
                isValid = false;
            }

            const password = passwordInput.value;
            const passwordRegex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/;
            
            if (!passwordRegex.test(password)) {
                showError('passwordError', 'Password must be at least 8 characters long and include one uppercase letter, one digit, and one special character (!@#$%^&*(), etc.)');
                isValid = false;
            }

            if (isValid) {
                handleSignup();
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
        if (inputElement) {
            inputElement.classList.add('error');
        }
    }

    function resetErrors() {
        const errors = document.querySelectorAll('.error-message');
        errors.forEach(err => err.style.display = 'none');
        
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => input.classList.remove('error'));
    }

    // Helper to show a general error message (not tied to a specific field)
    function showGeneralError(message) {
        // Try a dedicated general error container first
        const generalError = document.getElementById('generalError');
        if (generalError) {
            generalError.textContent = message;
            generalError.style.display = 'block';
        } else {
            // Fallback to passwordError field (common location)
            const passwordError = document.getElementById('passwordError');
            if (passwordError) {
                passwordError.textContent = message;
                passwordError.style.display = 'block';
            } else {
                // Last resort: alert (avoid if possible)
                alert(message);
            }
        }
    }

    async function handleSignup() {
        const originalBtnText = signupButton.innerHTML;
        signupButton.disabled = true;
        signupButton.innerHTML = '<span class="spinner"></span> Creating account...';

        // Clear any previous errors
        resetErrors();

        try {
            const formData = {
                name: fullNameInput.value.trim(),
                email: emailInput.value.trim(),
                password: passwordInput.value,
                business_name: businessNameInput ? businessNameInput.value.trim() : '',
                role: intent
            };

            const response = await fetch('/api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
                credentials: 'include'
            });

            const data = await response.json();
            console.log('✅ Registration response:', data);

            if (data.success) {
                // Success – show message and redirect to login
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registration Successful!',
                        text: data.message || 'Your account has been created. You may now log in.',
                        timer: 2000,
                        showConfirmButton: false,
                        background: 'rgba(30, 41, 59, 0.95)',
                        color: '#fff'
                    });
                }
                setTimeout(() => {
                    const loginUrl = (intent === 'admin') ? 'clientLogin.html?role=admin' : `clientLogin.html?role=${intent}`;
                    window.location.href = `${loginUrl}?registered=${encodeURIComponent(data.email || emailInput.value)}`;
                }, 2100);
            } else {
                // Backend returned success: false – show the exact error
                const errorMsg = data.message || 'Registration failed. Please try again.';
                showGeneralError(errorMsg);
                signupButton.disabled = false;
                signupButton.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error('❌ Network/parsing error:', error);
            showGeneralError('Network error. Please check your connection and try again.');
            signupButton.disabled = false;
            signupButton.innerHTML = originalBtnText;
        }
    }

    // Legacy OTP modal (kept for reference, not used in new flow)
    function showRegistrationOTPModal(email) {
        // This is a legacy function – OTP is now sent at login.
        if (typeof Swal === 'undefined') {
            alert('Please check your email for the verification code.');
            return;
        }

        Swal.fire({
            title: 'Verify Your Email',
            html: `
                <div style="text-align: left;">
                    <p style="color: #94a3b8; margin-bottom: 1.5rem; font-size: 0.95rem;">
                        We've sent a 6-digit verification code to <strong>${email}</strong>. 
                        Enter it below to complete your registration.
                    </p>
                    <div style="display: flex; justify-content: center;">
                        <input type="text" id="regOtpCode" maxlength="6" placeholder="000000" 
                               style="width: 200px; padding: 1rem; border: 2px solid #334155; background: #0f172a; color: #fff; border-radius: 12px; text-align: center; font-size: 2rem; letter-spacing: 0.5rem; font-weight: 800; font-family: monospace;" 
                               inputmode="numeric" pattern="[0-9]*">
                    </div>
                    <p style="color: #64748b; margin-top: 1.5rem; font-size: 0.85rem; text-align: center;">
                        Wait a few minutes if you don't see it, and check your spam folder.
                    </p>
                </div>
            `,
            background: 'rgba(15, 23, 42, 0.95)',
            color: '#fff',
            confirmButtonText: 'Verify & Create Account',
            confirmButtonColor: '#2563eb',
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            cancelButtonColor: '#334155',
            allowOutsideClick: false,
            preConfirm: () => {
                const otp = document.getElementById('regOtpCode').value;
                if (!otp || otp.length !== 6) {
                    Swal.showValidationMessage('Please enter the 6-digit code');
                    return false;
                }
                return otp;
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                const otp = result.value;
                
                Swal.fire({
                    title: 'Verifying...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await apiFetch('/api/auth/verify-otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            identity: email,
                            otp: otp,
                            intent: 'registration_verify'
                        })
                    });

                    const verifyResult = await response.json();

                    if (verifyResult.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Email verified. Your account is ready.',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        setTimeout(() => {
                            window.location.href = verifyResult.redirect || 'clientDashboard.html';
                        }, 2100);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: verifyResult.message || 'The code is invalid or expired.',
                            confirmButtonText: 'Try Again'
                        }).then(() => {
                            showRegistrationOTPModal(email);
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred during verification. Please try again.'
                    }).then(() => {
                        showRegistrationOTPModal(email);
                    });
                }
            }
        });
    }

    async function handleGoogleSignUp() {
        try {
            const configResponse = await apiFetch('/api/config/get-google-config.php');
            const configData = await configResponse.json();
            
            if (!configData.success || !configData.client_id) {
                Swal.fire('Error', 'Google configuration missing.', 'error');
                return;
            }

            let attempts = 0;
            const attemptGoogleInit = () => {
                if (typeof google !== 'undefined') {
                    try {
                        google.accounts.id.initialize({
                            client_id: configData.client_id,
                            callback: handleGoogleResponse,
                        });
                        google.accounts.id.prompt();
                    } catch (error) {
                        Swal.fire('Error', 'Failed to initialize Google Sign-up.', 'error');
                    }
                } else if (attempts < 20) {
                    attempts++;
                    setTimeout(attemptGoogleInit, 100);
                } else {
                    Swal.fire('Blocked', 'Google script not loaded. Check ad-blockers.', 'warning');
                }
            };
            attemptGoogleInit();
        } catch (error) {
            Swal.fire('Error', 'Failed to initialize Google Sign-up.', 'error');
        }
    }

    async function handleGoogleResponse(response) {
        try {
            const res = await apiFetch('/api/auth/google-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    credential: response.credential,
                    intent: intent
                })
            });
            const result = await res.json();

            if (result.success) {
                const storageKey = intent === 'admin' ? 'admin_user' : (intent === 'client' ? 'client_user' : 'user');
                const tokenKey = intent === 'admin' ? 'admin_auth_token' : (intent === 'client' ? 'client_auth_token' : 'auth_token');

                if (typeof storage !== 'undefined') {
                    storage.set(storageKey, result.user);
                    storage.set(tokenKey, result.user.token);
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Account Ready!',
                    text: 'Signed up with Google. Redirecting...',
                    timer: 1500,
                    showConfirmButton: false,
                    background: 'rgba(30, 41, 59, 0.95)',
                    color: '#fff'
                });
                
                setTimeout(() => {
                    const basePath = typeof window.getAppBasePath === 'function' ? window.getAppBasePath() : '../../';
                    window.location.href = basePath + result.redirect;
                }, 1600);
            } else {
                Swal.fire('Registration Failed', result.message, 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred during Google Sign-up.', 'error');
        }
    }

    // Event Image Slider Logic
    async function initSlider() {
        const sliderContainer = document.querySelector('.slider-images');
        if (!sliderContainer) return;

        const isAuthenticated = window.storage && typeof window.storage.getUser === 'function' && window.storage.getUser();
        
        if (!isAuthenticated) {
            sliderContainer.innerHTML = '';
            return;
        }

        try {
            const response = await apiFetch('/api/events/get-events.php?status=published&limit=10');
            const data = await response.json();

            if (data.success && data.events.length > 0) {
                const events = data.events.filter(e => e.image_path);
                if (events.length === 0) return;

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

                setInterval(updateSlider, 5000);
            }
        } catch (error) {
            // Silently fail – slider is not critical
        }
    }

    initSlider();
});