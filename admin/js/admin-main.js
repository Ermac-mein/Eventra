document.addEventListener('DOMContentLoaded', () => {
    initExportModal();
    initSidebar();
    initDrawers();
    initPreviews();
    
    // Initialize admin authentication and profile
    if (window.adminAuth) {
        window.adminAuth.loadAdminProfile();
    }
    
    // Initialize notification system
    if (window.notificationManager) {
        window.notificationManager.startPolling();
    }
});

function initDrawers() {
    const backdrop = document.createElement('div');
    backdrop.className = 'drawer-backdrop';
    document.body.appendChild(backdrop);

    const triggers = {
        'notifications': document.querySelector('.action-icon:first-child'), // Assuming first icon is bell
        'settings': document.querySelector('.action-icon:nth-child(2)'),     // Assuming second is settings
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

    // Attach listeners to triggers
    if (triggers.notifications) {
        triggers.notifications.onclick = () => {
            openDrawer('notifications');
            // Mark all notifications as read when drawer is opened
            if (window.notificationManager) {
                setTimeout(() => {
                    window.notificationManager.markAsRead();
                }, 500);
            }
        };
    }
    if (triggers.settings) triggers.settings.onclick = () => openDrawer('settings');
    if (triggers.profile) triggers.profile.onclick = () => openDrawer('profile');

    // Attach listeners to back arrows and backdrop
    document.querySelectorAll('.back-arrow').forEach(arrow => {
        arrow.onclick = closeAll;
    });

    backdrop.onclick = closeAll;
    
    // Add logout handler to profile drawer logout icon
    const logoutIcon = document.querySelector('#profileDrawer .drawer-header span[style*="color: #e74c3c"]');
    if (logoutIcon) {
        logoutIcon.onclick = async (e) => {
            e.stopPropagation();
            if (confirm('Are you sure you want to logout?')) {
                try {
                    const response = await fetch('../../api/auth/logout.php', {
                        method: 'POST'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Clear local storage
                        storage.remove('user');
                        storage.remove('auth_token');
                        
                        // Redirect to login
                        window.location.href = '../../public/pages/login.html';
                    } else {
                        alert('Logout failed: ' + result.message);
                    }
                } catch (error) {
                    console.error('Logout error:', error);
                    // Clear local storage anyway
                    storage.remove('user');
                    storage.remove('auth_token');
                    window.location.href = '../../public/pages/login.html';
                }
            }
        };
    }
}

function initExportModal() {
    const exportBtn = document.querySelector('.btn-export');
    const modalBackdrop = document.getElementById('exportModal');
    
    if (exportBtn && modalBackdrop) {
        exportBtn.addEventListener('click', () => {
            modalBackdrop.style.display = 'flex';
        });
        
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) {
                modalBackdrop.style.display = 'none';
            }
        });
        
        // Handle option clicks
        const options = document.querySelectorAll('.export-option');
        options.forEach(opt => {
            opt.addEventListener('click', () => {
                const format = opt.dataset.format;
                alert(`Exporting as ${format}...`);
                modalBackdrop.style.display = 'none';
            });
        });
    }
}

