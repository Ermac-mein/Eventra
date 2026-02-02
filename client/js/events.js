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
        const clientNameSlug = (storage.get('user').name || '').toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        
        return `
        <tr onclick="window.previewEvent(${event.id})" 
            style="cursor: pointer;"
            data-id="${event.id}" 
            data-tag="${event.tag || ''}" 
            data-client-name="${clientNameSlug}"
            data-image="${event.image_path || ''}">
            <td>${event.event_name}</td>
            <td>${event.state}</td>
            <td>₦${parseFloat(event.price).toLocaleString()}</td>
            <td class="text-center">
                <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                    ${event.attendee_count || 0}
                    <div style="display: flex;">
                        ${[...Array(Math.min(parseInt(event.attendee_count || 0), 3))].map((_, i) => `
                            <img src="https://ui-avatars.com/api/?name=User+${i}&background=random" 
                                 style="width: 15px; height: 15px; border-radius: 50%; border: 1px solid white; margin-left: ${i === 0 ? '0' : '-8px'};">
                        `).join('')}
                    </div>
                </div>
            </td>
            <td>${event.event_type}</td>
            <td><span style="color: ${getStatusColor(event.status)};">${event.status.charAt(0).toUpperCase() + event.status.slice(1)}</span></td>
            <td class="text-center" onclick="event.stopPropagation()">
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
    const result = await Swal.fire({
        title: 'Delete Event?',
        text: 'Are you sure you want to delete this event? This action cannot be reverted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

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

async function previewEvent(eventId) {
    const row = document.querySelector(`tr[data-id="${eventId}"]`);
    if (!row) return;

    const eventName = row.cells[0].innerText;
    const location = row.cells[1].innerText;
    const price = row.cells[2].innerText;
    const attendees = row.cells[3].innerText;
    const category = row.cells[4].innerText;
    const status = row.cells[5].innerText;
    const eventImage = row.dataset.image || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
    const tag = row.dataset.tag;
    const clientName = row.dataset.clientName;
    const shareLink = `${window.location.origin}/public/pages/event-details.html?event=${tag}&client=${clientName}`;

    // Create Modal Backdrop (if not exists)
    let backdrop = document.querySelector('.preview-modal-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'preview-modal-backdrop';
        backdrop.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px); transition: all 0.3s ease;';
        backdrop.innerHTML = `
            <div class="preview-modal" style="background: white; width: 90%; max-width: 600px; border-radius: 16px; overflow: hidden; position: relative; transform: translateY(20px); transition: all 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
                <button class="preview-close" style="position: absolute; top: 1rem; right: 1rem; background: white; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">×</button>
                <div id="previewContent"></div>
            </div>
        `;
        document.body.appendChild(backdrop);

        const closeBtn = backdrop.querySelector('.preview-close');
        closeBtn.onclick = () => {
            backdrop.style.opacity = '0';
            backdrop.querySelector('.preview-modal').style.transform = 'translateY(20px)';
            setTimeout(() => { backdrop.style.display = 'none'; }, 300);
        };
        backdrop.onclick = (e) => {
            if (e.target === backdrop) closeBtn.click();
        };
    }

    const content = backdrop.querySelector('#previewContent');
    content.innerHTML = `
        <div class="event-preview">
            <div style="height: 200px; overflow: hidden;">
                <img src="${eventImage}" style="width: 100%; height: 100%; object-fit: cover;" alt="Event">
            </div>
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem;">${eventName}</h1>
                    <p style="color: #6b7280; font-size: 0.875rem;">Status: ${status} | Attendees: ${attendees}</p>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div style="background: #f3f4f6; padding: 0.75rem; border-radius: 8px;">📂 ${category}</div>
                    <div style="background: #f3f4f6; padding: 0.75rem; border-radius: 8px;">📍 ${location}</div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600;">Attendees</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="display: flex;">
                            ${[...Array(Math.min(parseInt(attendees), 5))].map((_, i) => `
                                <img src="https://ui-avatars.com/api/?name=User+${i}&background=random" 
                                     style="width: 30px; height: 30px; border-radius: 50%; border: 2px solid white; margin-left: ${i === 0 ? '0' : '-10px'};">
                            `).join('')}
                        </div>
                        <span style="font-size: 0.85rem; color: #6b7280; font-weight: 600;">${attendees} people attending</span>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.35rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Event Tag</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <code style="background: #f9fafb; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e5e7eb; font-family: monospace; font-size: 0.875rem; flex: 1; color: #111827;">${tag}</code>
                            <button onclick="copyToClipboard('${tag}', 'Tag copied!')" style="background: white; border: 1px solid #d1d5db; padding: 0.5rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;" title="Copy Tag">📋</button>
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.35rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Shareable Link</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" readonly value="${shareLink}" 
                                   style="background: #f9fafb; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e5e7eb; font-family: monospace; font-size: 0.875rem; flex: 1; color: #111827;">
                            <button onclick="copyToClipboard('${shareLink}', 'Link copied!')" style="background: var(--client-primary, #4F46E5); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 600;">Copy Link</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    backdrop.style.display = 'flex';
    backdrop.style.opacity = '0';
    setTimeout(() => {
        backdrop.style.opacity = '1';
        backdrop.querySelector('.preview-modal').style.transform = 'translateY(0)';
    }, 10);
}

// Make functions globally available
window.editEvent = editEvent;
window.previewEvent = previewEvent;
window.deleteEvent = deleteEvent;
