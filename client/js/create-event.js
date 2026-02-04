/**
 * Event Creation Form with Auto-Generated Tags and Links
 */

function showCreateEventModal() {
    const user = storage.get('user');
    if (!user) return;

    const modalHTML = `
        <div id="createEventModal" class="modal-backdrop active" role="dialog" aria-modal="true" aria-hidden="false">
            <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Create New Event</h2>
                    <button class="modal-close" onclick="closeCreateEventModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="createEventForm" enctype="multipart/form-data">
                        <!-- Event Image Upload -->
                        <div style="margin-bottom: 2.5rem;">
                            <label style="display: block; font-weight: 700; margin-bottom: 0.75rem; color: #374151;">Event Banner</label>
                            <div style="position: relative; transition: all 0.3s ease;">
                                <img id="eventImagePreview" 
                                     src="" 
                                     style="width: 100%; height: 300px; object-fit: cover; border-radius: 16px; border: 2px dashed #e5e7eb; background: #f9fafb;">
                                <label for="eventImageInput" style="position: absolute; bottom: 1.5rem; right: 1.5rem; background: rgba(255, 255, 255, 0.9); color: #1f2937; padding: 0.75rem 1.5rem; border-radius: 12px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.1); backdrop-filter: blur(4px); transition: transform 0.2s;">
                                    📷 Upload Image
                                </label>
                                <input type="file" id="eventImageInput" name="event_image" accept="image/*" style="display: none;" onchange="previewEventImage(event)">
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 2.5rem;">
                            
                            <!-- Section: Basic Details -->
                            <section>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                    📝 Basic Details
                                </h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Event Name <span style="color: #ef4444">*</span></label>
                                        <input type="text" name="event_name" id="eventNameInput" required placeholder="e.g. Annual Tech Summit 2026" oninput="generateEventTagAndLink()" 
                                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                                        <div class="field-hint" style="font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem;">This will be used to generate the event tag and link</div>
                                    </div>

                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Category <span style="color: #ef4444">*</span></label>
                                        <select name="event_type" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
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
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Priority Level</label>
                                        <select name="priority" id="prioritySelect" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                            <option value="nearby">📍 Nearby</option>
                                            <option value="hot">🔥 Hot</option>
                                            <option value="trending">📈 Trending</option>
                                            <option value="featured">⭐ Featured</option>
                                            <option value="upcoming">🕒 Upcoming</option>
                                        </select>
                                    </div>

                                     <div class="form-group" style="grid-column: 1 / -1;">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Description <span style="color: #ef4444">*</span></label>
                                        <textarea name="description" rows="4" required placeholder="Describe what attendees can expect..." 
                                                  style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; resize: vertical;"></textarea>
                                    </div>
                                </div>
                            </section>

                            <!-- Section: Schedule & Ticket -->
                            <section>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                    📅 Schedule & Tickets
                                </h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Date <span style="color: #ef4444">*</span></label>
                                        <input type="date" name="event_date" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                    </div>

                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Time <span style="color: #ef4444">*</span></label>
                                        <input type="time" name="event_time" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                    </div>

                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Ticket Price (₦) <span style="color: #ef4444">*</span></label>
                                        <div style="display: flex; gap: 1rem;">
                                            <input type="number" name="price" id="priceInput" required placeholder="0.00" min="0" step="0.01" 
                                                   style="flex: 1; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                            <div style="display: flex; align-items: center; background: #f3f4f6; padding: 0 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-weight: 500; color: #374151;">
                                                    <input type="checkbox" id="freeEventCheckbox" style="width: 1.2rem; height: 1.2rem; accent-color: #10b981;"> 
                                                    Free
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Status</label>
                                        <select name="status" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                            <option value="draft" selected>Draft</option>
                                            <option value="scheduled">Scheduled</option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <!-- Section: Location -->
                            <section>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                    📍 Location Details
                                </h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">State <span style="color: #ef4444">*</span></label>
                                        <select name="state" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                            <option value="">Select State</option>
                                            ${getNigerianStates(true).map(state => `<option value="${state}">${state}</option>`).join('')}
                                        </select>
                                    </div>

                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Full Venue Address <span style="color: #ef4444">*</span></label>
                                        <textarea name="address" rows="2" required placeholder="Street address, landmarks..." 
                                                  style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;"></textarea>
                                    </div>
                                </div>
                            </section>

                             <!-- Auto-Generated Fields (Read-Only) -->
                            <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                                <h4 style="margin-bottom: 1rem; font-weight: 700; color: #475569; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em;">🔗 Auto-Generated Links</h4>
                                <div style="display: grid; gap: 1rem;">
                                    <div class="form-group">
                                        <label style="font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem; display: block;">Event Tag</label>
                                        <input type="text" id="eventTagField" name="tag" readonly placeholder="Enter event name first..." 
                                               style="width: 100%; padding: 0.5rem 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; color: #334155;">
                                    </div>

                                    <div class="form-group">
                                        <label style="font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.25rem; display: block;">Shareable Link</label>
                                        <input type="text" id="eventLinkField" name="external_link" readonly placeholder="Enter event name first..." 
                                               style="width: 100%; padding: 0.5rem 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; color: #334155;">
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Contact -->
                            <section>
                                <h3 style="font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                    📞 Contact Information
                                </h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Primary Contact <span style="color: #ef4444">*</span></label>
                                        <input type="tel" name="phone_contact_1" required placeholder="+234..." 
                                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                    </div>

                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Secondary Contact</label>
                                        <input type="tel" name="phone_contact_2" placeholder="+234... (optional)" 
                                               style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px;">
                                    </div>
                                </div>
                            </section>

                            <!-- Scheduled Publishing (if status is scheduled) -->
                            <div class="form-group" id="scheduledTimeGroup" style="display: none; background: #fff7ed; padding: 1.5rem; border-radius: 12px; border: 1px solid #fed7aa;">
                                <label style="font-weight: 600; color: #9a3412; margin-bottom: 0.5rem; display: block;">Scheduled Publish Time</label>
                                <input type="datetime-local" name="scheduled_publish_time" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #fed7aa; border-radius: 8px;">
                                <div class="field-hint" style="color: #c2410c; margin-top: 0.5rem; font-size: 0.9rem;">Event will be automatically published at this time</div>
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 1rem; margin-top: 1rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                                <button type="submit" class="btn btn-primary" style="flex: 2; padding: 1rem; font-size: 1rem; font-weight: 600; justify-content: center;">
                                    Create Event
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeCreateEventModal()" style="flex: 1; padding: 1rem; font-size: 1rem; justify-content: center;">
                                    Cancel
                                </button>
                            </div>
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

    // Free Event Checkbox Handler
    const freeCheckbox = document.getElementById('freeEventCheckbox');
    const priceInput = document.getElementById('priceInput');

    freeCheckbox.addEventListener('change', function() {
        if (this.checked) {
            priceInput.value = 0;
            priceInput.readOnly = true;
            priceInput.style.backgroundColor = '#f3f4f6';
            priceInput.style.color = '#9ca3af';
        } else {
            priceInput.readOnly = false;
            priceInput.value = '';
            priceInput.placeholder = '5000';
            priceInput.style.backgroundColor = 'white';
            priceInput.style.color = 'inherit';
            priceInput.focus();
        }
    });

    // Add status change handler
    document.querySelector('select[name="status"]').addEventListener('change', function(e) {
        const scheduledGroup = document.getElementById('scheduledTimeGroup');
        scheduledGroup.style.display = e.target.value === 'scheduled' ? 'block' : 'none';
    });

    // Priority default logic handled by backend default sanitization
    // Visibility dropdown removed as requested
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

