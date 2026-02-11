document.addEventListener('DOMContentLoaded', async () => {
    const eventsTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    
    async function loadEvents() {
        try {
            const response = await fetch('../../api/admin/get-all-events.php');
            const result = await response.json();

            if (result.success) {
                renderEvents(result.events);
                updateStats(result.stats);
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
            eventsTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #999;">No events found</td></tr>';
            return;
        }

        eventsTableBody.innerHTML = events.map(event => {
            // Determine display status (handle soft-deleted)
            let displayStatus = event.status;
            let statusClass = event.status;
            
            if (event.deleted_at) {
                displayStatus = 'deleted';
                statusClass = 'cancelled'; // Mapping to existing CSS class
            }

            return `
                <tr data-id="${event.id}" 
                    data-image="${event.image_path || ''}" 
                    data-tag="${event.tag || ''}" 
                    data-client-name="${(event.client_name || '').toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-') || ''}"
                    class="${event.deleted_at ? 'row-deleted' : ''}">
                    <td>${event.event_name}</td>
                    <td>${event.state || 'N/A'}</td>
                    <td>${event.client_name || 'Direct'}</td>
                    <td>${event.price > 0 ? '₦' + parseFloat(event.price).toLocaleString() : 'Free'}</td>
                    <td>${event.attendee_count || 0}</td>
                    <td>${event.event_type || 'General'}</td>
                    <td><span class="status-badge status-${statusClass}">${displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1)}</span></td>
                </tr>
            `;
        }).join('');

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function updateStats(stats) {
        if (!stats || statsValues.length < 5) return;

        // stats from API: total, published, deleted, scheduled, restored
        statsValues[0].textContent = stats.total || 0;
        statsValues[1].textContent = stats.published || 0;
        statsValues[2].textContent = stats.deleted || 0;
        statsValues[3].textContent = stats.scheduled || 0;
        statsValues[4].textContent = stats.restored || 0;
    }

    // Initial load
    await loadEvents();

    // Task 3: Real-time synchronization (10s polling)
    setInterval(loadEvents, 10000);
});
