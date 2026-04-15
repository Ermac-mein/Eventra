document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.getElementById('signupForm');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
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

    async function handleSignup() {
        const originalBtnText = signupButton.innerHTML;
        signupButton.disabled = true;
        signupButton.innerHTML = '<span class="spinner"></span> Creating account...';

        try {
            const response = await apiFetch('/api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: fullNameInput.value,
                    email: emailInput.value,
                    password: passwordInput.value,
                    role: intent
                })
            });
            const result = await response.json();

            if (result.success) {
                // ... Success handling ...
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registration Successful!',
                        text: result.message || 'Your account has been created. Please log in to continue.',
                        timer: 3000,
                        showConfirmButton: false,
                        background: 'rgba(30, 41, 59, 0.95)',
                        color: '#fff'
                    });
                } else if (successMessage) {
                    successMessage.classList.add('show');
                    successMessage.textContent = 'Account created! Redirecting to login...';
                }
                
                setTimeout(() => {
                    const redirectUrl = result.redirect || `clientLogin.html`;
                    window.location.href = redirectUrl;
                }, 3100);
            } else {
                // Display specific error message from server
                showError('passwordError', result.message || 'Registration failed. Please try again.');
                signupButton.disabled = false;
                signupButton.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error('Signup error details:', {
                message: error.message,
                stack: error.stack,
                error: error
            });
            showError('passwordError', 'Registration failed. ' + (error.message || 'Please check your connection and try again.'));
            signupButton.disabled = false;
            signupButton.innerHTML = originalBtnText;
        }
    }

    async function handleGoogleSignUp() {
        // This leverages the same handler as login, which creates account if it doesn't exist
        // We'll mimic the login logic to check for config and initialize Google
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
                // Isolate session storage by role
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

    // Event Image Slider Logic - RESTRICTED: Only show when user is authenticated to prevent data leakage
    async function initSlider() {
        const sliderContainer = document.querySelector('.slider-images');
        if (!sliderContainer) return;

        // Check if user is already authenticated
        const isAuthenticated = window.storage && typeof window.storage.getUser === 'function' && window.storage.getUser();
        
        // Don't fetch or display events on signup page when user is not authenticated
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
        }
    }

    initSlider();
});
