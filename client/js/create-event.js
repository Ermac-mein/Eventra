/**
 * Event Creation Form with Auto-Generated Tags and Links
 */

function showCreateEventModal() {
    const user = storage.get('user');
    if (!user) return;

    const modalHTML = `
        <div id="createEventModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Create New Event</h2>
                    <button class="modal-close" onclick="closeCreateEventModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="createEventForm" enctype="multipart/form-data">
                        <!-- Event Image Upload -->
                        <div style="margin-bottom: 2rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Event Image</label>
                            <div style="position: relative;">
                                <img id="eventImagePreview" 
                                     src="" 
                                     style="width: 100%; height: 250px; object-fit: cover; border-radius: 12px; border: 2px dashed #d1d5db;">
                                <label for="eventImageInput" style="position: absolute; bottom: 1rem; right: 1rem; background: var(--card-blue); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                                    📷 Upload Image
                                </label>
                                <input type="file" id="eventImageInput" name="event_image" accept="image/*" style="display: none;" onchange="previewEventImage(event)">
                            </div>
                        </div>

                        <!-- Event Basic Info -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Event Name *</label>
                                <input type="text" name="event_name" id="eventNameInput" required placeholder="Tech Summit 2026" oninput="generateEventTagAndLink()">
                                <div class="field-hint">This will be used to generate the event tag and link</div>
                            </div>

                            <div class="form-group">
                                <label>Event Type/Category *</label>
                                <select name="event_type" required>
                                    <option value="">Select Category</option>
                                    <option value="Conference">Conference</option>
                                    <option value="Workshop">Workshop</option>
                                    <option value="Seminar">Seminar</option>
                                    <option value="Entertainment">Entertainment</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Exhibition">Exhibition</option>
                                    <option value="Networking">Networking</option>
                                    <option value="Festival">Festival</option>
                                    <option value="Concert">Concert</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Priority Level</label>
                                <select name="priority" id="prioritySelect">
                                    <option value="nearby">📍 Nearby</option>
                                    <option value="hot">🔥 Hot</option>
                                    <option value="trending">📈 Trending</option>
                                    <option value="featured">⭐ Featured</option>
                                    <option value="upcoming">🕒 Upcoming</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Event Date *</label>
                                <input type="date" name="event_date" required>
                            </div>

                            <div class="form-group">
                                <label>Event Time *</label>
                                <input type="time" name="event_time" required>
                            </div>

                            <div class="form-group">
                                <label>Ticket Price (₦) *</label>
                                <input type="number" name="price" required placeholder="5000" min="0" step="0.01">
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="draft" selected>Draft</option>
                                    <option value="scheduled">Scheduled</option>
                                </select>
                            </div>
                        </div>

                        <!-- Auto-Generated Fields (Read-Only) -->
                        <div style="background: #f9fafb; padding: 1.5rem; border-radius: 12px; margin: 1.5rem 0;">
                            <h4 style="margin-bottom: 1rem; color: var(--client-text-main);">🔗 Auto-Generated Links</h4>
                            
                            <div class="form-group">
                                <label>Event Tag</label>
                                <input type="text" id="eventTagField" name="tag" readonly placeholder="Enter event name first...">
                                <div class="field-hint">Auto-generated from event name (lowercase, hyphenated)</div>
                            </div>

                            <div class="form-group">
                                <label>Shareable Link</label>
                                <input type="text" id="eventLinkField" name="external_link" readonly placeholder="Enter event name first...">
                                <div class="field-hint">Auto-generated shareable link for this event</div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label>Event Description *</label>
                            <textarea name="description" rows="4" required placeholder="Describe your event in detail..."></textarea>
                        </div>

                        <!-- Location Details -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>State *</label>
                                <select name="state" required>
                                    <option value="">Select State</option>
                                    ${getNigerianStates().map(state => `<option value="${state}">${state}</option>`).join('')}
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Visibility</label>
                                <select name="visibility">
                                    <option value="all_states">All States</option>
                                    <option value="specific_state">Specific State Only</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Venue Address *</label>
                            <textarea name="address" rows="2" required placeholder="Full venue address"></textarea>
                        </div>

                        <!-- Contact Information -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Primary Contact *</label>
                                <input type="tel" name="phone_contact_1" required placeholder="+234...">
                            </div>

                            <div class="form-group">
                                <label>Secondary Contact</label>
                                <input type="tel" name="phone_contact_2" placeholder="+234... (optional)">
                            </div>
                        </div>

                        <!-- Scheduled Publishing (if status is scheduled) -->
                        <div class="form-group" id="scheduledTimeGroup" style="display: none;">
                            <label>Scheduled Publish Time</label>
                            <input type="datetime-local" name="scheduled_publish_time">
                            <div class="field-hint">Event will be automatically published at this time</div>
                        </div>

                        <!-- Submit Buttons -->
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                Create Event
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeCreateEventModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existing = document.getElementById('createEventModal');
    if (existing) existing.remove();

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Add form submit handler
    document.getElementById('createEventForm').addEventListener('submit', handleEventCreation);

    // Add status change handler
    document.querySelector('select[name="status"]').addEventListener('change', function(e) {
        const scheduledGroup = document.getElementById('scheduledTimeGroup');
        scheduledGroup.style.display = e.target.value === 'scheduled' ? 'block' : 'none';
    });

    // Add priority change handler for nearby logic
    document.getElementById('prioritySelect').addEventListener('change', function(e) {
        const visibilitySelect = document.querySelector('select[name="visibility"]');
        const stateSelect = document.querySelector('select[name="state"]');
        const user = storage.get('user');

        if (e.target.value === 'nearby') {
            visibilitySelect.value = 'specific_state';
            visibilitySelect.disabled = true;
            
            if (user && user.state && !stateSelect.value) {
                stateSelect.value = user.state;
            }

            // Add a hidden input to ensure the value is still sent if disabled
            if (!document.getElementById('hiddenVisibility')) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'visibility';
                hidden.value = 'specific_state';
                hidden.id = 'hiddenVisibility';
                e.target.form.appendChild(hidden);
            }
        } else {
            visibilitySelect.disabled = false;
            const hidden = document.getElementById('hiddenVisibility');
            if (hidden) hidden.remove();
        }
    });

    // Trigger initial check if needed (e.g. if default is nearby)
    if (document.getElementById('prioritySelect').value === 'nearby') {
        const visibilitySelect = document.querySelector('select[name="visibility"]');
        visibilitySelect.value = 'specific_state';
        visibilitySelect.disabled = true;
        
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'visibility';
        hidden.value = 'specific_state';
        hidden.id = 'hiddenVisibility';
        document.getElementById('createEventForm').appendChild(hidden);
    }
}

function closeCreateEventModal() {
    const modal = document.getElementById('createEventModal');
    if (modal) modal.remove();
}

function previewEventImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('eventImagePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function generateEventTagAndLink() {
    const eventNameInput = document.getElementById('eventNameInput');
    const eventName = eventNameInput.value.trim();
    
    if (!eventName) {
        document.getElementById('eventTagField').value = '';
        document.getElementById('eventLinkField').value = '';
        return;
    }

    // Generate tag: lowercase, remove special chars, replace spaces with hyphens
    const tag = eventName
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

    // Get client name from stored user data
    const user = storage.get('user');
    const clientName = user.name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

    // Generate external link
    const baseUrl = window.location.origin;
    const externalLink = `${baseUrl}/public/pages/event-details.html?event=${tag}&client=${clientName}`;

    // Update fields
    document.getElementById('eventTagField').value = tag;
    document.getElementById('eventLinkField').value = externalLink;
}

async function handleEventCreation(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Creating...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../../api/events/create-event.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Event created successfully!', 'success');
            
            // Close modal
            closeCreateEventModal();
            
            // Reload page to show new event
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Failed to create event: ' + result.message, 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error creating event:', error);
        showNotification('An error occurred while creating event', 'error');
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Make functions globally available
window.showCreateEventModal = showCreateEventModal;
window.closeCreateEventModal = closeCreateEventModal;
window.previewEventImage = previewEventImage;
window.generateEventTagAndLink = generateEventTagAndLink;
