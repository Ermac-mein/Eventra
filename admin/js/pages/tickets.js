document.addEventListener('DOMContentLoaded', async () => {
    const ticketsTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    let allTickets = [];
    let sortConfig = { key: null, direction: 'asc' };
    
    async function loadTickets() {
        try {
            const response = await apiFetch('../../api/admin/get-tickets.php');
            const result = await response.json();

            if (result.success) {
                allTickets = result.tickets;
                renderTickets(allTickets);
                updateStats(allTickets);
            } else {
                console.error('Failed to load tickets:', result.message);
            }
        } catch (error) {
            console.error('Error fetching tickets:', error);
        }
    }

    function renderTickets(tickets) {
        if (!ticketsTableBody) return;
        
        if (tickets.length === 0) {
            ticketsTableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: #999;">No tickets found</td></tr>';
            return;
        }

        ticketsTableBody.innerHTML = tickets.map(ticket => `
            <tr data-id="${ticket.id}" data-image="${ticket.event_image || ''}">
                <td>${ticket.ticket_code}</td>
                <td>${ticket.event_name}</td>
                <td>₦${parseFloat(ticket.total_price).toLocaleString()}</td>
                <td>${ticket.user_name}</td>
                <td>${ticket.category || 'General'}</td>
                <td><span class="status-badge status-${ticket.status === 'active' ? 'ongoing' : ticket.status === 'used' ? 'concluded' : 'cancelled'}">${ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1)}</span></td>
            </tr>
        `).join('');

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function sortTickets(key) {
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

        const sortedTickets = [...allTickets].sort((a, b) => {
            let valA = a[key];
            let valB = b[key];

            // Handle numeric fields
            if (key === 'total_price') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            } else {
                valA = (valA || '').toString().toLowerCase();
                valB = (valB || '').toString().toLowerCase();
            }

            if (valA < valB) return sortConfig.direction === 'asc' ? -1 : 1;
            if (valA > valB) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });

        renderTickets(sortedTickets);
    }

    // Initialize sort listeners
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            sortTickets(th.dataset.sort);
        });
    });

    function updateStats(tickets) {
        if (statsValues.length < 4) return;

        const totalRevenue = tickets.reduce((acc, t) => acc + parseFloat(t.total_price), 0);

        statsValues[0].textContent = tickets.length;
        statsValues[1].textContent = tickets.filter(t => t.status === 'active').length;
        statsValues[2].textContent = tickets.filter(t => t.status === 'used').length;
        statsValues[3].textContent = '₦' + totalRevenue.toLocaleString();
    }

    await loadTickets();
});
