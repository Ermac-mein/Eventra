// Load Tesseract.js from CDN for universal hosting support
if (typeof Tesseract === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
    document.head.appendChild(script);
}

function showCreateEventModal() {
    const user = storage.getUser();
    if (!user) return;

    const modalHTML = `
        <div id="createEventModal" class="modal-backdrop active" role="dialog" aria-modal="true" aria-hidden="false" 
             style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); display: flex; justify-content: center; align-items: center; z-index: 10000; backdrop-filter: blur(8px);">
            <div class="modal-content" style="
                width: 95%;
                max-width: 900px;
                max-height: 92vh;
                overflow-y: auto;
                background: linear-gradient(135deg, #f5f3ff 0%, #fdf4ff 50%, ##1f2937 50%);
                border-radius: 24px;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
                position: relative;
                animation: slideIn 0.3s ease-out;">
                
                <!-- Decorative Background Pattern -->
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden; border-radius: 24px; opacity: 0.4; pointer-events: none;">
                    <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(2, 36, 41, 0.88), transparent); border-radius: 50%;"></div>
                    <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(8, 88, 102, 0.3), transparent); border-radius: 50%;"></div>
                    <div style="position: absolute; top: 50%; left: 50%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(8, 27, 68, 0.2), transparent); border-radius: 50%; transform: translate(-50%, -50%);"></div>
                </div>
                
                <div style="position: relative; z-index: 1;">
                    <div class="modal-header" style="padding: 2.5rem 3rem 1.5rem; border-bottom: 1px solid rgba(9, 29, 143, 0.1);">
                        <div style="text-align: center;">
                            <div style="display: inline-block; background: linear-gradient(135deg, #09287eff, #48aaecff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 0.5rem;">EVENTRA</div>
                            <h2 style="font-size: 2rem; font-weight: 800; color: #1f2937; margin: 0;">Create Event</h2>
                        </div>
                        <button class="modal-close" onclick="closeCreateEventModal()" 
                                style="position: absolute; top: 1.5rem; right: 1.5rem; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.2s;">×</button>
                    </div>
                    
                    <div class="modal-body" style="padding: 2.5rem 3rem 3rem;">
                        <form id="createEventForm" enctype="multipart/form-data">
                            <!-- Event Image Upload -->
                            <div style="margin-bottom: 3rem;">
                                <div style="position: relative; transition: all 0.3s ease;">
                                    <img id="eventImagePreview" 
                                         src="" 
                                         style="width: 100%; height: 280px; object-fit: cover; border-radius: 20px; border: 3px solid rgba(255, 255, 255, 0.8); box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                                    <label for="eventImageInput" style="position: absolute; bottom: 1.5rem; right: 1.5rem; background: rgba(255, 255, 255, 0.95); color: #8b5cf6; padding: 0.875rem 1.75rem; border-radius: 50px; cursor: pointer; font-weight: 700; box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3); backdrop-filter: blur(10px); transition: all 0.3s; border: 2px solid rgba(139, 92, 246, 0.2);">
                                        📷 Upload Banner
                                    </label>
                                    <input type="file" id="eventImageInput" name="event_image" accept="image/*" style="display: none;" onchange="previewEventImage(event)">
                                </div>
                            </div>

                            <div style="display: grid; gap: 2.5rem;">
                                <!-- Row 1: First & Last Name -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Event Name <span style="color: #ef4444">*</span></label>
                                        <input type="text" name="event_name" id="eventNameInput" required placeholder="Enter event name" oninput="generateEventTagAndLink()" 
                                               style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    </div>

                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Category <span style="color: #ef4444">*</span></label>
                                        <select name="event_type" required style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
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
                                            <option value="Educational">Educational</option>
                                            <option value="Social">Social</option>
                                            <option value="Personal">Personal</option>
                                            <option value="Community">Community</option>
                                            <option value="Religious">Religious</option>
                                            <option value="Cultural">Cultural</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Email Address (Full Width) -->
                                <div class="form-group">
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Description <span style="color: #ef4444">*</span></label>
                                    <textarea name="description" rows="4" required placeholder="Describe what attendees can expect..." 
                                              style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; resize: vertical; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04); font-family: inherit;"></textarea>
                                </div>

                                <!-- Address Line 1 -->
                                <div class="form-group">
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Full Venue Address <span style="color: #ef4444">*</span></label>
                                    <textarea name="address" rows="2" required placeholder="Street address, landmarks..." 
                                              style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04); font-family: inherit;"></textarea>
                                </div>

                                <!-- Row: City, State, Zip -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Date <span style="color: #ef4444">*</span></label>
                                        <input type="date" name="event_date" required style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    </div>

                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Time <span style="color: #ef4444">*</span></label>
                                        <input type="time" name="event_time" required style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    </div>

                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">State <span style="color: #ef4444">*</span></label>
                                        <select name="state" required style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                            <option value="">Select State</option>
                                            ${getNigerianStates(true).map(state => `<option value="${state}">${state}</option>`).join('')}
                                        </select>
                                    </div>
                                </div>

                                <!-- Contact & Price -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Primary Contact <span style="color: #ef4444">*</span></label>
                                        <input type="tel" name="phone_contact_1" required placeholder="+234..." 
                                               style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    </div>

                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Ticket Price (₦) <span style="color: #ef4444">*</span></label>
                                        <div style="display: flex; gap: 1rem; align-items: center;">
                                            <input type="number" name="price" id="priceInput" required placeholder="5000" min="0" step="0.01" 
                                                   style="flex: 1; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-weight: 600; color: #6b7280; background: white; padding: 0.75rem 1.25rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 2px solid #e5e7eb;">
                                                <input type="checkbox" id="freeEventCheckbox" style="width: 1.2rem; height: 1.2rem; accent-color: #8b5cf6;"> Free
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Fields -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Secondary Contact</label>
                                        <input type="tel" name="phone_contact_2" placeholder="+234... (optional)" 
                                               style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                    </div>

                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Priority Level</label>
                                        <select name="priority" id="prioritySelect" style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                            <option value="nearby">📍 Nearby</option>
                                            <option value="hot">🔥 Hot</option>
                                            <option value="trending">📈 Trending</option>
                                            <option value="featured">⭐ Featured</option>
                                            <option value="upcoming">🕒 Upcoming</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
                                    <select name="status" style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                        <option value="draft">Draft</option>
                                        <option value="scheduled">Schedule</option>
                                    </select>
                                </div>

                                <!-- Scheduled Time (Conditional) -->
                                <div class="form-group" id="scheduledTimeGroup" style="display: none; background: linear-gradient(135deg, #fef3c7, #fde68a); padding: 1.5rem; border-radius: 16px; border: 2px solid #fbbf24;">
                                    <label style="font-weight: 700; color: #92400e; margin-bottom: 0.75rem; display: block; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px;">Scheduled Publish Time</label>
                                    <input type="datetime-local" name="scheduled_publish_time" style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #fbbf24; border-radius: 12px; background: white; font-size: 1rem;">
                                    <div style="color: #b45309; margin-top: 0.75rem; font-size: 0.875rem; font-weight: 500;">Event will be automatically published at this time</div>
                                </div>

                                <!-- Auto-Generated Info -->
                                <div style="background: rgba(139, 92, 246, 0.05); padding: 2rem; border-radius: 16px; border: 2px solid rgba(139, 92, 246, 0.2);">
                                    <h4 style="margin: 0 0 1.25rem 0; font-weight: 800; color: #8b5cf6; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;">🔗 Auto-Generated Links</h4>
                                    <div style="display: grid; gap: 1.25rem;">
                                        <div>
                                            <label style="font-size: 0.8rem; font-weight: 700; color: #8b5cf6; margin-bottom: 0.5rem; display: block; text-transform: uppercase; letter-spacing: 0.5px;">Event Tag</label>
                                            <input type="text" id="eventTagField" name="tag" readonly placeholder="Enter event name first..." 
                                                   style="width: 100%; padding: 0.875rem 1.25rem; background: white; border: 2px solid rgba(139, 92, 246, 0.2); border-radius: 10px; font-family: 'Courier New', monospace; color: #8b5cf6; font-weight: 600; font-size: 0.95rem;">
                                        </div>

                                        <div>
                                            <label style="font-size: 0.8rem; font-weight: 700; color: #8b5cf6; margin-bottom: 0.5rem; display: block; text-transform: uppercase; letter-spacing: 0.5px;">Shareable Link</label>
                                            <input type="text" id="eventLinkField" name="external_link" readonly placeholder="Enter event name first..." 
                                                   style="width: 100%; padding: 0.875rem 1.25rem; background: white; border: 2px solid rgba(139, 92, 246, 0.2); border-radius: 10px; font-family: 'Courier New', monospace; color: #8b5cf6; font-weight: 600; font-size: 0.85rem;">
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div style="display: flex; gap: 1.25rem; margin-top: 1rem;">
                                    <button type="submit" class="btn btn-primary" style="flex: 2; padding: 1.25rem; font-size: 1.125rem; font-weight: 700; justify-content: center; background: linear-gradient(135deg, #8b5cf6, #4c1d95); border: none; border-radius: 14px; color: white; cursor: pointer; box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3); transition: all 0.3s;">
                                        Create Event ✨
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="closeCreateEventModal()" style="flex: 1; padding: 1.25rem; font-size: 1.125rem; justify-content: center; background: white; border: 2px solid #e5e7eb; border-radius: 14px; color: #6b7280; cursor: pointer; font-weight: 600; transition: all 0.3s;">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <style>
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(30px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            #createEventModal input:focus,
            #createEventModal select:focus,
            #createEventModal textarea:focus {
                outline: none;
                border-color: #8b5cf6 !important;
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2) !important;
            }

            #createEventModal input::placeholder,
            #createEventModal textarea::placeholder {
                color: #9ca3af;
            }

            #createEventModal .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 15px 35px rgba(26, 50, 158, 0.4);
            }

            #createEventModal .btn-secondary:hover {
                background: #f9fafb;
                border-color: #d1d5db;
            }

            #createEventModal .modal-close:hover {
                transform: rotate(90deg);
                background: #fee2e2;
                color: #dc2626;
            }

            #createEventModal label[for="eventImageInput"]:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 30px rgba(24, 55, 122, 0.4);
                background: rgba(255, 255, 255, 1);
            }
        </style>
    `;

    // Remove existing modal if any
    const existing = document.getElementById('createEventModal');
    if (existing) existing.remove();

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Inject Flyer Auto-Extract Button after the image input
    const imageInput = document.getElementById('eventImageInput');
    if (imageInput && imageInput.parentNode) {
        const flyerBtnHTML = '<div style="text-align:center; margin-top:0.75rem;"><button type="button" id="analyzeFlyer" onclick="autoExtractFromFlyer()" disabled style="background:linear-gradient(135deg,#7c3aed,#4c1d95);color:white;border:none;padding:0.65rem 1.75rem;border-radius:50px;font-size:0.875rem;font-weight:700;cursor:pointer;opacity:0.4;transition:all 0.3s;box-shadow:0 4px 12px rgba(124,58,237,0.3);pointer-events:none;">Auto-Extract Data from Flyer</button><div id="flyerExtractStatus" style="font-size:0.78rem;color:#6b7280;margin-top:0.4rem;min-height:1.2em;"></div></div>';
        imageInput.parentNode.insertAdjacentHTML('afterend', flyerBtnHTML);
    }

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.querySelector('input[name="event_date"]');
    if (dateInput) dateInput.setAttribute('min', today);

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
        
        // Ensure priority fields are visible regardless of status
    });
}

