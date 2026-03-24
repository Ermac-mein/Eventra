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
    
    // Auto-refresh every 30s
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadTickets();
        }
    }, 30000);
});

async function loadTickets() {
    const tbody = document.getElementById('ticketsTableBody');
    if (tbody && _allTickets.length === 0) {
        setTableStatusRow(tbody, 'Loading...');
    }

    const statusFilter = (document.querySelector('[data-status].active') || {}).dataset?.status ?? '';
    const search = (document.getElementById('ticketSearchInput') || {}).value ?? '';
    const offset = (_tktPage - 1) * TKT_PER_PAGE;

    try {
        const url = `/api/admin/get-tickets.php?limit=${TKT_PER_PAGE}&offset=${offset}&search=${encodeURIComponent(search)}&status=${statusFilter}`;
        const res = await apiFetch(url);
        const data = await res.json();
        
        if (!data.success) throw new Error(data.message || 'Failed');
        
        _allTickets = data.tickets || [];
        // We still use _filteredTickets for local UI logic but the source is now paginated/filtered by server
        _filteredTickets = _allTickets; 
        
        renderTicketsTable();
        // Updated pagination renders based on data.total
        renderTktPagination(data.total || 0);
        updateStats(data);
    } catch (err) {
        console.error('Tickets load error', err);
        if (tbody) {
            setTableStatusRow(tbody, 'Error loading tickets.', '#ef4444');
        }
    }
}

function applyFilters() {
    // Now just triggers loadTickets for server-side filtering
    loadTickets();
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

    if (!_filteredTickets.length) {
        setTableStatusRow(tbody, 'No tickets found.');
        return;
    }

    tbody.textContent = '';
    _filteredTickets.forEach(t => {
        const row = createTicketRow(t);
        tbody.appendChild(row);
    });

    // Handle Select All
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.onchange = (e) => {
            const checkboxes = document.querySelectorAll('.ticket-checkbox');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
        };
    }

    // Prevent modal open on checkbox click
    document.querySelectorAll('.ticket-checkbox, #selectAll').forEach(cb => {
        cb.onclick = (e) => e.stopPropagation();
    });

    if (window.lucide) lucide.createIcons();
}

function renderTktPagination(totalOverride) {
    const info = document.getElementById('tktPagInfo');
    const btns = document.getElementById('tktPagBtns');
    if (!info || !btns) return;

    const total = totalOverride !== undefined ? totalOverride : _filteredTickets.length;
    const pages = Math.max(1, Math.ceil(total / TKT_PER_PAGE));
    const from = total === 0 ? 0 : (_tktPage - 1) * TKT_PER_PAGE + 1;
    const to = Math.min(_tktPage * TKT_PER_PAGE, total);
    const statusMsg = total === 0 ? 'No tickets' : `Showing ${from}–${to} of ${total} tickets`;
    info.textContent = statusMsg;
    btns.textContent = '';

    const prev = document.createElement('button');
    prev.className = 'tkt-page-btn'; prev.textContent = '← Prev'; prev.disabled = _tktPage <= 1;
    prev.onclick = () => { _tktPage--; loadTickets(); };
    btns.appendChild(prev);

    for (let i = Math.max(1, _tktPage - 2); i <= Math.min(pages, _tktPage + 2); i++) {
        const b = document.createElement('button');
        b.className = `tkt-page-btn${i === _tktPage ? ' active' : ''}`;
        b.textContent = i;
        const pg = i;
        b.onclick = () => { _tktPage = pg; loadTickets(); };
        btns.appendChild(b);
    }

    const next = document.createElement('button');
    next.className = 'tkt-page-btn'; next.textContent = 'Next →'; next.disabled = _tktPage >= pages;
    next.onclick = () => { _tktPage++; loadTickets(); };
    btns.appendChild(next);
}

function updateStats(data) {
    const stats = data.stats || {};
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('ticketsIssued',    stats.total_tickets || 0);
    set('ticketsScanned',   stats.used_tickets || 0);
    set('ticketsRemaining', stats.remaining_tickets || 0);
    set('ticketsCancelled', stats.cancelled_tickets || 0);
    
    const revenueEl = document.getElementById('totalRevenue');
    if (revenueEl) {
        revenueEl.textContent = '₦' + (stats.total_revenue || 0).toLocaleString();
    }
}

