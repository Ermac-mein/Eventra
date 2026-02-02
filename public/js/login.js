document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const rememberMeInput = document.getElementById('rememberMe');
    const togglePassword = document.getElementById('togglePassword');
    const loginButton = document.getElementById('loginButton');
    const successMessage = document.getElementById('successMessage');
    const googleSignIn = document.getElementById('googleSignIn');
    const forgotPasswordLink = document.querySelector('.forgot-password');

    // Role Modal Elements
    const roleModal = document.getElementById('roleModal');
    const cancelRole = document.getElementById('cancelRole');
    const confirmRole = document.getElementById('confirmRole');

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
    }

    // Forgot Password
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', async (e) => {
            e.preventDefault();
            
            const { value: email } = await Swal.fire({
                title: 'Reset Password',
                input: 'email',
                inputLabel: 'Enter your email address',
                inputPlaceholder: 'm@example.com',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            });

            if (email) {
                try {
                    const response = await fetch('../../api/auth/forgot-password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email })
                    });
                    const result = await response.json();
                    Swal.fire({
                        title: result.success ? 'Success' : 'Error',
                        text: result.message,
                        icon: result.success ? 'success' : 'error'
                    });
                } catch (error) {
                    Swal.fire('Error', 'An error occurred. Please try again.', 'error');
                }
            }
        });
    }

    // Form submission
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            let isValid = true;
            resetErrors();

            if (!validateEmail(emailInput.value)) {
                showError('emailError', 'Please enter a valid email address');
                isValid = false;
            }

            if (passwordInput.value.length < 6) {
                showError('passwordError', 'Password must be at least 6 characters');
                isValid = false;
            }

            if (isValid) {
                handleLogin();
            }
        });
    }

    // Google Sign In (Role Selection Flow)
    if (googleSignIn) {
        googleSignIn.addEventListener('click', () => {
            roleModal.style.display = 'flex';
        });
    }

    if (cancelRole) {
        cancelRole.addEventListener('click', () => {
            roleModal.style.display = 'none';
        });
    }

    if (confirmRole) {
        confirmRole.addEventListener('click', () => {
            const selectedRole = document.querySelector('input[name="sign_role"]:checked').value;
            
            // Set pending_role cookie (My Idea implementation)
            document.cookie = `pending_role=${selectedRole}; max-age=300; path=/; samesite=lax`;
            
            roleModal.style.display = 'none';
            handleGoogleSignIn();
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

    async function handleLogin() {
        const originalBtnText = loginButton.innerHTML;
        loginButton.disabled = true;
        loginButton.innerHTML = '<span class="spinner"></span> Logging in...';

        try {
            const response = await fetch('../../api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: emailInput.value,
                    password: passwordInput.value,
                    remember_me: rememberMeInput?.checked || false
                })
            });

            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                console.error("Non-JSON response received:", text);
                throw new Error("Server returned non-JSON response. Status: " + response.status);
            }

            const result = await response.json();

            if (result.success) {
                storage.set('user', result.user);
                storage.set('auth_token', result.user.token);

                if (successMessage) {
                    successMessage.classList.add('show');
                    successMessage.textContent = 'Login successful! Redirecting...';
                }
                
                setTimeout(() => {
                    if (result.user.role === 'admin') {
                        window.location.href = '../../admin/pages/dashboard.html';
                    } else if (result.user.role === 'client') {
                        window.location.href = '../../client/pages/dashboard.html';
                    } else {
                        const redirectPath = storage.get('redirect_after_login') || 'index.html';
                        storage.remove('redirect_after_login');
                        window.location.href = redirectPath;
                    }
                }, 1000);
            } else {
                // If the message contains "Email", show it there, otherwise show at password
                const errorElement = result.message?.toLowerCase().includes('email') ? 'emailError' : 'passwordError';
                showError(errorElement, result.message || 'Invalid email or password');
                loginButton.disabled = false;
                loginButton.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error('Error:', error);
            showError('passwordError', 'An error occurred. Please try again later.');
            loginButton.disabled = false;
            loginButton.innerHTML = originalBtnText;
        }
    }

    async function handleGoogleSignIn() {
        // Fetch Google Client ID from server
        let clientId;
        try {
            const configResponse = await fetch('../../api/config/get-google-config.php');
            const configData = await configResponse.json();
            
            if (!configData.success || !configData.client_id) {
                Swal.fire('Configuration Error', 'Google Sign-in is not configured on the server. Please contact the administrator.', 'error');
                return;
            }
            
            clientId = configData.client_id;
        } catch (error) {
            console.error('Failed to fetch Google config:', error);
            Swal.fire('Error', 'Could not load Google Sign-in configuration. Please try again later.', 'error');
            return;
        }
        
        if (typeof google === 'undefined') {
            const errorMsg = 'Google Sign-in is currently blocked by your browser or an extension (e.g., ad-blocker, privacy extension).\n\nTo use Google Sign-in:\n1. Disable your ad blocker for this site\n2. Disable privacy extensions temporarily\n3. Try again\n\nAlternatively, you can sign in using email and password.';
            Swal.fire('Blocked', errorMsg, 'warning');
            return;
        }

        try {
            google.accounts.id.initialize({
                client_id: clientId,
                callback: handleCredentialResponse,
                auto_select: false,
                cancel_on_tap_outside: true,
            });

            google.accounts.id.prompt();
        } catch (error) {
            console.error('Google Initialization Error:', error);
            const errorMsg = 'Could not initialize Google Sign-in.\n\nPossible causes:\n- Ad blocker or privacy extension is blocking Google\n- Network connectivity issues\n- Browser security settings\n\nPlease try:\n1. Disabling ad blockers\n2. Using email/password login instead';
            Swal.fire('Error', errorMsg, 'error');
        }
    }

    async function handleCredentialResponse(response) {
        const decodedToken = parseJwt(response.credential);
        const googleData = {
            google_id: decodedToken.sub,
            email: decodedToken.email,
            name: decodedToken.name,
            profile_pic: decodedToken.picture
        };

        try {
            const res = await fetch('../../api/auth/google-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(googleData)
            });
            
            // Handle non-JSON responses (like 405 or 500 html errors)
            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await res.text();
                console.error("Non-JSON response received:", text);
                throw new Error("Server returned non-JSON response. Status: " + res.status);
            }

            const result = await res.json();

            if (result.success) {
                storage.set('user', result.user);
                storage.set('auth_token', result.user.token);
                
                if (successMessage) {
                    successMessage.classList.add('show');
                    successMessage.textContent = 'Google Sign-in successful! Redirecting...';
                }

                setTimeout(() => {
                    window.location.href = result.redirect || 'index.html';
                }, 1000);
            } else {
                Swal.fire('Login Failed', result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire('Error', 'An error occurred during Google Sign-in.', 'error');
        }
    }

    function parseJwt(token) {
        var base64Url = token.split('.')[1];
        var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
            return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
        return JSON.parse(jsonPayload);
    };

    // Event Image Slider Logic
    async function initSlider() {
        const sliderContainer = document.querySelector('.slider-images');
        const sliderTitle = document.getElementById('sliderExtTitle');
        const sliderLoc = document.getElementById('sliderEventLoc');
        
        if (!sliderContainer) return;

        try {
            const response = await fetch('../../api/events/get-events.php?status=published&limit=10');
            const data = await response.json();

            if (data.success && data.events.length > 0) {
                const events = data.events.filter(e => e.image_path);
                if (events.length === 0) return;

                // Inject images
                sliderContainer.innerHTML = events.map((event, index) => `
                    <img src="${event.image_path}" 
                         alt="${event.event_name}" 
                         class="slider-img ${index === 0 ? 'active' : ''}" 
                         data-index="${index}">
                `).join('');

                let currentIndex = 0;
                
                const updateSlider = () => {
                    const images = document.querySelectorAll('.slider-img');
                    images[currentIndex].classList.remove('active');
                    
                    currentIndex = (currentIndex + 1) % images.length;
                    
                    images[currentIndex].classList.add('active');
                    
                    // Update text with a small delay for fade
                    setTimeout(() => {
                        const event = events[currentIndex];
                        sliderTitle.textContent = event.event_name;
                        sliderLoc.textContent = `${event.state} - ${event.event_type}`;
                    }, 500);
                };

                // Cycle every 5 seconds
                setInterval(updateSlider, 5000);
                
                // Set initial text
                sliderTitle.textContent = events[0].event_name;
                sliderLoc.textContent = `${events[0].state} - ${events[0].event_type}`;
            }
        } catch (error) {
            console.error('Slider init error:', error);
        }
    }

    initSlider();
});
