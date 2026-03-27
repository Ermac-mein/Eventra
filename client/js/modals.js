/**
 * Client Modals JavaScript
 * Handles all modal functionality for client dashboard
 */

// Profile Edit Modal
function showProfileEditModal() {
    const user = storage.get('client_user') || storage.get('user');
    if (!user) return;

    const modalHTML = `
        <div id="profileEditModal" class="modal-backdrop active" role="dialog" aria-modal="true">
            <div class="modal-content modal-content-animate" style="max-width: 800px;">
                <div class="modal-header">
                    <h2>Edit Profile</h2>
                    <button class="modal-close" onclick="closeProfileEditModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="profileEditForm" enctype="multipart/form-data">
                        <!-- Profile Picture -->
                        <div class="profile-edit-avatar-container">
                                <div class="avatar-wrapper">
                                    <img id="profilePreview" class="profile-preview-img"
                                         src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random&size=160`}">
                                    ${getVerificationBadge(user.verification_status)}
                                    
                                    <label for="profilePicInput" class="avatar-upload-label">
                                        📷
                                    </label>
                                </div>
                                <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" style="display: none;" onchange="previewProfilePic(event)">
                        </div>

                        <!-- Personal Information Section -->
                        <h3 class="modal-form-section-title">Personal Information</h3>
                        
                        <div class="modal-grid">
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Client ID</label>
                                <input type="text" value="${escapeHTML(user.custom_id) || 'Generating...'}" readonly style="width: 100%; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 8px; background: #f8fafc; color: #7c3aed; font-weight: 700; font-family: monospace; letter-spacing: 1px;">
                            </div>

                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Full Name *</label>
                                <input type="text" name="name" value="${escapeHTML(user.name)}" required class="form-control">
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Email</label>
                                <input type="email" value="${escapeHTML(user.email)}" disabled class="form-control disabled">
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Phone</label>
                                <input type="tel" name="phone" value="${escapeHTML(user.phone) || ''}" placeholder="+234..." class="form-control" required>
                            </div>
                            
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                    <span>NIN (National Identity Number)</span>
                                    <div id="ninStatus" class="verification-status-indicator"></div>
                                </label>
                                <input type="text" id="ninInput" name="nin" value="${escapeHTML(user.nin) || ''}" placeholder="11-digit NIN" class="form-control" onblur="validateAndVerifyField('nin')" required>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Date of Birth</label>
                                <input type="date" name="dob" value="${escapeHTML(user.dob) || ''}" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Gender</label>
                                <select name="gender" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" ${user.gender === 'male' ? 'selected' : ''}>Male</option>
                                    <option value="female" ${user.gender === 'female' ? 'selected' : ''}>Female</option>
                                    <option value="other" ${user.gender === 'other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Address</label>
                                <textarea name="address" rows="2" placeholder="Full address" class="form-control" required>${escapeHTML(user.address) || ''}</textarea>
                            </div>
                            
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Job Title</label>
                                <input type="text" name="job_title" value="${escapeHTML(user.job_title) || ''}" placeholder="Event Organizer" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Company</label>
                                <input type="text" name="company" value="${escapeHTML(user.company) || ''}" placeholder="Company Name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">City</label>
                                <input type="text" name="city" value="${escapeHTML(user.city) || ''}" placeholder="Lagos" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">State</label>
                                <select name="state" class="form-control" required>
                                    <option value="">Select State</option>
                                    ${getNigerianStates().map(state => 
                                        `<option value="${state}" ${user.state === state ? 'selected' : ''}>${state}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Country</label>
                                <input type="text" name="country" value="${escapeHTML(user.country) || ''}" placeholder="Nigeria" class="form-control" required>
                            </div>
                        </div>

                        <!-- Payment Information Section -->
                        <h3 class="modal-form-section-title">Payment Information</h3>
                        
                        <div class="modal-grid">
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Settlement Bank</label>
                                <select id="bankSelect" name="bank_code" class="form-control" onchange="resolveAccount()" required>
                                    <option value="">Select Bank</option>
                                </select>
                                <input type="hidden" name="bank_name" id="bankNameInput" value="${escapeHTML(user.bank_name) || ''}">
                            </div>
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                    <span>Account Number (10 Digits)</span>
                                    <div id="accountStatus" class="verification-status-indicator">
                                        ${user.subaccount_code 
                                            ? '<span style="color:#10b981; font-weight: bold;" title="Verified Subaccount">✓ Verified</span>' 
                                            : '<span style="color:#f59e0b; font-weight: bold; font-size: 0.85rem;" title="Setup Incomplete">⚠️ Incomplete Setup</span>'}
                                    </div>
                                </label>
                                <input type="text" id="accountNumberInput" name="account_number" value="${(user.account_number && !/^[0]*$/.test(user.account_number)) ? escapeHTML(user.account_number) : ''}" maxlength="10" placeholder="10-digit Account Number" class="form-control" oninput="this.value = this.value.replace(/[^0-9]/g, '');" onblur="resolveAccount()" required>
                            </div>
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                    <span>BVN (11 Digits)</span>
                                    <div id="bvnStatus" class="verification-status-indicator"></div>
                                </label>
                                <input type="text" id="bvnInput" name="bvn" value="${escapeHTML(user.bvn) || ''}" maxlength="11" placeholder="11-digit BVN" class="form-control" oninput="this.value = this.value.replace(/[^0-9]/g, '');" onblur="validateAndVerifyField('bvn')" required>
                                <small style="display: block; margin-top: 5px; color: #64748b; font-size: 0.8rem; font-style: italic;">Note: Your BVN is for identity verification only.</small>
                            </div>
                            <div class="form-group modal-grid-full">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Account Holder Name (Auto-resolved)</label>
                                <input type="text" id="accountNameInput" name="account_name" value="${escapeHTML(user.account_name) || ''}" class="form-control" style="font-weight: 500;">
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                            <button type="button" class="btn btn-secondary" onclick="closeProfileEditModal()" style="flex: 1;">Cancel</button>
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

    // Populate Banks
    const bankSelect = document.getElementById('bankSelect');
    if (bankSelect && window.PaystackBanks) {
        window.PaystackBanks.populate(bankSelect, user.bank_code);
    }

    // Add form submit handler
    const profileEditForm = document.getElementById('profileEditForm');
    profileEditForm.addEventListener('submit', handleProfileUpdate);

    // Add persistence: save on input
    profileEditForm.addEventListener('input', () => saveFormState('profileEditForm'));
    profileEditForm.addEventListener('change', () => saveFormState('profileEditForm'));

    // Restore saved state
    restoreFormState('profileEditForm');

    // Initialize verification statuses
    if (user.nin_verified == 1) updateFieldStatus('nin', 'success');
    if (user.bvn_verified == 1) updateFieldStatus('bvn', 'success');
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
        const response = await apiFetch('/api/clients/update-profile.php', {
            method: 'POST',
            body: formData
        });

        const profileResult = await response.json();

        if (profileResult.success) {
            showNotification('Profile updated successfully!', 'success');
            
            // Clear saved form state
            clearFormState('profileEditForm');
            
            // Update stored user data
            storage.set('client_user', profileResult.user);
            storage.set('user', profileResult.user); // Sync both
            
            // Close modal
            closeProfileEditModal();
            
            // Reload page to reflect changes
            if (window.loadDashboardStats) {
                window.loadDashboardStats(profileResult.user.id);
            }
            
            // Update sidebar profile if exists
            if (document.getElementById('sidebarUserName')) {
                document.getElementById('sidebarUserName').textContent = profileResult.user.name;
            }
            
            setTimeout(() => window.location.reload(), 1000);
        } else {
            console.warn('Failed to update profile:', profileResult.message);
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        // User requested notifications and indicators only on success
    }
}

// Real-time Account Resolution
async function resolveAccount() {
    const bankCode = document.getElementById('bankSelect').value;
    const accountNumber = document.getElementById('accountNumberInput').value.trim();
    const statusDiv = document.getElementById('accountStatus');
    const nameInput = document.getElementById('accountNameInput');
    const bankNameInput = document.getElementById('bankNameInput');

    if (bankCode) {
        const selectedOption = document.getElementById('bankSelect').options[document.getElementById('bankSelect').selectedIndex];
        bankNameInput.value = selectedOption.text;
    }

    if (!bankCode || accountNumber.length !== 10) {
        statusDiv.innerHTML = '';
        nameInput.value = '';
        return;
    }

    // Show Loading
    statusDiv.innerHTML = '<span class="spinner" style="width: 16px; height: 16px; border: 2px solid #8b5cf6; border-top-color: transparent; border-radius: 50%; display: inline-block; animation: spin 0.8s linear infinite;"></span>';

    try {
        const response = await apiFetch(`/api/clients/bank-details.php?bank_code=${bankCode}&account_number=${accountNumber}`, {
            method: 'GET'
        });
        const result = await response.json();

        if (result.success) {
            statusDiv.innerHTML = '<span style="color:#10b981; font-weight: bold;">✓ Verified</span>';
            nameInput.value = escapeHTML(result.account_name);
        } else {
            statusDiv.innerHTML = '<span style="color:#ef4444; font-weight: bold;">✕ Invalid</span>';
            nameInput.value = 'Resolution Failed';
        }
    } catch (error) {
        console.error('Error resolving account:', error);
        statusDiv.innerHTML = '<span style="color:#ef4444;">✕ Error</span>';
    }
}

// Dynamic Field Verification Logic
async function validateAndVerifyField(type) {
    const input = document.getElementById(`${type}Input`);
    const statusDiv = document.getElementById(`${type}Status`);
    if (!input || !statusDiv) return;

    const value = input.value.trim();
    if (!value) {
        statusDiv.innerHTML = ''; // Hide if empty
        return;
    }

    // Show Spinner
    updateFieldStatus(type, 'loading');

    try {
        const response = await apiFetch('/api/clients/verify-identity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, number: value })
        });

        const result = await response.json();

        if (result.success) {
            updateFieldStatus(type, 'success');
            showNotification(`${type.toUpperCase()} verified successfully!`, 'success');
            
            // Update local user object for preview
            const user = storage.get('client_user') || storage.get('user');
            if (user) {
                user[`${type}_verified`] = 1;
                user[type] = value;
                storage.set('client_user', user);
                updateVerificationBadge();
            }
            
            // Sync hidden form input
            const hiddenStatus = document.getElementById(`${type}VerifiedInput`);
            if (hiddenStatus) hiddenStatus.value = 1;
        } else {
            const errorMsg = result.message || `Invalid ${type.toUpperCase()}`;
            updateFieldStatus(type, 'error', escapeHTML(errorMsg));
            // User requested notifications only on success
            
            const user = storage.get('client_user') || storage.get('user');
            if (user) {
                user[`${type}_verified`] = 0;
                storage.set('client_user', user);
                updateVerificationBadge();
            }

            // Sync hidden form input
            const hiddenStatus = document.getElementById(`${type}VerifiedInput`);
            if (hiddenStatus) hiddenStatus.value = 0;
        }
    } catch (error) {
        console.error(`Error verifying ${type}:`, error);
        updateFieldStatus(type, 'error', 'Connection error');
    }
}

function updateFieldStatus(type, status, message = '') {
    const statusDiv = document.getElementById(`${type}Status`);
    if (!statusDiv) return;

    if (status === 'loading') {
        statusDiv.innerHTML = '<span class="spinner" style="width: 16px; height: 16px; border: 2px solid #3b82f6; border-top-color: transparent; border-radius: 50%; display: inline-block; animation: spin 0.8s linear infinite;"></span>';
    } else if (status === 'success') {
        statusDiv.innerHTML = '<span style="color:#10b981; font-size: 1.1rem; font-weight: bold;" title="Verified">✓</span>';
    } else if (status === 'error') {
        statusDiv.innerHTML = `<span style="color:#ef4444; font-size: 1.1rem; font-weight: bold; cursor: help;" title="${escapeHTML(message)}">✕</span>`;
    }
}

function updateVerificationBadge() {
    const user = storage.get('client_user') || storage.get('user');
    const container = document.querySelector('.avatar-wrapper');
    if (!container || !user) return;

    // Replace existing badge
    const oldBadge = container.querySelector('.verification-badge');
    if (oldBadge) oldBadge.remove();
    
    container.insertAdjacentHTML('beforeend', getVerificationBadge(user.verification_status));
    
    // Re-initialize icons if using Lucide
    if (window.lucide) window.lucide.createIcons();
}

// Add CSS for spin animation
if (!document.getElementById('modal-animations')) {
    const style = document.createElement('style');
    style.id = 'modal-animations';
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);
}

// Event Preview Modal
function showEventPreviewModal(eventId) {
    // Show loading
    const loadingHTML = `
        <div id="eventPreviewModal" class="modal-backdrop active" role="dialog" aria-modal="true" aria-hidden="false">
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
        const response = await apiFetch(`/api/events/get-event-details.php?event_id=${eventId}`);
        const result = await response.json();

        if (result.success && result.event) {
            displayEventPreview(result.event);
        } else {
            showNotification(result.message || 'Event not found', 'error');
            closeEventPreviewModal();
        }
    } catch (error) {
        console.error('Error fetching event:', error);
        showNotification('Failed to load event details', 'error');
        closeEventPreviewModal();
    }
}

function displayEventPreview(event) {
    const eventImage = event.image_path || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
    const status = event.status || 'draft';
    const price = parseFloat(event.price) === 0 ? 'Free' : `₦${parseFloat(event.price).toLocaleString()}`;
    const date = new Date(event.event_date).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    const time = event.event_time ? event.event_time.substring(0, 5) : '--:--';
    
    // Get client name for sharing
    const user = storage.get('client_user') || storage.get('user') || {};
    const shareLink = `${window.location.origin}/public/pages/event-details.html?event=${event.tag}&client=${clientNameSlug}`;

    const modalContent = `
        <div id="eventPreviewModal" class="modal-backdrop active" role="dialog" aria-modal="true">
            <div class="modal-content modal-content-animate" style="max-width: 800px; padding: 0; overflow: hidden;">
                <div class="event-preview">
                    <!-- Close Button -->
                    <button onclick="closeEventPreviewModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; background: rgba(255,255,255,0.2); border: none; width: 40px; height: 40px; border-radius: 50%; color: white; font-size: 1.5rem; cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);">&times;</button>
                    
                    <div class="event-preview-hero">
                        <img src="${eventImage.startsWith('http') ? eventImage : (eventImage.startsWith('/') ? '../..' + eventImage : '../../' + eventImage)}" alt="Event">
                        <div class="event-status-badge" style="background: ${getStatusBadgeColor(status.toLowerCase())};">
                            ${status}
                        </div>
                    </div>
                    
                    <div class="event-preview-content" style="background: white;">
                        <div style="margin-bottom: 2.5rem;">
                            <h1 class="event-preview-title">${escapeHTML(event.event_name)}</h1>
                            <p style="color: #6b7280; font-size: 1.1rem;">Organized by ${escapeHTML(user.name) || 'Eventra'}</p>
                        </div>

                        <div class="event-info-grid">
                            <div class="event-info-item">
                                <div class="event-info-icon" style="background: #eef2ff;">📅</div>
                                <div>
                                    <div class="event-info-label">Date</div>
                                    <div class="event-info-value">${date}</div>
                                </div>
                            </div>
                            <div class="event-info-item">
                                <div class="event-info-icon" style="background: #fff7ed;">🕒</div>
                                <div>
                                    <div class="event-info-label">Time</div>
                                    <div class="event-info-value">${time}</div>
                                </div>
                            </div>
                            <div class="event-info-item">
                                <div class="event-info-icon" style="background: #f0fdf4;">💰</div>
                                <div>
                                    <div class="event-info-label">Price</div>
                                    <div class="event-info-value">${price}</div>
                                </div>
                            </div>
                            <div class="event-info-item">
                                <div class="event-info-icon" style="background: #fdf2f8;">📂</div>
                                <div>
                                    <div class="event-info-label">Category</div>
                                    <div class="event-info-value">${escapeHTML(event.category || event.event_type) || 'General'}</div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 2.5rem;">
                            <label style="display: block; font-size: 0.9rem; color: #111827; margin-bottom: 1rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">📍 Venue & Location</label>
                            <div style="background: #f9fafb; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e7eb; color: #4b5563; font-weight: 500; line-height: 1.5;">
                                ${escapeHTML(event.address) || 'No address provided'}
                                ${event.state ? `<br><span style="color: #111827; font-weight: 700;">${escapeHTML(event.state)}</span>` : ''}
                            </div>
                        </div>

                        <div style="margin-bottom: 2.5rem;">
                            <label style="display: block; font-size: 0.9rem; color: #111827; margin-bottom: 1rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">📝 Event Description</label>
                            <div style="color: #4b5563; line-height: 1.7; white-space: pre-wrap; background: #f9fafb; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e7eb; font-size: 1.05rem;">
                                ${escapeHTML(event.description) || 'No description available'}
                            </div>
                        </div>

                        <div style="margin-bottom: 2.5rem;">
                            <label style="display: block; font-size: 0.9rem; color: #111827; margin-bottom: 1rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">👥 Audience</label>
                            <div style="display: flex; align-items: center; gap: 15px; background: #f9fafb; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e7eb;">
                                <div style="display: flex;">
                                    ${[...Array(Math.min(parseInt(event.attendee_count) || 0, 5))].map((_, i) => `
                                        <img src="https://ui-avatars.com/api/?name=User+${i}&background=random" 
                                             style="width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; margin-left: ${i === 0 ? '0' : '-12px'}; transition: transform 0.2s;">
                                    `).join('')}
                                    ${(parseInt(event.attendee_count) || 0) > 5 ? `<div style="width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; margin-left: -12px; background: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">+${parseInt(event.attendee_count) - 5}</div>` : ''}
                                </div>
                                <span style="font-size: 1.1rem; color: #111827; font-weight: 700;">${parseInt(event.attendee_count) || 0} people attending</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 3rem; padding-top: 2.5rem; border-top: 2px solid #f3f4f6;">
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-size: 0.9rem; color: #111827; margin-bottom: 1rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">🔗 Events Tag</label>
                                <div style="display: flex; gap: 0.75rem; align-items: center;">
                                    <code style="background: #f3f4f6; padding: 0.85rem 1.25rem; border-radius: 12px; border: 1px solid #e5e7eb; font-family: 'JetBrains Mono', monospace; font-size: 1rem; flex: 1; color: #111827; font-weight: 700;">${escapeHTML(event.tag)}</code>
                                    <button onclick="navigator.clipboard.writeText('${escapeHTML(event.tag)}').then(() => showNotification('Tag copied!', 'success'))" style="background: white; border: 1px solid #d1d5db; width: 48px; height: 48px; border-radius: 12px; cursor: pointer; transition: all 0.2s; font-size: 1.25rem; display: flex; align-items: center; justify-content: center;" title="Copy Tag">📋</button>
                                </div>
                            </div>
                            <div style="margin-bottom: 2.5rem;">
                                <label style="display: block; font-size: 0.9rem; color: #111827; margin-bottom: 1rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">🚀 Shareable Link</label>
                                <div style="display: flex; gap: 0.75rem; align-items: center;">
                                    <input type="text" readonly value="${escapeHTML(shareLink)}" 
                                           style="background: #f3f4f6; padding: 0.85rem 1.25rem; border-radius: 12px; border: 1px solid #e5e7eb; font-family: inherit; font-size: 1rem; flex: 1; color: #111827; font-weight: 600;">
                                    <button onclick="navigator.clipboard.writeText('${escapeHTML(shareLink)}').then(() => showNotification('Link copied!', 'success'))" style="background: #4F46E5; color: white; border: none; padding: 0.85rem 1.75rem; border-radius: 12px; cursor: pointer; transition: all 0.2s; font-size: 1rem; font-weight: 700; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);">Copy Link</button>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem;">
                                <button onclick="editEvent(${event.id})" class="btn" style="flex: 1; background: white; border: 2px solid #e5e7eb; color: #374151; padding: 1.1rem; border-radius: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 1rem;">
                                    ✏️ Edit Event
                                </button>
                                ${status.toLowerCase() !== 'published' ? `
                                    <button onclick="publishEvent(${event.id})" class="btn" style="flex: 2; background: #10b981; color: white; border: none; padding: 1.1rem; border-radius: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 1rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">
                                        ✓ Publish Now
                                    </button>
                                ` : `
                                    <button onclick="window.open('${shareLink}', '_blank')" class="btn" style="flex: 2; background: #4f46e5; color: white; border: none; padding: 1.1rem; border-radius: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 1rem; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);">
                                        👁️ View Public Page
                                    </button>
                                `}
                            </div>
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

    // Animate in
    setTimeout(() => {
        const modal = document.getElementById('eventPreviewModal');
        if (modal) {
            modal.querySelector('.modal-content').style.transform = 'translateY(0)';
        }
    }, 10);
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
function getNigerianStates(includeGlobal = false) {
    const states = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
        'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
        'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
        'Yobe', 'Zamfara'
    ];
    if (includeGlobal) {
        states.unshift('All States');
    }
    return states;
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
        const response = await apiFetch('/api/events/publish-event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId })
        });

        const publishResult = await response.json();

        if (publishResult.success) {
            showNotification('Event published successfully!', 'success');
            closeEventActionModal();
            // Trigger dashboard stat update if on dashboard
            if (window.loadDashboardStats) {
                const user = storage.get('client_user') || storage.get('user');
                window.loadDashboardStats(user ? user.id : null);
            }
            
            // Reload page to reflect changes
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Failed to publish event: ' + publishResult.message, 'error');
        }
    } catch (error) {
        console.error('Error publishing event:', error);
        showNotification('An error occurred while publishing event', 'error');
    }
}

// Edit Event Modal
function showEditEventModal(event) {
    const modalHTML = `
        <link rel="stylesheet" href="../../public/css/time-picker.css">
        <div id="editEventModal" class="modal-backdrop active" role="dialog" aria-modal="true" aria-hidden="false">
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
                                     src="${event.image_path ? (event.image_path.startsWith('/') ? '../..' + event.image_path : '../../' + event.image_path) : ''}" 
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
                                <input type="text" name="event_name" value="${escapeHTML(event.event_name)}" required>
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
                                    <option value="Social" ${event.event_type === 'Social' ? 'selected' : ''}>Social</option>
                                    <option value="Personal" ${event.event_type === 'Personal' ? 'selected' : ''}>Personal</option>
                                    <option value="Community" ${event.event_type === 'Community' ? 'selected' : ''}>Community</option>
                                    <option value="Religious" ${event.event_type === 'Religious' ? 'selected' : ''}>Religious</option>
                                    <option value="Cultural" ${event.event_type === 'Cultural' ? 'selected' : ''}>Cultural</option>
                                    <option value="Educational" ${event.event_type === 'Educational' ? 'selected' : ''}>Educational</option>
                                    <option value="Business" ${event.event_type === 'Business' ? 'selected' : ''}>Business</option>
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
                                <div id="editEventTimePickerContainer" class="time-picker-container">
                                    <div class="time-picker-display" onclick="toggleTimePicker('editEventTimePickerDropdown')">
                                        <span id="editEventTimeDisplay">${event.event_time ? event.event_time.substring(0, 5) : 'Select Time'}</span>
                                        <span style="font-size: 0.8rem; opacity: 0.5;">🕒</span>
                                    </div>
                                    <div id="editEventTimePickerDropdown" class="time-picker-dropdown">
                                        <!-- Top Section: Hours -->
                                        <div class="time-picker-section">
                                            <label class="time-picker-label">Hours</label>
                                            <div class="time-picker-grid hours" id="editHourGrid">
                                                ${[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(h => `<button type="button" class="time-btn" onclick="selectHour('${h}', 'editEventTimePickerContainer')">${h}</button>`).join('')}
                                            </div>
                                        </div>
                                        <!-- Middle Section: Minutes -->
                                        <div class="time-picker-section">
                                            <label class="time-picker-label">Minutes</label>
                                            <div class="time-picker-grid minutes" id="editMinuteGrid">
                                                ${['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'].map(m => `<button type="button" class="time-btn" onclick="selectMinute('${m}', 'editEventTimePickerContainer')">${m}</button>`).join('')}
                                            </div>
                                        </div>
                                        <!-- Bottom Section: Period -->
                                        <div class="time-picker-section">
                                            <div class="time-picker-ampm">
                                                <button type="button" class="time-btn ampm-btn" onclick="selectAmPm('am', 'editEventTimePickerContainer')">am</button>
                                                <button type="button" class="time-btn ampm-btn" onclick="selectAmPm('pm', 'editEventTimePickerContainer')">pm</button>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="event_time" id="editEventTimeInput" value="${event.event_time || ''}" required>
                                </div>
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
                                    ${getNigerianStates(true).map(state => 
                                        `<option value="${escapeHTML(state)}" ${event.state === state ? 'selected' : ''}>${escapeHTML(state)}</option>`
                                    ).join('')}
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Venue Address *</label>
                            <textarea name="address" rows="2" required>${escapeHTML(event.address)}</textarea>
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

    // Add persistence
    const editEventForm = document.getElementById('editEventForm');
    editEventForm.addEventListener('input', () => saveFormState('editEventForm'));
    editEventForm.addEventListener('change', () => saveFormState('editEventForm'));

    // Restore saved state
    restoreFormState('editEventForm');

    // Add submit handler
    editEventForm.addEventListener('submit', handleEventUpdate);

    // Initialize Time Picker highlights if time exists
    if (event.event_time) {
        if (typeof setTimePickerValue === 'function') {
            setTimePickerValue('editEventTimePickerContainer', event.event_time);
        }
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
        const response = await apiFetch('/api/events/update-event.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Event updated successfully!', 'success');
            
            // Clear saved form state
            clearFormState('editEventForm');
            
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
    const imgSrc = ticket.event_image
        ? (ticket.event_image.startsWith('http') ? ticket.event_image : '../../' + ticket.event_image)
        : null;
    const heroBg = imgSrc ? `url(${imgSrc})` : 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
    const price = parseFloat(ticket.price || ticket.total_price || 0) === 0
        ? 'Free'
        : `₦${parseFloat(ticket.price || ticket.total_price || 0).toLocaleString()}`;
    const statusColor = (ticket.status === 'confirmed' || ticket.status === 'paid' || ticket.status === 'active') ? '#10b981' : '#ef4444';

    const modalContent = `
        <div id="ticketPreviewModal" class="modal-backdrop active" role="dialog" aria-modal="true" aria-hidden="false">
            <div class="modal-content" style="max-width: 520px; padding:0; border-radius:20px; overflow:hidden;">
                <!-- Event Image Hero -->
                <div style="height:180px; background:${heroBg}; background-size:cover; background-position:center; position:relative;">
                    <button class="modal-close" onclick="closeTicketPreviewModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(0,0,0,.4);border:none;color:white;width:34px;height:34px;border-radius:50%;font-size:1.3rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
                    <div style="position:absolute;bottom:1rem;left:1.5rem;">
                        <div style="font-size:.68rem;font-weight:700;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Event</div>
                        <div style="font-size:1.2rem;font-weight:800;color:white;text-shadow:0 2px 8px rgba(0,0,0,.45);">${ticket.event_name || 'N/A'}</div>
                    </div>
                </div>
                <!-- Details -->
                <div style="padding:1.5rem; display:grid; gap:1rem;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <div style="font-size:.78rem; color:#666; margin-bottom:.2rem;">🎫 Ticket ID</div>
                            <div style="font-weight:600;">${ticket.id}</div>
                        </div>
                        <div>
                            <div style="font-size:.78rem; color:#666; margin-bottom:.2rem;">👤 Buyer</div>
                            <div style="font-weight:600;">${ticket.buyer_name || ticket.user_name || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="font-size:.78rem; color:#666; margin-bottom:.2rem;">💰 Price</div>
                            <div style="font-weight:600;">${price}</div>
                        </div>
                        <div>
                            <div style="font-size:.78rem; color:#666; margin-bottom:.2rem;">📆 Purchase Date</div>
                            <div style="font-weight:600;">${ticket.purchase_date || ticket.created_at || 'N/A'}</div>
                        </div>
                        <div style="grid-column:1/-1;">
                            <div style="font-size:.78rem; color:#666; margin-bottom:.2rem;">📊 Status</div>
                            <div style="font-weight:700; color:${statusColor};">${ticket.status ? ticket.status.toUpperCase() : 'N/A'}</div>
                        </div>
                    </div>
                    <button onclick="closeTicketPreviewModal()" class="btn btn-secondary" style="width:100%; margin-top:.5rem;">
                        Close
                    </button>
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
    const hasValidUrl = user.profile_pic && user.profile_pic.startsWith('http');
    const profileImage = user.profile_pic 
        ? (hasValidUrl ? user.profile_pic : `../../${user.profile_pic}`)
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=random&size=150`;

    const modalContent = `
        <div id="userPreviewModal" class="modal-backdrop active" role="dialog" aria-modal="true" aria-hidden="false">
            <div class="modal-content" style="max-width: 800px; border-radius: 16px; overflow: hidden; padding: 0;">
                <div class="modal-header" style="background: var(--client-bg-body); padding: 1.5rem 2rem; border-bottom: 1px solid var(--client-border);">
                    <h2 style="margin: 0; font-size: 1.25rem;">User Details</h2>
                    <button class="modal-close" onclick="closeUserPreviewModal()" style="font-size: 1.5rem;">×</button>
                </div>
                <div class="modal-body" style="padding: 2.5rem 2rem;">
                    <div style="display: flex; gap: 2.5rem; flex-wrap: wrap;">
                        <div style="text-align: center; flex: 0 0 160px;">
                            <img src="${profileImage}" style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid var(--client-primary); box-shadow: 0 8px 16px rgba(0,0,0,0.1);">
                            <div style="margin-top: 1rem; font-weight: 800; font-size: 1.25rem; color: var(--client-text-main);">${user.name || 'N/A'}</div>
                            <div style="font-size: 0.9rem; color: var(--client-text-muted); font-weight: 500;">${user.email || 'N/A'}</div>
                        </div>
                        
                        <div style="flex: 1; min-width: 300px; display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem 2rem;">
                            <!-- Column 1 -->
                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Phone</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.phone || 'N/A'}</div>
                            </div>
                            
                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">State / Province</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.state || 'N/A'}</div>
                            </div>

                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">City</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.city || 'N/A'}</div>
                            </div>
                            
                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Country</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.country || 'N/A'}</div>
                            </div>

                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Gender</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem; text-transform: capitalize;">${user.gender || 'N/A'}</div>
                            </div>
                            
                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Date of Birth</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.dob ? formatDate(user.dob) : 'N/A'}</div>
                            </div>

                            <div style="grid-column: 1 / -1; height: 1px; background: var(--client-border); margin: 0.5rem 0;"></div>

                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Organiser</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.client_name || 'Direct'}</div>
                            </div>

                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Date Joined</div>
                                <div style="font-weight: 600; color: var(--client-text-main); font-size: 1rem;">${user.created_at ? formatDate(user.created_at) : 'N/A'}</div>
                            </div>

                            <div>
                                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--client-text-muted); font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.5px;">Status</div>
                                <div style="font-weight: 700; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; display: inline-block; ${(user.status === 'active' || user.status === 1 || user.status === '1') ? 'background: #d1fae5; color: #10b981;' : 'background: #fee2e2; color: #ef4444;'}">
                                    ${(user.status === 'active' || user.status === 1 || user.status === '1') ? 'Active' : 'Inactive'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 1.5rem 2rem; background: #f9fafb; border-top: 1px solid var(--client-border); display: flex; justify-content: flex-end;">
                    <button onclick="closeUserPreviewModal()" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        Close
                    </button>
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

// Removed local showNotification to use global one from utils.js

// Make functions globally available
window.showProfileEditModal = showProfileEditModal;
window.closeProfileEditModal = closeProfileEditModal;
window.previewProfilePic = previewProfilePic;
window.showEventPreviewModal = showEventPreviewModal;
window.closeEventPreviewModal = closeEventPreviewModal;
window.shareEvent = shareEvent;
window.publishEvent = publishEvent;
window.showEditEventModal = showEditEventModal;
window.closeEditEventModal = closeEditEventModal;
window.previewEditEventImage = previewEditEventImage;
window.showTicketPreviewModal = showTicketPreviewModal;
window.closeTicketPreviewModal = closeTicketPreviewModal;
window.showUserPreviewModal = showUserPreviewModal;
window.closeUserPreviewModal = closeUserPreviewModal;
