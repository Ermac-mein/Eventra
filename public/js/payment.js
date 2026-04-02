/**
 * Payment Logic — Callback & Verification
 * Handles: Paystack redirect callback, order polling, and success UI.
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 0. Wait for AuthController to be ready to ensure tokens/session are synced
    if (window.authController) {
        await window.authController.init();
        await window.authController.ready;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const reference = urlParams.get('reference');
    const orderData = JSON.parse(sessionStorage.getItem('pending_order'));
    
    const paymentLoading = document.getElementById('paymentLoading');
    const paymentForm = document.getElementById('paymentForm');
    const statusContainer = document.getElementById('paymentStatusContainer');
    const summaryContent = document.getElementById('summaryContent');

    // 1. Check if this is a callback from Paystack
    if (reference) {
        if (paymentLoading) paymentLoading.style.display = 'none';
        if (paymentForm) paymentForm.style.display = 'none';
        if (statusContainer) statusContainer.style.display = 'block';

        // Trigger server-side verification (Idempotent)
        (async () => {
            const title = document.getElementById('statusTitle');
            const msg = document.getElementById('statusMessage');
            const icon = document.getElementById('statusIcon');

            if (title) title.textContent = 'Verifying Payment...';
            if (msg) msg.textContent = 'Confirming your transaction...';
            if (icon) icon.textContent = '⏳';

            try {
                const verifyRes = await apiFetch(`/api/payments/verify-payment.php?reference=${reference}`);
                // Proceed to polling regardless of immediate result, get-order will handle the final state
                startPolling(reference);
            } catch (err) {
                console.error('Verification trigger failed', err);
                startPolling(reference);
            }
        })();
        return;
    }

    // 2. No reference? Check for pending order in session
    if (!orderData) {
        Swal.fire('Error', 'No pending order found.', 'error').then(() => {
            window.location.href = 'index.html';
        });
        return;
    }

    const { eventId, quantity, contactInfo, authorization_url } = orderData;

    // 3. Pending order has an authorization URL (Paid Event)
    if (authorization_url) {
        if (paymentForm) paymentForm.style.display = 'none';
        
        // Show OTP modal
        showOTPModal(
            contactInfo.email,
            contactInfo.phone,
            (verified) => {
                // OTP verified - proceed to Paystack
                window.location.href = authorization_url;
            },
            () => {
                // OTP cancelled - show back button
                Swal.fire('Payment Cancelled', 'You cancelled the OTP verification.', 'info').then(() => {
                    window.location.href = 'checkout.html?id=' + eventId + '&quantity=' + quantity;
                });
            }
        );
        return;
    }

    // 4. Fallback: Load Event Details for summary / Legacy OTP Flow / Free Events
    try {
        const res = await apiFetch(`/api/events/get-event-details.php?event_id=${eventId}`);
        const result = await res.json();
        
        if (result.success && result.event) {
            const eventData = result.event;
            renderSummary(eventData, quantity);
            
            const isFree = parseFloat(eventData.price || 0) === 0;
            if (isFree) {
                setupFreeEventState(paymentForm, eventData, quantity);
            } else {
                // If it's not free and has no auth_url, it's likely the old manual OTP flow
                // Re-enable the form for legacy support if needed, but marketplace is priority
                if (paymentLoading) paymentLoading.style.display = 'none';
                if (paymentForm) paymentForm.style.display = 'block';
                setupLegacyFlow(paymentForm, currentReference, contactInfo, eventId, quantity);
            }
        } else {
            Swal.fire('Error', 'Failed to load event details.', 'error').then(() => {
                window.location.href = 'index.html';
            });
        }
    } catch (e) {
        console.error('Failed to load event details', e);
        Swal.fire('Error', 'An error occurred fetching event details.', 'error');
    }
});

// ─── Polling Logic ──────────────────────────────────────────────────────────

let pollCount = 0;
let consecutiveErrors = 0;
const maxPolls = 15; // ~45 seconds of polling
const maxConsecutiveErrors = 3;

async function startPolling(reference) {
    const icon = document.getElementById('statusIcon');
    const title = document.getElementById('statusTitle');
    const msg = document.getElementById('statusMessage');
    const actions = document.getElementById('successActions');
    const downloadBtn = document.getElementById('downloadTicketBtn');

    const poll = async () => {
        pollCount++;
        
        try {
            const res = await apiFetch(`/api/payments/get-order.php?reference=${reference}`);
            
            if (!res) {
                console.warn('Polling stopped: User unauthenticated or request aborted.');
                return;
            }

            // Reset consecutive errors on any successful response (even if 404/500 is handled by apiFetch as throw)
            // Wait, apiFetch throws for 404/500. So we only reach here for 200 OK.
            consecutiveErrors = 0;

            const result = await res.json();

            if (result.success && result.order) {
                const order = result.order;
                const status = result.status || order.payment_status;
                
                if (status === 'paid' || status === 'success') {
                    // SUCCESS!
                    const cleanedName = (order.event_name || '').replace(/\s*#\d+$/, '');
                    icon.textContent = '🎉';
                    title.textContent = 'Payment Successful!';
                    msg.innerHTML = `Your tickets for <strong>${escapeHTML(cleanedName)}</strong> are ready.<br>Reference: ${escapeHTML(reference)}`;
                    
                    if (order) {
                        renderSummary(order, 1); // Pass order object and a default quantity since order might not have it in expected format
                    }
                    
                    if (order.ticket && order.ticket.barcode) {
                        const barcode = order.ticket.barcode;
                        downloadBtn.href = `/api/tickets/download-ticket.php?code=${barcode}`;
                        downloadBtn.target = '_blank';
                        actions.style.display = 'flex';
                    }
                    
                    sessionStorage.removeItem('pending_order');
                    sessionStorage.setItem('purchase_success_redirection', 'true');
                    return; // Stop polling

                } 
                
                if (status === 'failed') {
                    icon.textContent = '❌';
                    title.textContent = 'Payment Failed';
                    msg.textContent = 'The transaction was declined. Please try again or contact support.';
                    return; // Stop polling
                }

                // If status is 'pending', we continue polling below
                if (pollCount % 3 === 0) {
                    msg.textContent = 'Still waiting for confirmation from the payment gateway...';
                }
            }
        } catch (e) {
            consecutiveErrors++;
            console.error(`Polling error (${consecutiveErrors}/${maxConsecutiveErrors}):`, e);

            if (consecutiveErrors >= maxConsecutiveErrors) {
                icon.textContent = '⚠️';
                title.textContent = 'Connection Issue';
                msg.textContent = 'We are having trouble reaching the server. Please refresh the page in a few moments to check your status.';
                return; // Stop polling on repeated errors
            }
            
            // For 404 Specifically (if handled by apiFetch throw)
            if (e.message.includes('404')) {
                // If it's early in polling, treat 404 as "not yet created"
                if (pollCount > 8) {
                    icon.textContent = '❓';
                    title.textContent = 'Order Not Found';
                    msg.textContent = 'We could not locate your order record. If you were debited, please contact support with your reference.';
                    return;
                }
            }
        }

        if (pollCount >= maxPolls) {
            icon.textContent = '⏳';
            title.textContent = 'Verification in Progress';
            msg.innerHTML = "Confirmation is taking longer than expected. We'll continue processing in the background. You can safely close this page and check your mail later.";
            return;
        }

        setTimeout(poll, 4000); // Increased delay to 4s as requested (3-5s)
    };

    poll();
}

// ─── Free Event Handler ─────────────────────────────────────────────────────

function setupFreeEventState(form, eventData, quantity) {
    const paymentLoading = document.getElementById('paymentLoading');
    if (paymentLoading) paymentLoading.style.display = 'none';
    form.style.display = 'block';

    const titleEl = document.querySelector('.section-title');
    if (titleEl) {
        titleEl.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> Confirm Free Tickets`;
    }
    
    form.innerHTML = `
        <div style="text-align: center; padding: 1rem 0;">
            <p style="color: #64748b; margin-bottom: 2rem;">This event is free. Click below to secure your ${quantity} ticket(s).</p>
            <button type="button" class="pay-btn" id="confirmFreeBtn">
                ✓ Confirm & Claim Free Tickets
            </button>
        </div>
    `;

    document.getElementById('confirmFreeBtn').addEventListener('click', async () => {
        const btn = document.getElementById('confirmFreeBtn');
        btn.disabled = true;
        btn.textContent = 'Processing...';

        try {
            const finalRef = 'FREE-' + Math.random().toString(36).substr(2, 9).toUpperCase();
            const res = await apiFetch('/api/tickets/purchase-ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    event_id: eventData.id,
                    quantity: quantity,
                    payment_reference: finalRef
                })
            });
            const result = await res.json();

            if (result.success) {
                sessionStorage.removeItem('pending_order');
                Swal.fire({
                    title: 'Tickets Issued!',
                    text: 'Your free tickets are ready. Check your email.',
                    icon: 'success'
                }).then(() => { window.location.href = 'index.html'; });
            } else {
                Swal.fire('Error', result.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Confirm & Claim Free Tickets';
            }
        } catch (e) {
            Swal.fire('Error', 'An internal error occurred.', 'error');
            btn.disabled = false;
        }
    });
}

// ─── Summary UI ─────────────────────────────────────────────────────────────

function renderSummary(event, qty) {
    const priceNum = parseFloat(event.price || 0);
    const total = priceNum * qty;
    const container = document.getElementById('summaryContent');
    if (!container) return;
    
    const relPath = event.image_path ? `../../${event.image_path.replace(/^\/+/ , '')}` : null;
    const fallback = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&h=400&fit=crop';
    const imgUrl = (relPath || event.absolute_image_url || fallback);
    const cleanEventName = (event.event_name || '').replace(/\s*#\d+$/, '');
    
    // Normalize address/location
    let locationStr = '';
    if (event.location || event.address) {
        locationStr = [event.location || event.address, event.city, event.state].filter(Boolean).join(', ');
    } else {
        locationStr = 'Location details unavailable';
    }

    container.innerHTML = `
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <img src="${imgUrl}" onerror="this.src='${fallback}'" style="width: 80px; height: 80px; border-radius: 1rem; object-fit: cover;">
            <div>
                <h4 style="font-weight: 700; color: #1e293b;">${escapeHTML(cleanEventName)}</h4>
                <p style="font-size: 0.8rem; color: #64748b;">${escapeHTML(locationStr)}</p>
            </div>
        </div>
        <div class="summary-item">
            <span>Price</span>
            <span>${priceNum === 0 ? 'FREE' : '₦' + priceNum.toLocaleString()}</span>
        </div>
        <div class="summary-item">
            <span>Quantity</span>
            <span>× ${qty}</span>
        </div>
        <div class="summary-total">
            <span>Amount Paid</span>
            <span>${total === 0 ? 'FREE' : '₦' + total.toLocaleString()}</span>
        </div>
    `;
}

// ─── Legacy OTP Flow (Optional Refactor) ────────────────────────────────────
// Keeping variables to maintain minimal functionality if form is shown
let currentReference = 'PAY-' + Math.random().toString(36).substr(2, 9).toUpperCase();

function setupLegacyFlow(form, ref, contact, eid, qty) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Use the centralized showOTPModal instead of hardcoded ones
        showOTPModal(
            contact.email,
            contact.phone,
            () => {
                // OTP verified - proceed to purchase
                completePurchaseAction(ref, eid, qty);
            },
            () => {
                showNotification('Verification cancelled', 'info');
            }
        );
    });
}

// Functions below removed to prevent conflict with otp-modal.js
// triggerOTP and verifyOTP are now handled by showOTPModal utility

async function completePurchaseAction(reference, eventId, quantity) {
    try {
        const res = await apiFetch('/api/tickets/purchase-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                event_id: eventId,
                quantity: quantity,
                payment_reference: reference
            })
        });
        const result = await res.json();

        if (result.success) {
            // Success container logic
            const paymentForm = document.getElementById('paymentForm');
            const statusContainer = document.getElementById('paymentStatusContainer');
            if (paymentForm) paymentForm.style.display = 'none';
            if (statusContainer) statusContainer.style.display = 'block';
            
            startPolling(reference);
        } else {
            showNotification(result.message, 'error');
        }
    } catch (e) {
        showNotification('Error completing purchase', 'error');
    }
}

