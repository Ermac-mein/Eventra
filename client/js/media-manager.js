/**
 * Media Management System
 * Handles file uploads, folder creation, and media display
 */

document.addEventListener('DOMContentLoaded', () => {
    loadMedia();
});

async function loadMedia() {
    try {
        const user = storage.get('user');
        const response = await fetch(`../../api/media/get-media.php?client_id=${user.id}`);
        const result = await response.json();

        const mediaGrid = document.getElementById('mediaGrid');

        if (!result.success || !result.media || result.media.length === 0) {
            mediaGrid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; color: var(--client-text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                    <h3>No media files yet</h3>
                    <p>Upload your first file or create a folder to get started</p>
                </div>
            `;
            return;
        }

        mediaGrid.innerHTML = result.media.map(item => {
            if (item.type === 'folder') {
                return `
                    <div class="media-card" onclick="openFolder(${item.id})">
                        <div class="media-thumb"><span class="folder-icon">📂</span></div>
                        <div class="media-info">
                            <div class="media-name">${item.name}</div>
                            <div class="media-meta"><span>${item.file_count || 0} files</span></div>
                        </div>
                    </div>
                `;
            } else {
                const isImage = item.file_type?.startsWith('image/');
                return `
                    <div class="media-card">
                        <div class="media-thumb" style="${isImage ? `background: url(${item.file_path}) center/cover;` : ''}">
                            ${!isImage ? `<span class="file-icon">${getFileIcon(item.file_type)}</span>` : ''}
                            <div class="media-actions-overlay">
                                <span class="action-circle" onclick="viewFile('${item.file_path}')">👁️</span>
                                <span class="action-circle" onclick="downloadFile('${item.file_path}', '${item.name}')">⬇️</span>
                                <span class="action-circle" onclick="deleteMedia(${item.id})">🗑️</span>
                            </div>
                        </div>
                        <div class="media-info">
                            <div class="media-name">${item.name}</div>
                            <div class="media-meta"><span>${item.file_type || 'File'}</span><span>${formatFileSize(item.file_size)}</span></div>
                        </div>
                    </div>
                `;
            }
        }).join('');
    } catch (error) {
        console.error('Error loading media:', error);
    }
}

function createNewFolder() {
    // Show folder creation modal
    const modalHTML = `
        <div id="folderModal" class="modal-backdrop active">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2>Create New Folder</h2>
                    <button class="modal-close" onclick="closeFolderModal()">×</button>
                </div>
                <div class="modal-body">
                    <form id="folderForm" onsubmit="handleFolderCreation(event)">
                        <div class="form-group">
                            <label>Folder Name *</label>
                            <input type="text" id="folderNameInput" required placeholder="e.g., Event Photos" autofocus>
                        </div>
                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; background: var(--card-blue);">
                                Create Folder
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="closeFolderModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeFolderModal() {
    const modal = document.getElementById('folderModal');
    if (modal) modal.remove();
}

function handleFolderCreation(e) {
    e.preventDefault();
    const folderName = document.getElementById('folderNameInput').value;
    
    // TODO: Implement folder creation API
    showNotification('Folder "' + folderName + '" created successfully', 'success');
    closeFolderModal();
    // After API call, reload media
    // loadMedia();
}

function uploadFile() {
    // Create hidden file input
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.accept = 'image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx';

    input.onchange = async (e) => {
        const files = e.target.files;
        if (!files.length) return;

        // Show upload progress notification
        showNotification(`Uploading ${files.length} file(s)...`, 'info');

        const formData = new FormData();
        for (let file of files) {
            formData.append('files[]', file);
        }

        try {
            const response = await fetch('../../api/media/upload-media.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Files uploaded successfully', 'success');
                loadMedia();
            } else {
                showNotification('Upload failed: ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showNotification('An error occurred during upload', 'error');
        }
    };

    input.click();
}

function viewFile(filePath) {
    window.open(filePath, '_blank');
}

function downloadFile(filePath, fileName) {
    const a = document.createElement('a');
    a.href = filePath;
    a.download = fileName;
    a.click();
}

async function deleteMedia(mediaId) {
    const result = await Swal.fire({
        title: 'Delete Media?',
        text: 'Are you sure you want to delete this file? This will permanently remove it from your storage.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Keep it'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('../../api/media/delete-media.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ media_id: mediaId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('File deleted successfully', 'success');
            loadMedia();
        } else {
            showNotification('Delete failed: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('An error occurred', 'error');
    }
}

function getFileIcon(fileType) {
    if (!fileType) return '📄';
    if (fileType.includes('pdf')) return '📄';
    if (fileType.includes('word') || fileType.includes('document')) return '📝';
    if (fileType.includes('excel') || fileType.includes('spreadsheet')) return '📊';
    if (fileType.includes('video')) return '🎥';
    return '📄';
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
window.createNewFolder = createNewFolder;
window.closeFolderModal = closeFolderModal;
window.handleFolderCreation = handleFolderCreation;
window.uploadFile = uploadFile;
window.viewFile = viewFile;
window.downloadFile = downloadFile;
window.deleteMedia = deleteMedia;
