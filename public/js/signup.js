document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.getElementById('signupForm');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const signupButton = document.getElementById('signupButton');
    const successMessage = document.getElementById('successMessage');
    const togglePassword = document.getElementById('togglePassword');

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
    }

    // Form submission
    if (signupForm) {
        signupForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Basic validation
            let isValid = true;
            resetErrors();

            if (fullNameInput.value.trim().length < 2) {
                showError('fullNameError', 'Please enter your company or full name');
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

        try {
            const response = await fetch('../../api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: fullNameInput.value,
                    email: emailInput.value,
                    password: passwordInput.value
                })
            });
            const result = await response.json();

            if (result.success) {
                // Premium Success Feedback
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Account Created!',
                        text: 'Your client account is ready. Redirecting to login...',
                        timer: 2000,
                        showConfirmButton: false,
                        background: 'rgba(30, 41, 59, 0.95)',
                        color: '#fff'
                    });
                } else if (successMessage) {
                    successMessage.classList.add('show');
                    successMessage.textContent = 'Client account created successfully! Redirecting to login...';
                }
                
                // Redirect after success
                setTimeout(() => {
                    window.location.href = 'login.html?role=client';
                }, 2100);
            } else {
                showError('passwordError', result.message || 'Registration failed. Please try again.');
                signupButton.disabled = false;
                signupButton.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error('Error:', error);
            showError('passwordError', 'An error occurred. Please try again later.');
            signupButton.disabled = false;
            signupButton.innerHTML = originalBtnText;
        }
    }
});
