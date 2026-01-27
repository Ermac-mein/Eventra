document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.getElementById('signupForm');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const signupButton = document.getElementById('signupButton');
    const successMessage = document.getElementById('successMessage');

    // Form submission
    if (signupForm) {
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

            if (passwordInput.value.length < 6) {
                showError('passwordError', 'Password must be at least 6 characters');
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

    async function handleSignup() {
        // Show loading state
        const originalBtnText = signupButton.innerHTML;
        signupButton.disabled = true;
        signupButton.innerHTML = '<span class="spinner"></span> Creating account...';

        // Simulate API call
        try {
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            // Show success
            if (successMessage) {
                successMessage.style.display = 'block';
                successMessage.textContent = 'Account created successfully! Redirecting to login...';
            }
            
            // Redirect after success
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1500);

        } catch (error) {
            showError('passwordError', 'Registration failed. Please try again.');
            signupButton.disabled = false;
            signupButton.innerHTML = originalBtnText;
        }
    }
});