function closeCreateEventModal() {
    const modal = document.getElementById('createEventModal');
    if (modal) modal.remove();
}

function previewEventImage(event) {
    const file = event.target.files[0];
    const analyzeBtn = document.getElementById('analyzeFlyer');
    const status = document.getElementById('flyerExtractStatus');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('eventImagePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);

        if (analyzeBtn) {
            analyzeBtn.disabled = false;
            analyzeBtn.style.opacity = '1';
            analyzeBtn.style.pointerEvents = 'auto';
            if (status) status.textContent = 'Flyer ready. Click "Auto-Extract" to populate the form.';
        }
    } else {
        if (analyzeBtn) {
            analyzeBtn.disabled = true;
            analyzeBtn.style.opacity = '0.4';
            analyzeBtn.style.pointerEvents = 'none';
            if (status) status.textContent = '';
        }
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
    const user = storage.getUser();
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
    submitBtn.textContent = 'Creating... ⏳';
    submitBtn.disabled = true;
    
    try {
        const response = await apiFetch('../../api/events/create-event.php', {
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
window.autoExtractFromFlyer = autoExtractFromFlyer;

/**
 * Auto-Extract event data from the uploaded flyer image using OCR.
 * Calls extract-flyer.php and populates the Create Event form fields.
 */
async function autoExtractFromFlyer() {
    const imageInput = document.getElementById('eventImageInput');
    const statusEl = document.getElementById('flyerExtractStatus');
    const analyzeBtn = document.getElementById('analyzeFlyer');

    if (!imageInput || !imageInput.files[0]) {
        if (statusEl) statusEl.textContent = 'Please upload a flyer image first.';
        return;
    }

    // Loading state
    analyzeBtn.disabled = true;
    analyzeBtn.textContent = 'Analyzing...';
    if (statusEl) statusEl.textContent = 'Reading flyer with OCR, please wait...';

    try {
        const file = imageInput.files[0];
        
        // Step 1: Pre-process image for better OCR (Grayscale + Contrast)
        if (statusEl) statusEl.textContent = 'Enhancing image for better readability...';
        const processedImageBase64 = await preprocessFlyerImage(file);

        // Step 2: Client-side OCR with Tesseract.js
        if (typeof Tesseract === 'undefined') {
            throw new Error('Tesseract.js library is still loading. Please try again in a few seconds.');
        }

        const worker = await Tesseract.createWorker('eng');
        const { data: { text } } = await worker.recognize(processedImageBase64);
        await worker.terminate();

        console.log('[OCR Debug] Raw Extracted Text:', text);

        if (!text || text.trim().length < 5) {
            throw new Error('No readable text found on this flyer. Please ensure the image contains text and is well-lit.');
        }

        // Step 2: Send extracted text to backend for intelligent parsing
        const formData = new FormData();
        formData.append('extracted_text', text);

        const basePath = getBasePath ? getBasePath() : '../../';
        const response = await apiFetch(basePath + 'api/events/extract-flyer.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success && result.fields) {
            const f = result.fields;
            let filledCount = 0;

            // Helper to set a field if extracted value is non-empty
            const fill = (selector, value) => {
                if (value) {
                    const el = document.querySelector(selector);
                    if (el) { el.value = value; filledCount++; }
                }
            };

            fill('[name="event_name"]', f.event_name);
            fill('[name="event_date"]', f.event_date);
            fill('[name="event_time"]', f.event_time);
            fill('[name="address"]', f.address);
            fill('[name="price"]', f.price);
            fill('[name="phone_contact_1"]', f.phone);

            // State: match against select options
            if (f.state) {
                const stateSelect = document.querySelector('[name="state"]');
                if (stateSelect) {
                    const opt = Array.from(stateSelect.options).find(o =>
                        o.value.toLowerCase() === f.state.toLowerCase()
                    );
                    if (opt) { stateSelect.value = opt.value; filledCount++; }
                }
            }

            // Trigger event name → tag generation
            const eventNameInput = document.getElementById('eventNameInput');
            if (eventNameInput && f.event_name) {
                eventNameInput.value = f.event_name;
                if (typeof generateEventTagAndLink === 'function') generateEventTagAndLink();
            }

            if (statusEl) statusEl.innerHTML = filledCount > 0
                ? `<span style="color:#16a34a">&#10003; ${filledCount} field(s) filled! Review and adjust as needed.</span>`
                : `<span style="color:#d97706">Flyer analyzed but no clear event data was found. Please fill in the form manually.</span>`;
        } else {
            if (statusEl) statusEl.innerHTML = `<span style="color:#dc2626">Could not extract data: ${result.message || 'Unknown error'}. Please fill in the form manually.</span>`;
        }
    } catch (err) {
        console.error('Flyer extraction error:', err);
        if (statusEl) {
            statusEl.textContent = err.message;
            statusEl.style.color = '#ef4444';
        }
        
        // Make button "inaccessible" as requested for no-text / failure cases
        if (analyzeBtn) {
            analyzeBtn.disabled = true;
            analyzeBtn.style.opacity = '0.4';
            analyzeBtn.style.pointerEvents = 'none';
        }
    } finally {
        if (!analyzeBtn.disabled) {
            analyzeBtn.disabled = false;
            analyzeBtn.textContent = 'Auto-Extract Data from Flyer';
        }
    }
}

/**
 * Pre-processes a flyer image for better OCR accuracy.
 * Converts to grayscale and boosts contrast using HTML5 Canvas.
 */
async function preprocessFlyerImage(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Set dimensions
                canvas.width = img.width;
                canvas.height = img.height;
                
                // Draw original
                ctx.drawImage(img, 0, 0);
                
                // Apply Grayscale + Contrast Enhancement
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i];
                    const g = data[i+1];
                    const b = data[i+2];
                    
                    // Simple grayscale
                    let gray = 0.2126 * r + 0.7152 * g + 0.0722 * b;
                    
                    // Boost contrast (Thresholding-like effect)
                    // Push darks darker and brights brighter
                    gray = (gray > 128) ? Math.min(255, gray * 1.2) : gray * 0.8;
                    
                    data[i] = data[i+1] = data[i+2] = gray;
                }
                
                ctx.putImageData(imageData, 0, 0);
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = reject;
            img.src = e.target.result;
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}
