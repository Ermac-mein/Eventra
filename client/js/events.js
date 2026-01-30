/**
 * Client Events Page JavaScript
 * Handles event creation, management, and display
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = storage.get('user');
    
    if (!user || user.role !== 'client') {
        window.location.href = '../../public/pages/login.html';
        return;
    }

    const clientId = user.id;

    // Load events
    await loadEvents(clientId);

    // Initialize create event button
    initCreateEventButton();
});

async function loadEvents(clientId) {
    try {
        const response = await fetch(`../../api/events/get-events.php?client_id=${clientId}&limit=100`);
        const result = await response.json();

        if (result.success) {
            // Update stats cards
            if (result.stats) {
                updateStatsCards(result.stats);
            }

            // Update events table
            updateEventsTable(result.events);
        }
    } catch (error) {
        console.error('Error loading events:', error);
    }
}

function updateStatsCards(stats) {
    const cards = document.querySelectorAll('.summary-card .summary-value');
    if (cards.length >= 4) {
        cards[0].textContent = stats.total_events || 0;
        cards[1].textContent = stats.published_events || 0;
        cards[2].textContent = 0; // Deleted events (not tracked in current schema)
        cards[3].textContent = stats.scheduled_events || 0;
    }
}

function updateEventsTable(events) {
    const tbody = document.querySelector('.table-card table tbody');
    if (!tbody) return;

    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--client-text-muted);">No events yet. Create your first event!</td></tr>';
        return;
    }

    tbody.innerHTML = events.map(event => {
        // Only show edit icon for draft and scheduled events
        const canEdit = event.status === 'draft' || event.status === 'scheduled';
        
        return `
        <tr>
            <td>${event.event_name}</td>
            <td>${event.state}</td>
            <td>₦${parseFloat(event.price).toLocaleString()}</td>
            <td class="text-center">${event.attendee_count || 0}</td>
            <td>${event.event_type}</td>
            <td><span style="color: ${getStatusColor(event.status)};">${event.status.charAt(0).toUpperCase() + event.status.slice(1)}</span></td>
            <td class="text-center">
                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                    ${canEdit ? `
                        <button onclick="editEvent(${event.id})" class="action-icon-btn" title="Edit Event" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0.25rem 0.5rem; transition: transform 0.2s;">
                            ✏️
                        </button>
                    ` : `
                        <button class="action-icon-btn" title="Published events cannot be edited" style="background: none; border: none; cursor: not-allowed; font-size: 1.2rem; padding: 0.25rem 0.5rem; opacity: 0.3;">
                            🔒
                        </button>
                    `}
                    <button onclick="deleteEvent(${event.id})" class="action-icon-btn" title="Delete Event" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0.25rem 0.5rem; transition: transform 0.2s;">
                        🗑️
                    </button>
                </div>
            </td>
        </tr>
    `;
    }).join('');
}

function getStatusColor(status) {
    const colors = {
        'published': 'var(--card-green)',
        'scheduled': 'var(--card-blue)',
        'draft': 'var(--card-red)',
        'cancelled': '#999'
    };
    return colors[status] || '#000';
}

function initCreateEventButton() {
    const createBtn = document.querySelector('.btn-primary');
    if (createBtn && createBtn.textContent.includes('Create Event')) {
        createBtn.addEventListener('click', () => {
            showCreateEventModal();
        });
    }
}

// showCreateEventModal is defined in create-event.js
// Event row clicks now open showEventActionModal instead of edit


async function editEvent(eventId) {
    try {
        const user = storage.get('user');
        const response = await fetch(`../../api/events/get-events.php?client_id=${user.id}&limit=100`);
        const result = await response.json();

        if (result.success) {
            const event = result.events.find(e => e.id == eventId);
            if (event) {
                // Check if event is published
                if (event.status === 'published') {
                    showNotification('Published events cannot be edited. Only draft and scheduled events can be modified.', 'error');
                    return;
                }
                showEditEventModal(event);
            } else {
                showNotification('Event not found', 'error');
            }
        }
    } catch (error) {
        console.error('Error fetching event:', error);
        showNotification('Failed to load event details', 'error');
    }
}


async function deleteEvent(eventId) {
    if (!confirm('Are you sure you want to delete this event?')) {
        return;
    }

    try {
        const response = await fetch('../../api/events/delete-event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Event deleted successfully', 'success');
            // Reload events
            const user = storage.get('user');
            await loadEvents(user.id);
        } else {
            showNotification('Failed to delete event: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting event:', error);
        showNotification('An error occurred', 'error');
    }
}

// Make functions globally available
window.editEvent = editEvent;
window.previewEvent = previewEvent;
window.deleteEvent = deleteEvent;
