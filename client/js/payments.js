/**
 * Payments Dashboard — Client JS
 * Handles: loading, filtering, sorting, pagination, detail modal, export
 */

let _paymentsState = {
    viewMode: 'transactions', // 'transactions' or 'refunds'
    page: 1,
    limit: 20,
    sort: 'date_desc',
    dateRange: 'all',
    status: '',
    search: '',
    totalPages: 1,
    allPayments: [], // current loaded set for stats
};

// ─── App Role Detection ────────────────────────────────────────────────────
const _isAdmin = (() => {
    try {
        const user = storage.getUser();
        return user && user.role === 'admin';
    } catch (e) { return false; }
})();

// ─── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    const user = storage.getUser();
    if (!user) return;

    // Avatar
    // Load client profile for avatar consistency
    const userProfileAvatars = document.querySelectorAll('.user-avatar');
    if (userProfileAvatars.length > 0) {
        const avatarUrl = user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || user.business_name || 'User')}&background=random&color=fff`;
        userProfileAvatars.forEach(avatar => {
            avatar.style.backgroundImage = `url(${avatarUrl})`;
            avatar.style.backgroundSize = 'cover';
            avatar.style.backgroundPosition = 'center';
            avatar.textContent = '';
        });
    }

    // Search functionality handled by global search in header if needed, 
    // or removed as per user request to declutter the table area.

    // Wire sort
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', (e) => {
            _paymentsState.sort = e.target.value;
            _paymentsState.page = 1;
            loadPayments();
        });
    }

    // Wire date-range filter tabs
    document.querySelectorAll('[data-range]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-range]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _paymentsState.dateRange = btn.dataset.range;
            _paymentsState.page = 1;
            loadPayments();
        });
    });

    // Wire status filter tabs
    document.querySelectorAll('[data-status]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-status]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _paymentsState.status = btn.dataset.status;
            _paymentsState.page = 1;
            loadPayments();
        });
    });

    // Wire View Mode tabs
    document.querySelectorAll('[data-view]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _paymentsState.viewMode = btn.dataset.view;
            _paymentsState.page = 1;
            _paymentsState.status = ''; // reset status filter when switching views
            
            // Sync status tabs UI
            document.querySelectorAll('[data-status]').forEach(b => {
                b.classList.toggle('active', b.dataset.status === '');
            });

            updateTableHeaders();
            loadPayments();
        });
    });

    await loadPayments();
});

function updateTableHeaders() {
    const theadRow = document.querySelector('thead tr');
    if (!theadRow) return;

    if (_paymentsState.viewMode === 'transactions') {
        theadRow.innerHTML = `
            <th style="cursor:pointer;" onclick="changeSort('date_desc','date_asc')">Date <i data-lucide="chevron-down" style="width:14px;display:inline-block;vertical-align:middle;margin-left:4px;"></i></th>
            <th style="cursor:pointer;" onclick="changeSort('event','event')">Event</th>
            <th>Organizer</th>
            <th style="cursor:pointer;" onclick="changeSort('amount_desc','amount_asc')">Amount</th>
            <th style="text-align:center;">Tickets</th>
            <th>User Email</th>
            <th style="cursor:pointer;" onclick="changeSort('status','status')">Status</th>
        `;
    } else {
        theadRow.innerHTML = `
            <th>Date Requested</th>
            <th>Event / User</th>
            <th>Reason</th>
            <th>Amount</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
        `;
    }
    if (window.lucide) lucide.createIcons();
}

// ─── Load payments from API ────────────────────────────────────────────────
async function loadPayments() {
    const tbody = document.getElementById('paymentsTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;"><span class="btn-spinner" style="margin-right:8px"></span>Loading...</td></tr>';

    const { viewMode, page, limit, sort, dateRange, status, search } = _paymentsState;
    
    if (viewMode === 'transactions') {
        const params = new URLSearchParams({
            page, limit, sort,
            date_range: dateRange,
            ...(status && { status }),
            ...(search && { search }),
        });

        try {
            const res = await apiFetch(`../../api/payments/get-payments.php?${params}`);
            const data = await res.json();

            if (!data.success) {
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2rem;color:#ef4444;">Failed to load payments: ${data.message}</td></tr>`;
                return;
            }

            _paymentsState.totalPages = data.pages || 1;
            _paymentsState.allPayments = data.payments || [];

            renderPaymentsTable(data.payments);
            renderPagination(data.total, data.page, data.limit, data.pages);
            computeStats(data.payments, data.total);
        } catch (err) {
            console.error('Payments load error', err);
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#ef4444;">An error occurred loading payments.</td></tr>';
        }
    } else {
        // Load Refund Requests
        const params = new URLSearchParams({
            ...(status && { status }),
        });
        try {
            const res = await apiFetch(`../../api/payments/get-refund-requests.php?${params}`);
            const data = await res.json();

            if (!data.success) {
                if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:#ef4444;">Failed to load refunds: ${data.message}</td></tr>`;
                return;
            }

            renderRefundsTable(data.requests);
            // Pagination not implemented for refunds in this version (usually smaller set)
            const info = document.getElementById('paginationInfo');
            if (info) info.textContent = `Total ${data.total} refund requests`;
            const btns = document.getElementById('paginationBtns');
            if (btns) btns.innerHTML = '';
        } catch (err) {
            console.error('Refunds load error', err);
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#ef4444;">An error occurred loading refund requests.</td></tr>';
        }
    }
}

