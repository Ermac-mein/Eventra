document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const loginButton = document.getElementById('loginButton');
    const successMessage = document.getElementById('successMessage');
    const googleSignIn = document.getElementById('googleSignIn');

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon (if using FontAwesome or similar, here we just change text for simplicity or would toggle classes)
            // For this design, we'll just keep it simple
            togglePassword.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
    }

    // Form submission
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Basic validation
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

    // Google Sign In Mock
    if (googleSignIn) {
        googleSignIn.addEventListener('click', () => {
            console.log('Google Sign In clicked');
            // Mock redirections
            alert('Redirecting to Google Sign In...');
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
        
        // Find corresponding input to highlight
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

    async function handleLogin() {
        // Show loading state
        const originalBtnText = loginButton.innerHTML;
        loginButton.disabled = true;
        loginButton.innerHTML = '<span class="spinner"></span> Logging in...';

        // Simulate API call
        try {
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            // Show success
            if (successMessage) {
                successMessage.classList.add('show');
                successMessage.textContent = 'Login successful! Redirecting...';
            }
            
            // Redirect after success
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);

        } catch (error) {
            showError('passwordError', 'Invalid email or password');
            loginButton.disabled = false;
            loginButton.innerHTML = originalBtnText;
        }
    }
});
