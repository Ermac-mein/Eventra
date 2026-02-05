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
        tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; padding: 2rem; color: var(--client-text-muted);">No events yet. Create your first event!</td></tr>';
        return;
    }

    tbody.innerHTML = events.map(event => {
        const clientNameSlug = (storage.get('user').name || '').toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        const shareLink = `${window.location.origin}/public/pages/event-details.html?event=${event.tag}&client=${clientNameSlug}`;
        
        return `
        <tr onclick="window.previewEvent(${event.id})" 
            style="cursor: pointer;"
            data-id="${event.id}" 
            data-tag="${event.tag || ''}" 
            data-status="${event.status}"
            data-client-name="${clientNameSlug}"
            data-description="${event.description || ''}"
            data-address="${event.address || ''}"
            data-phone="${event.phone_contact_1 || ''}"
            data-date="${event.event_date}"
            data-time="${event.event_time}"
            data-priority="${event.priority}"
            data-image="${event.image_path || ''}">
            <td style="font-weight: 600;">${event.event_name}</td>
            <td>
                <span style="padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; 
                      background: ${event.priority === 'featured' ? '#fef3c7' : event.priority === 'hot' ? '#fee2e2' : '#f3f4f6'}; 
                      color: ${event.priority === 'featured' ? '#92400e' : event.priority === 'hot' ? '#991b1b' : '#374151'};">
                    ${event.priority}
                </span>
            </td>
            <td>${new Date(event.event_date).toLocaleDateString()}</td>
            <td>${event.event_time.substring(0, 5)}</td>
            <td>${event.event_type}</td>
            <td>${event.phone_contact_1}</td>
            <td>
                ${parseFloat(event.price) === 0 
                    ? '<span style="background: #ecfdf5; color: #10b981; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">Free</span>' 
                    : `₦${parseFloat(event.price).toLocaleString()}`}
            </td>
            <td class="text-center">
                <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                    ${event.attendee_count || 0}
                </div>
            </td>
            <td><code style="font-size: 0.75rem;">${event.tag}</code></td>
            <td>
                <button onclick="event.stopPropagation(); copyToClipboard('${shareLink}', 'Link copied!')" class="btn-primary" style="padding: 4px 8px; font-size: 0.7rem; border-radius: 4px;">Copy</button>
            </td>
            <td><span style="color: ${getStatusColor(event.status)}; font-weight: 600;">${event.status.charAt(0).toUpperCase() + event.status.slice(1)}</span></td>
            <td class="text-center" onclick="event.stopPropagation()">
                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                    ${event.status === 'published' 
                        ? `<button class="action-icon-btn" title="Published events cannot be edited" style="background: none; border: none; cursor: not-allowed; font-size: 1.2rem; padding: 0.25rem 0.5rem; opacity: 0.4;">
                               🔒
                           </button>`
                        : `<button onclick="editEvent(${event.id})" class="action-icon-btn" title="Edit Event" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0.25rem 0.5rem; transition: transform 0.2s;">
                               ✏️
                           </button>`
                    }
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
                // The user wants to allow editing published events
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
    const attendees = row.dataset.attendees || row.cells[7].innerText;
    const category = row.cells[4].innerText;
    const status = row.cells[10].innerText.trim();
    const eventStatus = row.dataset.status || status;
    const eventImage = row.dataset.image || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
    const tag = row.dataset.tag;
    const description = row.dataset.description;
    const address = row.dataset.address;
    const date = row.dataset.date;
    const time = row.dataset.time;
    const priority = row.dataset.priority;
    const price = row.cells[6].innerText;

    const clientName = row.dataset.clientName;
    const shareLink = `${window.location.origin}/public/pages/event-details.html?event=${tag}&client=${clientName}`;

    // Create Modal Backdrop (if not exists)
    let backdrop = document.querySelector('.preview-modal-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'preview-modal-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-hidden', 'false');
        backdrop.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px); transition: all 0.3s ease;';
        backdrop.innerHTML = `
            <div class="preview-modal" style="background: white; width: 95%; max-width: 650px; border-radius: 16px; overflow: hidden; position: relative; transform: translateY(20px); transition: all 0.3s ease; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; display: flex; flex-direction: column;">
                <button class="preview-close" aria-label="Close Preview" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.8); border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.1); backdrop-filter: blur(4px);">×</button>
                <div id="previewContent" style="overflow-y: auto; flex: 1;"></div>
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
            <div style="height: 250px; overflow: hidden; position: relative;">
                <img src="${eventImage}" style="width: 100%; height: 100%; object-fit: cover;" alt="Event">
                <div style="position: absolute; top: 1rem; left: 1rem; background: ${getStatusColor(eventStatus.toLowerCase())}; color: white; padding: 0.5rem 1rem; border-radius: 30px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    ${eventStatus}
                </div>
            </div>
            <div style="padding: 2rem;">
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h1 style="font-size: 1.85rem; font-weight: 800; color: #111827; line-height: 1.2; flex: 1;">${eventName}</h1>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #eef2ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">📅</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Date</div>
                            <div style="font-weight: 700; color: #374151;">${new Date(date).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #fff7ed; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">🕒</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Time</div>
                            <div style="font-weight: 700; color: #374151;">${time.substring(0, 5)}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">🎟️</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Price</div>
                            <div style="font-weight: 700; color: #374151;">${price.includes('Free') ? 'Free' : price}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #fdf2f8; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">📂</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase;">Category</div>
                            <div style="font-weight: 700; color: #374151;">${category}</div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📍 Location & Address</label>
                    <div style="background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb; color: #4b5563; font-weight: 500;">
                        ${address || 'No address provided'}
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📝 Description</label>
                    <div style="color: #4b5563; line-height: 1.6; white-space: pre-wrap; background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">${description || 'No description available'}</div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">👥 Attendees</label>
                    <div style="display: flex; align-items: center; gap: 15px; background: #f9fafb; padding: 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="display: flex;">
                            ${[...Array(Math.min(parseInt(attendees), 5))].map((_, i) => `
                                <img src="https://ui-avatars.com/api/?name=User+${i}&background=random" 
                                     style="width: 36px; height: 36px; border-radius: 50%; border: 3px solid white; margin-left: ${i === 0 ? '0' : '-12px'}; transition: transform 0.2s;">
                            `).join('')}
                        </div>
                        <span style="font-size: 1rem; color: #111827; font-weight: 700;">${attendees} people attending</span>
                    </div>
                </div>
                
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #f3f4f6;">
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">🔗 Events Tag</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <code style="background: #f3f4f6; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #e5e7eb; font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; flex: 1; color: #111827; font-weight: 600;">${tag}</code>
                            <button onclick="copyToClipboard('${tag}', 'Tag copied!')" style="background: white; border: 1px solid #d1d5db; padding: 0.75rem; border-radius: 10px; cursor: pointer; transition: all 0.2s; font-size: 1.25rem;" title="Copy Tag">📋</button>
                        </div>
                    </div>
                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">🚀 Shareable Link</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" readonly value="${shareLink}" 
                                   style="background: #f3f4f6; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #e5e7eb; font-family: inherit; font-size: 0.9rem; flex: 1; color: #111827; font-weight: 500;">
                            <button onclick="copyToClipboard('${shareLink}', 'Link copied!')" style="background: #4F46E5; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; cursor: pointer; transition: all 0.2s; font-size: 0.95rem; font-weight: 700; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);">Copy Link</button>
                        </div>
                    </div>

                    ${eventStatus.toLowerCase() !== 'published' ? `
                        <button onclick="publishEvent(${eventId})" class="btn" style="width: 100%; border-radius: 12px; font-weight: 700; background: #10b981; color: white; padding: 1rem; border: none; cursor: pointer; transition: all 0.2s; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">
                            <span>✓</span> Publish Event Now
                        </button>
                    ` : ''}
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

async function publishEvent(eventId) {
    if (document.activeElement) document.activeElement.blur();
    
    const result = await Swal.fire({
        title: 'Publish Event?',
        text: 'Are you sure you want to publish this event? It will be visible to all users on the platform.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'Yes, Publish',
        cancelButtonText: 'Wait'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('../../api/events/publish-event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Event published successfully!', 'success');
            // Close preview modal if open
            const previewBackdrop = document.querySelector('.preview-modal-backdrop');
            if (previewBackdrop) {
                previewBackdrop.querySelector('.preview-close').click();
            }
            // Trigger dashboard stat update if on dashboard
            if (window.loadDashboardStats) {
                window.loadDashboardStats(storage.get('user').id);
            }
            
            // Reload page to reflect changes
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Failed to publish event: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error publishing event:', error);
        showNotification('An error occurred while publishing event', 'error');
    }
}

// Make functions globally available
window.editEvent = editEvent;
window.previewEvent = previewEvent;
window.deleteEvent = deleteEvent;
window.publishEvent = publishEvent;
