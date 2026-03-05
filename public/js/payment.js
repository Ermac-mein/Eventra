/**
 * Payment Logic
 * Handles card processing, OTP flow, and ticket generation.
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Data Loading
    const orderData = JSON.parse(sessionStorage.getItem('pending_order'));
    if (!orderData) {
        Swal.fire('Error', 'No pending order found.', 'error').then(() => {
            window.location.href = 'index.html';
        });
        return;
    }

    const { eventId, quantity, contactInfo } = orderData;
    let eventData = null;

    // Handle Free Events logic (moved inside data loaded)
    function setupFreeEventState() {
        const titleEl = document.querySelector('.section-title');
        if (titleEl) {
            titleEl.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Secure Confirmation
            `;
        }
        paymentForm.innerHTML = `
            <div style="text-align: center; padding: 2rem 0;">
                <p style="color: #64748b; margin-bottom: 2rem;">This event is free of charge. Click below to secure your tickets.</p>
                <button type="button" class="pay-btn" id="confirmFreeBtn">
                    Confirm & Claim Free Tickets
                </button>
            </div>
        `;
        document.getElementById('confirmFreeBtn').addEventListener('click', () => {
            finalizePayment();
        });
    }

    // Load Event Details for summary
    try {
        const res = await apiFetch(`../../api/events/get-event-details.php?event_id=${eventId}`);
        const result = await res.json();
        if (result.success && result.event) {
            eventData = result.event;
            // Handle free events state here to avoid flicker if loaded after
            const isFree = parseFloat(eventData.price || 0) === 0;
            if (isFree) {
                setupFreeEventState();
            }
            renderSummary(eventData, quantity);
            
            // Hide loading state and show content
            const paymentLoading = document.getElementById('paymentLoading');
            if(paymentLoading) paymentLoading.style.display = 'none';
            paymentForm.style.display = 'block';
        } else {
            Swal.fire('Error', 'Failed to load event details.', 'error').then(() => {
                window.location.href = 'index.html';
            });
        }
    } catch (e) {
        console.error('Failed to load event details', e);
        Swal.fire('Error', 'An error occurred fetching event details.', 'error');
    }

    paymentForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Card Validation (Simple)
        const cnum = document.getElementById('cardNumber')?.value.replace(/\s/g, '');
        const cexp = document.getElementById('cardExpiry')?.value.trim();
        const ccvv = document.getElementById('cardCvv')?.value.trim();

        if (cnum && (cnum.length < 16 || !cexp.includes('/') || ccvv.length < 3)) {
            showNotification('Please enter valid card details.', 'error');
            return;
        }

        // Show OTP selection as per Requirement 5
        otpSelectModal.style.display = 'flex';
    });

    // 3. OTP Flow
    let currentReference = 'PAY-' + Math.random().toString(36).substr(2, 9).toUpperCase();
    let selectedChannel = '';

    document.getElementById('channelEmail').addEventListener('click', () => sendOtp('email'));
    document.getElementById('channelSms').addEventListener('click', () => sendOtp('sms'));

    async function sendOtp(channel) {
        selectedChannel = channel;
        document.getElementById('activeChannel').textContent = channel;
        
        try {
            const res = await apiFetch('../../api/otps/generate-otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    channel: channel,
                    payment_reference: currentReference,
                    email: contactInfo.email,
                    phone: contactInfo.phone
                })
            });
            const result = await res.json();
            if (result.success) {
                otpSelectModal.style.display = 'none';
                otpInputModal.style.display = 'flex';
                showNotification('OTP sent!', 'success');
            } else {
                showNotification(result.message, 'error');
            }
        } catch (e) {
            showNotification('Failed to send OTP.', 'error');
        }
    }

    // OTP Input Handling
    const otpInputs = document.querySelectorAll('.otp-input');
    otpInputs.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            if (e.target.value && idx < 5) otpInputs[idx + 1].focus();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) otpInputs[idx - 1].focus();
        });
    });

    // 4. Verification & Finalization
    document.getElementById('verifyOtpBtn').addEventListener('click', async () => {
        const otp = Array.from(otpInputs).map(i => i.value).join('');
        if (otp.length < 6) {
            showNotification('Enter 6-digit code.', 'error');
            return;
        }

        const btn = document.getElementById('verifyOtpBtn');
        btn.disabled = true;
        btn.textContent = 'Verifying...';

        try {
            const res = await apiFetch('../../api/otps/verify-otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    otp: otp,
                    payment_reference: currentReference
                })
            });
            const result = await res.json();

            if (result.success) {
                finalizePayment();
            } else {
                showNotification(result.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Verify & Complete';
            }
        } catch (e) {
            showNotification('Verification Error.', 'error');
            btn.disabled = false;
            btn.textContent = 'Verify & Complete';
        }
    });

    async function finalizePayment() {
        Swal.fire({
            title: 'Finalizing Transaction',
            html: 'Connecting to banking server...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const isFree = parseFloat(eventData?.price || 0) === 0;
            const finalRef = isFree ? ('FREE-' + Math.random().toString(36).substr(2, 9).toUpperCase()) : currentReference;

            const res = await apiFetch('../../api/tickets/purchase-ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    event_id: eventId,
                    quantity: quantity,
                    payment_reference: finalRef // purchase-ticket.php will verify this
                })
            });
            const result = await res.json();

            if (result.success) {
                // Requirement 6: Only success UI after verification
                sessionStorage.removeItem('pending_order');
                Swal.fire({
                    title: 'Payment Successful!',
                    text: 'Your tickets have been generated and sent to your email.',
                    icon: 'success',
                    confirmButtonText: 'View My Tickets'
                }).then(() => {
                    window.location.href = '../../client/pages/tickets.html';
                });
            } else {
                Swal.fire('Payment Failed', result.message, 'error');
            }
        } catch (e) {
            Swal.fire('Fatal Error', 'Payment verification failed.', 'error');
        }
    }

    function renderSummary(event, qty) {
        const priceNum = parseFloat(event.price || 0);
        const total = priceNum * qty;
        const container = document.getElementById('summaryContent');
        
        let placeholderAttr = `onerror="this.src='https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&h=400&fit=crop'"`;
        const imgUrl = event.absolute_image_url || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&h=400&fit=crop';
        
        const locParts = [];
        if (event.address && event.address !== 'undefined') locParts.push(event.address);
        if (event.city && event.city !== 'undefined') locParts.push(event.city);
        if (event.state && event.state !== 'undefined') locParts.push(event.state);
        let locStr = locParts.join(', ');
        if (!locStr) locStr = event.location || 'Location details unavailable';

        container.innerHTML = `
            <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                <img src="${imgUrl}" ${placeholderAttr} style="width: 80px; height: 80px; border-radius: 1rem; object-fit: cover;">
                <div>
                    <h4 style="font-weight: 700;">${event.event_name}</h4>
                    <p style="font-size: 0.8rem; color: #64748b;">${locStr}</p>
                </div>
            </div>
            <div class="summary-item">
                <span>Price</span>
                <span>${priceNum === 0 ? 'FREE' : '₦' + priceNum.toLocaleString()}</span>
            </div>
            <div class="summary-total">
                <span>Total Amount</span>
                <span>${total === 0 ? 'FREE' : '₦' + total.toLocaleString()}</span>
            </div>
        `;
    }
});
