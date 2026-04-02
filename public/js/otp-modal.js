/**
 * OTP Modal - Verification for Payment
 */
function showOTPModal(email, phone, onVerified, onCancel) {
    const reference = 'PAY-' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);

    const inputOptions = {};
    if (email) inputOptions['email'] = `Email Delivery`;
    if (phone) inputOptions['sms'] = `SMS Delivery`;
    
    // Fallback if neither exists
    if (!email && !phone) {
        Swal.fire('Error', 'No contact information available for verification.', 'error');
        if (onCancel) onCancel();
        return;
    }

    Swal.fire({
        title: 'Verify Your Identity',
        text: 'How would you like to receive your verification code?',
        input: 'radio',
        inputOptions: inputOptions,
        inputValue: email ? 'email' : 'sms',
        showCancelButton: true,
        confirmButtonText: 'Send Code',
        showLoaderOnConfirm: true,
        preConfirm: async (channel) => {
            try {
                const res = await apiFetch('/api/otps/generate-otp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ channel, payment_reference: reference })
                });
                
                if (!res) throw new Error('Unauthenticated or request aborted');

                const result = await res.json();
                if (!result.success) {
                    Swal.showValidationMessage(result.message);
                    return false;
                }
                return result; // returning the json payload for next step
            } catch (error) {
                Swal.showValidationMessage(`Request failed: ${error.message}`);
                return false;
            }
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            // result.value contains the JSON result from generate-otp.php
            Swal.fire({
                title: 'Enter Verification Code',
                html: `<p style="font-size: 0.9em; color: #666; margin-bottom: 1rem;">${result.value.message}</p>
                       <input id="otpCode" class="swal2-input" placeholder="6-digit code" autocomplete="one-time-code" maxlength="6" style="text-align: center; letter-spacing: 0.4em; font-size: 1.5em; font-weight: bold; width: 280px; max-width: 100%; box-sizing: border-box;">`,
                showCancelButton: true,
                confirmButtonText: 'Verify',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    const otp = Swal.getPopup().querySelector('#otpCode').value;
                    if (!otp || otp.length !== 6) {
                        Swal.showValidationMessage('Please enter a 6-digit code');
                        return false;
                    }

                    try {
                        const res = await apiFetch('/api/otps/verify-otp.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ otp, payment_reference: reference })
                        });
                        
                        if (!res) throw new Error('Unauthenticated or request aborted');

                        const data = await res.json();
                        if (!data.success) {
                            Swal.showValidationMessage(data.message);
                            return false;
                        }
                        return true;
                    } catch (error) {
                        Swal.showValidationMessage(`Request failed: ${error.message}`);
                        return false;
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((verifyResult) => {
                if (verifyResult.isConfirmed) {
                    onVerified();
                } else if (verifyResult.isDismissed && onCancel) {
                    onCancel();
                }
            });
        } else if (result.isDismissed && onCancel) {
            onCancel();
        }
    });
}
