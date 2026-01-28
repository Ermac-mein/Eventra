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
    if (logoutIcon && window.adminAuth) {
        logoutIcon.onclick = (e) => {
            e.stopPropagation();
            if (confirm('Are you sure you want to logout?')) {
                window.adminAuth.handleLogout();
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
function initPreviews() {
    // Create Modal Backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'preview-modal-backdrop';
    backdrop.innerHTML = `
        <div class="preview-modal">
            <span class="preview-close">←</span>
            <div id="previewContent"></div>
        </div>
    `;
    document.body.appendChild(backdrop);

    const closeBtn = backdrop.querySelector('.preview-close');
    const content = backdrop.querySelector('#previewContent');

    function closePreview() {
        backdrop.classList.remove('active');
        setTimeout(() => {
            backdrop.style.display = 'none';
        }, 300);
    }

    closeBtn.onclick = closePreview;
    backdrop.onclick = (e) => {
        if (e.target === backdrop) closePreview();
    };

    // Attach to table rows
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
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
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Phone</span><span class="profile-detail-val">${contact}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Job Title</span><span class="profile-detail-val">Lawyer</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Company</span><span class="profile-detail-val">Company</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Address</span><span class="profile-detail-val">No.5 Kanta Road</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">City</span><span class="profile-detail-val">${location} South</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">State</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">DOB</span><span class="profile-detail-val">1994-05-24</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Gender</span><span class="profile-detail-val">Male</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Created Date</span><span class="profile-detail-val">2026-01-21</span></div>
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
                
                html = `
                    <div class="profile-preview">
                        <div class="profile-preview-header">Client Profile</div>
                        <div class="profile-preview-cover-box">
                            <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&fit=crop" alt="Cover">
                            <div class="profile-preview-avatar-wrapper">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&fit=crop" class="profile-preview-avatar" alt="Avatar">
                                <div class="profile-verified-badge">✓</div>
                            </div>
                        </div>
                        <div class="profile-preview-info">
                            <h2>${name}</h2>
                            <p>${email}</p>
                        </div>
                        <div class="profile-preview-details">
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Phone</span><span class="profile-detail-val">${contact}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Job Title</span><span class="profile-detail-val">Lawyer</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Company</span><span class="profile-detail-val">Company</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Address</span><span class="profile-detail-val">No.5 Kanta Road</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">City</span><span class="profile-detail-val">${location} South</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">State</span><span class="profile-detail-val">${location}</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">DOB</span><span class="profile-detail-val">1994-05-24</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Gender</span><span class="profile-detail-val">Male</span></div>
                            <div class="profile-preview-detail-item"><span class="profile-detail-label">Created Date</span><span class="profile-detail-val">2026-01-21</span></div>
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
                
                html = `
                    <div class="ticket-preview">
                        <div class="ticket-card-preview">
                            <div class="ticket-main">
                                <div class="ticket-top">EVENTRA</div>
                                <div class="ticket-info">
                                    <div class="ticket-event-name">${event} Ticket</div>
                                    <div class="ticket-meta-info">
                                        <div class="ticket-meta-line">📍 No.5 kanta road, Kaduna</div>
                                        <div class="ticket-meta-line">📅 May 05, 2025 | 🕕 6:00pm</div>
                                        <div class="ticket-meta-line">👥 Attendees: ${attendees}</div>
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
                                Lorem ipsum dolor sit amet consectetur. Vulptuate consequat ut mi amet lacus sollicitudin nisi. 
                                Senectus facilisi nulla enim in et nec...... 
                                Status: ${status} | Attendees: ${attendees}
                            </p>
                            <div class="event-preview-grid-details">
                                <div class="event-grid-item">📂 ${category}</div>
                                <div class="event-grid-item">📞 08123456789</div>
                                <div class="event-grid-item">📍 ${location}</div>
                                <div class="event-grid-item">🏠 No. 5 kanta road along CBN</div>
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
