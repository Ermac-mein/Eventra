document.addEventListener('DOMContentLoaded', async () => {
    const clientsTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    let allClients = [];
    let sortConfig = { key: null, direction: 'asc' };
    
    async function loadClients() {
        try {
            const response = await apiFetch('../../api/admin/get-clients.php');
            const result = await response.json();

            if (result.success) {
                allClients = result.clients;
                renderClients(allClients);
                updateStats(allClients);
            } else {
                console.error('Failed to load clients:', result.message);
            }
        } catch (error) {
            console.error('Error fetching clients:', error);
        }
    }

    function renderClients(clients) {
        if (!clientsTableBody) return;
        
        if (clients.length === 0) {
            clientsTableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: #999;">No clients found</td></tr>';
            return;
        }

        clientsTableBody.innerHTML = clients.map(client => `
            <tr data-id="${client.id}">
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
                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${client.address || 'N/A'}">${client.address || 'N/A'}</td>
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

        // Handle Select All
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.onchange = (e) => {
                const checkboxes = document.querySelectorAll('.client-checkbox');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
            };
        }

        // Prevent preview open on checkbox click
        document.querySelectorAll('.client-checkbox, #selectAll').forEach(cb => {
            cb.onclick = (e) => e.stopPropagation();
        });

        // Re-initialize Lucide icons for badges
        if (window.lucide) {
            window.lucide.createIcons();
        }

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function sortClients(key) {
        if (sortConfig.key === key) {
            sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortConfig.key = key;
            sortConfig.direction = 'asc';
        }

        // Update UI headers
        document.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
            if (th.dataset.sort === key) {
                th.classList.add(sortConfig.direction);
            }
        });

        const sortedClients = [...allClients].sort((a, b) => {
            let valA = a[key];
            let valB = b[key];

            // Handle ID as number
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

        renderClients(sortedClients);
    }

    // Initialize sort listeners
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            sortClients(th.dataset.sort);
        });
    });

    function updateStats(clients) {
        if (statsValues.length === 0) return;

        if (statsValues[0]) statsValues[0].textContent = clients.length;
        if (statsValues[1]) statsValues[1].textContent = clients.filter(c => c.status === 'active').length;
        if (statsValues[2]) statsValues[2].textContent = clients.reduce((acc, c) => acc + parseInt(c.event_count || 0), 0);
    }

    await loadClients();

    // Auto-refresh every 30s
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadClients();
        }
    }, 30000);
});
