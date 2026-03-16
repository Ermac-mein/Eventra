/**
 * Admin Tickets Dashboard — Fully rewritten
 * Supports status filtering, search, sort, pagination, and ticket detail modal.
 */

let _allTickets = [];
let _filteredTickets = [];
let _tktSort = { key: 'created_at', dir: 'desc' };
let _tktPage = 1;
const TKT_PER_PAGE = 20;

document.addEventListener('DOMContentLoaded', async () => {
    await loadTickets();

    // Status filter tabs
    document.querySelectorAll('[data-status]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-status]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _tktPage = 1;
            applyFilters();
        });
    });

    // Search
    const search = document.getElementById('ticketSearchInput');
    if (search) {
        let debounce;
        search.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => { _tktPage = 1; applyFilters(); }, 350);
        });
    }
});

async function loadTickets() {
    const tbody = document.getElementById('ticketsTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">Loading...</td></tr>`;

    try {
        const res = await apiFetch('../../api/admin/get-tickets.php?limit=1000');
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed');
        _allTickets = data.tickets || [];
        applyFilters();
        updateStats(_allTickets);
    } catch (err) {
        console.error('Tickets load error', err);
        const tbody = document.getElementById('ticketsTableBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2rem;color:#ef4444;">Error loading tickets.</td></tr>`;
    }
}

function applyFilters() {
    const statusFilter = (document.querySelector('[data-status].active') || {}).dataset?.status ?? '';
    const search = (document.getElementById('ticketSearchInput') || {}).value?.toLowerCase() ?? '';

    _filteredTickets = _allTickets.filter(t => {
        const matchStatus = !statusFilter || t.status === statusFilter;
        const matchSearch = !search ||
            (t.ticket_code || '').toLowerCase().includes(search) ||
            (t.event_name || '').toLowerCase().includes(search) ||
            (t.user_name || '').toLowerCase().includes(search) ||
            (t.category || '').toLowerCase().includes(search);
        return matchStatus && matchSearch;
    });

    // Apply sort
    _filteredTickets.sort((a, b) => {
        let va = a[_tktSort.key] ?? '';
        let vb = b[_tktSort.key] ?? '';
        if (_tktSort.key === 'total_price') { va = parseFloat(va) || 0; vb = parseFloat(vb) || 0; }
        else { va = va.toString().toLowerCase(); vb = vb.toString().toLowerCase(); }
        if (va < vb) return _tktSort.dir === 'asc' ? -1 : 1;
        if (va > vb) return _tktSort.dir === 'asc' ? 1 : -1;
        return 0;
    });

    renderTicketsTable();
    renderTktPagination();
}

function sortTicketsTable(key) {
    if (_tktSort.key === key) {
        _tktSort.dir = _tktSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        _tktSort.key = key;
        _tktSort.dir = 'asc';
    }
    _tktPage = 1;
    applyFilters();
}

function renderTicketsTable() {
    const tbody = document.getElementById('ticketsTableBody');
    if (!tbody) return;

    const start = (_tktPage - 1) * TKT_PER_PAGE;
    const page = _filteredTickets.slice(start, start + TKT_PER_PAGE);

    if (!page.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">No tickets found.</td></tr>`;
        return;
    }

    tbody.innerHTML = page.map(t => {
        const statusClass = t.status === 'active' ? 'tkt-active' : t.status === 'used' ? 'tkt-used' : 'tkt-cancelled';
        const statusLabel = { active: '✓ Active', used: '👁 Used', cancelled: '✕ Cancelled' }[t.status] || t.status;
        const price = parseFloat(t.total_price) === 0
            ? '<span style="color:#10b981;font-weight:700">Free</span>'
            : `<strong>₦${parseFloat(t.total_price).toLocaleString()}</strong>`;
        const imgSrc = t.event_image
            ? (t.event_image.startsWith('http') ? t.event_image : '../../' + t.event_image)
            : '';
        const imgEl = imgSrc
            ? `<img src="${imgSrc}" class="tkt-event-img" onerror="this.style.display='none'">`
            : `<span class="tkt-event-img" style="display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;">🎟</span>`;
        const date = t.created_at ? new Date(t.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : '—';
        const encoded = JSON.stringify(t).replace(/"/g, '&quot;');

        return `<tr onclick="openAdminTicketModal(${encoded})">
            <td style="font-family:monospace;font-size:.8rem;color:#475569;">${t.ticket_code || t.id}</td>
            <td>
                ${imgEl}
                <span class="tkt-event-name" title="${escapeHtml(t.event_name || '')}">${escapeHtml(t.event_name || '—')}</span>
            </td>
            <td style="font-weight:500;color:#374151;">${escapeHtml(t.user_name || '—')}</td>
            <td>${price}</td>
            <td><span style="font-size:.82rem;color:#64748b;">${escapeHtml(t.category || 'General')}</span></td>
            <td style="font-size:.83rem;color:#64748b;">${date}</td>
            <td><span class="tkt-badge ${statusClass}">${statusLabel}</span></td>
        </tr>`;
    }).join('');

    if (window.lucide) lucide.createIcons();
}

function renderTktPagination() {
    const info = document.getElementById('tktPagInfo');
    const btns = document.getElementById('tktPagBtns');
    if (!info || !btns) return;

    const total = _filteredTickets.length;
    const pages = Math.max(1, Math.ceil(total / TKT_PER_PAGE));
    const from = total === 0 ? 0 : (_tktPage - 1) * TKT_PER_PAGE + 1;
    const to = Math.min(_tktPage * TKT_PER_PAGE, total);
    info.textContent = total === 0 ? 'No tickets' : `Showing ${from}–${to} of ${total} tickets`;
    btns.innerHTML = '';

    const prev = document.createElement('button');
    prev.className = 'tkt-page-btn'; prev.textContent = '← Prev'; prev.disabled = _tktPage <= 1;
    prev.onclick = () => { _tktPage--; renderTicketsTable(); renderTktPagination(); };
    btns.appendChild(prev);

    for (let i = Math.max(1, _tktPage - 2); i <= Math.min(pages, _tktPage + 2); i++) {
        const b = document.createElement('button');
        b.className = `tkt-page-btn${i === _tktPage ? ' active' : ''}`;
        b.textContent = i;
        const pg = i;
        b.onclick = () => { _tktPage = pg; renderTicketsTable(); renderTktPagination(); };
        btns.appendChild(b);
    }

    const next = document.createElement('button');
    next.className = 'tkt-page-btn'; next.textContent = 'Next →'; next.disabled = _tktPage >= pages;
    next.onclick = () => { _tktPage++; renderTicketsTable(); renderTktPagination(); };
    btns.appendChild(next);
}

function updateStats(tickets) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('ticketsIssued',   tickets.length);
    set('ticketsScanned',  tickets.filter(t => t.status === 'used').length);
    set('ticketsRemaining',tickets.filter(t => t.status === 'active').length);
    set('ticketsCancelled',tickets.filter(t => t.status === 'cancelled').length);
}

function openAdminTicketModal(ticket) {
    const existing = document.getElementById('adminTicketModal');
    if (existing) existing.remove();

    const imgSrc = ticket.event_image
        ? (ticket.event_image.startsWith('http') ? ticket.event_image : '../../' + ticket.event_image)
        : null;
    const heroBg = imgSrc ? `url(${imgSrc})` : 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
    const price = parseFloat(ticket.total_price) === 0 ? 'Free' : `₦${parseFloat(ticket.total_price).toLocaleString()}`;
    const statusClass = ticket.status === 'active' ? 'tkt-active' : ticket.status === 'used' ? 'tkt-used' : 'tkt-cancelled';
    const statusLabel = { active: '✓ Active', used: '👁 Used', cancelled: '✕ Cancelled' }[ticket.status] || ticket.status;

    const html = `
    <div id="adminTicketModal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:9100;backdrop-filter:blur(6px);padding:1rem;">
        <div style="background:white;border-radius:20px;overflow:hidden;max-width:520px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.25);animation:slideUp .3s ease-out;">
            <!-- Event Image Hero -->
            <div style="height:160px;background:${heroBg};background-size:cover;background-position:center;position:relative;">
                <button onclick="document.getElementById('adminTicketModal').remove()" style="position:absolute;top:1rem;right:1rem;background:rgba(0,0,0,.4);border:none;color:white;width:34px;height:34px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
                <div style="position:absolute;bottom:1rem;left:1.5rem;">
                    <div style="font-size:.7rem;font-weight:700;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.07em;margin-bottom:2px;">Event</div>
                    <div style="font-size:1.25rem;font-weight:800;color:white;text-shadow:0 2px 8px rgba(0,0,0,.4);">${escapeHtml(ticket.event_name || '—')}</div>
                </div>
            </div>
            <!-- Details -->
            <div style="padding:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                    <span class="tkt-badge ${statusClass}" style="font-size:.82rem;">${statusLabel}</span>
                    <span style="font-family:monospace;font-size:.8rem;color:#64748b;">${ticket.ticket_code || '#' + ticket.id}</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Buyer</div><div style="font-weight:600;">${escapeHtml(ticket.user_name || '—')}</div></div>
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Price</div><div style="font-weight:700;">${price}</div></div>
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Category</div><div style="font-weight:600;">${escapeHtml(ticket.category || 'General')}</div></div>
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Date Purchased</div><div style="font-weight:600;">${ticket.created_at ? new Date(ticket.created_at).toLocaleDateString() : '—'}</div></div>
                    <div style="grid-column:1/-1;"><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Barcode</div><div style="font-family:monospace;font-size:.82rem;color:#475569;">${ticket.barcode || '—'}</div></div>
                </div>
                <button onclick="document.getElementById('adminTicketModal').remove()" style="margin-top:1.5rem;width:100%;padding:.75rem;background:#6366f1;color:white;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-size:.9rem;">Close</button>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { document.getElementById('adminTicketModal')?.remove(); document.removeEventListener('keydown', esc); } });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

window.sortTicketsTable = sortTicketsTable;
window.openAdminTicketModal = openAdminTicketModal;
