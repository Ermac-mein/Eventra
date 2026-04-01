/**
 * OTP Modal Handler for Payment Flow
 * Displays OTP selection (email/SMS), sends OTP, and verifies before payment
 */

function showOTPModal(userEmail, userPhone, onVerified, onCancel) {
    const modalHTML = `
        <div id="otpModalBackdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; z-index: 2000;">
            <div id="otpModal" style="background: white; border-radius: 16px; padding: 2.5rem; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease;">
                <!-- Header -->
                <div style="margin-bottom: 2rem; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Verify Your Identity</h2>
                    <p style="color: #64748b; margin: 0.5rem 0 0;">We'll send a one-time code to confirm your payment</p>
                </div>

                <!-- OTP Channel Selection -->
                <div id="channelSelection" style="display: block;">
                    <p style="font-size: 0.875rem; font-weight: 600; color: #64748b; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px;">Choose delivery method:</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                        <button class="otp-channel-btn" data-channel="email" onclick="selectOTPChannel('email')" style="padding: 1.25rem; border: 2px solid #e2e8f0; border-radius: 12px; background: white; cursor: pointer; transition: all 0.2s; text-align: center;">
                            <div style="font-size: 1.75rem; margin-bottom: 0.5rem;">📧</div>
                            <div style="font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">Email</div>
                            <div style="font-size: 0.75rem; color: #94a3b8; word-break: break-all;">${escapeHTML(userEmail)}</div>
                        </button>
                        <button class="otp-channel-btn" data-channel="sms" onclick="selectOTPChannel('sms')" style="padding: 1.25rem; border: 2px solid #e2e8f0; border-radius: 12px; background: white; cursor: pointer; transition: all 0.2s; text-align: center;">
                            <div style="font-size: 1.75rem; margin-bottom: 0.5rem;">💬</div>
                            <div style="font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">SMS</div>
                            <div style="font-size: 0.75rem; color: #94a3b8; word-break: break-all;">${maskPhoneNumber(userPhone)}</div>
                        </button>
                    </div>

                    <button onclick="sendOTP()" style="width: 100%; padding: 0.875rem; background: #FF5A5F; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s;">
                        Send Code
                    </button>
                </div>

                <!-- OTP Input Stage -->
                <div id="otpInputStage" style="display: none;">
                    <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; text-align: center;">Enter the 6-digit code we sent to you</p>
                    
                    <div style="display: flex; gap: 0.75rem; margin-bottom: 2rem; justify-content: center;">
                        <input type="text" maxlength="1" class="otp-input" style="width: 50px; height: 50px; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                        <input type="text" maxlength="1" class="otp-input" style="width: 50px; height: 50px; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                        <input type="text" maxlength="1" class="otp-input" style="width: 50px; height: 50px; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                        <input type="text" maxlength="1" class="otp-input" style="width: 50px; height: 50px; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                        <input type="text" maxlength="1" class="otp-input" style="width: 50px; height: 50px; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                        <input type="text" maxlength="1" class="otp-input" style="width: 50px; height: 50px; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 8px; font-weight: 600;">
                    </div>

                    <button onclick="verifyOTP()" style="width: 100%; padding: 0.875rem; background: #FF5A5F; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s; margin-bottom: 0.75rem;">
                        Verify & Continue
                    </button>
                    <button onclick="resendOTP()" style="width: 100%; padding: 0.875rem; background: transparent; color: #FF5A5F; border: 2px solid #FFD8DB; border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;">
                        Resend Code
                    </button>
                    <p id="otpError" style="color: #ef4444; font-size: 0.875rem; text-align: center; margin-top: 1rem; display: none;"></p>
                </div>

                <!-- Footer -->
                <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                    <p style="color: #94a3b8; font-size: 0.75rem; margin: 0;">Your information is secure and encrypted</p>
                </div>
            </div>
        </div>

        <style>
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .otp-channel-btn:hover {
                border-color: #FF5A5F !important;
                background: #FFF5F5 !important;
            }

            .otp-channel-btn.selected {
                border-color: #FF5A5F !important;
                background: #FFF5F5 !important;
            }

            .otp-input:focus {
                outline: none;
                border-color: #FF5A5F !important;
                box-shadow: 0 0 0 3px rgba(255, 90, 95, 0.1);
            }

            .otp-input::-webkit-outer-spin-button,
            .otp-input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            .otp-input[type=number] {
                -moz-appearance: textfield;
            }
        </style>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const backdrop = document.getElementById('otpModalBackdrop');
    let selectedChannel = null;

    // OTP State Management
    window.otpState = {
        channel: null,
        timestamp: null,
        onVerified: onVerified,
        onCancel: onCancel || (() => closeOTPModal()),
        userEmail: userEmail,
        userPhone: userPhone
    };

    // Channel Selection
    window.selectOTPChannel = function(channel) {
        selectedChannel = channel;
        window.otpState.channel = channel;
        document.querySelectorAll('.otp-channel-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.dataset.channel === channel);
        });
    };

    // Send OTP
    window.sendOTP = async function() {
        if (!selectedChannel) {
            showNotification('Please select a delivery method', 'error');
            return;
        }

        const sendBtn = document.querySelector('[onclick="sendOTP()"]');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="btn-spinner"></span> Sending...';

        try {
            const response = await apiFetch('/api/otps/generate-otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    channel: selectedChannel,
                    email: window.otpState.userEmail,
                    phone: window.otpState.userPhone
                })
            });

            const result = await response.json();

            if (result.success) {
                window.otpState.timestamp = Date.now();
                window.otpState.payment_reference = result.payment_reference;
                showNotification(`OTP sent to ${selectedChannel === 'email' ? 'email' : 'phone'}`, 'success');
                document.getElementById('channelSelection').style.display = 'none';
                document.getElementById('otpInputStage').style.display = 'block';
                setupOTPInputAutoTab();
                startOTPTimer();
            } else {
                showNotification(result.message || 'Failed to send OTP', 'error');
                sendBtn.disabled = false;
                sendBtn.innerHTML = 'Send Code';
            }
        } catch (error) {
            console.error('Send OTP error:', error);
            showNotification('Error sending OTP', 'error');
            sendBtn.disabled = false;
            sendBtn.innerHTML = 'Send Code';
        }
    };

    // Verify OTP
    window.verifyOTP = async function() {
        // Guard: Ensure otpState and modal elements exist
        if (!window.otpState) {
            console.warn('OTP Modal not properly initialized');
            return;
        }

        const inputs = document.querySelectorAll('.otp-input');
        const otpError = document.getElementById('otpError');
        
        if (!inputs.length || !otpError) {
            console.warn('OTP input elements not found');
            return;
        }

        const otpCode = Array.from(inputs).map(input => input.value).join('');

        if (otpCode.length !== 6 || !/^\d+$/.test(otpCode)) {
            otpError.textContent = 'Please enter a valid 6-digit code';
            otpError.style.display = 'block';
            return;
        }

        const verifyBtn = document.querySelector('[onclick="verifyOTP()"]');
        if (verifyBtn) {
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="btn-spinner"></span> Verifying...';
        }

        try {
            const response = await apiFetch('/api/otps/verify-otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    otp: otpCode,
                    payment_reference: window.otpState.payment_reference || 'PAY-' + Date.now()
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Identity verified! Redirecting to payment...', 'success');
                
                // Capture callback before clearing state
                const onVerified = window.otpState.onVerified;
                closeOTPModal();
                
                if (typeof onVerified === 'function') {
                    onVerified(result.token || true);
                }
            } else {
                if (otpError) {
                    otpError.textContent = result.message || 'Invalid OTP. Please try again.';
                    otpError.style.display = 'block';
                }
                if (verifyBtn) {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = 'Verify & Continue';
                }
            }
        } catch (error) {
            console.error('Verify OTP error:', error);
            if (otpError) {
                otpError.textContent = 'Error verifying OTP';
                otpError.style.display = 'block';
            }
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = 'Verify & Continue';
            }
        }
    };

    // Resend OTP
    window.resendOTP = async function() {
        const inputs = document.querySelectorAll('.otp-input');
        inputs.forEach(input => input.value = '');
        document.getElementById('otpError').style.display = 'none';
        await window.sendOTP();
    };

    // Setup OTP Input Auto-Tab
    function setupOTPInputAutoTab() {
        const inputs = document.querySelectorAll('.otp-input');
        
        function checkAndSubmit() {
            // Guard: Only auto-submit if otpState is properly initialized and modal is visible
            if (!window.otpState || document.getElementById('otpModalBackdrop').style.display === 'none') {
                return;
            }
            
            const allFilled = Array.from(inputs).every(input => input.value && /^\d$/.test(input.value));
            if (allFilled) {
                // Add slight delay to ensure all inputs are registered
                setTimeout(() => {
                    // Final check before submitting
                    if (window.otpState && document.getElementById('otpModalBackdrop').style.display !== 'none') {
                        window.verifyOTP();
                    }
                }, 300);
            }
        }
        
        inputs.forEach((input, index) => {
            input.addEventListener('keyup', (e) => {
                if (e.key === 'Backspace' && input.value === '' && index > 0) {
                    inputs[index - 1].focus();
                } else if (input.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                checkAndSubmit();
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').split('');
                digits.forEach((digit, i) => {
                    if (i < inputs.length) {
                        inputs[i].value = digit;
                    }
                });
                if (digits.length >= inputs.length) {
                    inputs[inputs.length - 1].focus();
                }
                checkAndSubmit();
            });
            
            input.addEventListener('input', (e) => {
                checkAndSubmit();
            });
        });
    }

    // OTP Timer
    function startOTPTimer() {
        const resendBtn = document.querySelector('[onclick="resendOTP()"]');
        let timeLeft = 300; // 5 minutes

        const countdown = setInterval(() => {
            timeLeft--;
            resendBtn.textContent = `Resend Code (${Math.floor(timeLeft / 60)}:${String(timeLeft % 60).padStart(2, '0')})`;

            if (timeLeft <= 0) {
                clearInterval(countdown);
                resendBtn.textContent = 'Resend Code';
                resendBtn.disabled = false;
            } else {
                resendBtn.disabled = true;
            }
        }, 1000);
    }

    // Close Modal
    window.closeOTPModal = function() {
        backdrop.remove();
        window.otpState = null;
    };

    // Close on backdrop click
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            window.otpState.onCancel();
            closeOTPModal();
        }
    });
}

function maskPhoneNumber(phone) {
    if (!phone) return 'N/A';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length < 4) return phone;
    return '*' * (cleaned.length - 4) + cleaned.slice(-4);
}
