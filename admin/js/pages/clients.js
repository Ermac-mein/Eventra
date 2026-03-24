document.addEventListener('DOMContentLoaded', async () => {
    const clientsTableBody = document.querySelector('table tbody');
    let allClients = [];
    let sortConfig = { key: null, direction: 'asc' };

    // Load stats cards from the server for accurate real-time values
    async function loadStats() {
        try {
            const res = await apiFetch('/api/stats/get-admin-dashboard-stats.php');
            const data = await res.json();
            if (!data.success) return;

            const s = data.stats;
            const totalEl = document.getElementById('totalClients');
            const activeEl = document.getElementById('clientsActive');
            const eventsEl = document.getElementById('clientsEvents');

            if (totalEl) totalEl.textContent = s.total_clients ?? 0;
            // "Active" = online within last 5 min
            if (activeEl) activeEl.textContent = s.online_clients ?? 0;
            // Sum all events from client list (loaded separately for accuracy)
            if (eventsEl) {
                const evtRes = await apiFetch('/api/admin/get-clients.php?limit=9999&offset=0');
                const evtData = await evtRes.json();
                if (evtData.success) {
                    const total = evtData.clients.reduce((acc, c) => acc + parseInt(c.event_count || 0), 0);
                    eventsEl.textContent = total;
                }
            }
        } catch (e) {
            console.error('Stats load error:', e);
        }
    }

    async function loadClients() {
        try {
            const response = await apiFetch('/api/admin/get-clients.php');
            const result = await response.json();

            if (result.success) {
                allClients = result.clients;
                renderClients(allClients);
            } else {
                console.error('Failed to load clients:', result.message);
                if (clientsTableBody) {
                    clientsTableBody.innerHTML = `<tr><td colspan="18" style="text-align:center;padding:2rem;color:#ef4444;">Failed to load clients: ${result.message}</td></tr>`;
                }
            }
        } catch (error) {
            console.error('Error fetching clients:', error);
            if (clientsTableBody) {
                clientsTableBody.innerHTML = `<tr><td colspan="18" style="text-align:center;padding:2rem;color:#ef4444;">Network error loading clients.</td></tr>`;
            }
        }
    }

    // Expose globally so approveClient() can trigger a refresh
    window.loadClients = loadClients;

    function renderClients(clients) {
        if (!clientsTableBody) return;

        if (clients.length === 0) {
            clientsTableBody.innerHTML = '<tr><td colspan="18" style="text-align: center; padding: 2rem; color: #999;">No clients found</td></tr>';
            return;
        }

        clientsTableBody.innerHTML = clients.map(client => `
            <tr data-id="${client.id}" data-profile-pic="${client.profile_pic || ''}">
                <td style="padding-left: 1.5rem;"><input type="checkbox" class="client-checkbox" data-id="${client.id}"></td>
                <td>
                    <div style="font-weight: 700; color: var(--admin-primary);">${client.custom_id || 'N/A'}</div>
                </td>
                <td style="display: flex; align-items: center; gap: 12px; padding: 1.2rem 1rem;">
                    <div class="avatar-wrapper">
                        <img src="${getProfileImg(client.profile_pic, client.name)}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        ${getVerificationBadge(client.verification_status)}
                    </div>
                    <span style="font-weight: 600; color: var(--admin-text-main);">${client.name}</span>
                </td>
                <td>${client.email}</td>
                <td>${client.nin || 'N/A'}</td>
                <td>${client.dob || 'N/A'}</td>
                <td style="text-transform: capitalize;">${client.gender || 'N/A'}</td>
                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${client.address || ''}">${client.address || 'N/A'}</td>
                <td>${client.city || 'N/A'}</td>
                <td>${client.country || 'N/A'}</td>
                <td>${client.state || 'N/A'}</td>
                <td>${client.job_title || 'N/A'}</td>
                <td><code>${client.account_number || 'N/A'}</code></td>
                <td>${client.account_name || 'N/A'}</td>
                <td>${client.bank_name || 'N/A'}</td>
                <td>${client.phone || 'N/A'}</td>
                <td><span class="status-badge status-${client.verification_status === 'verified' ? 'active' : client.verification_status === 'rejected' ? 'offline' : 'ongoing'}">${client.verification_status || 'Pending'}</span></td>
                <td><span class="status-badge status-${client.status === 'active' ? 'active' : 'offline'}">${client.status === 'active' ? 'Active' : 'Offline'}</span></td>
            </tr>
        `).join('');

        // Update pagination info
        const paginationInfo = document.getElementById('paginationInfo');
        if (paginationInfo) {
            paginationInfo.textContent = `1 - ${clients.length} of ${clients.length}`;
        }

        // Handle Select All
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.onchange = (e) => {
                document.querySelectorAll('.client-checkbox').forEach(cb => cb.checked = e.target.checked);
            };
        }

        // Prevent preview open on checkbox click
        document.querySelectorAll('.client-checkbox, #selectAll').forEach(cb => {
            cb.onclick = (e) => e.stopPropagation();
        });

        // Re-initialize Lucide icons for badges
        if (window.lucide) window.lucide.createIcons();

        // Re-initialize previews for new rows
        if (window.initPreviews) window.initPreviews();
    }

    function sortClients(key) {
        if (sortConfig.key === key) {
            sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortConfig.key = key;
            sortConfig.direction = 'asc';
        }

        document.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
            if (th.dataset.sort === key) th.classList.add(sortConfig.direction);
        });

        const sorted = [...allClients].sort((a, b) => {
            let valA = a[key];
            let valB = b[key];
            if (key === 'id') {
                valA = parseInt(valA) || 0;
                valB = parseInt(valB) || 0;
            } else {
                valA = (valA || '').toString().toLowerCase();
                valB = (valB || '').toString().toLowerCase();
            }
            if (valA < valB) return sortConfig.direction === 'asc' ? -1 : 1;
            if (valA > valB) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });

        renderClients(sorted);
    }

    // Sort listeners
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => sortClients(th.dataset.sort));
    });

    // Initial load
    await loadClients();
    await loadStats();

    // Auto-refresh every 30s
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadClients();
            loadStats();
        }
    }, 30000);
});
