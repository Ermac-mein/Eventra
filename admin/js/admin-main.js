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
            const result = await Swal.fire({
                title: 'Logout Request',
                text: 'Are you sure you want to logout from Eventra Admin?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Stay'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('../../api/auth/logout.php', {
                        method: 'POST'
                    });
                    
                    const resultData = await response.json();
                    
                    if (resultData.success) {
                        // Clear local storage
                        storage.remove('user');
                        storage.remove('auth_token');
                        
                        // Redirect to login
                        window.location.href = '../../public/pages/login.html';
                    } else {
                        Swal.fire('Logout Failed', resultData.message, 'error');
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
                Swal.fire('Exporting', `Exporting as ${format}...`, 'info');
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
                            <div class="priority-badge" style="position: absolute; top: 1rem; right: 1rem; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: white; background: ${row.dataset.priority === 'hot' ? '#ff4757' : row.dataset.priority === 'trending' ? '#3742fa' : '#2ed573'};">
                                ${row.dataset.priority || 'Standard'}
                            </div>
                        </div>
                        <div class="event-preview-content">
                            <div class="event-preview-main-info" style="margin-bottom: 1rem;">
                                <h1 class="event-preview-title" style="font-size: 1.5rem; margin-bottom: 0.25rem;">${event}</h1>
                                <p style="color: #6b7280; font-size: 0.85rem;">Organized by: ${row.dataset.clientName || 'Eventra'}</p>
                            </div>
                            
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600;">Attendees</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="display: flex;">
                                        ${[...Array(Math.min(parseInt(attendees), 5))].map((_, i) => `
                                            <img src="https://ui-avatars.com/api/?name=User+${i}&background=random" 
                                                 style="width: 25px; height: 25px; border-radius: 50%; border: 2px solid white; margin-left: ${i === 0 ? '0' : '-10px'};">
                                        `).join('')}
                                    </div>
                                    <span style="font-size: 0.85rem; color: #4b5563; font-weight: 600;">${attendees} people attending</span>
                                </div>
                            </div>

                            <div class="event-preview-grid-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="event-grid-item" style="background: #f8fafc; padding: 0.6rem; border-radius: 6px; font-size: 0.85rem;">📂 ${category}</div>
                                <div class="event-grid-item" style="background: #f8fafc; padding: 0.6rem; border-radius: 6px; font-size: 0.85rem;">📍 ${location}</div>
                            </div>
                            
                            <div class="event-preview-footer" style="padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                                <div class="event-price-final">
                                    <label style="font-size: 0.8rem; color: #64748b;">Ticket Price:</label>
                                    <span style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);">${price}</span>
                                </div>
                            </div>
                            <div class="event-preview-sharing" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                                <div style="margin-bottom: 0.75rem;">
                                    <label style="display: block; font-size: 0.7rem; color: #94a3b8; margin-bottom: 0.25rem; text-transform: uppercase; font-weight: 600;">Shareable Link</label>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input type="text" readonly value="${window.location.origin}/public/pages/event-details.html?event=${row.dataset.tag}&client=${row.dataset.clientName}" 
                                               style="background: #f8fafc; padding: 0.4rem 0.6rem; border-radius: 4px; border: 1px solid #e2e8f0; font-family: monospace; font-size: 0.75rem; flex: 1; color: #475569;">
                                        <button onclick="copyToClipboard('${window.location.origin}/public/pages/event-details.html?event=${row.dataset.tag}&client=${row.dataset.clientName}', 'Link copied!')" style="background: #ef4444; color: white; border: none; padding: 0.4rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 600;">Copy</button>
                                    </div>
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

window.copyToClipboard = function(text, successMsg) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        if (window.showToast) {
            window.showToast(successMsg, 'success');
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: successMsg,
                showConfirmButton: false,
                timer: 2000
            });
        }
    }).catch(err => {
        console.error('Failed to copy:', err);
        Swal.fire('Error', 'Failed to copy to clipboard', 'error');
    });
};
