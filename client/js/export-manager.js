/**
 * Export Functionality
 * Handles CSV and PDF export for all pages
 */

function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showNotification('No data to export', 'error');
        return;
    }

    // Get headers from first object
    const headers = Object.keys(data[0]);
    
    // Create CSV content
    let csvContent = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = headers.map(header => {
            const value = row[header] || '';
            // Escape commas and quotes
            return `"${String(value).replace(/"/g, '""')}"`;
        });
        csvContent += values.join(',') + '\n';
    });

    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename + '.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

async function exportEventsToPDF() {
    showExportModal('events');
}

async function exportTicketsToPDF() {
    showExportModal('tickets');
}

async function exportUsersToPDF() {
    showExportModal('users');
}

async function exportMediaToPDF() {
    showExportModal('media');
}

function showExportModal(dataType) {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.add('active');
        
        // Add click handlers to export options
        const options = modal.querySelectorAll('.export-option');
        options.forEach(option => {
            option.onclick = () => {
                handleExport(dataType, option.getAttribute('data-format'));
                hideExportModal();
            };
        });
        
        // Close on backdrop click
        modal.onclick = (e) => {
            if (e.target === modal) {
                hideExportModal();
            }
        };
    }
}

function hideExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

async function handleExport(dataType, format) {
    try {
        const user = storage.get('user');
        let endpoint, dataKey, exportData;
        
        switch(dataType) {
            case 'events':
                endpoint = `../../api/events/get-events.php?client_id=${user.id}`;
                dataKey = 'events';
                break;
            case 'tickets':
                endpoint = `../../api/tickets/get-tickets.php?client_id=${user.id}`;
                dataKey = 'tickets';
                break;
            case 'users':
                endpoint = `../../api/users/get-users.php?client_id=${user.id}`;
                dataKey = 'users';
                break;
            case 'media':
                endpoint = `../../api/media/get-media.php?client_id=${user.id}`;
                dataKey = 'media';
                break;
        }
        
        const response = await fetch(endpoint);
        const result = await response.json();

        if (!result.success || !result[dataKey] || result[dataKey].length === 0) {
            showNotification(`No ${dataType} to export`, 'error');
            return;
        }

        // Format data based on type
        if (dataType === 'events') {
            exportData = result.events.map(event => ({
                'Event Name': event.event_name,
                'State': event.state,
                'Price': event.price,
                'Attendees': event.attendee_count || 0,
                'Type': event.event_type,
                'Status': event.status,
                'Date': event.event_date,
                'Time': event.event_time
            }));
        } else if (dataType === 'tickets') {
            exportData = result.tickets.map(ticket => ({
                'Ticket ID': ticket.ticket_id,
                'Event Name': ticket.event_name,
                'Buyer': ticket.buyer_name,
                'Price': ticket.price,
                'Date': ticket.purchase_date,
                'Status': ticket.status
            }));
        } else if (dataType === 'users') {
            exportData = result.users.map(user => ({
                'Name': user.name,
                'Email': user.email,
                'Status': user.status,
                'Engagement': user.engagement_level,
                'Date Joined': user.created_at
            }));
        } else if (dataType === 'media') {
            exportData = result.media.map(media => ({
                'Name': media.name,
                'Type': media.type,
                'File Type': media.file_type || 'N/A',
                'Size': formatFileSize(media.file_size),
                'Created': media.created_at
            }));
        }

        exportToCSV(exportData, `${dataType}_export_${new Date().toISOString().split('T')[0]}`);
        showNotification(`${dataType.charAt(0).toUpperCase() + dataType.slice(1)} exported successfully`, 'success');
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Export failed', 'error');
    }
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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
window.exportToCSV = exportToCSV;
window.exportEventsToPDF = exportEventsToPDF;
window.exportTicketsToPDF = exportTicketsToPDF;
window.exportUsersToPDF = exportUsersToPDF;
window.exportMediaToPDF = exportMediaToPDF;
window.showExportModal = showExportModal;
window.hideExportModal = hideExportModal;
