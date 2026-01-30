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
            const email = prompt("Enter your email address to reset password:");
            if (email) {
                try {
                    const response = await fetch('../../api/auth/forgot-password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email })
                    });
                    const result = await response.json();
                    alert(result.message);
                } catch (error) {
                    alert("An error occurred. Please try again.");
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
                alert('Google Sign-in is not configured on the server. Please contact the administrator.');
                return;
            }
            
            clientId = configData.client_id;
        } catch (error) {
            console.error('Failed to fetch Google config:', error);
            alert('Could not load Google Sign-in configuration. Please try again later.');
            return;
        }
        
        if (typeof google === 'undefined') {
            const errorMsg = 'Google Sign-in is currently blocked by your browser or an extension (e.g., ad-blocker, privacy extension).\n\nTo use Google Sign-in:\n1. Disable your ad blocker for this site\n2. Disable privacy extensions temporarily\n3. Try again\n\nAlternatively, you can sign in using email and password.';
            alert(errorMsg);
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
            alert(errorMsg);
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
                alert('Google Sign-in failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred during Google Sign-in.');
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
});