// ─── Render Table ──────────────────────────────────────────────────────────
function renderPaymentsTable(payments) {
    const tbody = document.getElementById('paymentsTableBody');
    if (!tbody) return;

    if (!payments.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">No payments found.</td></tr>';
        return;
    }

    tbody.innerHTML = payments.map(p => {
        const badgeClass = `status-${p.status}`;
        const amountDisplay = parseFloat(p.amount) === 0 ? '<span style="color:#10b981;font-weight:700">Free</span>' : `₦${parseFloat(p.amount).toLocaleString()}`;
        const rowData = encodeURIComponent(JSON.stringify(p));
        return `
        <tr class="table-row-clickable" onclick="openDetailModal(${JSON.stringify(p).replace(/"/g,'&quot;')})">
            <td>
                <div style="font-weight:600;color:#1e293b;font-size:0.9rem;">${p.relative_time}</div>
                <div style="font-size:0.78rem;color:#94a3b8;">${new Date(p.created_at).toLocaleString()}</div>
            </td>
            <td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHtml(p.event_name || '-')}">
                ${escapeHtml(p.event_name || '—')}
            </td>
            <td>
                <span style="font-size:0.85rem;color:#475569;font-weight:500;">${escapeHtml(p.client_name || '—')}</span>
            </td>
            <td style="font-weight:700;">${amountDisplay}</td>
            <td style="text-align:center;">
                <span style="background:#eef2ff;color:#4f46e5;padding:2px 10px;border-radius:20px;font-size:0.8rem;font-weight:700;">${p.ticket_count || 0}</span>
            </td>
            <td>
                <span style="font-size:0.85rem;color:#64748b;">${escapeHtml(p.buyer_email || '—')}</span>
            </td>
            <td><span class="status-badge ${badgeClass}">${ucfirst(p.status)}</span></td>
        </tr>`;
    }).join('');
}

function renderRefundsTable(requests) {
    const tbody = document.getElementById('paymentsTableBody');
    if (!tbody) return;

    if (!requests.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">No refund requests found.</td></tr>';
        return;
    }

    tbody.innerHTML = requests.map(r => {
        const statusClass = `status-${r.status}`;
        return `
        <tr>
            <td>
                <div style="font-weight:600;">${new Date(r.created_at).toLocaleDateString()}</div>
                <div style="font-size:0.75rem;color:#64748b;">${new Date(r.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>
            </td>
            <td>
                <div style="font-weight:700;color:#1e293b;">${escapeHtml(r.event_name)}</div>
                <div style="font-size:0.85rem;color:#64748b;">Requested by: ${escapeHtml(r.user_name)}</div>
            </td>
            <td style="max-width:240px;font-size:0.9rem;line-height:1.4;">${escapeHtml(r.reason)}</td>
            <td style="font-weight:700;color:#ef4444;">₦${parseFloat(r.amount).toLocaleString()}</td>
            <td><span class="status-badge ${statusClass}">${ucfirst(r.status)}</span></td>
            <td style="text-align:right;">
                ${r.status === 'pending' ? `
                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                        <button onclick="reviewRefund(${r.id}, 'approve')" class="page-btn active" style="padding:4px 12px;background:#10b981;border-color:#10b981;font-size:0.8rem;">Approve</button>
                        <button onclick="reviewRefund(${r.id}, 'decline')" class="page-btn" style="padding:4px 12px;color:#ef4444;font-size:0.8rem;">Decline</button>
                    </div>
                ` : `
                    <span style="font-size:0.75rem;color:#94a3b8;">Processed ${r.processed_at ? new Date(r.processed_at).toLocaleDateString() : 'n/a'}</span>
                `}
            </td>
        </tr>`;
    }).join('');
}

