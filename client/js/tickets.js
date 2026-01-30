/**
 * Client Tickets Page JavaScript
 * Handles ticket display and preview
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = storage.get('user');
    
    if (!user || user.role !== 'client') {
        window.location.href = '../../public/pages/login.html';
        return;
    }

    await loadTickets(user.id);
});

async function loadTickets(clientId) {
    try {
        const response = await fetch(`../../api/tickets/get-tickets.php?client_id=${clientId}`);
        const result = await response.json();

        if (result.success) {
            updateTicketsTable(result.tickets || []);
        }
    } catch (error) {
        console.error('Error loading tickets:', error);
    }
}

function updateTicketsTable(tickets) {
    const tbody = document.getElementById('ticketsTableBody');
    if (!tbody) return;

    if (tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--client-text-muted);">No tickets sold yet.</td></tr>';
        return;
    }

    tbody.innerHTML = tickets.map(ticket => `
        <tr style="cursor: pointer;" onclick='showTicketPreviewModal(${JSON.stringify(ticket).replace(/'/g, "&#39;")})'>
            <td>${ticket.id}</td>
            <td>${ticket.event_name || 'N/A'}</td>
            <td>${ticket.buyer_name || 'N/A'}</td>
            <td>₦${parseFloat(ticket.price || 0).toLocaleString()}</td>
            <td>${ticket.purchase_date || 'N/A'}</td>
            <td><span style="color: ${ticket.status === 'confirmed' ? '#10b981' : '#ef4444'};">${ticket.status ? ticket.status.toUpperCase() : 'N/A'}</span></td>
        </tr>
    `).join('');
}
