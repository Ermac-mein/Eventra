/**
 * Client Dashboard JavaScript
 * Handles dashboard data loading and display
 */

document.addEventListener('DOMContentLoaded', async () => {
    // Try namespaced key first, fall back to generic if necessary
    const user = storage.get('client_user') || storage.get('user');
    
    if (!user || user.role !== 'client') {
        window.location.href = '../../client/pages/clientLogin.html';
        return;
    }

    const clientId = user.id;

    // Load client profile
    await loadClientProfile(clientId);

    // Load dashboard stats
    await loadDashboardStats(clientId);
});

async function loadClientProfile(clientId) {
    try {
        const response = await fetch(`../../api/users/get-profile.php`);
        const result = await response.json();

        if (result.success) {
            const user = result.user;
            
            // Update profile display using unified elements
            const profileAvatar = document.querySelector('.user-avatar');
            
            // Set profile picture
            // Set profile picture with fallback
            if (profileAvatar) {
                if (user.profile_pic) {
                    profileAvatar.style.backgroundImage = `url(${user.profile_pic})`;
                } else {
                    // Default avatar using UI Avatars
                    const defaultAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || user.business_name || 'User')}&background=random&color=fff`;
                    profileAvatar.style.backgroundImage = `url(${defaultAvatar})`;
                }
                profileAvatar.style.backgroundSize = 'cover';
                profileAvatar.style.backgroundPosition = 'center';
            }
            
            // StateManager will handle all .user-avatar updates automatically
            if (window.stateManager) {
                window.stateManager.setState({ user: user, profilePicture: user.profile_pic });
            }

            // Store user data
            storage.set('user', user);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

async function loadDashboardStats(clientId) {
    try {
        const response = await fetch('../../api/stats/get-client-dashboard-stats.php');
        const result = await response.json();

        if (!result.success) {
            console.error('Failed to load dashboard stats');
            return;
        }

        const stats = result.stats;
        
        // Update stats cards using background colors matching HTML
        const cards = {
            purple: stats.upcoming_events || 0,  // Events card (purple)
            blue: stats.total_tickets || 0,      // Active Tickets card (blue)
            orange: stats.total_users || 0,      // Registered Users card (orange)
            red: stats.media_uploads || 0        // Media Items card (red)
        };

        Object.keys(cards).forEach(color => {
            const cardValue = document.querySelector(`.client-stat-card.${color} .stat-main-value`);
            if (cardValue) cardValue.textContent = cards[color];
        });

        // Load upcoming events
        loadUpcomingEvents(result.upcoming_events_list);

        // Load recent ticket sales
        loadRecentTickets(result.recent_sales);

    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Make functions globally available for dynamic updates
window.loadDashboardStats = loadDashboardStats;
window.loadUpcomingEventsList = loadUpcomingEvents;

async function loadUpcomingEvents(events) {
    const eventsList = document.getElementById('upcomingEventsList');
    if (!eventsList) return;

    if (!events || events.length === 0) {
        eventsList.innerHTML = '<p style="text-align: center; color: var(--client-text-muted); padding: 2rem;">No upcoming events. Create your first event!</p>';
        return;
    }

    eventsList.innerHTML = events.map(event => `
        <div class="event-feed-item" style="cursor: pointer;" onclick="window.location.href='events.html'">
            <img src="${event.image_path || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop'}" 
                 class="event-feed-img" alt="${event.event_name}">
            <div class="event-feed-info">
                <div class="event-feed-title">${event.event_name} | 
                    <span style="font-weight: 500; font-size: 0.9rem; color: var(--client-text-muted);">
                        ${formatDate(event.event_date)} • ${event.event_time}
                    </span>
                </div>
                <p style="font-size: 0.8rem; color: var(--client-text-muted); margin-bottom: 12px; line-height: 1.4;">
                    ${event.description.substring(0, 100)}...
                </p>
                <div class="event-feed-meta">
                    <span>📍 ${event.state}</span>
                    <span>💰 Price: ₦${parseFloat(event.price).toLocaleString()}</span>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px; font-size: 0.75rem;">
                    <span style="color: var(--card-green);">
                        ● Published
                    </span>
                    <span style="color: var(--client-text-muted);">${event.ticket_count || 0} Tickets Sold</span>
                    <span style="color: var(--card-green);">₦${parseFloat(event.event_revenue || 0).toLocaleString()} Revenue</span>
                </div>
            </div>
        </div>
    `).join('');
}

async function loadRecentTickets(tickets) {
    const salesList = document.getElementById('recentTicketSalesList');
    if (!salesList) return;

    if (!tickets || tickets.length === 0) {
        salesList.innerHTML = '<p style="text-align: center; color: var(--client-text-muted); padding: 2rem;">No ticket sales yet.</p>';
        return;
    }

    salesList.innerHTML = tickets.map(ticket => `
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f4f8;">
            <div style="display: flex; gap: 12px; align-items: center;">
                <img src="${ticket.user_profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(ticket.user_name)}&background=random`}" 
                     style="width: 32px; height: 32px; border-radius: 50%;">
                <div>
                    <div style="font-size: 0.85rem; font-weight: 600;">${ticket.user_name}</div>
                    <div style="font-size: 0.7rem; color: var(--client-text-muted);">${ticket.event_name}</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.8rem; font-weight: 600;">${ticket.quantity} ticket${ticket.quantity > 1 ? 's' : ''}</div>
                <div style="font-size: 0.7rem; color: var(--client-text-muted);">${timeAgo(ticket.purchase_date)}</div>
            </div>
        </div>
    `).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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