function initSidebar() {
    // Logic to handle mobile toggle if needed, or active state highlighting
    const currentPath = window.location.pathname;
    const menuItems = document.querySelectorAll('.menu-item a');
    
    menuItems.forEach(item => {
        if (currentPath.includes(item.getAttribute('href'))) {
            item.parentElement.classList.add('active');
        }
    });
}
window.initPreviews = function() {
    // Create Modal Backdrop (if not exists)
    let backdrop = document.querySelector('.preview-modal-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'preview-modal-backdrop';
        backdrop.innerHTML = `
            <div class="preview-modal">
                <span class="preview-close">←</span>
                <div id="previewContent"></div>
            </div>
        `;
        document.body.appendChild(backdrop);

        const closeBtn = backdrop.querySelector('.preview-close');
        closeBtn.onclick = () => {
            backdrop.classList.remove('active');
            setTimeout(() => {
                backdrop.style.display = 'none';
            }, 300);
        };
        backdrop.onclick = (e) => {
            if (e.target === backdrop) {
                backdrop.classList.remove('active');
                setTimeout(() => {
                    backdrop.style.display = 'none';
                }, 300);
            }
        };
    }

    const content = backdrop.querySelector('#previewContent');

    // Attach to table rows
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        // Remove existing click listener if any (by cloning and replacing, or better just check)
        if (row.dataset.previewAttached) return;
        row.dataset.previewAttached = 'true';

        row.onclick = () => {
            const path = window.location.pathname;
            let html = '';

            if (path.includes('users.html')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 6) return;
                const name = cells[1].innerText;
                const location = cells[2].innerText;
                const email = cells[3].innerText;
                const status = cells[4].innerText;
                const contact = cells[5].innerText;
                
                const profilePic = row.dataset.profilePic || `https://ui-avatars.com/api/?name=${name}`;
                
                html = `
                    <div class="profile-preview">
                        <div class="profile-preview-header">User Profile</div>
                        <div class="profile-preview-cover-box">
                            <img src="${profilePic}" alt="Cover">
                            <div class="profile-preview-avatar-wrapper">
                                <img src="${profilePic}" class="profile-preview-avatar" alt="Avatar">
                                <div class="profile-verified-badge">✓</div>
                            </div>
                        </div>
                        <div class="profile-preview-info">
                            <h2>${name}</h2>
                            <p>${email}</p>
                        </div>
                        <div class="profile-preview-details">
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Phone</span><span class="profile-detail-val">${contact}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Job Title</span><span class="profile-detail-val">Student</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Address</span><span class="profile-detail-val">Nigeria</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">City</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">State</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Status</span><span class="profile-detail-val">${status}</span></div>
                        </div>
                    </div>
                `;
            } else if (path.includes('clients.html')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 6) return;
                const name = cells[1].innerText;
                const email = cells[2].innerText;
                const location = cells[3].innerText;
                const contact = cells[4].innerText;
                const status = cells[5].innerText;
                
                const profilePic = row.dataset.profilePic || `https://ui-avatars.com/api/?name=${name}`;
                
                html = `
                    <div class="profile-preview">
                        <div class="profile-preview-header">Client Profile</div>
                        <div class="profile-preview-cover-box">
                            <img src="${profilePic}" alt="Cover">
                            <div class="profile-preview-avatar-wrapper">
                                <img src="${profilePic}" class="profile-preview-avatar" alt="Avatar">
                                <div class="profile-verified-badge">✓</div>
                            </div>
                        </div>
                        <div class="profile-preview-info">
                            <h2>${name}</h2>
                            <p>${email}</p>
                        </div>
                        <div class="profile-preview-details">
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Phone</span><span class="profile-detail-val">${contact}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">City</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">State</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Status</span><span class="profile-detail-val">${status}</span></div>
                        </div>
                    </div>
                `;
            } else if (path.includes('tickets.html')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 6) return;
                const serial = cells[0].innerText;
                const event = cells[1].innerText;
                const price = cells[2].innerText;
                const attendees = cells[3].innerText;
                const category = cells[4].innerText;
                
                const eventImage = row.dataset.image || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
                
                html = `
                    <div class="ticket-preview">
                        <div class="ticket-card-preview" style="background: url('${eventImage}') no-repeat center center; background-size: cover; position: relative;">
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); border-radius: 12px;"></div>
                            <div class="ticket-main" style="position: relative; z-index: 1;">
                                <div class="ticket-top">EVENTRA</div>
                                <div class="ticket-info">
                                    <div class="ticket-event-name">${event} Ticket</div>
                                    <div class="ticket-meta-info">
                                        <div class="ticket-meta-line">📍 Venue: Nigeria</div>
                                        <div class="ticket-meta-line">👥 Attendees: ${attendees}</div>
                                        <div class="ticket-meta-line">🔖 Serial: ${serial}</div>
                                    </div>
                                </div>
                                <div class="ticket-bottom-info">
                                    <div class="ticket-type">${category}</div>
                                    <div class="ticket-price-box">
                                        <div class="ticket-price-label">Ticket Price</div>
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
            } else if (path.includes('events.html')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 6) return;
                const event = cells[0].innerText;
                const location = cells[1].innerText;
                const price = cells[2].innerText;
                const attendees = cells[3].innerText;
                const category = cells[4].innerText;
                const status = cells[5].innerText;
                
                const eventImage = row.dataset.image || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
                
                html = `
                    <div class="event-preview">
                        <div class="event-preview-image-box">
                            <img src="${eventImage}" class="event-preview-image" alt="Event">
                        </div>
                        <div class="event-preview-content">
                            <div class="event-preview-main-info">
                                <h1 class="event-preview-title">${event}</h1>
                            </div>
                            <p class="event-preview-desc">
                                Status: ${status} | Attendees: ${attendees}
                            </p>
                            <div class="event-preview-grid-details">
                                <div class="event-grid-item">📂 ${category}</div>
                                <div class="event-grid-item">📍 ${location}</div>
                            </div>
                            <div class="event-preview-footer">
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
