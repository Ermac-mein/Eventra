/**
 * OTP Modal - SMS Verification for Payment
 */
function showOTPModal(email, phone, onVerified, onCancel) {
    if (!phone) {
        if (onCancel) onCancel();
        return alert('Phone number required');
    }

    const reference = 'PAY-' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);

    Swal.fire({
        title: 'Verify Phone',
        html: '<input id="otpCode" class="swal2-input" placeholder="6-digit SMS code">',
        showCancelButton: true,
        confirmButtonText: 'Verify',
        preConfirm: async (otp) => {
            if (!otp || otp.length !== 6) return false;

            const res = await fetch('/api/otps/verify-otp.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({otp, payment_reference: reference})
            });
            const result = await res.json();

            if (!result.success) {
                Swal.showValidationMessage(result.message);
                return false;
            }
            return true;
        },
        didOpen: async () => {
            // Send SMS
            await fetch('/api/otps/send-sms-otp.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({phone, purpose: 'payment'})
            });
        }
    }).then(result => {
        if (result.isConfirmed) onVerified();
        else if (onCancel) onCancel();
    });
}
