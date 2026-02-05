/**
 * Admin Dashboard JavaScript
 * Handles admin dashboard data loading and display
 */

document.addEventListener('DOMContentLoaded', async () => {
    // Check for namespaced admin user
    const user = storage.get('admin_user') || storage.get('user');
    
    if (!user || user.role !== 'admin') {
        window.location.href = '../../public/pages/login.html';
        return;
    }

    // Load admin profile
    await loadAdminProfile();

    // Load dashboard stats
    await loadDashboardStats();
});

async function loadAdminProfile() {
    try {
        const user = storage.get('admin_user');
        const response = await fetch(`../../api/users/get-profile.php?user_id=${user.id}`);
        const result = await response.json();

        if (result.success) {
            const adminUser = result.user;
            
            // Update profile display
            const profileAvatar = document.querySelector('.user-avatar');

            // Set profile picture
            const avatarUrl = adminUser.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(adminUser.name)}&background=random`;
            if (profileAvatar) {
                profileAvatar.style.backgroundImage = `url(${avatarUrl})`;
                profileAvatar.style.backgroundSize = 'cover';
                profileAvatar.style.backgroundPosition = 'center';
            }

            // Store updated user data
            storage.set('admin_user', adminUser);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

async function loadDashboardStats() {
    try {
        const response = await fetch('../../api/stats/get-admin-dashboard-stats.php');
        const result = await response.json();

        if (!result.success) {
            console.error('Failed to load dashboard stats');
            return;
        }

        const stats = result.stats;

        // Update stat cards with specific labels to avoid nth-child issues
        const findStatValue = (label) => {
            const cards = document.querySelectorAll('.stat-card');
            for (const card of cards) {
                if (card.querySelector('.stat-label').textContent.includes(label)) {
                    return card.querySelector('.stat-value');
                }
            }
            return null;
        };

        const totalEventsVal = findStatValue('Total Events');
        if (totalEventsVal) totalEventsVal.textContent = stats.total_events;

        const activeUsersVal = findStatValue('Active Users');
        if (activeUsersVal) activeUsersVal.textContent = stats.active_users;

        const totalClientsVal = findStatValue('Total Clients');
        if (totalClientsVal) totalClientsVal.textContent = stats.total_clients;

        const revenueVal = findStatValue('Revenue');
        if (revenueVal) revenueVal.textContent = '₦' + parseFloat(stats.total_revenue).toLocaleString();

        // Load recent activities
        loadRecentActivities(result.recent_activities);

        // Load top users
        loadTopUsers(result.top_users);

        // Load active clients
        loadActiveClients(result.active_clients);

        // Load upcoming events
        loadUpcomingEvents(result.upcoming_events);

    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

function loadRecentActivities(activities) {
    const container = document.getElementById('recentActivitiesList');
    if (!container) return;

    if (activities.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No recent activities</p>';
        return;
    }

    container.innerHTML = activities.map(activity => {
        const icon = getActivityIcon(activity.type);
        const color = getActivityColor(activity.type);
        
        return `
            <div class="activity-item">
                <div class="activity-icon" style="background: ${color.bg}; color: ${color.text};">${icon}</div>
                <div class="activity-content">
                    <div class="activity-details">${activity.message}</div>
                    <div class="activity-time">${timeAgo(activity.created_at)}</div>
                </div>
            </div>
        `;
    }).join('');
}

function loadTopUsers(users) {
    const container = document.getElementById('topUsersList');
    if (!container) return;

    if (users.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No users yet</p>';
        return;
    }

    container.innerHTML = users.map(user => `
        <div class="quick-item">
            <img src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`}" 
                 class="small-avatar" alt="${user.name}">
            <div style="flex:1">
                <div style="font-size: 0.9rem; font-weight: 600;">${user.name}</div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted);">${user.state || 'N/A'} • ${user.ticket_count || 0} tickets</div>
            </div>
            <div class="status-badge status-${user.status || 'active'}">${user.status || 'Active'}</div>
        </div>
    `).join('');
}

function loadActiveClients(clients) {
    const container = document.getElementById('activeClientsList');
    if (!container) return;

    if (clients.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No clients yet</p>';
        return;
    }

    container.innerHTML = clients.map(client => `
        <div class="quick-item">
            <img src="${client.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(client.name)}&background=random`}" 
                 class="small-avatar" alt="${client.name}">
            <div style="flex:1">
                <div style="font-size: 0.9rem; font-weight: 600;">${client.name}</div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted);">${client.company || client.email} • ${client.event_count || 0} events</div>
            </div>
            <div class="status-badge status-${client.status || 'active'}">${client.status || 'Active'}</div>
        </div>
    `).join('');
}

function loadUpcomingEvents(events) {
    const container = document.getElementById('upcomingEventsSlider');
    if (!container) return;

    if (events.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No upcoming events</p>';
        return;
    }

    container.innerHTML = events.map(event => `
        <div class="event-mini-card">
            <img src="${event.image_path || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=200&fit=crop'}" 
                 class="event-mini-img" alt="${event.event_name}">
            <div class="event-mini-info">
                <div class="event-mini-title">${event.event_name}</div>
                <div class="event-mini-meta">
                    <span>${event.state}</span>
                    <span>${formatDate(event.event_date)}</span>
                </div>
            </div>
        </div>
    `).join('');

    // Handle animation dynamically based on item count
    if (events.length > 3) {
        container.style.animation = `slideEvents ${events.length * 6}s linear infinite`;
        // Clone items for seamless loop
        container.innerHTML += container.innerHTML;
    } else {
        container.style.animation = 'none';
        container.style.justifyContent = 'center';
    }
}

function getActivityIcon(type) {
    const icons = {
        'event_created': '🎭',
        'event_deleted': '🗑️',
        'event_published': '📢',
        'ticket_purchase': '🎫',
        'user_login': '👤',
        'client_login': '💼',
        'admin_login': '⚡',
        'admin_logout': '🌙',
        'login': '🔐'
    };
    return icons[type] || '📌';
}

function getActivityColor(type) {
    const colors = {
        'event_created': { bg: '#e3f2fd', text: '#2196f3' },
        'event_deleted': { bg: '#ffebee', text: '#f44336' },
        'event_published': { bg: '#e8f5e9', text: '#4caf50' },
        'ticket_purchase': { bg: '#fff3e0', text: '#ff9800' },
        'user_login': { bg: '#f3e5f5', text: '#9c27b0' },
        'client_login': { bg: '#e1f5fe', text: '#03a9f4' },
        'admin_login': { bg: '#fff4e5', text: '#ff9800' },
        'admin_logout': { bg: '#f1f5f9', text: '#64748b' },
        'login': { bg: '#f3e5f5', text: '#9c27b0' }
    };
    return colors[type] || { bg: '#f5f5f5', text: '#666' };
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return `${seconds} secs ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} mins ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hours ago`;
    const days = Math.floor(hours / 24);
    return `${days} days ago`;
}
