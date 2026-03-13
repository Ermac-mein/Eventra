/**
 * Payment Logic — Callback & Verification
 * Handles: Paystack redirect callback, order polling, and success UI.
 */

document.addEventListener('DOMContentLoaded', async () => {
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

        startPolling(reference);
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

    // 3. If we have a Paystack authorization_url, redirect immediately
    if (authorization_url) {
        window.location.href = authorization_url;
        return;
    }

    // 4. Fallback: Load Event Details for summary / Legacy OTP Flow / Free Events
    try {
        const res = await apiFetch(`../../api/events/get-event-details.php?event_id=${eventId}`);
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
const maxPolls = 20; // 1 minute roughly

async function startPolling(reference) {
    const icon = document.getElementById('statusIcon');
    const title = document.getElementById('statusTitle');
    const msg = document.getElementById('statusMessage');
    const actions = document.getElementById('successActions');
    const downloadBtn = document.getElementById('downloadTicketBtn');

    const poll = async () => {
        pollCount++;
        try {
            const res = await apiFetch(`../../api/payments/get-order.php?reference=${reference}`);
            const result = await res.json();

            if (result.success && result.order) {
                const order = result.order;
                
                if (order.payment_status === 'paid') {
                    // SUCCESS!
                    icon.textContent = '🎉';
                    title.textContent = 'Payment Successful!';
                    msg.innerHTML = `Your tickets for <strong>${order.event_name}</strong> are ready.<br>Reference: ${reference}`;
                    
                    if (order.tickets && order.tickets.length > 0) {
                        const barcode = order.tickets[0].barcode;
                        downloadBtn.href = `../../api/tickets/download-ticket.php?barcode=${barcode}`;
                        downloadBtn.target = '_blank';
                        actions.style.display = 'flex';
                    }
                    
                    sessionStorage.removeItem('pending_order');
                    return; // Stop polling
                } 
                
                if (order.payment_status === 'failed') {
                    icon.textContent = '❌';
                    title.textContent = 'Payment Failed';
                    msg.textContent = 'Paystack declined the transaction. Please try again.';
                    return;
                }
            }
        } catch (e) {
            console.error('Polling error', e);
        }

        if (pollCount >= maxPolls) {
            icon.textContent = '🤔';
            title.textContent = 'Taking a bit longer...';
            msg.innerHTML = "We haven't received confirmation yet. If you've been debited, don't worry—your ticket will be sent to your email eventually.<br><br>You can safely close this page.";
            return;
        }

        setTimeout(poll, 3000); // Poll every 3 seconds
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
            const res = await apiFetch('../../api/tickets/purchase-ticket.php', {
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
    const imgUrl = encodeURI(relPath || event.absolute_image_url || fallback);
    
    const locStr = [event.address, event.city, event.state].filter(Boolean).join(', ') || 'Location details unavailable';

    container.innerHTML = `
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <img src="${imgUrl}" onerror="this.src='${fallback}'" style="width: 80px; height: 80px; border-radius: 1rem; object-fit: cover;">
            <div>
                <h4 style="font-weight: 700; color: #1e293b;">${event.event_name}</h4>
                <p style="font-size: 0.8rem; color: #64748b;">${locStr}</p>
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
            <span>Total Amount</span>
            <span>${total === 0 ? 'FREE' : '₦' + total.toLocaleString()}</span>
        </div>
    `;
}

// ─── Legacy OTP Flow (Optional Refactor) ────────────────────────────────────
// Keeping variables to maintain minimal functionality if form is shown
let currentReference = 'PAY-' + Math.random().toString(36).substr(2, 9).toUpperCase();

function setupLegacyFlow(form, ref, contact, eid, qty) {
    // This is essentially parts of the old payment.js 
    // Simplified for this version to focus on Paystack Redirect
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        Swal.fire('Marketplace Notice', 'Please use the official Paystack gateway.', 'info');
    });
}

