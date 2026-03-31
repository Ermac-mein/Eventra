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
        <link rel="stylesheet" href="../../public/css/time-picker.css">
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
                                        📷 Upload Banner <span style="color: #ef4444">*</span>
                                    </label>
                                    <input type="file" id="eventImageInput" name="event_image" accept="image/*" required style="display: none;" onchange="previewEventImage(event)">
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
                                            <option value="Business">Business</option>
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
                                    <div class="form-group" style="position: relative;">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Date <span style="color: #ef4444">*</span></label>
                                        <div style="position: relative;">
                                            <input type="text" id="customDateDisplay" readonly required placeholder="Select a date" 
                                                   style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04); cursor: pointer;"
                                                   onclick="openMaterialDatePicker()">
                                            <span style="position: absolute; right: 1rem; top: 1rem; color: #9ca3af; pointer-events: none; font-size: 1.25rem;">📅</span>
                                        </div>
                                        <input type="hidden" name="event_date" id="eventDateInput" required>
                                        
                                        <!-- Material Datepicker Dropdown -->
                                        <div id="materialDatePicker" class="material-datepicker">
                                            <div class="mdp-header">
                                                <div id="mdpYear" class="mdp-year">2026</div>
                                                <div id="mdpDateDisplay" class="mdp-date">Thu, Apr 16</div>
                                            </div>
                                            <div class="mdp-body">
                                                <div class="mdp-month-nav">
                                                    <button type="button" class="mdp-nav-btn" onclick="mdpChangeMonth(-1)">&#10094;</button>
                                                    <div id="mdpMonthYear">April 2026</div>
                                                    <button type="button" class="mdp-nav-btn" onclick="mdpChangeMonth(1)">&#10095;</button>
                                                </div>
                                                <div class="mdp-days-grid" id="mdpDaysGrid">
                                                    <!-- Days will be generated by JS -->
                                                </div>
                                            </div>
                                            <div class="mdp-footer">
                                                <button type="button" class="mdp-btn" onclick="closeMaterialDatePicker()">CANCEL</button>
                                                <button type="button" class="mdp-btn" onclick="confirmMaterialDatePicker()">OK</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Time <span style="color: #ef4444">*</span></label>
                                        <div id="eventTimePickerContainer" class="time-picker-container">
                                            <div class="time-picker-display" onclick="toggleTimePicker('eventTimePickerDropdown')">
                                                <span id="eventTimeDisplay">Select Time</span>
                                                <span style="font-size: 0.8rem; opacity: 0.5;">🕒</span>
                                            </div>
                                            <div id="eventTimePickerDropdown" class="time-picker-dropdown">
                                                <!-- Top Section: Hours -->
                                                <div class="time-picker-section">
                                                    <label class="time-picker-label">Hours</label>
                                                    <div class="time-picker-grid hours" id="hourGrid">
                                                        ${[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(h => `<button type="button" class="time-btn" onclick="selectHour('${h}', 'eventTimePickerContainer')">${h}</button>`).join('')}
                                                    </div>
                                                </div>
                                                <!-- Middle Section: Minutes -->
                                                <div class="time-picker-section">
                                                    <label class="time-picker-label">Minutes</label>
                                                    <div class="time-picker-grid minutes" id="minuteGrid">
                                                        ${['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'].map(m => `<button type="button" class="time-btn" onclick="selectMinute('${m}', 'eventTimePickerContainer')">${m}</button>`).join('')}
                                                    </div>
                                                </div>
                                                <!-- Bottom Section: Period -->
                                                <div class="time-picker-section">
                                                    <div class="time-picker-ampm">
                                                        <button type="button" class="time-btn ampm-btn" onclick="selectAmPm('am', 'eventTimePickerContainer')">am</button>
                                                        <button type="button" class="time-btn ampm-btn" onclick="selectAmPm('pm', 'eventTimePickerContainer')">pm</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="event_time" id="eventTimeInput" required>
                                        </div>
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

                                    <div class="form-group" id="priceInputGroup">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Event Type</label>
                                        <div style="display: flex; align-items: center; min-height: 52px;">
                                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-weight: 600; color: #6b7280; background: white; padding: 0.75rem 1.25rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 2px solid #e5e7eb;">
                                                <input type="checkbox" id="freeEventCheckbox" style="width: 1.2rem; height: 1.2rem; accent-color: #8b5cf6;"> FREE
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ticket Type Configuration -->
                                <div id="ticketTypeConfigSection" style="background: linear-gradient(135deg, #e0f2fe, #f0f9ff); padding: 2rem; border-radius: 16px; border: 2px solid #0ea5e9;">
                                    <h4 style="margin: 0 0 1.5rem 0; font-weight: 800; color: #0369a1; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;">💳 Ticket Type Configuration</h4>
                                    <div style="display: grid; gap: 1rem; margin-bottom: 1.5rem;">
                                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1rem; background: white; border-radius: 12px; border: 2px solid transparent; transition: all 0.3s;">
                                            <input type="radio" name="ticketTypeMode" value="regular-only" class="ticket-type-radio" style="width: 1.2rem; height: 1.2rem; accent-color: #0369a1; cursor: pointer;">
                                            <div>
                                                <div style="font-weight: 700; color: #1e293b;">Regular Only</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">Offer only standard tickets</div>
                                            </div>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1rem; background: white; border-radius: 12px; border: 2px solid transparent; transition: all 0.3s;">
                                            <input type="radio" name="ticketTypeMode" value="vip-only" class="ticket-type-radio" style="width: 1.2rem; height: 1.2rem; accent-color: #0369a1; cursor: pointer;">
                                            <div>
                                                <div style="font-weight: 700; color: #1e293b;">VIP Only</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">Offer only premium VIP tickets</div>
                                            </div>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1rem; background: white; border-radius: 12px; border: 2px solid transparent; transition: all 0.3s;">
                                            <input type="radio" name="ticketTypeMode" value="both" class="ticket-type-radio" style="width: 1.2rem; height: 1.2rem; accent-color: #0369a1; cursor: pointer;" checked>
                                            <div>
                                                <div style="font-weight: 700; color: #1e293b;">Both VIP & Regular</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">Offer both ticket types with different prices</div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Regular Price Section -->
                                    <div id="regularPriceSection" style="display: block; margin-bottom: 1.5rem;">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #0369a1; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Regular Ticket Price (₦)</label>
                                        <input type="number" name="regular_price" id="regularPriceInput" placeholder="5000" min="0" step="0.01" 
                                               style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #0ea5e9; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-weight: 600; color: #64748b; margin-top: 0.75rem;">
                                            <input type="number" name="regular_quantity" id="regularQuantityInput" placeholder="Unlimited" min="1" 
                                                   style="flex: 1; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;">
                                            <span style="white-space: nowrap;">Max tickets (optional)</span>
                                        </label>
                                    </div>

                                    <!-- VIP Price Section -->
                                    <div id="vipPriceSection" style="display: block;">
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #7c3aed; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">✨ VIP Ticket Price (₦)</label>
                                        <input type="number" name="vip_price" id="vipPriceInput" placeholder="10000" min="0" step="0.01" 
                                               style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #c4b5fd; border-radius: 12px; font-size: 1rem; background: #faf5ff; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-weight: 600; color: #64748b; margin-top: 0.75rem;">
                                            <input type="number" name="vip_quantity" id="vipQuantityInput" placeholder="Unlimited" min="1" 
                                                   style="flex: 1; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;">
                                            <span style="white-space: nowrap;">Max tickets (optional)</span>
                                        </label>
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
                                        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Priority Level <span style="color: #ef4444">*</span></label>
                                        <select name="priority" id="prioritySelect" required style="width: 100%; padding: 1rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background: white; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                            <option value="">Select Priority</option>
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

            .material-datepicker {
                position: absolute; top: calc(100% + 5px); left: 0; display: none; background: #fff; width: 320px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); font-family: 'Inter', sans-serif; z-index: 99999; overflow: hidden;
            }
            .material-datepicker.active { display: block; animation: slideDown 0.2s ease-out; }
            .mdp-header { background: #008080; color: white; padding: 20px; }
            .mdp-year { font-size: 1rem; font-weight: 600; opacity: 0.8; margin-bottom: 5px; }
            .mdp-date { font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
            .mdp-body { padding: 15px; background: white; }
            .mdp-month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-weight: 600; color: #374151; font-size: 1.1rem; }
            .mdp-nav-btn { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #6b7280; padding: 5px 10px; border-radius: 50%; transition: 0.2s; }
            .mdp-nav-btn:hover { background: #f3f4f6; color: #111827; }
            .mdp-days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; }
            .mdp-day-header { font-size: 0.75rem; font-weight: 600; color: #9ca3af; margin-bottom: 5px; text-transform: uppercase; }
            .mdp-day { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; cursor: pointer; font-size: 0.9rem; font-weight: 500; color: #374151; transition: 0.2s; margin: auto; }
            .mdp-day:hover:not(.disabled) { background: #f3f4f6; }
            .mdp-day.selected { background: #008080; color: white; box-shadow: 0 4px 12px rgba(0,128,128,0.3); }
            .mdp-day.disabled { color: #d1d5db; cursor: not-allowed; text-decoration: line-through; opacity: 0.5; }
            .mdp-footer { padding: 10px 20px 20px; display: flex; justify-content: flex-end; gap: 15px; background: white; }
            .mdp-btn { background: none; border: none; color: #008080; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s; padding: 8px 16px; border-radius: 8px; text-transform: uppercase; }
            .mdp-btn:hover { background: rgba(0,128,128,0.08); }
            @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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

    // Set minimum date to today is handled by the new Material DatePicker JS component
    // Close date picker when clicking outside
    document.addEventListener('click', (e) => {
        const dp = document.getElementById('materialDatePicker');
        const display = document.getElementById('customDateDisplay');
        if (dp && dp.classList.contains('active')) {
            if (!dp.contains(e.target) && e.target !== display) {
                closeMaterialDatePicker();
            }
        }
    });

    // Add form submit handler
    const createEventForm = document.getElementById('createEventForm');
    createEventForm.addEventListener('submit', handleEventCreation);

    // Add persistence: save on input
    createEventForm.addEventListener('input', () => saveFormState('createEventForm'));
    createEventForm.addEventListener('change', () => saveFormState('createEventForm'));

    // Restore saved state
    restoreFormState('createEventForm');

    // Sync time picker UI with restored value if it exists
    const restoredTime = document.getElementById('eventTimeInput')?.value;
    if (restoredTime && typeof setTimePickerValue === 'function') {
        setTimePickerValue('eventTimePickerContainer', restoredTime);
    }

    // Free Event Checkbox Handler
    const freeCheckbox = document.getElementById('freeEventCheckbox');
    const priceInput = document.getElementById('priceInput');
    const priceInputGroup = document.getElementById('priceInputGroup');
    const ticketConfig = document.getElementById('ticketTypeConfigSection');

    freeCheckbox.addEventListener('change', function() {
        if (this.checked) {
            // If free, hide ticket config and set hidden inputs to 0
            if (ticketConfig) ticketConfig.style.display = 'none';
            if (regularPriceInput) { regularPriceInput.value = 0; regularPriceInput.required = false; }
            if (vipPriceInput) { vipPriceInput.value = 0; vipPriceInput.required = false; }
        } else {
            // Restore visibility and requirements
            if (ticketConfig) ticketConfig.style.display = 'block';
            updateTicketTypeSections(); // Recalculate requirements
        }
    });

    // Add status change handler
    document.querySelector('select[name="status"]').addEventListener('change', function(e) {
        const scheduledGroup = document.getElementById('scheduledTimeGroup');
        scheduledGroup.style.display = e.target.value === 'scheduled' ? 'block' : 'none';
        
        // Ensure priority fields are visible regardless of status
    });

    // Ticket Type Mode Handler
    const ticketTypeRadios = document.querySelectorAll('.ticket-type-radio');
    const regularPriceSection = document.getElementById('regularPriceSection');
    const vipPriceSection = document.getElementById('vipPriceSection');
    const regularPriceInput = document.getElementById('regularPriceInput');
    const vipPriceInput = document.getElementById('vipPriceInput');

    function updateTicketTypeSections() {
        const selectedMode = document.querySelector('input[name="ticketTypeMode"]:checked')?.value || 'both';
        
        regularPriceSection.style.display = (selectedMode === 'regular-only' || selectedMode === 'both') ? 'block' : 'none';
        vipPriceSection.style.display = (selectedMode === 'vip-only' || selectedMode === 'both') ? 'block' : 'none';
        
        // Update required attribute
        regularPriceInput.required = (selectedMode === 'regular-only' || selectedMode === 'both');
        vipPriceInput.required = (selectedMode === 'vip-only' || selectedMode === 'both');
        
        // Update main price input if it were present, but since it's removed, we just ensure 
        // that regular/vip prices are correctly prioritized for the backend if needed 
        // (though backend handles them independently now).
    }

    ticketTypeRadios.forEach(radio => {
        radio.addEventListener('change', updateTicketTypeSections);
    });

    // Sync prices when regular/vip price inputs change
    regularPriceInput.addEventListener('change', updateTicketTypeSections);
    vipPriceInput.addEventListener('change', updateTicketTypeSections);

    // Initial update
    updateTicketTypeSections();
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
        const response = await apiFetch('/api/events/create-event.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Event created successfully!', 'success');
            
            // Clear saved form state
            clearFormState('createEventForm');
            
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
        const response = await apiFetch('/api/events/extract-flyer.php', {
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
                    if (el) { 
                        el.value = value; 
                        filledCount++;
                        
                        // Handle custom time picker sync
                        if (selector === '[name="event_time"]') {
                            setTimePickerValue('eventTimePickerContainer', value);
                        }
                    }
                }
            };

            fill('[name="event_name"]', f.event_name);
            fill('[name="event_date"]', f.event_date);
            fill('[name="event_time"]', f.event_time);
            fill('[name="address"]', f.address);
            fill('[name="price"]', f.price);
            fill('[name="phone_contact_1"]', f.phone);

            // Sync visual datepicker if populated
            if (f.event_date) {
                const dateObj = new Date(f.event_date);
                if (!isNaN(dateObj)) {
                    document.getElementById('customDateDisplay').value = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    // Globally accessible selected date for material design picker
                    if (window.mdpSelectedDate !== undefined) window.mdpSelectedDate = dateObj;
                }
            }

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
            if (statusEl) statusEl.innerHTML = `<span style="color:#dc2626">Could not extract data: ${escapeHTML(result.message || 'Unknown error')}. Please fill in the form manually.</span>`;
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

// Global Material DatePicker Logic
window.mdpCurrentDate = new Date();
window.mdpSelectedDate = null;
window.mdpToday = new Date();
window.mdpToday.setHours(0,0,0,0);

function openMaterialDatePicker() {
    document.getElementById('materialDatePicker').classList.add('active');
    if (!window.mdpSelectedDate) window.mdpSelectedDate = new Date();
    window.mdpCurrentDate = new Date(window.mdpSelectedDate);
    renderMaterialDatePicker();
}

function closeMaterialDatePicker() {
    document.getElementById('materialDatePicker').classList.remove('active');
}

function mdpChangeMonth(delta) {
    window.mdpCurrentDate.setMonth(window.mdpCurrentDate.getMonth() + delta);
    renderMaterialDatePicker();
}

function selectMdpDate(year, month, date) {
    const selected = new Date(year, month, date);
    if (selected < window.mdpToday) return; // Previous days not accessible
    window.mdpSelectedDate = selected;
    renderMaterialDatePicker();
}

function confirmMaterialDatePicker() {
    if (window.mdpSelectedDate) {
        // Format YYYY-MM-DD for input value
        const yyyy = window.mdpSelectedDate.getFullYear();
        const mm = String(window.mdpSelectedDate.getMonth() + 1).padStart(2, '0');
        const dd = String(window.mdpSelectedDate.getDate()).padStart(2, '0');
        
        document.getElementById('eventDateInput').value = `${yyyy}-${mm}-${dd}`;
        
        // Format display
        const displayOpts = { month: 'long', day: 'numeric', year: 'numeric' };
        document.getElementById('customDateDisplay').value = window.mdpSelectedDate.toLocaleDateString('en-US', displayOpts);
    }
    closeMaterialDatePicker();
}

function renderMaterialDatePicker() {
    const year = window.mdpCurrentDate.getFullYear();
    const month = window.mdpCurrentDate.getMonth();
    
    // Update header (if selected date exists, use it, else current)
    const refDate = window.mdpSelectedDate || window.mdpCurrentDate;
    document.getElementById('mdpYear').textContent = refDate.getFullYear();
    const shortDays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const shortMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('mdpDateDisplay').textContent = `${shortDays[refDate.getDay()]}, ${shortMonths[refDate.getMonth()]} ${refDate.getDate()}`;
    
    // Month Year display
    const longMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('mdpMonthYear').textContent = `${longMonths[month]} ${year}`;
    
    // generate grid
    const grid = document.getElementById('mdpDaysGrid');
    
    let html = '';
    ['S','M','T','W','T','F','S'].forEach(d => {
        html += `<div class="mdp-day-header">${d}</div>`;
    });
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    for(let i=0; i<firstDay; i++) {
        html += `<div></div>`;
    }
    
    for(let d=1; d<=daysInMonth; d++) {
        const dateObj = new Date(year, month, d);
        const isPast = dateObj < window.mdpToday;
        
        let classes = 'mdp-day';
        if (isPast) classes += ' disabled';
        
        if (window.mdpSelectedDate && window.mdpSelectedDate.getFullYear() === year && window.mdpSelectedDate.getMonth() === month && window.mdpSelectedDate.getDate() === d) {
            classes += ' selected';
        }
        
        if (isPast) {
            html += `<div class="${classes}">${d}</div>`;
        } else {
            html += `<div class="${classes}" onclick="selectMdpDate(${year}, ${month}, ${d})">${d}</div>`;
        }
    }
    
    grid.innerHTML = html;
}

window.openMaterialDatePicker = openMaterialDatePicker;
window.closeMaterialDatePicker = closeMaterialDatePicker;
window.mdpChangeMonth = mdpChangeMonth;
window.selectMdpDate = selectMdpDate;
window.confirmMaterialDatePicker = confirmMaterialDatePicker;
