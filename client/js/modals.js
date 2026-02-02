/**
 * Client Modals JavaScript
 * Handles all modal functionality for client dashboard
 */

// Profile Edit Modal
function showProfileEditModal() {
    const user = storage.get('user');
    if (!user) return;

    const modalHTML = `
        <div id="profileEditModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Edit Profile</h2>
                    <button class="modal-close" onclick="closeProfileEditModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="profileEditForm" enctype="multipart/form-data">
                        <!-- Profile Picture -->
                        <div style="text-align: center; margin-bottom: 2rem;">
                            <div style="position: relative; display: inline-block;">
                                <img id="profilePreview" 
                                     src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random&size=150`}" 
                                     style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--client-primary);">
                                <label for="profilePicInput" style="position: absolute; bottom: 0; right: 0; background: var(--client-primary); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem;">
                                    📷
                                </label>
                                <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" style="display: none;" onchange="previewProfilePic(event)">
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="name" value="${user.name}" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="${user.email}" disabled style="background: #f5f5f5;">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" value="${user.phone || ''}" placeholder="+234...">
                            </div>
                            <div class="form-group">
                                <label>Job Title</label>
                                <input type="text" name="job_title" value="${user.job_title || ''}" placeholder="Event Organizer">
                            </div>
                            <div class="form-group">
                                <label>Company</label>
                                <input type="text" name="company" value="${user.company || ''}" placeholder="Company Name">
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" value="${user.city || ''}" placeholder="Lagos">
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <select name="state">
                                    <option value="">Select State</option>
                                    ${getNigerianStates().map(state => 
                                        `<option value="${state}" ${user.state === state ? 'selected' : ''}>${state}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" value="${user.dob || ''}">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" ${user.gender === 'male' ? 'selected' : ''}>Male</option>
                                    <option value="female" ${user.gender === 'female' ? 'selected' : ''}>Female</option>
                                    <option value="other" ${user.gender === 'other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="form-group" style="margin-top: 1rem;">
                            <label>Address</label>
                            <textarea name="address" rows="3" placeholder="Full address">${user.address || ''}</textarea>
                        </div>

                        <!-- Submit Button -->
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeProfileEditModal()" style="flex: 1;">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existing = document.getElementById('profileEditModal');
    if (existing) existing.remove();

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Add form submit handler
    document.getElementById('profileEditForm').addEventListener('submit', handleProfileUpdate);
}

function closeProfileEditModal() {
    const modal = document.getElementById('profileEditModal');
    if (modal) modal.remove();
}

function previewProfilePic(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

async function handleProfileUpdate(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('../../api/users/update-profile.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Profile updated successfully!', 'success');
            
            // Update stored user data
            storage.set('user', result.user);
            
            // Close modal
            closeProfileEditModal();
            
            // Reload page to reflect changes
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Failed to update profile: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        showNotification('An error occurred while updating profile', 'error');
    }
}

// Event Preview Modal
function showEventPreviewModal(eventId) {
    // Show loading
    const loadingHTML = `
        <div id="eventPreviewModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h2>Event Details</h2>
                    <button class="modal-close" onclick="closeEventPreviewModal()">×</button>
                </div>
                <div class="modal-body" style="text-align: center; padding: 3rem;">
                    <div class="spinner"></div>
                    <p>Loading event details...</p>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', loadingHTML);

    // Fetch event details
    fetchEventDetails(eventId);
}

async function fetchEventDetails(eventId) {
    try {
        const response = await fetch(`../../api/events/get-events.php?limit=100`);
        const result = await response.json();

        if (result.success) {
            const event = result.events.find(e => e.id == eventId);
            if (event) {
                displayEventPreview(event);
            } else {
                showNotification('Event not found', 'error');
                closeEventPreviewModal();
            }
        }
    } catch (error) {
        console.error('Error fetching event:', error);
        showNotification('Failed to load event details', 'error');
        closeEventPreviewModal();
    }
}

function displayEventPreview(event) {
    const modalContent = `
        <div id="eventPreviewModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Event Preview</h2>
                    <button class="modal-close" onclick="closeEventPreviewModal()">×</button>
                </div>
                <div class="modal-body" style="padding: 0;">
                    <!-- User Profile Image for Event Preview -->
                    <div style="width: 100%; height: 300px; overflow: hidden;">
                        <img src="${(storage.get('user') || {}).profile_pic || 'https://ui-avatars.com/api/?name=' + encodeURIComponent((storage.get('user') || {}).name || 'User')}" 
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>

                    <!-- Event Content -->
                    <div style="padding: 2rem;">
                        <!-- Title and Status -->
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
                            <div>
                                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">${event.event_name}</h1>
                                <div style="display: flex; gap: 1rem; align-items: center;">
                                    <span style="padding: 0.25rem 0.75rem; background: ${getStatusBadgeColor(event.status)}; color: white; border-radius: 20px; font-size: 0.85rem;">
                                        ${event.status.toUpperCase()}
                                    </span>
                                    <span style="padding: 0.25rem 0.75rem; background: ${getPriorityBadgeColor(event.priority)}; color: white; border-radius: 20px; font-size: 0.85rem;">
                                        ${getPriorityIcon(event.priority)} ${event.priority.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--client-primary);">
                                    ₦${parseFloat(event.price).toLocaleString()}
                                </div>
                                <div style="font-size: 0.85rem; color: #666;">Ticket Price</div>
                            </div>
                        </div>

                        <!-- Quick Info -->
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                            <div>
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📅 Date & Time</div>
                                <div style="font-weight: 600;">${formatDate(event.event_date)} at ${event.event_time}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📍 Location</div>
                                <div style="font-weight: 600;">${event.state}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">🎭 Category</div>
                                <div style="font-weight: 600;">${event.event_type}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">👥 Attendees</div>
                                <div style="font-weight: 600;">${event.attendee_count || 0} registered</div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div style="margin-bottom: 2rem;">
                            <h3 style="margin-bottom: 1rem;">About This Event</h3>
                            <p style="line-height: 1.8; color: #555;">${event.description}</p>
                        </div>

                        <!-- Contact & Address -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <div>
                                <h4 style="margin-bottom: 0.75rem;">📞 Contact Information</h4>
                                <div style="color: #555;">
                                    <div>${event.phone_contact_1}</div>
                                    ${event.phone_contact_2 ? `<div>${event.phone_contact_2}</div>` : ''}
                                </div>
                            </div>
                            <div>
                                <h4 style="margin-bottom: 0.75rem;">🏠 Venue Address</h4>
                                <div style="color: #555;">${event.address}</div>
                            </div>
                        </div>

                        <!-- Event Links -->
                        <div style="padding: 1.5rem; background: #f8f9fa; border-radius: 10px; margin-bottom: 2rem;">
                            <h4 style="margin-bottom: 1rem;">🔗 Event Links</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <div>
                                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Event Tag:</div>
                                    <code style="background: white; padding: 0.5rem; border-radius: 5px; display: inline-block;">${event.tag}</code>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Shareable Link:</div>
                                    <a href="${event.external_link}" target="_blank" style="color: var(--client-primary); word-break: break-all;">
                                        ${event.external_link}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 1rem;">
                            <button onclick="editEvent(${event.id})" class="btn btn-primary" style="flex: 1;">
                                ✏️ Edit Event
                            </button>
                            <button onclick="shareEvent('${event.external_link}')" class="btn btn-secondary" style="flex: 1;">
                                🔗 Share Event
                            </button>
                            <button onclick="closeEventPreviewModal()" class="btn btn-secondary">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal
    const existing = document.getElementById('eventPreviewModal');
    if (existing) existing.remove();

    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalContent);
}

function closeEventPreviewModal() {
    const modal = document.getElementById('eventPreviewModal');
    if (modal) modal.remove();
}

function shareEvent(link) {
    if (navigator.share) {
        navigator.share({
            title: 'Check out this event!',
            url: link
        }).catch(err => console.log('Error sharing:', err));
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(link).then(() => {
            showNotification('Event link copied to clipboard!', 'success');
        });
    }
}

// Helper Functions
function getNigerianStates() {
    return [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
        'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
        'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
        'Yobe', 'Zamfara'
    ];
}

function getStatusBadgeColor(status) {
    const colors = {
        'published': '#10b981',
        'scheduled': '#3b82f6',
        'draft': '#ef4444',
        'cancelled': '#6b7280'
    };
    return colors[status] || '#6b7280';
}

function getPriorityBadgeColor(priority) {
    const colors = {
        'hot': '#ef4444',
        'trending': '#f59e0b',
        'featured': '#8b5cf6',
        'nearby': '#10b981',
        'upcoming': '#3b82f6'
    };
    return colors[priority] || '#6b7280';
}

function getPriorityIcon(priority) {
    const icons = {
        'hot': '🔥',
        'trending': '📈',
        'featured': '⭐',
        'nearby': '📍',
        'upcoming': '🕒'
    };
    return icons[priority] || '';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long',
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

// Event Action Modal (for publishing/canceling events)
function showEventActionModal(eventId) {
    // Fetch event details first
    fetchEventForAction(eventId);
}

async function fetchEventForAction(eventId) {
    try {
        const user = storage.get('user');
        const response = await fetch(`../../api/events/get-events.php?client_id=${user.id}&limit=100`);
        const result = await response.json();

        if (result.success) {
            const event = result.events.find(e => e.id == eventId);
            if (event) {
                displayEventActionModal(event);
            } else {
                showNotification('Event not found', 'error');
            }
        }
    } catch (error) {
        console.error('Error fetching event:', error);
        showNotification('Failed to load event details', 'error');
    }
}

function displayEventActionModal(event) {
    const modalContent = `
        <div id="eventActionModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Event Details</h2>
                    <button class="modal-close" onclick="closeEventActionModal()">×</button>
                </div>
                <div class="modal-body">
                    <!-- User Profile Image for Action Modal -->
                    <div style="width: 100%; height: 200px; overflow: hidden; border-radius: 12px; margin-bottom: 1.5rem;">
                        <img src="${(storage.get('user') || {}).profile_pic || 'https://ui-avatars.com/api/?name=' + encodeURIComponent((storage.get('user') || {}).name || 'User')}" 
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>

                    <!-- Event Info -->
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">${event.event_name}</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📅 Date & Time</div>
                            <div style="font-weight: 600;">${formatDate(event.event_date)} at ${event.event_time}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📍 Location</div>
                            <div style="font-weight: 600;">${event.state}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">🎭 Category</div>
                            <div style="font-weight: 600;">${event.event_type}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">💰 Price</div>
                            <div style="font-weight: 600;">₦${parseFloat(event.price).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📊 Status</div>
                            <div style="font-weight: 600; color: ${getStatusBadgeColor(event.status)};">${event.status.toUpperCase()}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">👥 Attendees</div>
                            <div style="font-weight: 600;">${event.attendee_count || 0} registered</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin-bottom: 0.75rem;">About This Event</h4>
                        <p style="line-height: 1.6; color: #555;">${event.description}</p>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        ${event.status !== 'published' ? `
                            <button onclick="publishEvent(${event.id})" class="btn btn-primary" style="flex: 1; background: var(--card-blue);">
                                ✓ Publish Event
                            </button>
                        ` : `
                            <button class="btn btn-secondary" style="flex: 1; opacity: 0.5; cursor: not-allowed;" disabled>
                                Already Published
                            </button>
                        `}
                        <button onclick="closeEventActionModal()" class="btn btn-secondary" style="flex: 1;">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existing = document.getElementById('eventActionModal');
    if (existing) existing.remove();

    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalContent);
}

function closeEventActionModal() {
    const modal = document.getElementById('eventActionModal');
    if (modal) modal.remove();
}

async function publishEvent(eventId) {
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
            closeEventActionModal();
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

// Edit Event Modal
function showEditEventModal(event) {
    const modalHTML = `
        <div id="editEventModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2>Edit Event</h2>
                    <button class="modal-close" onclick="closeEditEventModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="editEventForm" enctype="multipart/form-data">
                        <input type="hidden" name="event_id" value="${event.id}">
                        
                        <!-- Event Image -->
                        <div style="margin-bottom: 2rem;">
                            <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Event Image</label>
                            <div style="position: relative;">
                                <img id="editEventImagePreview" 
                                     src="${event.image_path || ''}" 
                                     style="width: 100%; height: 250px; object-fit: cover; border-radius: 12px; border: 2px dashed #d1d5db;">
                                <label for="editEventImageInput" style="position: absolute; bottom: 1rem; right: 1rem; background: var(--card-blue); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                                    📷 Change Image
                                </label>
                                <input type="file" id="editEventImageInput" name="event_image" accept="image/*" style="display: none;" onchange="previewEditEventImage(event)">
                            </div>
                        </div>

                        <!-- Event Basic Info -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Event Name *</label>
                                <input type="text" name="event_name" value="${event.event_name}" required>
                            </div>

                            <div class="form-group">
                                <label>Event Type/Category *</label>
                                <select name="event_type" required>
                                    <option value="Conference" ${event.event_type === 'Conference' ? 'selected' : ''}>Conference</option>
                                    <option value="Workshop" ${event.event_type === 'Workshop' ? 'selected' : ''}>Workshop</option>
                                    <option value="Seminar" ${event.event_type === 'Seminar' ? 'selected' : ''}>Seminar</option>
                                    <option value="Entertainment" ${event.event_type === 'Entertainment' ? 'selected' : ''}>Entertainment</option>
                                    <option value="Sports" ${event.event_type === 'Sports' ? 'selected' : ''}>Sports</option>
                                    <option value="Exhibition" ${event.event_type === 'Exhibition' ? 'selected' : ''}>Exhibition</option>
                                    <option value="Networking" ${event.event_type === 'Networking' ? 'selected' : ''}>Networking</option>
                                    <option value="Festival" ${event.event_type === 'Festival' ? 'selected' : ''}>Festival</option>
                                    <option value="Concert" ${event.event_type === 'Concert' ? 'selected' : ''}>Concert</option>
                                    <option value="Other" ${event.event_type === 'Other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Priority Level</label>
                                <select name="priority" id="editPrioritySelect">
                                    <option value="nearby" ${event.priority === 'nearby' || event.priority === 'normal' ? 'selected' : ''}>📍 Nearby</option>
                                    <option value="hot" ${event.priority === 'hot' ? 'selected' : ''}>🔥 Hot</option>
                                    <option value="trending" ${event.priority === 'trending' ? 'selected' : ''}>📈 Trending</option>
                                    <option value="featured" ${event.priority === 'featured' ? 'selected' : ''}>⭐ Featured</option>
                                    <option value="upcoming" ${event.priority === 'upcoming' ? 'selected' : ''}>🕒 Upcoming</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Event Date *</label>
                                <input type="date" name="event_date" value="${event.event_date}" required>
                            </div>

                            <div class="form-group">
                                <label>Event Time *</label>
                                <input type="time" name="event_time" value="${event.event_time}" required>
                            </div>

                            <div class="form-group">
                                <label>Ticket Price (₦) *</label>
                                <input type="number" name="price" value="${event.price}" required min="0" step="0.01">
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="draft" ${event.status === 'draft' ? 'selected' : ''}>Draft</option>
                                    <option value="scheduled" ${event.status === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                                    <option value="published" ${event.status === 'published' ? 'selected' : ''}>Published</option>
                                </select>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label>Event Description *</label>
                            <textarea name="description" rows="4" required>${event.description}</textarea>
                        </div>

                        <!-- Location Details -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>State *</label>
                                <select name="state" required>
                                    ${getNigerianStates().map(state => 
                                        `<option value="${state}" ${event.state === state ? 'selected' : ''}>${state}</option>`
                                    ).join('')}
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Visibility</label>
                                <select name="visibility">
                                    <option value="all_states" ${event.visibility === 'all_states' ? 'selected' : ''}>All States</option>
                                    <option value="specific_state" ${event.visibility === 'specific_state' ? 'selected' : ''}>Specific State Only</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Venue Address *</label>
                            <textarea name="address" rows="2" required>${event.address}</textarea>
                        </div>

                        <!-- Contact Information -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Primary Contact *</label>
                                <input type="tel" name="phone_contact_1" value="${event.phone_contact_1}" required>
                            </div>

                            <div class="form-group">
                                <label>Secondary Contact</label>
                                <input type="tel" name="phone_contact_2" value="${event.phone_contact_2 || ''}">
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                Update Event
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeEditEventModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existing = document.getElementById('editEventModal');
    if (existing) existing.remove();

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Add form submit handler
    document.getElementById('editEventForm').addEventListener('submit', handleEventUpdate);

    // Add priority change handler for nearby logic
    document.getElementById('editPrioritySelect').addEventListener('change', function(e) {
        const visibilitySelect = document.querySelector('#editEventForm select[name="visibility"]');
        const stateSelect = document.querySelector('#editEventForm select[name="state"]');
        const user = storage.get('user');

        if (e.target.value === 'nearby') {
            visibilitySelect.value = 'specific_state';
            visibilitySelect.disabled = true;

            if (user && user.state && !stateSelect.value) {
                stateSelect.value = user.state;
            }

            if (!document.getElementById('editHiddenVisibility')) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'visibility';
                hidden.value = 'specific_state';
                hidden.id = 'editHiddenVisibility';
                e.target.form.appendChild(hidden);
            }
        } else {
            visibilitySelect.disabled = false;
            const hidden = document.getElementById('editHiddenVisibility');
            if (hidden) hidden.remove();
        }
    });

    // Trigger initial check
    if (document.getElementById('editPrioritySelect').value === 'nearby') {
        const visibilitySelect = document.querySelector('#editEventForm select[name="visibility"]');
        visibilitySelect.value = 'specific_state';
        visibilitySelect.disabled = true;
        
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'visibility';
        hidden.value = 'specific_state';
        hidden.id = 'editHiddenVisibility';
        document.getElementById('editEventForm').appendChild(hidden);
    }
}

function closeEditEventModal() {
    const modal = document.getElementById('editEventModal');
    if (modal) modal.remove();
}

function previewEditEventImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('editEventImagePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

async function handleEventUpdate(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../../api/events/update-event.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Event updated successfully!', 'success');
            closeEditEventModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Failed to update event: ' + result.message, 'error');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error updating event:', error);
        showNotification('An error occurred while updating event', 'error');
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

// Ticket Preview Modal
function showTicketPreviewModal(ticket) {
    const modalContent = `
        <div id="ticketPreviewModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Ticket Details</h2>
                    <button class="modal-close" onclick="closeTicketPreviewModal()">×</button>
                </div>
                <div class="modal-body" style="padding: 0;">
                    <!-- User Profile Image for Ticket Preview -->
                    <div style="width: 100%; height: 200px; overflow: hidden; border-radius: 12px 12px 0 0;">
                        <img src="${(storage.get('user') || {}).profile_pic || 'https://ui-avatars.com/api/?name=' + encodeURIComponent((storage.get('user') || {}).name || 'User')}" 
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div style="display: grid; gap: 1.5rem; padding: 1.5rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">🎫 Ticket ID</div>
                            <div style="font-weight: 600;">${ticket.id}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📅 Event Name</div>
                            <div style="font-weight: 600;">${ticket.event_name || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">👤 Buyer</div>
                            <div style="font-weight: 600;">${ticket.buyer_name || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">💰 Price</div>
                            <div style="font-weight: 600;">₦${parseFloat(ticket.price || 0).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📆 Purchase Date</div>
                            <div style="font-weight: 600;">${ticket.purchase_date || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📊 Status</div>
                            <div style="font-weight: 600; color: ${ticket.status === 'confirmed' ? '#10b981' : '#ef4444'};">
                                ${ticket.status ? ticket.status.toUpperCase() : 'N/A'}
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 2rem;">
                        <button onclick="closeTicketPreviewModal()" class="btn btn-secondary" style="width: 100%;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existing = document.getElementById('ticketPreviewModal');
    if (existing) existing.remove();

    document.body.insertAdjacentHTML('beforeend', modalContent);
}

function closeTicketPreviewModal() {
    const modal = document.getElementById('ticketPreviewModal');
    if (modal) modal.remove();
}

// User Preview Modal
function showUserPreviewModal(user) {
    const modalContent = `
        <div id="userPreviewModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>User Details</h2>
                    <button class="modal-close" onclick="closeUserPreviewModal()">×</button>
                </div>
                <div class="modal-body">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <img src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=random&size=150`}" 
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--client-primary);">
                    </div>
                    <div style="display: grid; gap: 1.5rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">👤 Name</div>
                            <div style="font-weight: 600;">${user.name || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📧 Email</div>
                            <div style="font-weight: 600;">${user.email || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📊 Status</div>
                            <div style="font-weight: 600; color: ${user.status === 'active' ? '#10b981' : '#ef4444'};">
                                ${user.status ? user.status.toUpperCase() : 'N/A'}
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📈 Engagement</div>
                            <div style="font-weight: 600;">${user.engagement || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">📅 Date Joined</div>
                            <div style="font-weight: 600;">${user.date_joined ? formatDate(user.date_joined) : 'N/A'}</div>
                        </div>
                    </div>
                    <div style="margin-top: 2rem;">
                        <button onclick="closeUserPreviewModal()" class="btn btn-secondary" style="width: 100%;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existing = document.getElementById('userPreviewModal');
    if (existing) existing.remove();

    document.body.insertAdjacentHTML('beforeend', modalContent);
}

function closeUserPreviewModal() {
    const modal = document.getElementById('userPreviewModal');
    if (modal) modal.remove();
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);

}

// Make functions globally available
window.showProfileEditModal = showProfileEditModal;
window.closeProfileEditModal = closeProfileEditModal;
window.previewProfilePic = previewProfilePic;
window.showEventPreviewModal = showEventPreviewModal;
window.closeEventPreviewModal = closeEventPreviewModal;
window.shareEvent = shareEvent;
window.showEventActionModal = showEventActionModal;
window.closeEventActionModal = closeEventActionModal;
window.publishEvent = publishEvent;
window.showEditEventModal = showEditEventModal;
window.closeEditEventModal = closeEditEventModal;
window.previewEditEventImage = previewEditEventImage;
window.showTicketPreviewModal = showTicketPreviewModal;
window.closeTicketPreviewModal = closeTicketPreviewModal;
window.showUserPreviewModal = showUserPreviewModal;
window.closeUserPreviewModal = closeUserPreviewModal;
