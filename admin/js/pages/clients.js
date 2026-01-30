document.addEventListener('DOMContentLoaded', async () => {
    const clientsTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    
    async function loadClients() {
        try {
            const response = await fetch('../../api/admin/get-clients.php');
            const result = await response.json();

            if (result.success) {
                renderClients(result.clients);
                updateStats(result.clients);
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
            <tr data-id="${client.id}" data-profile-pic="${client.profile_pic || ''}">
                <td>${client.id}</td>
                <td><img src="${client.profile_pic || 'https://ui-avatars.com/api/?name=' + client.name}" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; vertical-align: middle;"> ${client.name}</td>
                <td>${client.email}</td>
                <td>${client.state || 'N/A'}</td>
                <td>${client.phone || 'N/A'}</td>
                <td><span class="status-badge status-${client.status === 'active' ? 'ongoing' : 'concluded'}">${client.status === 'active' ? 'Active' : 'Offline'}</span></td>
            </tr>
        `).join('');

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function updateStats(clients) {
        if (statsValues.length < 4) return;

        statsValues[0].textContent = clients.length;
        statsValues[1].textContent = clients.filter(c => c.status === 'active').length;
        statsValues[2].textContent = clients.filter(c => c.status !== 'active').length;
        statsValues[3].textContent = clients.reduce((acc, c) => acc + parseInt(c.event_count || 0), 0);
    }

    await loadClients();
});
