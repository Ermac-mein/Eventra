document.addEventListener('DOMContentLoaded', () => {
    initExportModal();
    initSidebar();
    initDrawers();
    initLogout();
    initPreviews();
    initSettings();
    
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
    // Create overlay backdrop for drawers
    let backdrop = document.querySelector('.drawer-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'drawer-backdrop';
        document.body.appendChild(backdrop);
    }

    const profileBtn = document.getElementById('openProfileDrawer');
    const notificationBellIcon = document.getElementById('notificationBellIcon') || document.querySelector('.notification-bell-icon');
    const profileDrawer = document.getElementById('profileDrawer');
    const notificationsDrawer = document.getElementById('notificationsDrawer');
    const backArrows = document.querySelectorAll('.back-arrow');

    function openDrawer(drawerElement) {
        if (!drawerElement) return;
        backdrop.style.display = 'block';
        setTimeout(() => {
            drawerElement.classList.add('open');
            backdrop.classList.add('active');
        }, 10);
    }

    function closeAll() {
        if (profileDrawer) profileDrawer.classList.remove('open');
        if (notificationsDrawer) notificationsDrawer.classList.remove('open');
        backdrop.classList.remove('active');
        setTimeout(() => backdrop.style.display = 'none', 400);
    }

    // Attach listeners to notification bell
    if (notificationBellIcon) {
        notificationBellIcon.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openDrawer(notificationsDrawer);
            // Mark all notifications as read when drawer is opened
            if (window.notificationManager) {
                setTimeout(() => {
                    window.notificationManager.markAsRead();
                }, 500);
            }
        };
    }
    
    if (profileBtn) {
        profileBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openDrawer(profileDrawer);
        };
    }

    // Attach listener for the logout button inside the profile drawer
    document.addEventListener('click', (e) => {
        if (e.target.closest('#profileDrawerLogout')) {
            e.preventDefault();
            logout(); // Call the existing global logout function
        }
    });

    // Attach listeners to back arrows (slide-out on arrow click)
    backArrows.forEach(arrow => {
        arrow.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeAll();
        };
    });

    // Close drawers on backdrop/overlay click (click-away listener)
    backdrop.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeAll();
    };
}

/**
 * Global logout function for Admin
 */
async function logout() {
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
            const response = await apiFetch('../../api/auth/logout.php', {
                method: 'POST'
            });
            
            const resultData = await response.json();
            
            if (resultData.success) {
                // Clear local storage (namespaced)
                storage.remove('admin_user');
                storage.remove('admin_auth_token');
                
                // Redirect to login
                window.location.href = '../../admin/pages/adminLogin.html';
            } else {
                Swal.fire('Logout Failed', resultData.message, 'error');
            }
        } catch (error) {
            console.error('Logout error:', error);
            // Clear local storage anyway
            storage.remove('admin_user');
            storage.remove('admin_auth_token');
            window.location.href = '../../admin/pages/adminLogin.html';
        }
    }
}

// Make logout globally accessible
window.logout = logout;

function initLogout() {
    // Attach to any element with class 'logout-link'
    document.querySelectorAll('.logout-link, [onclick*="logout"]').forEach(link => {
        // Remove inline onclick if it exists to prevent double firing, 
        // or just ensure the inline one calls the global function we just defined.
        // If they use onclick="logout()", it calls window.logout, which matches our function.
        // So we mainly need to handle elements that rely on listeners.
        
        // Ensure pointer cursor
        link.style.cursor = 'pointer';
    });

    // Specific listeners if needed (e.g. ID-based)
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.onclick = (e) => {
            e.preventDefault();
            logout();
        };
    }
}

function initExportModal() {
    const modalBackdrop = document.getElementById('exportModal');
    
    // Use event delegation for naturally occurring and dynamic export buttons
    document.addEventListener('click', (e) => {
        const exportBtn = e.target.closest('.btn-export, #headerExportBtn');
        if (exportBtn && modalBackdrop) {
            // Check if there's a table on the current page
            const hasTable = document.querySelector('table tbody tr');
            
            if (!hasTable || hasTable.innerText.includes('Loading') || hasTable.innerText.includes('No data')) {
                // If on dashboard, maybe we want to export something else? 
                // For now, follow the requirement to consolidate.
                if (window.location.pathname.includes('adminDashboard.html')) {
                    // Dashboard might not have a main table, but maybe stats?
                    // Let's assume the user wants to export the main view data.
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Data to Export',
                        text: 'Please wait for data to load or navigate to a page with records before exporting.',
                        confirmButtonColor: '#1976D2'
                    });
                    return;
                }
            }
            
            modalBackdrop.style.display = 'flex';
        }
    });
    
    if (modalBackdrop) {
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
                if (format === 'CSV') {
                    exportCurrentTableToCSV();
                } else if (format === 'PDF') {
                    exportCurrentTableToPDF();
                } else if (format === 'Excel') {
                    exportCurrentTableToExcel();
                }
                modalBackdrop.style.display = 'none';
            });
        });
    }
}

