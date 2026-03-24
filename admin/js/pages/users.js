document.addEventListener('DOMContentLoaded', async () => {
    const usersTableBody = document.querySelector('table tbody');
    let allUsers = [];
    let sortConfig = { key: null, direction: 'asc' };
    
    async function loadUsers() {
        try {
            const response = await apiFetch('/api/admin/get-users.php');
            const result = await response.json();

            if (result.success) {
                allUsers = result.users;
                renderUsers(allUsers);
                updateStats(result.summary);
            } else {
                console.error('Failed to load users:', result.message);
            }
        } catch (error) {
            console.error('Error fetching users:', error);
        }
    }

    function renderUsers(users) {
        if (!usersTableBody) return;
        
        if (users.length === 0) {
            usersTableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: #999;">No users found</td></tr>';
            return;
        }

        usersTableBody.innerHTML = users.map(user => `
            <tr data-id="${user.id}">
                <td style="padding-left: 1.5rem;"><input type="checkbox" class="user-checkbox" data-id="${user.id}"></td>
                <td>
                    <div style="font-weight: 700; color: var(--admin-primary);">${user.custom_id || 'N/A'}</div>
                </td>
                <td style="display: flex; align-items: center; gap: 12px; padding: 1.2rem 1rem;">
                    <div class="avatar-wrapper">
                        <img src="${getProfileImg(user.profile_pic, user.name)}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    </div>
                    <span style="font-weight: 600; color: var(--admin-text-main);">${user.name}</span>
                </td>
                <td style="font-size: 0.85rem;">${user.email}</td>
                <td>${user.phone || 'N/A'}</td>
                <td style="text-transform: capitalize;">${user.gender || 'N/A'}</td>
                <td>${user.state || 'N/A'}</td>
                <td>${user.country || 'N/A'}</td>
                <td>${user.city || 'N/A'}</td>
                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${user.address || 'N/A'}">${user.address || 'N/A'}</td>
                <td><span class="status-badge status-${user.is_online == 1 ? 'ongoing' : 'concluded'}">${user.is_online == 1 ? 'Online' : 'Offline'}</span></td>
                <td>${user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'}</td>
            </tr>
        `).join('');

        // Handle Select All
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.onchange = (e) => {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
            };
        }

        // Prevent preview open on checkbox click
        document.querySelectorAll('.user-checkbox, #selectAll').forEach(cb => {
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

    function sortUsers(key) {
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

        const sortedUsers = [...allUsers].sort((a, b) => {
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

        renderUsers(sortedUsers);
    }

    // Initialize sort listeners
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            sortUsers(th.dataset.sort);
        });
    });

    function updateStats(summary) {
        console.log('Updating user stats with:', summary);
        if (!summary) return;

        const checkedInEl = document.getElementById('usersCheckedIn');
        const activeEl = document.getElementById('usersActive');
        const registeredEl = document.getElementById('usersRegistered');

        if (checkedInEl) checkedInEl.textContent = summary.total_checked_in || 0;
        if (activeEl) activeEl.textContent = summary.total_active || 0;
        if (registeredEl) registeredEl.textContent = summary.total_registered || 0;
    }

    await loadUsers();

    // Auto-refresh every 10s for real-time feel
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadUsers();
        }
    }, 10000);
});
