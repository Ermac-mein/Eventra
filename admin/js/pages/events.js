document.addEventListener('DOMContentLoaded', async () => {
    const eventsTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    
    async function loadEvents() {
        try {
            const response = await fetch('../../api/admin/get-all-events.php');
            const result = await response.json();

            if (result.success) {
                renderEvents(result.events);
                updateStats(result.events);
            } else {
                console.error('Failed to load events:', result.message);
            }
        } catch (error) {
            console.error('Error fetching events:', error);
        }
    }

    function renderEvents(events) {
        if (!eventsTableBody) return;
        
        if (events.length === 0) {
            eventsTableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: #999;">No events found</td></tr>';
            return;
        }

        eventsTableBody.innerHTML = events.map(event => `
            <tr data-id="${event.id}" data-image="${event.image_path || ''}">
                <td>${event.event_name}</td>
                <td>${event.state || 'N/A'}</td>
                <td>${event.price > 0 ? '₦' + parseFloat(event.price).toLocaleString() : 'Free'}</td>
                <td>${event.attendee_count || 0}</td>
                <td>${event.event_type || 'General'}</td>
                <td><span class="status-badge status-${event.status}">${event.status.charAt(0).toUpperCase() + event.status.slice(1)}</span></td>
            </tr>
        `).join('');

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function updateStats(events) {
        if (statsValues.length < 4) return;

        const stats = {
            created: events.length,
            published: events.filter(e => e.status === 'published').length,
            deleted: events.filter(e => e.status === 'cancelled').length, // Assuming cancelled is 'deleted' in this context
            scheduled: events.filter(e => e.status === 'scheduled').length
        };

        statsValues[0].textContent = stats.created;
        statsValues[1].textContent = stats.published;
        statsValues[2].textContent = stats.deleted;
        statsValues[3].textContent = stats.scheduled;
    }

    await loadEvents();
});
