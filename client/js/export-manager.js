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
    await exportTableToPDF('Events');
}

async function exportTicketsToPDF() {
    await exportTableToPDF('Tickets');
}

async function exportUsersToPDF() {
    await exportTableToPDF('Users');
}

async function exportMediaToPDF() {
    await exportTableToPDF('Media');
}

async function exportTableToPDF(dataType) {
    const table = document.querySelector('table');
    
    // Fallback to data-fetch export if no table is present (e.g. Media Grid)
    if (!table) {
        return handleExport(dataType, 'PDF');
    }

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // ... rest of the function remains same but with better styling ...
        doc.setFontSize(18);
        doc.setFont(undefined, 'bold');
        doc.text(`${dataType.charAt(0).toUpperCase() + dataType.slice(1)} Report`, 14, 15);
        
        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.text(`Exported on: ${new Date().toLocaleString()}`, 14, 22);

        const headers = [];
        const rows = [];
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach(cell => {
            const text = cell.innerText.trim();
            if (text && text !== 'Actions') headers.push(text);
        });
        
        const bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            // Skip the action column (usually last)
            for (let i = 0; i < cells.length; i++) {
                if (i < headers.length) {
                    rowData.push(cells[i].innerText.trim().replace(/\n/g, ' '));
                }
            }
            if (rowData.length > 0 && !rowData[0].includes('Loading') && !rowData[0].includes('No')) {
                rows.push(rowData);
            }
        });

        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 28,
            theme: 'striped',
            headStyles: { fillColor: [99, 102, 241], textColor: 255 },
            margin: { top: 30 }
        });

        const filename = `${dataType}_export_${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(filename);
        showNotification('PDF Export Complete', 'success');
    } catch (error) {
        console.error('PDF export error:', error);
        showNotification('PDF export failed', 'error');
    }
}

function hideExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function showExportModal(dataType) {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.add('active');
        
        // Add click handlers to export options
        const options = modal.querySelectorAll('.export-option');
        options.forEach(option => {
            // Clone to remove old listeners
            const fresh = option.cloneNode(true);
            option.parentNode.replaceChild(fresh, option);

            fresh.addEventListener('click', () => {
                const format = fresh.getAttribute('data-format');
                if (format === 'Excel') {
                    exportTableToExcel(dataType);
                } else if (format === 'PDF') {
                    exportTableToPDF(dataType);
                } else {
                    handleExport(dataType, format);
                }
                hideExportModal();
            });
        });
        
        // Close on backdrop click
        modal.onclick = (e) => {
            if (e.target === modal) {
                hideExportModal();
            }
        };
    }
}

async function exportTableToExcel(dataType) {
    const table = document.querySelector('table');
    if (!table) {
        return handleExport(dataType, 'Excel');
    }

    try {
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
            if (rowData.length > 0 && !rowData[0].includes('Loading') && !rowData[0].includes('No')) {
                worksheet_data.push(rowData);
            }
        });

        // Create worksheet
        const worksheet = XLSX.utils.aoa_to_sheet(worksheet_data);
        
        // Set column widths
        const colWidths = headers.map(() => ({ wch: 20 }));
        worksheet['!cols'] = colWidths;

        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(workbook, worksheet, dataType);

        // Generate Excel file
        const filename = `${dataType.toLowerCase()}-export-${new Date().toISOString().split('T')[0]}.xlsx`;
        XLSX.writeFile(workbook, filename);

        showNotification(`${dataType} exported successfully as Excel`, 'success');
    } catch (error) {
        console.error('Excel export error:', error);
        showNotification('Failed to export as Excel', 'error');
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
        
        const response = await apiFetch(endpoint);
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

        if (format === 'CSV') {
            exportToCSV(exportData, `${dataType}_export_${new Date().toISOString().split('T')[0]}`);
        } else if (format === 'PDF') {
            // If we have clean data, we could generate a better PDF, 
            // but for now let's use the table scraper if available, 
            // otherwise we'd need a data-to-pdf converter.
            exportTableToPDF(dataType);
        } else if (format === 'Excel') {
            exportTableToExcel(dataType);
        }
        
        showNotification(`${dataType.charAt(0).toUpperCase() + dataType.slice(1)} exported successfully as ${format}`, 'success');
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