function exportCurrentTableToPDF() {
    const table = document.querySelector('table');
    if (!table) return;

    if (window.showToast) window.showToast('Generating PDF...', 'info');

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Get page title
        const pageTitle = document.querySelector('.page-title h1')?.innerText || 'Eventra Export';
        
        // Add title
        doc.setFontSize(18);
        doc.setFont(undefined, 'bold');
        doc.text(pageTitle, 14, 15);
        
        // Add export date
        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.text(`Exported on: ${new Date().toLocaleDateString()}`, 14, 22);

        // Extract table data
        const headers = [];
        const rows = [];
        
        // Get headers
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach(cell => {
            headers.push(cell.innerText.trim());
        });
        
        // Get rows
        const bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                // Clean up text content
                let text = cell.innerText.trim().replace(/\n/g, ' ');
                rowData.push(text);
            });
            if (rowData.length > 0 && !rowData[0].includes('Loading') && !rowData[0].includes('No data')) {
                rows.push(rowData);
            }
        });

        // Generate table
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 28,
            theme: 'grid',
            headStyles: {
                fillColor: [59, 130, 246],
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 10
            },
            bodyStyles: {
                fontSize: 9
            },
            alternateRowStyles: {
                fillColor: [248, 250, 252]
            },
            margin: { top: 28 }
        });

        // Save the PDF
        const filename = `eventra-export-${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(filename);

        Swal.fire('Success', 'Data exported successfully as PDF', 'success');
    } catch (error) {
        console.error('PDF export error:', error);
        Swal.fire('Error', 'Failed to export as PDF. Please try again.', 'error');
    }
}

function exportCurrentTableToExcel() {
    const table = document.querySelector('table');
    if (!table) return;

    if (window.showToast) window.showToast('Generating Excel...', 'info');

    try {
        // Extract table data
        const workbook = XLSX.utils.book_new();
        const worksheet_data = [];
        
        // Get headers
        const headers = [];
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach(cell => {
            headers.push(cell.innerText.trim());
        });
        worksheet_data.push(headers);
        
        // Get rows
        const bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                let text = cell.innerText.trim().replace(/\n/g, ' ');
                rowData.push(text);
            });
            if (rowData.length > 0 && !rowData[0].includes('Loading') && !rowData[0].includes('No data')) {
                worksheet_data.push(rowData);
            }
        });

        // Create worksheet
        const worksheet = XLSX.utils.aoa_to_sheet(worksheet_data);
        
        // Set column widths
        const colWidths = headers.map(() => ({ wch: 20 }));
        worksheet['!cols'] = colWidths;

        // Add worksheet to workbook
        const sheetName = document.querySelector('.page-title h1')?.innerText || 'Export';
        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);

        // Generate Excel file
        const filename = `eventra-export-${new Date().toISOString().split('T')[0]}.xlsx`;
        XLSX.writeFile(workbook, filename);

        Swal.fire('Success', 'Data exported successfully as Excel', 'success');
    } catch (error) {
        console.error('Excel export error:', error);
        Swal.fire('Error', 'Failed to export as Excel. Please try again.', 'error');
    }
}

function exportCurrentTableToCSV() {
    const table = document.querySelector('table');
    if (!table) return;

    if (window.showToast) window.showToast('Generating CSV...', 'info');

    const rows = Array.from(table.querySelectorAll('tr'));
    const csvContent = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return cells.map(cell => {
            // Clean up the text: remove extra whitespace, handle quotes
            let text = cell.innerText.trim().replace(/\n/g, ' ');
            if (text.includes(',') || text.includes('"')) {
                text = `"${text.replace(/"/g, '""')}"`;
            }
            return text;
        }).join(',');
    }).join('\n');

    const filename = `eventra-export-${new Date().toISOString().split('T')[0]}.csv`;
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    Swal.fire('Success', 'Data exported successfully as CSV', 'success');
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
            <div class="preview-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); margin: 0; width: 600px; max-height: 90vh; overflow-y: auto;">
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
                backdrop.classList.remove('flex-mode');
            }, 300);
        };
        backdrop.onclick = (e) => {
            if (e.target === backdrop) {
                backdrop.classList.remove('active');
                setTimeout(() => {
                    backdrop.style.display = 'none';
                    backdrop.classList.remove('flex-mode');
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
                
                // Fetch details for user to get full metadata
                html = `
                    <div class="profile-preview">
                        <div class="profile-preview-header">User Profile</div>
                        <div class="profile-preview-info" style="padding: 2rem; text-align: center;">
                            <p>Loading user details...</p>
                        </div>
                    </div>
                `;
                content.innerHTML = html;
                backdrop.style.display = 'flex';
                setTimeout(() => backdrop.classList.add('active'), 10);

                apiFetch(`../../api/admin/get-users.php`) // We search in the cached allUsers or just refetch? Let's use the data we already have in the row if possible or fetch.
                    .then(res => res.json())
                    .then(data => {
                        const user = data.users.find(u => u.id == row.dataset.id);
                        if (user) {
                            const profilePic = getProfileImg(user.profile_pic, user.name);
                            content.innerHTML = `
                                <div class="profile-preview">
                                    <div class="profile-preview-header">User Profile</div>
                                    <div class="profile-preview-cover-box">
                                        <img src="${profilePic}" alt="Cover" style="filter: blur(5px); opacity: 0.5;">
                                        <div class="profile-preview-avatar-wrapper">
                                            <div class="avatar-wrapper">
                                                <img src="${profilePic}" class="profile-preview-avatar" alt="Avatar" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid white;">
                                                ${getVerificationBadge(user.email_verified_at ? 'verified' : '')}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="profile-preview-info">
                                        <h2>${user.name}</h2>
                                        <p>${user.email}</p>
                                    </div>
                                    <div class="profile-preview-details">
                                        <div class="profile-preview-detail-item"><span class="profile-detail-label">Phone</span><span class="profile-detail-val">${user.phone || 'N/A'}</span></div>
                                        <div class="profile-preview-detail-item"><span class="profile-detail-label">Gender</span><span class="profile-detail-val" style="text-transform: capitalize;">${user.gender || 'N/A'}</span></div>
                                        <div class="profile-preview-detail-item"><span class="profile-detail-label">DOB</span><span class="profile-detail-val">${user.dob || 'N/A'}</span></div>
                                        <div class="profile-preview-detail-item"><span class="profile-detail-label">Last Login</span><span class="profile-detail-val">${user.last_login_at ? new Date(user.last_login_at).toLocaleString() : 'Never'}</span></div>
                                        <div class="profile-preview-detail-item" style="grid-column: span 2;"><span class="profile-detail-label">Address</span><span class="profile-detail-val">${user.address || 'N/A'}, ${user.city || ''}, ${user.state || ''}, ${user.country || ''}</span></div>
                                        <div class="profile-preview-detail-item"><span class="profile-detail-label">Status</span><span class="profile-detail-val" style="text-transform: capitalize;">${user.status}</span></div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                return;
            } else if (path.includes('clients.html')) {
                const clientId = row.dataset.id;
                const name = row.cells[1].innerText;
                const profilePic = getProfileImg(row.dataset.profilePic, name);
                
                // Show loading state
                html = `
                    <div class="profile-preview">
                        <div class="profile-preview-header">Client Profile</div>
                        <div class="profile-preview-info" style="padding: 2rem; text-align: center;">
                            <p>Loading client details...</p>
                        </div>
                    </div>
                `;
                content.innerHTML = html;
                backdrop.style.display = 'flex';
                setTimeout(() => backdrop.classList.add('active'), 10);

                // Fetch details
                apiFetch(`../../api/admin/get-client-details.php?id=${clientId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const client = data.client;
                            const events = data.events;
                            const buyers = data.buyers;
                            const isVerified = client.verification_status === 'verified';

                            content.innerHTML = `
                                <div class="profile-preview modernized-preview">
                                    <div class="profile-preview-header">
                                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                            <span>Client Profile</span>
                                            <span class="status-badge status-${client.verification_status === 'verified' ? 'active' : (client.verification_status === 'rejected' ? 'offline' : 'ongoing')}" style="font-size: 0.7rem; padding: 0.3rem 0.8rem;">
                                                ${client.verification_status.toUpperCase()}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="profile-preview-cover-box" style="height: 160px;">
                                        <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.4)); z-index: 1;"></div>
                                        <img src="${getProfileImg(client.profile_pic, client.business_name)}" alt="Cover" style="filter: blur(10px); opacity: 0.6; width: 100%; height: 100%; object-fit: cover;">
                                        <div class="profile-preview-avatar-wrapper" style="bottom: -40px; left: 50%; transform: translateX(-50%); z-index: 2;">
                                            <div class="avatar-wrapper">
                                                <img src="${getProfileImg(client.profile_pic, client.business_name)}" class="profile-preview-avatar" alt="Avatar" style="width: 100px; height: 100px; border-radius: 20px; border: 4px solid white; box-shadow: 0 10px 25px rgba(0,0,0,0.1); background: white; object-fit: cover;">
                                                <div style="position: absolute; bottom: 5px; right: 5px; scale: 1.2;">
                                                    ${getVerificationBadge(client.verification_status)}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="profile-preview-info" style="padding: 50px 24px 20px; text-align: center;">
                                        <h2 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 0.25rem;">${client.business_name}</h2>
                                        <p style="color: #64748b; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                            <i data-lucide="mail" style="width: 14px;"></i> ${client.email}
                                        </p>
                                    </div>

                                    <div class="profile-preview-details" style="padding: 0 24px 24px; display: grid; gap: 1.5rem;">
                                        <!-- Basic Info Cards -->
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                            <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #f1f5f9;">
                                                <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">Contact Information</div>
                                                <div style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">${client.phone || 'No Phone'}</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">${client.state || 'N/A'}, ${client.country || 'N/A'}</div>
                                            </div>
                                            <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #f1f5f9;">
                                                <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">Company Details</div>
                                                <div style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.25rem;">${client.company || 'Private Participant'}</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">${client.job_title || 'N/A'}</div>
                                            </div>
                                        </div>

                                        <!-- Bank Details Section -->
                                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1rem;">
                                                <div style="background: #eff6ff; color: #3b82f6; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                    <i data-lucide="landmark" style="width: 18px;"></i>
                                                </div>
                                                <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">Settlement Account</span>
                                            </div>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                                                <div>
                                                    <span style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 4px;">BANK NAME</span>
                                                    <span style="font-weight: 600; color: #334155;">${client.bank_name || 'N/A'}</span>
                                                </div>
                                                <div>
                                                    <span style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 4px;">ACCOUNT NUMBER</span>
                                                    <span style="font-weight: 600; font-family: 'JetBrains Mono', monospace; color: #334155;">${client.account_number || 'N/A'}</span>
                                                </div>
                                                <div style="grid-column: span 2;">
                                                    <span style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 4px;">ACCOUNT NAME</span>
                                                    <span style="font-weight: 600; color: #334155; display: block; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0;">${client.account_name || 'N/A'}</span>
                                                </div>
                                                <div style="grid-column: span 2; display: flex; align-items: center; justify-content: space-between; background: #fafafa; padding: 0.75rem; border-radius: 8px;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <img src="https://checkout.paystack.com/static/media/paystack-logo.22f16870.svg" style="height: 12px;" alt="Paystack">
                                                        <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Subaccount</span>
                                                    </div>
                                                    <span style="font-family: monospace; font-weight: 700; color: ${client.subaccount_code ? 'var(--admin-primary)' : '#94a3b8'}; font-size: 0.85rem;">
                                                        ${client.subaccount_code || 'NOT_LINKED'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Verification Actions -->
                                        <div style="background: #f8fafc; border-radius: 16px; padding: 1.25rem;">
                                            <div style="font-weight: 700; color: #333; margin-bottom: 1rem; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                                                <i data-lucide="shield-check" style="width: 18px; color: #10b981;"></i> Identity Verification
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                                <!-- NIN/BVN Rows -->
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                                    <div style="background: white; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">NIN NUMBER</div>
                                                        <div style="font-weight: 600; font-size: 0.85rem; margin: 4px 0;">${client.nin || 'N/A'}</div>
                                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                                            <span style="font-size: 0.75rem; font-weight: 700; color: ${Number(client.nin_verified) === 1 ? '#10b981' : '#f59e0b'};">
                                                                ${Number(client.nin_verified) === 1 ? '✓ Verified' : 'Pending'}
                                                            </span>
                                                            <button onclick="toggleVerification(${client.id}, 'nin', ${Number(client.nin_verified) === 1 ? 0 : 1})" style="background: ${Number(client.nin_verified) === 1 ? '#fee2e2' : '#dcfce7'}; color: ${Number(client.nin_verified) === 1 ? '#ef4444' : '#15803d'}; border: none; border-radius: 4px; padding: 2px 8px; font-size: 0.65rem; font-weight: 800; cursor: pointer;">
                                                                ${Number(client.nin_verified) === 1 ? 'REVOKE' : 'VERIFY'}
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div style="background: white; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700;">BVN NUMBER</div>
                                                        <div style="font-weight: 600; font-size: 0.85rem; margin: 4px 0;">${client.bvn || 'N/A'}</div>
                                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                                            <span style="font-size: 0.75rem; font-weight: 700; color: ${Number(client.bvn_verified) === 1 ? '#10b981' : '#f59e0b'};">
                                                                ${Number(client.bvn_verified) === 1 ? '✓ Verified' : 'Pending'}
                                                            </span>
                                                            <button onclick="toggleVerification(${client.id}, 'bvn', ${Number(client.bvn_verified) === 1 ? 0 : 1})" style="background: ${Number(client.bvn_verified) === 1 ? '#fee2e2' : '#dcfce7'}; color: ${Number(client.bvn_verified) === 1 ? '#ef4444' : '#15803d'}; border: none; border-radius: 4px; padding: 2px 8px; font-size: 0.65rem; font-weight: 800; cursor: pointer;">
                                                                ${Number(client.bvn_verified) === 1 ? 'REVOKE' : 'VERIFY'}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Main Approval Buttons -->
                                                <div class="profile-preview-actions" style="display: flex; gap: 12px; margin-top: 2rem;">
                                                    <button class="btn btn-primary" onclick="approveClient(${client.id}, 1, this)" style="flex: 1; background: #10b981; border: none; padding: 0.8rem; border-radius: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; color: white; opacity: ${client.verification_status === 'verified' ? '0.5' : '1'};" ${client.verification_status === 'verified' ? 'disabled' : ''}>
                                                        <i data-lucide="check-circle" style="width: 18px;"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger" onclick="approveClient(${client.id}, 0, this)" style="flex: 1; background: #ef4444; border: none; padding: 0.8rem; border-radius: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; color: white; opacity: ${client.verification_status === 'rejected' ? '0.5' : '1'};" ${client.verification_status === 'rejected' ? 'disabled' : ''}>
                                                        <i data-lucide="x-circle" style="width: 18px;"></i> Reject
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Conditional Display Section -->
                                        ${isVerified ? `
                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem;">
                                                <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; color: #1e293b;">
                                                    <div style="background: #fff7ed; color: #f97316; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                        <i data-lucide="calendar" style="width: 18px;"></i>
                                                    </div>
                                                    Published Events (${events.length})
                                                </h3>
                                                <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 5px;" class="custom-scrollbar">
                                                    ${events.length > 0 ? events.map(ev => `
                                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; transition: transform 0.2s;">
                                                            <div>
                                                                <div style="font-weight: 700; font-size: 0.9rem; color: #1e293b;">${ev.event_name}</div>
                                                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">${ev.event_date}</div>
                                                            </div>
                                                            <div style="text-align: right; background: white; padding: 4px 12px; border-radius: 20px; border: 1px solid #e2e8f0;">
                                                                <div style="font-size: 0.8rem; font-weight: 800; color: var(--admin-primary);">${ev.tickets_sold} sold</div>
                                                            </div>
                                                        </div>
                                                    `).join('') : '<p style="font-size: 0.9rem; color: #94a3b8; text-align: center; padding: 1rem;">No events published yet.</p>'}
                                                </div>
                                            </div>

                                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem;">
                                                <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; color: #1e293b;">
                                                    <div style="background: #f0fdf4; color: #16a34a; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                        <i data-lucide="users" style="width: 18px;"></i>
                                                    </div>
                                                    Ticket Buyers (${buyers.length})
                                                </h3>
                                                <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 5px;" class="custom-scrollbar">
                                                    ${buyers.length > 0 ? buyers.map(b => `
                                                        <div style="display: flex; align-items: center; gap: 12px; padding: 0.75rem; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9;">
                                                            <img src="${getProfileImg(b.profile_pic, b.name)}" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover;">
                                                            <div style="flex: 1;">
                                                                <div style="font-weight: 700; font-size: 0.9rem; color: #1e293b;">${b.name}</div>
                                                                <div style="font-size: 0.7rem; color: #64748b;">${b.email}</div>
                                                            </div>
                                                            <div style="font-size: 0.85rem; font-weight: 800; color: #10b981; padding: 4px 10px; background: white; border-radius: 8px;">${b.tickets_bought}</div>
                                                        </div>
                                                    `).join('') : '<p style="font-size: 0.9rem; color: #94a3b8; text-align: center; padding: 1rem;">No ticket buyers yet.</p>'}
                                                </div>
                                            </div>
                                        ` : `
                                            <div style="background: #fff7ed; border: 1px dashed #fdba74; border-radius: 16px; padding: 2rem; text-align: center;">
                                                <i data-lucide="lock" style="width: 40px; height: 40px; color: #f97316; margin-bottom: 1rem;"></i>
                                                <h4 style="font-weight: 800; color: #9a3412; margin-bottom: 0.5rem;">Verification Required</h4>
                                                <p style="font-size: 0.85rem; color: #c2410c; max-width: 250px; margin: 0 auto;">Event listings and buyer analytics are locked until this client has been fully verified.</p>
                                            </div>
                                        `}
                                    </div>
                                </div>
                            `;
                            lucide.createIcons();
                        } else {
                            content.innerHTML = `<div style="padding: 2rem; text-align: center; color: #ef4444;">Failed to load details: ${data.message}</div>`;
                        }
                    });
                return; // Prevent default row click behavior which shows old static modal
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

window.approveClient = async function(clientId, status, btnElement) {
    const action = status ? 'approve' : 'reject';
    const result = await Swal.fire({
        title: action.charAt(0).toUpperCase() + action.slice(1) + ' Client?',
        text: `Are you sure you want to ${action} this client?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: status ? '#10b981' : '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${action}!`,
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;
    
    btnElement.disabled = true;
    const ogText = btnElement.innerText;
    btnElement.innerText = 'Processing...';

    try {
        const res = await apiFetch('../../api/admin/approve-client.php', {
            method: 'POST',
            body: JSON.stringify({ client_id: clientId, status: status })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Success', `Client ${status ? 'approved' : 'declined'} successfully.`, 'success');
            // Close preview to force refresh next time it's opened
            setTimeout(() => {
                const closeBtn = document.querySelector('.preview-close');
                if (closeBtn) closeBtn.click();
            }, 1000);
        } else {
            Swal.fire('Error', data.message || 'Verification failed', 'error');
            btnElement.disabled = false;
            btnElement.innerText = ogText;
        }
    } catch(e) {
        Swal.fire('Error', 'Network error. Please try again.', 'error');
        btnElement.disabled = false;
        btnElement.innerText = ogText;
    }
}

window.toggleVerification = async function(clientId, type, status) {
    if (!clientId || !type) return;
    
    try {
        const response = await apiFetch('../../api/admin/verify-client.php', {
            method: 'POST',
            body: JSON.stringify({ client_id: clientId, type: type, status: status })
        });
        
        const result = await response.json();
        if (result.success) {
            if (window.showToast) {
                window.showToast(result.message, 'success');
            } else {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: result.message,
                    showConfirmButton: false,
                    timer: 2000
                });
            }
            
            // Re-open the modal to refresh the data
            const row = document.querySelector(`tr[data-id="${clientId}"]`);
            if (row) {
                // remove dataset attached to force unbind is hard, let's just trigger the click on the backdrop close and then row click
                const backdrop = document.querySelector('.preview-modal-backdrop');
                if (backdrop) backdrop.classList.remove('active');
                
                // Fetch data again manually or just let row click handle it
                setTimeout(() => row.click(), 300);
            }
        } else {
            Swal.fire('Error', result.message || 'Failed to update verification status', 'error');
        }
    } catch (e) {
        console.error('Error toggling verification', e);
        Swal.fire('Error', 'An unexpected error occurred.', 'error');
    }
};

function initSettings() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const notifToggle = document.getElementById('notifToggle');

    // Load dark mode preference
    if (localStorage.getItem('dark-mode') === 'enabled') {
        document.body.classList.add('dark-mode');
        if (darkModeToggle) darkModeToggle.checked = true;
    }

    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', () => {
            if (darkModeToggle.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('dark-mode', 'enabled');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('dark-mode', 'disabled');
            }
        });
    }

    if (notifToggle) {
        notifToggle.addEventListener('change', () => {
            const status = notifToggle.checked ? 'enabled' : 'disabled';
            if (window.showToast) window.showToast(`Notifications ${status}`, 'info');
        });
    }
}
