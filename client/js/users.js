/**
 * Client Users Page JavaScript
 * Handles user display and preview
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = storage.get('user');
    
    if (!user || user.role !== 'client') {
        window.location.href = '../../public/pages/clientLogin.html';
        return;
    }

    await loadUsers(user.id);
});

async function loadUsers(clientId) {
    try {
        const response = await fetch(`../../api/users/get-users.php?client_id=${clientId}`);
        const result = await response.json();

        if (result.success) {
            updateUsersTable(result.users || []);
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

function updateUsersTable(users) {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--client-text-muted);">No users have logged in or purchased tickets yet.</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => {
        // Determine status display
        const isActive = user.status === 'active' || user.status === 1 || user.status === '1';
        const statusText = isActive ? 'Active' : 'Inactive';
        const statusColor = isActive ? '#10b981' : '#ef4444';
        
        return `
        <tr style="cursor: pointer;" onclick='showUserPreviewModal(${JSON.stringify(user).replace(/'/g, "&#39;")})'>
            <td>${user.name || 'N/A'}</td>
            <td>${user.email || 'N/A'}</td>
            <td>${user.phone || 'N/A'}</td>
            <td>${user.state || 'N/A'}</td>
            <td>${user.client_name || 'Direct'}</td>
            <td><span style="color: ${statusColor}; font-weight: 600;">${statusText}</span></td>
            <td>${user.engagement || 'N/A'}</td>
            <td>${formatDate(user.created_at)}</td>
        </tr>
    `;
    }).join('');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
