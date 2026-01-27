document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initDrawers();
    initExportModal();
    initPreviews();
    initMediaModals();
    initManagementInteractions();
});

function initNavigation() {
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.sidebar-menu .menu-item a');
    
    menuLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        if (currentPath.includes(linkPath)) {
            link.parentElement.classList.add('active');
        }
    });
}

function initDrawers() {
    if (!document.querySelector('.drawer-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.className = 'drawer-backdrop';
        document.body.appendChild(backdrop);
    }
    const backdrop = document.querySelector('.drawer-backdrop');

    // Inject Admin-style Drawers
    if (!document.getElementById('notificationsDrawer')) {
        const drawersHTML = `
            <div class="drawer" id="notificationsDrawer">
                <div class="drawer-header"><span class="back-arrow">←</span><h2>Notifications</h2></div>
                <div class="drawer-content" style="padding: 1rem;">
                    <div style="padding: 1rem; background: #f8fafc; border-radius: 12px; margin-bottom: 15px; border: 1px solid var(--client-border);">
                        <p style="font-size: 0.85rem; line-height: 1.4;">New ticket purchase by Jane Roberts for "Music Festival".</p>
                        <small style="color: var(--client-text-muted);">2 mins ago</small>
                    </div>
                </div>
            </div>

            <div class="drawer" id="settingsDrawer">
                <div class="drawer-header"><span class="back-arrow">←</span><h2>Settings</h2></div>
                <div class="drawer-content" style="padding: 1.5rem;">
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="margin-bottom: 10px; font-size: 0.95rem;">Theme</h4>
                        <button class="export-option-v2">🌙 Dark Mode (Beta)</button>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 10px; font-size: 0.95rem;">Preferences</h4>
                        <button class="export-option-v2">🔔 Email Alerts</button>
                    </div>
                </div>
            </div>

            <div class="drawer" id="profileDrawer">
                <div class="drawer-header">
                    <span class="back-arrow">←</span>
                    <h2 style="text-align: left; flex: none;">Client Profile</h2>
                </div>
                <div class="drawer-content">
                    <div style="width: 100%; height: 120px; background: url('https://images.unsplash.com/photo-1557683316-973673baf926?w=800&fit=crop') center/cover; border-radius: 0 0 15px 15px;"></div>
                    <div style="margin: -50px auto 0; width: 100px; height: 100px; position: relative;">
                        <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400&fit=crop" style="width: 100%; height: 100%; border-radius: 50%; border: 4px solid white; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    </div>
                    <div style="text-align: center; padding: 1rem 1.5rem;">
                        <h3 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 5px;">Odinson Liam</h3>
                        <p style="color: var(--client-text-muted); font-size: 0.9rem;">odinsonliam43@example.com</p>
                    </div>
                    <div style="padding: 0 1.5rem 2rem;">
                        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                            <span style="color: var(--client-text-muted); font-size: 0.85rem;">Phone</span>
                            <span style="font-weight: 600; font-size: 0.9rem;">+2348190987654</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                            <span style="color: var(--client-text-muted); font-size: 0.85rem;">Job Title</span>
                            <span style="font-weight: 600; font-size: 0.9rem;">Lawyer</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', drawersHTML);
    }

    const triggers = {
        'notifications': document.querySelector('[data-drawer="notifications"]'),
        'settings': document.querySelector('[data-drawer="settings"]'),
        'profile': document.querySelector('.user-profile')
    };

    const drawers = {
        'notifications': document.getElementById('notificationsDrawer'),
        'settings': document.getElementById('settingsDrawer'),
        'profile': document.getElementById('profileDrawer')
    };

    function openDrawer(id) {
        const drawer = drawers[id];
        if (!drawer) return;
        backdrop.style.display = 'block';
        setTimeout(() => drawer.classList.add('open'), 10);
    }

    function closeAll() {
        Object.values(drawers).forEach(d => d && d.classList.remove('open'));
        setTimeout(() => backdrop.style.display = 'none', 400);
    }

    if (triggers.notifications) triggers.notifications.onclick = () => openDrawer('notifications');
    if (triggers.settings) triggers.settings.onclick = () => openDrawer('settings');
    if (triggers.profile) triggers.profile.onclick = () => openDrawer('profile');

    document.querySelectorAll('.back-arrow').forEach(arrow => {
        arrow.onclick = closeAll;
    });

    backdrop.onclick = closeAll;
}

function initExportModal() {
    const exportBtn = document.querySelector('.btn-export');
    
    // Inject Admin-style Export Modal
    if (!document.getElementById('exportModalV2')) {
        const modalHTML = `
            <div class="modal-backdrop-v2" id="exportModalV2">
                <div class="modal-v2">
                    <h2 style="font-weight: 800; font-size: 1.5rem; margin-bottom: 0.5rem;">Export Options</h2>
                    <p style="color: var(--client-text-muted); margin-bottom: 1.5rem;">Choose your preferred file format.</p>
                    <div class="export-options-v2">
                        <button class="export-option-v2" data-format="General File">📄 Export as file</button>
                        <button class="export-option-v2" data-format="CSV">📊 Export as CSV</button>
                        <button class="export-option-v2" data-format="Excel">📗 Export as XLSX</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    const modal = document.getElementById('exportModalV2');
    if (exportBtn && modal) {
        exportBtn.onclick = () => modal.style.display = 'flex';
        modal.onclick = (e) => {
            if (e.target === modal) modal.style.display = 'none';
        };

        modal.querySelectorAll('.export-option-v2').forEach(opt => {
            opt.onclick = () => {
                alert(`Exporting as ${opt.dataset.format}...`);
                modal.style.display = 'none';
            };
        });
    }
}

function initPreviews() {
    if (!document.querySelector('.preview-modal-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.className = 'preview-modal-backdrop';
        backdrop.innerHTML = `
            <div class="preview-modal">
                <span class="preview-close">←</span>
                <div id="previewContent"></div>
            </div>
        `;
        document.body.appendChild(backdrop);
    }

    const backdrop = document.querySelector('.preview-modal-backdrop');
    const content = backdrop.querySelector('#previewContent');
    const closeBtn = backdrop.querySelector('.preview-close');

    function closePreview() {
        backdrop.classList.remove('active');
        setTimeout(() => backdrop.style.display = 'none', 300);
    }

    closeBtn.onclick = closePreview;
    backdrop.onclick = (e) => { if (e.target === backdrop) closePreview(); };

    // Attach to table rows
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.style.cursor = 'pointer';
        row.onclick = () => {
            const h1 = document.querySelector('h1')?.innerText.toLowerCase() || '';
            let html = '';

            if (h1.includes('users')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 4) return;
                const name = cells[1].innerText;
                const location = cells[2].innerText;
                const email = cells[3].innerText;
                
                html = `
                    <div class="profile-preview">
                        <div class="profile-preview-header">User Profile</div>
                        <div class="profile-preview-cover-box">
                            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=800&fit=crop" alt="Cover">
                            <div class="profile-preview-avatar-wrapper">
                                <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=400&fit=crop" class="profile-preview-avatar" alt="Avatar">
                                <div class="profile-verified-badge">✓</div>
                            </div>
                        </div>
                        <div class="profile-preview-info">
                            <h2>${name}</h2>
                            <p>${email}</p>
                        </div>
                        <div class="profile-preview-details">
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">City</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Role</span><span class="profile-detail-val">Attendee</span></div>
                        </div>
                    </div>
                `;
            } else if (h1.includes('tickets')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 4) return;
                const event = cells[1].innerText;
                const price = cells[2].innerText;
                const status = cells[3].innerText;
                
                html = `
                    <div class="ticket-preview">
                        <div class="ticket-card-preview">
                            <div class="ticket-main-v2">
                                <div class="ticket-top">EVENTRA</div>
                                <div class="ticket-info">
                                    <div class="ticket-event-name">${event} Ticket</div>
                                    <div class="ticket-meta-info">
                                        <div class="ticket-meta-line">📍 Kaduna HQ</div>
                                        <div class="ticket-meta-line">📅 May 05, 2025 | 🕕 6:00pm</div>
                                        <div class="ticket-meta-line">🎫 Status: ${status}</div>
                                    </div>
                                </div>
                                <div class="ticket-bottom-info">
                                    <div class="ticket-type">Client Pass</div>
                                    <div class="ticket-price-box">
                                        <div class="ticket-price-label">Price Paid</div>
                                        <div class="ticket-price-val">${price}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="ticket-barcode-section">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Barcode_93.svg/1200px-Barcode_93.svg.png" class="barcode-img" alt="barcode">
                            </div>
                        </div>
                    </div>
                `;
            } else if (h1.includes('events')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 4) return;
                const event = cells[0].innerText;
                const location = cells[1].innerText;
                const price = cells[2].innerText;
                const status = cells[3].innerText;
                
                html = `
                    <div class="event-preview">
                        <div class="event-preview-image-box">
                            <img src="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop" class="event-preview-image" alt="Event">
                        </div>
                        <div class="event-preview-content">
                            <div class="event-preview-main-info">
                                <h1 class="event-preview-title">${event}</h1>
                                <div class="event-preview-quick-meta">
                                    <span>📅 May 5, 2025</span>
                                    <span>🕕 6:00PM</span>
                                </div>
                            </div>
                            <p class="event-preview-desc">
                                Full event details for ${event}. Status: ${status}. 
                                Join hundreds of others in this premium experience.
                            </p>
                            <div class="event-preview-grid-details">
                                <div class="event-grid-item">📞 08123456789</div>
                                <div class="event-grid-item">📍 ${location}</div>
                            </div>
                            <div class="event-preview-footer">
                                <div class="event-social-actions">
                                    <span>🔗</span><span>❤️</span><span>💬</span>
                                </div>
                                <div class="event-price-final">
                                    <label>Price:</label>
                                    <span>${price}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            if (html) {
                content.innerHTML = html;
                backdrop.style.display = 'flex';
                setTimeout(() => backdrop.classList.add('active'), 10);
            }
        };
    });
}

function initManagementInteractions() {
    // Hook Create Event Button
    const searchString = "Create Event";
    const allButtons = document.querySelectorAll('button');
    const createBtn = Array.from(allButtons).find(b => b.textContent.includes(searchString));
    
    if (createBtn) {
        createBtn.onclick = (e) => {
            e.preventDefault();
            showCreateEventForm();
        };
    }

    // Dashboard Feed Items
    document.querySelectorAll('.event-feed-item').forEach(item => {
        item.style.cursor = 'pointer';
        item.onclick = () => {
             // ... existing logic or simulate click
        };
    });
}

function showCreateEventForm() {
    if (!document.querySelector('.preview-modal-backdrop')) {
        initPreviews();
    }
    const backdrop = document.querySelector('.preview-modal-backdrop');
    const modal = backdrop.querySelector('.preview-modal');
    const content = backdrop.querySelector('#previewContent');

    // Apply specific class for this modal
    modal.className = 'preview-modal create-event-modal';

    content.innerHTML = `
        <div class="create-event-header">
            <span class="back-btn" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">←</span>
            <h2>Create Event</h2>
            <p>Please fill the fields completely to create an event</p>
        </div>
        <div class="create-event-form">
            <div class="form-group-v2">
                <label>Event Name</label>
                <input type="text" placeholder="Event Name">
            </div>
            <div class="form-group-v2">
                <label>Description</label>
                <textarea rows="3" placeholder="Enter description" style="resize: none;"></textarea>
            </div>
            <div class="form-group-v2">
                <label>Event Type</label>
                <select>
                    <option value="" disabled selected>Select Event Type</option>
                    <option>Music</option>
                    <option>Tech</option>
                    <option>Sports</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group-v2">
                    <label>Date</label>
                    <input type="date">
                </div>
                <div class="form-group-v2">
                    <label>Time</label>
                    <input type="time">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-v2">
                    <label>Phone Contact 1</label>
                    <input type="text" placeholder="Enter Phone Number">
                </div>
                <div class="form-group-v2">
                    <label>Phone Contact 2 (Optional)</label>
                    <input type="text" placeholder="Enter Phone Number">
                </div>
            </div>
            <div class="form-group-v2">
                <label>State</label>
                <select>
                    <option value="" disabled selected>Select State</option>
                    <option>Kaduna</option>
                    <option>Lagos</option>
                    <option>Abuja</option>
                </select>
            </div>
            <div class="form-group-v2">
                <label>Address</label>
                <input type="text" placeholder="Enter Address">
            </div>
            <div class="form-group-v2">
                <label>Event Visibility</label>
                <div class="radio-group">
                    <label class="radio-item"><input type="radio" name="visibility" checked> All States</label>
                    <label class="radio-item"><input type="radio" name="visibility"> Specific State</label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-v2">
                    <label>Tag</label>
                    <input type="text" placeholder="e.g #name">
                </div>
                <div class="form-group-v2">
                    <label>Link</label>
                    <input type="text" placeholder="e.g https://clientname/eventname">
                </div>
            </div>
            <div class="form-group-v2">
                <label>Price</label>
                <input type="text" placeholder="Enter Price">
            </div>
            <div class="upload-area">
                <span class="plus-icon">+</span>
                <span>Upload An Image</span>
            </div>
        </div>
        <div class="form-footer-actions">
            <button class="btn-cancel" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">Cancel</button>
            <button class="btn-submit-event" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">Create Event</button>
        </div>
    `;

    // Overwrite the default close behavior briefly to reset class
    const closeBtn = backdrop.querySelector('.preview-close');
    const oldClose = closeBtn.onclick;
    closeBtn.onclick = () => {
        oldClose();
        setTimeout(() => { modal.className = 'preview-modal'; }, 300);
        closeBtn.onclick = oldClose; // restore
    };

    backdrop.style.display = 'flex';
    setTimeout(() => backdrop.classList.add('active'), 10);
}

function initMediaModals() {
    const btnNewFolder = document.getElementById('btnNewFolder');
    const btnUploadFile = document.getElementById('btnUploadFile');

    if (btnNewFolder) {
        btnNewFolder.onclick = showNewFolderModal;
    }
    if (btnUploadFile) {
        btnUploadFile.onclick = showUploadFileModal;
    }
}

function showNewFolderModal() {
    if (!document.querySelector('.preview-modal-backdrop')) {
        initPreviews();
    }
    const backdrop = document.querySelector('.preview-modal-backdrop');
    const modal = backdrop.querySelector('.preview-modal');
    const content = backdrop.querySelector('#previewContent');

    modal.className = 'preview-modal media-modal';

    content.innerHTML = `
        <h2>New Folder</h2>
        <p>Give your new folder a name to keep things organized.</p>
        <input type="text" class="media-field" placeholder="Enter folder name..." autofocus>
        <div class="media-modal-actions">
            <button class="btn-media-cancel" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">Cancel</button>
            <button class="btn-media-confirm" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">Create Folder</button>
        </div>
    `;

    const closeBtn = backdrop.querySelector('.preview-close');
    const oldClose = closeBtn.onclick;
    closeBtn.onclick = () => {
        oldClose();
        setTimeout(() => { modal.className = 'preview-modal'; }, 300);
        closeBtn.onclick = oldClose;
    };

    backdrop.style.display = 'flex';
    setTimeout(() => backdrop.classList.add('active'), 10);
}

function showUploadFileModal() {
    if (!document.querySelector('.preview-modal-backdrop')) {
        initPreviews();
    }
    const backdrop = document.querySelector('.preview-modal-backdrop');
    const modal = backdrop.querySelector('.preview-modal');
    const content = backdrop.querySelector('#previewContent');

    modal.className = 'preview-modal media-modal';

    content.innerHTML = `
        <h2>Upload File</h2>
        <p>Select a file from your device to upload to the gallery.</p>
        <div class="upload-zone-mini">
            <span class="upload-icon">☁️</span>
            <span>Click or drag to upload</span>
        </div>
        <div class="media-modal-actions">
            <button class="btn-media-cancel" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">Cancel</button>
            <button class="btn-media-confirm" onclick="this.closest('.preview-modal-backdrop').querySelector('.preview-close').click()">Upload Now</button>
        </div>
    `;

    const closeBtn = backdrop.querySelector('.preview-close');
    const oldClose = closeBtn.onclick;
    closeBtn.onclick = () => {
        oldClose();
        setTimeout(() => { modal.className = 'preview-modal'; }, 300);
        closeBtn.onclick = oldClose;
    };

    backdrop.style.display = 'flex';
    setTimeout(() => backdrop.classList.add('active'), 10);
}
