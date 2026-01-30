/**
 * Client Dashboard JavaScript
 * Handles dashboard data loading and display
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = storage.get('user');
    
    if (!user || user.role !== 'client') {
        window.location.href = '../../public/pages/login.html';
        return;
    }

    const clientId = user.id;

    // Load client profile
    await loadClientProfile(clientId);

    // Load dashboard stats
    await loadDashboardStats(clientId);

    // Load recent events
    await loadRecentEvents(clientId);

    // Load recent tickets
    await loadRecentTickets(clientId);
});

async function loadClientProfile(clientId) {
    try {
        const response = await fetch(`../../api/users/get-profile.php?user_id=${clientId}`);
        const result = await response.json();

        if (result.success) {
            const user = result.user;
            
            // Update profile display using unified elements
            const profileName = document.querySelector('.user-profile div[style*="font-weight: 700"]');
            const profileSubtext = document.querySelector('.user-profile div[style*="font-size: 0.75rem"]');
            
            if (profileName) profileName.textContent = user.name;
            if (profileSubtext && user.job_title && user.state) {
                profileSubtext.textContent = `${user.job_title} • ${user.state}`;
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
        // Load event stats
        const eventsResponse = await fetch(`../../api/events/get-events.php?client_id=${clientId}&limit=1`);
        const eventsResult = await eventsResponse.json();

        if (eventsResult.success && eventsResult.stats) {
            const stats = eventsResult.stats;
            
            // Update stats cards
            const upcomingEventsCard = document.querySelector('.client-stat-card.green .stat-main-value');
            if (upcomingEventsCard) {
                upcomingEventsCard.textContent = stats.published_events || 0;
            }
        }

        // Load ticket stats
        const ticketsResponse = await fetch(`../../api/tickets/get-tickets.php?client_id=${clientId}&limit=1`);
        const ticketsResult = await ticketsResponse.json();

        if (ticketsResult.success && ticketsResult.stats) {
            const stats = ticketsResult.stats;
            
            const ticketsCard = document.querySelector('.client-stat-card.purple .stat-main-value');
            if (ticketsCard) {
                ticketsCard.textContent = stats.total_quantity || 0;
            }
        }

        // Load user stats (users who bought tickets)
        const usersResponse = await fetch(`../../api/users/get-users.php?role=user&limit=1`);
        const usersResult = await usersResponse.json();

        if (usersResult.success && usersResult.stats) {
            const usersCard = document.querySelector('.client-stat-card.orange .stat-main-value');
            if (usersCard) {
                usersCard.textContent = usersResult.stats.total_regular_users || 0;
            }
        }

        // Load media stats
        const mediaResponse = await fetch(`../../api/media/get-media.php?client_id=${clientId}`);
        const mediaResult = await mediaResponse.json();

        if (mediaResult.success && mediaResult.stats) {
            const mediaCard = document.querySelector('.client-stat-card.red .stat-main-value');
            if (mediaCard) {
                mediaCard.textContent = mediaResult.stats.total_files || 0;
            }
        }

    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadRecentEvents(clientId) {
    try {
        const response = await fetch(`../../api/events/get-events.php?client_id=${clientId}&limit=3`);
        const result = await response.json();

        if (result.success && result.events) {
            const eventsList = document.querySelector('.event-feed-list');
            if (!eventsList) return;

            if (result.events.length === 0) {
                eventsList.innerHTML = '<p style="text-align: center; color: var(--client-text-muted); padding: 2rem;">No events yet. Create your first event!</p>';
                return;
            }

            eventsList.innerHTML = result.events.map(event => `
                <div class="event-feed-item" style="cursor: pointer;" onclick="showEventActionModal(${event.id})">
                    <img src="${event.image_path || ''}" 
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
                            <span style="color: ${event.status === 'published' ? 'var(--card-green)' : 'var(--card-red)'};">
                                ● ${event.status.charAt(0).toUpperCase() + event.status.slice(1)}
                            </span>
                            <span style="color: var(--client-text-muted);">${event.attendee_count || 0} Tickets Sold</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading events:', error);
    }
}

async function loadRecentTickets(clientId) {
    try {
        const response = await fetch(`../../api/tickets/get-tickets.php?client_id=${clientId}&limit=4`);
        const result = await response.json();

        if (result.success && result.tickets) {
            const salesList = document.querySelector('.sales-list');
            if (!salesList) return;

            if (result.tickets.length === 0) {
                salesList.innerHTML = '<p style="text-align: center; color: var(--client-text-muted); padding: 2rem;">No ticket sales yet.</p>';
                return;
            }

            salesList.innerHTML = result.tickets.map(ticket => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f4f8;">
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <img src="${ticket.user_profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(ticket.user_name)}&background=random`}" 
                             style="width: 32px; height: 32px; border-radius: 50%;">
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 600;">${ticket.user_name}</div>
                            <div style="font-size: 0.7rem; color: var(--client-text-muted);">${timeAgo(ticket.purchase_date)}</div>
                        </div>
                    </div>
                    <div style="font-size: 0.8rem; font-weight: 600;">${ticket.quantity} ticket${ticket.quantity > 1 ? 's' : ''}</div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading tickets:', error);
    }
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