function openAdminTicketModal(ticket) {
    const existing = document.getElementById('adminTicketModal');
    if (existing) existing.remove();

    const imgSrc = ticket.event_image
        ? (ticket.event_image.startsWith('http') ? ticket.event_image : '../../' + ticket.event_image)
        : null;
    const heroBg = imgSrc ? `url("${imgSrc.replace(/"/g, '%22')}")` : 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
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
                    <span class="tkt-badge ${statusClass}" style="font-size:.82rem;">${escapeHtml(statusLabel)}</span>
                    <span style="font-family:monospace;font-size:.8rem;color:#64748b;">${escapeHtml(ticket.ticket_code || '#' + ticket.id)}</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Buyer</div><div style="font-weight:600;">${escapeHtml(ticket.user_name || '—')}</div></div>
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Price</div><div style="font-weight:700;">${price}</div></div>
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Category</div><div style="font-weight:600;">${escapeHtml(ticket.category || 'General')}</div></div>
                    <div><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Date Purchased</div><div style="font-weight:600;">${ticket.created_at ? new Date(ticket.created_at).toLocaleDateString() : '—'}</div></div>
                    <div style="grid-column:1/-1;"><div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Barcode</div><div style="font-family:monospace;font-size:.82rem;color:#475569;">${escapeHtml(ticket.barcode || '—')}</div></div>
                </div>
                <button onclick="document.getElementById('adminTicketModal').remove()" style="margin-top:1.5rem;width:100%;padding:.75rem;background:#6366f1;color:white;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-size:.9rem;">Close</button>
            </div>
        </div>
    </div>`;

    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const modalEl = template.content.firstElementChild;
    document.body.appendChild(modalEl);
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { modalEl?.remove(); document.removeEventListener('keydown', esc); } });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setTableStatusRow(tbody, message, color = '#94a3b8') {
    tbody.textContent = '';
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 8;
    td.style.textAlign = 'center';
    td.style.padding = '2.5rem';
    td.style.color = color;
    td.textContent = message;
    tr.appendChild(td);
    tbody.appendChild(tr);
}

function createTicketRow(t) {
    const tr = document.createElement('tr');
    tr.onclick = () => openAdminTicketModal(t);

    const statusClass = t.status === 'valid' ? 'tkt-active' : t.status === 'used' ? 'tkt-used' : 'tkt-cancelled';
    const statusLabel = { valid: '✓ Valid', used: '👁 Used', cancelled: '✕ Cancelled' }[t.status] || t.status;

    // Checkbox Cell
    const tdCheck = document.createElement('td');
    tdCheck.style.paddingLeft = '1.5rem';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.className = 'ticket-checkbox';
    input.dataset.id = t.id;
    input.onclick = (e) => e.stopPropagation();
    tdCheck.appendChild(input);
    tr.appendChild(tdCheck);

    // ID Cell
    const tdId = document.createElement('td');
    const divId = document.createElement('div');
    divId.style.fontSize = '.7rem';
    divId.style.color = 'var(--admin-primary)';
    divId.style.fontFamily = 'monospace';
    divId.style.fontWeight = '700';
    divId.textContent = t.custom_id || 'N/A';
    tdId.appendChild(divId);
    tr.appendChild(tdId);

    // Event Cell
    const tdEvent = document.createElement('td');
    if (t.event_image) {
        const img = document.createElement('img');
        img.src = t.event_image.startsWith('http') ? t.event_image : '../../' + t.event_image;
        img.className = 'tkt-event-img';
        img.onerror = () => img.style.display = 'none';
        tdEvent.appendChild(img);
    } else {
        const spanIcon = document.createElement('span');
        spanIcon.className = 'tkt-event-img';
        spanIcon.style.display = 'inline-flex';
        spanIcon.style.alignItems = 'center';
        spanIcon.style.justifyContent = 'center';
        spanIcon.style.fontSize = '1.1rem';
        spanIcon.textContent = '🎟';
        tdEvent.appendChild(spanIcon);
    }
    const spanName = document.createElement('span');
    spanName.className = 'tkt-event-name';
    spanName.title = t.event_name || '';
    spanName.textContent = t.event_name || '—';
    tdEvent.appendChild(spanName);
    tr.appendChild(tdEvent);

    // User Cell
    const tdUser = document.createElement('td');
    tdUser.style.fontWeight = '500';
    tdUser.style.color = '#374151';
    tdUser.textContent = t.user_name || '—';
    tr.appendChild(tdUser);

    // Price Cell
    const tdPrice = document.createElement('td');
    if (t.price_display === 'Free') {
        const spanFree = document.createElement('span');
        spanFree.style.color = '#10b981';
        spanFree.style.fontWeight = '700';
        spanFree.textContent = 'Free';
        tdPrice.appendChild(spanFree);
    } else {
        const strongPrice = document.createElement('strong');
        strongPrice.textContent = t.price_display;
        tdPrice.appendChild(strongPrice);
    }
    tr.appendChild(tdPrice);

    // Category Cell
    const tdCat = document.createElement('td');
    const spanCat = document.createElement('span');
    spanCat.style.fontSize = '.82rem';
    spanCat.style.color = '#64748b';
    spanCat.textContent = t.category || 'General';
    tdCat.appendChild(spanCat);
    tr.appendChild(tdCat);

    // Date Cell
    const tdDate = document.createElement('td');
    tdDate.style.fontSize = '.83rem';
    tdDate.style.color = '#64748b';
    tdDate.textContent = t.created_at ? new Date(t.created_at).toLocaleDateString() : '—';
    tr.appendChild(tdDate);

    // Status Cell
    const tdStatus = document.createElement('td');
    const spanBadge = document.createElement('span');
    spanBadge.className = `tkt-badge ${statusClass}`;
    spanBadge.textContent = statusLabel;
    tdStatus.appendChild(spanBadge);
    tr.appendChild(tdStatus);

    return tr;
}

window.sortTicketsTable = sortTicketsTable;
window.openAdminTicketModal = openAdminTicketModal;
