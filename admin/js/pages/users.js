document.addEventListener('DOMContentLoaded', async () => {
    const usersTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    
    async function loadUsers() {
        try {
            const response = await fetch('../../api/admin/get-users.php');
            const result = await response.json();

            if (result.success) {
                renderUsers(result.users);
                updateStats(result.users);
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
            <tr data-id="${user.id}" data-profile-pic="${user.profile_pic || ''}">
                <td>${user.id}</td>
                <td><img src="${user.profile_pic || 'https://ui-avatars.com/api/?name=' + user.name}" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; vertical-align: middle;"> ${user.name}</td>
                <td>${user.state || user.city || 'N/A'}</td>
                <td>${user.email}</td>
                <td>${user.client_name || 'Direct'}</td>
                <td><span class="status-badge status-${user.status === 'active' ? 'ongoing' : 'concluded'}">${user.status === 'active' ? 'Active' : 'Offline'}</span></td>
                <td>${user.phone || 'N/A'}</td>
            </tr>
        `).join('');

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function updateStats(users) {
        if (statsValues.length < 4) return;

        statsValues[0].textContent = users.length;
        statsValues[1].textContent = users.filter(u => u.status === 'active').length;
        statsValues[2].textContent = users.filter(u => u.status !== 'active').length;
        statsValues[3].textContent = users.filter(u => new Date(u.created_at) > new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)).length;
    }

    await loadUsers();
});
