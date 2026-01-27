document.addEventListener('DOMContentLoaded', () => {
    initExportModal();
    initSidebar();
    initDrawers();
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
    if (triggers.notifications) triggers.notifications.onclick = () => openDrawer('notifications');
    if (triggers.settings) triggers.settings.onclick = () => openDrawer('settings');
    if (triggers.profile) triggers.profile.onclick = () => openDrawer('profile');

    // Attach listeners to back arrows and backdrop
    document.querySelectorAll('.back-arrow').forEach(arrow => {
        arrow.onclick = closeAll;
    });

    backdrop.onclick = closeAll;
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