async function reviewRefund(requestId, action) {
    let note = '';
    if (action === 'decline') {
        const { value: text } = await Swal.fire({
            title: 'Decline Reason',
            input: 'textarea',
            inputLabel: 'Provide a reason for the user',
            inputPlaceholder: 'Type your reason here...',
            inputAttributes: { 'aria-label': 'Type your reason here' },
            showCancelButton: true
        });
        if (text === undefined) return; // cancelled
        note = text;
    } else {
        const confirm = await Swal.fire({
            title: 'Approve Refund?',
            text: "This will call Paystack to refund the money and cancel the ticket. This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: 'Yes, Approve'
        });
        if (!confirm.isConfirmed) return;
    }

    try {
        const res = await apiFetch('../../api/payments/review-refund.php', {
            method: 'POST',
            body: JSON.stringify({ refund_request_id: requestId, action, note })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Success', data.message, 'success');
            loadPayments();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (err) {
        console.error('Refund review error', err);
        Swal.fire('Error', 'An error occurred while processing the refund.', 'error');
    }
}

// ─── Stats Cards ───────────────────────────────────────────────────────────
function computeStats(payments, total) {
    const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    const paid    = payments.filter(p => p.status === 'paid');
    const failed  = payments.filter(p => p.status === 'failed');
    const revenue = paid.reduce((s, p) => s + parseFloat(p.amount), 0);
    setEl('statTotal',   total);
    setEl('statPaid',    paid.length);
    setEl('statFailed',  failed.length);
    setEl('statRevenue', revenue === 0 ? '₦0' : `₦${revenue.toLocaleString(undefined, {minimumFractionDigits: 0})}`);
}

// ─── Pagination ────────────────────────────────────────────────────────────
function renderPagination(total, page, limit, pages) {
    const info = document.getElementById('paginationInfo');
    const btns = document.getElementById('paginationBtns');
    if (!info || !btns) return;

    const from = total === 0 ? 0 : (page - 1) * limit + 1;
    const to   = Math.min(page * limit, total);
    info.textContent = `Showing ${from}–${to} of ${total} payments`;

    btns.innerHTML = '';
    const prev = document.createElement('button');
    prev.className = 'page-btn';
    prev.textContent = '← Prev';
    prev.disabled = page <= 1;
    prev.onclick = () => { _paymentsState.page--; loadPayments(); };
    btns.appendChild(prev);

    // Show nearby pages
    const startPage = Math.max(1, page - 2);
    const endPage   = Math.min(pages, page + 2);
    for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement('button');
        btn.className = `page-btn${i === page ? ' active' : ''}`;
        btn.textContent = i;
        const pg = i;
        btn.onclick = () => { _paymentsState.page = pg; loadPayments(); };
        btns.appendChild(btn);
    }

    const next = document.createElement('button');
    next.className = 'page-btn';
    next.textContent = 'Next →';
    next.disabled = page >= pages;
    next.onclick = () => { _paymentsState.page++; loadPayments(); };
    btns.appendChild(next);
}

// ─── Detail Modal ──────────────────────────────────────────────────────────
function openDetailModal(payment) {
    const modal = document.getElementById('paymentDetailModal');
    const content = document.getElementById('paymentDetailContent');
    if (!modal || !content) return;

    const statusClass = `status-${payment.status}`;
    const amountDisplay = parseFloat(payment.amount) === 0
        ? '<span style="color:#10b981;font-weight:700">Free</span>'
        : `<strong>₦${parseFloat(payment.amount).toLocaleString()}</strong>`;

    const imageUrl = payment.event_image ? payment.event_image : '/public/assets/event-placeholder.jpg';
    const backgroundImage = payment.event_image ? `url(${imageUrl})` : 'linear-gradient(135deg, #f1f5f9, #e2e8f0)';

    content.innerHTML = `
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="width: 100px; height: 100px; border-radius: 16px; background: ${backgroundImage}; background-size: cover; background-position: center; margin: 0 auto 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></div>
            <h3 style="font-size:1.25rem;font-weight:700;color:#1e293b;margin:0 0 0.5rem;">${escapeHtml(payment.event_name || '—')}</h3>
            <p style="font-size:0.9rem;color:#64748b;margin:0 0 1rem;">Organized by: <span style="font-weight:600;color:#1e293b;">${escapeHtml(payment.client_name || '—')}</span></p>
            <span class="status-badge ${statusClass}" style="font-size:1rem;padding:.4rem 1.2rem;">${ucfirst(payment.status)}</span>
        </div>
        <div class="detail-row"><span class="detail-label">Reference</span><span class="detail-value" style="font-family:monospace;font-size:0.85rem">${payment.reference || '—'}</span></div>
        <div class="detail-row"><span class="detail-label">Amount</span><span class="detail-value">${amountDisplay}</span></div>
        <div class="detail-row"><span class="detail-label">Tickets</span><span class="detail-value">${payment.ticket_count || 0} ticket(s)</span></div>
        <div class="detail-row"><span class="detail-label">Buyer</span><span class="detail-value">${escapeHtml(payment.buyer_name || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${escapeHtml(payment.buyer_email || '—')}</span></div>
        <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value">${new Date(payment.created_at).toLocaleString()}</span></div>
        ${payment.paid_at ? `<div class="detail-row"><span class="detail-label">Paid At</span><span class="detail-value">${new Date(payment.paid_at).toLocaleString()}</span></div>` : ''}
        ${payment.ticket_barcodes ? `<div class="detail-row"><span class="detail-label">Barcodes</span><span class="detail-value" style="font-family:monospace;font-size:0.8rem;word-break:break-all">${payment.ticket_barcodes}</span></div>` : ''}
    `;

    modal.classList.add('open');
    document.addEventListener('keydown', closeModalOnEsc);
}

function closeDetailModal() {
    const modal = document.getElementById('paymentDetailModal');
    if (modal) modal.classList.remove('open');
    document.removeEventListener('keydown', closeModalOnEsc);
}

function closeModalOnEsc(e) {
    if (e.key === 'Escape') closeDetailModal();
}

// ─── Sort helper ───────────────────────────────────────────────────────────
function changeSort(desc, asc) {
    const current = _paymentsState.sort;
    const sel = document.getElementById('sortSelect');
    const newSort = current === desc ? asc : desc;
    _paymentsState.sort = newSort;
    _paymentsState.page = 1;
    if (sel) sel.value = newSort;
    loadPayments();
}

// ─── Export ────────────────────────────────────────────────────────────────
function toggleExportMenu(btn) {
    const menu = document.getElementById('exportMenu');
    if (menu) menu.classList.toggle('open');
    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.export-dropdown')) {
            menu && menu.classList.remove('open');
            document.removeEventListener('click', closeMenu);
        }
    });
}

function exportPayments(format) {
    const menu = document.getElementById('exportMenu');
    if (menu) menu.classList.remove('open');

    const { sort, dateRange, status, search } = _paymentsState;
    const params = new URLSearchParams({
        format, sort,
        date_range: dateRange,
        ...(status && { status }),
        ...(search && { search }),
    });
    window.open(`../../api/payments/export-payments.php?${params}`, '_blank');
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Expose for onclick attributes
window.openDetailModal = openDetailModal;
window.closeDetailModal = closeDetailModal;
window.changeSort = changeSort;
window.toggleExportMenu = toggleExportMenu;
window.exportPayments = exportPayments;
