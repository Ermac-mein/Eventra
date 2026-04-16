document.addEventListener('DOMContentLoaded', async () => {
    // Load cached stats immediately for better UX
    loadCachedStats();

    // Load client profile
    await loadClientProfile();

    // Load dashboard stats (will fetch fresh data and cache it)
    await loadDashboardStats();

    // Enable 30s polling for real-time updates (reduced from 15s) to decrease database load
    // Visibility check prevents queries when tab is in background
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadDashboardStats();
        }
    }, 30000);

    // Initialize heartbeat
    if (typeof initHeartbeat === 'function') initHeartbeat();
});

async function loadClientProfile() {
    try {
        const response = await apiFetch('/api/users/get-profile.php');
        const result = await response.json();

        if (result.success) {
            const user = result.user;
            
            // Update profile display using unified elements
            const profileAvatars = document.querySelectorAll('.user-avatar');
            
            profileAvatars.forEach(avatar => {
                const avatarUrl = user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || user.business_name || 'User')}&background=random&color=fff`;
                avatar.style.backgroundImage = `url(${avatarUrl})`;
                avatar.style.backgroundSize = 'cover';
                avatar.style.backgroundPosition = 'center';
                avatar.textContent = ''; // clear initial if any

                // Add Verification Badge if not already present
                const status = user.verification_status || 'pending';
                let badgeClass = 'unverified';
                let badgeIcon = '';
                let badgeTitle = 'Verification Pending';

                if (status === 'verified') {
                    badgeClass = 'verified';
                    badgeIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    badgeTitle = 'Verified Organizer';
                } else if (status === 'rejected') {
                    badgeClass = 'rejected';
                    badgeIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                    badgeTitle = 'Verification Declined';
                } else {
                    // Pending
                    badgeIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line><circle cx="12" cy="12" r="10"></circle></svg>';
                }

                let badge = avatar.querySelector('.verification-badge-overlay');
                if (!badge) {
                    badge = document.createElement('div');
                    avatar.style.position = 'relative'; 
                    avatar.appendChild(badge);
                }
                
                badge.className = `verification-badge-overlay ${badgeClass}`;
                badge.innerHTML = badgeIcon;
                badge.title = badgeTitle;
            });
            
            if (window.stateManager) {
                window.stateManager.setState({ user: user, profilePicture: user.profile_pic });
            }

            // Show verification banner if pending or rejected
            const banner = document.getElementById('verificationBanner');
            if (banner) {
                if (user.verification_status === 'verified') {
                    banner.style.display = 'none';
                } else if (user.verification_status === 'rejected') {
                    banner.style.display = 'block';
                    banner.style.background = '#fee2e2';
                    banner.style.color = '#991b1b';
                    banner.style.borderColor = '#fecaca';
                    banner.innerHTML = `<strong>Verification Declined:</strong> Your account details were rejected. <a href="javascript:void(0)" onclick="window.showProfileEditModal()" style="font-weight:700; margin-left:8px; color: inherit; text-decoration: underline;">Update Profile</a>`;
                } else {
                    banner.style.display = 'block';
                    banner.style.background = '#fff3cd';
                    banner.style.color = '#856404';
                    banner.style.borderColor = '#ffeeba';
                    banner.innerHTML = `<strong>Verification Pending:</strong> You cannot create or publish events until your account is verified. This usually takes 24-48 hours.`;
                }
            }

            storage.setUser(user);
        }
    } catch (error) {
    }
}

async function loadDashboardStats() {
    try {
        const response = await apiFetch('/api/stats/get-client-dashboard-stats.php');
        
        if (!response.ok) {
            loadCachedStats();
            return;
        }

        const result = await response.json();

        if (!result.success) {
            loadCachedStats();
            return;
        }

        const stats = result.stats;
        if (!stats) {
            loadCachedStats();
            return;
        }

        // Cache stats to localStorage for persistence
        cacheDashboardStats({
            stats: stats,
            events: result.events,
            attendees: result.attendees,
            timestamp: Date.now()
        });

        // Update stats cards using specific IDs
        displayStatsCards(stats);

        // Load upcoming events / performance breakdown
        loadUpcomingEvents(result.events);

        // Sync with global state manager
        if (window.stateManager) {
            window.stateManager.setState({ events: result.events || [] });
        }

        // Load detailed attendee list
        loadRecentTickets(result.attendees);

    } catch (error) {
        loadCachedStats();
    }
}

function cacheDashboardStats(data) {
    try {
        if (window.storage) {
            window.storage.set('dashboard_stats', data);
        } else {
            localStorage.setItem('dashboard_stats', JSON.stringify(data));
        }
    } catch (error) {
    }
}

function loadCachedStats() {
    try {
        let cachedData = null;
        
        if (window.storage) {
            cachedData = window.storage.get('dashboard_stats');
        } else {
            const cached = localStorage.getItem('dashboard_stats');
            cachedData = cached ? JSON.parse(cached) : null;
        }

        if (!cachedData || !cachedData.stats) {
            return;
        }

        // Display cached stats
        displayStatsCards(cachedData.stats);
        loadUpcomingEvents(cachedData.events || []);
        loadRecentTickets(cachedData.attendees || []);

        // Sync with global state manager
        if (window.stateManager) {
            window.stateManager.setState({ events: cachedData.events || [] });
        }
    } catch (error) {
    }
}

function displayStatsCards(stats) {
    const upcomingEventsEl = document.getElementById('upcomingEventsCount');
    const ticketsEl = document.getElementById('ticketsCount');
    const usersEl = document.getElementById('usersCount');
    const mediaEl = document.getElementById('mediaCount');

    if (upcomingEventsEl) upcomingEventsEl.textContent = stats.total_events !== undefined ? stats.total_events : 0;
    if (ticketsEl) ticketsEl.textContent = stats.total_tickets !== undefined ? stats.total_tickets : 0;
    if (usersEl) usersEl.textContent = stats.total_users !== undefined ? stats.total_users : 0;
    if (mediaEl) mediaEl.textContent = stats.total_media !== undefined ? stats.total_media : 0;
}

async function loadUpcomingEvents(events) {
    const eventsList = document.getElementById('upcomingEventsList');
    if (!eventsList) return;

    if (!events || events.length === 0) {
        eventsList.innerHTML = '<p style="text-align: center; color: var(--client-text-muted); padding: 2rem;">No events found. Create your first event!</p>';
        return;
    }

    let html = events.map(event => `
        <div class="event-feed-item summarized" style="cursor: pointer; display: flex; gap: 15px; align-items: center; padding: 1rem;" onclick="window.location.href='events.html?highlight=${event.id}'">
            <div style="width: 50px; height: 50px; border-radius: 8px; flex-shrink: 0; background: #f3f4f6; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #9ca3af;">
                ${event.image_path ? `<img src="${event.image_path.startsWith('/') ? '../..' + event.image_path : '../../' + event.image_path}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML='📷'">` : '📷'}
            </div>
            <div class="event-feed-info" style="flex: 1;">
                <div class="event-feed-title" style="font-size: 0.95rem; margin-bottom: 2px;">${event.event_name}</div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-weight: 500; font-size: 0.8rem; color: var(--client-text-muted);">
                        ${formatDate(event.event_date)}
                    </div>
                    <div style="display: flex; gap: 8px; font-size: 0.7rem; align-items: center;">
                        <span class="status-badge status-${event.status.toLowerCase()}" style="padding: 2px 6px; font-size: 0.65rem;">
                            ${event.status}
                        </span>
                        <span style="color: var(--client-text-muted); font-weight: 600;">${event.tickets_sold || 0} Sold</span>
                        ${event.tickets_sold > 0 ? '<span title="Event Locked" style="cursor:help;">🔒</span>' : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');



    eventsList.innerHTML = html;
}



function getGridColumns(container) {
    if (!container) return 2;
    const style = window.getComputedStyle(container);
    const cols = style.getPropertyValue('grid-template-columns').split(' ').length;
    return cols || 2;
}

async function loadRecentTickets(attendees) {
    const salesList = document.getElementById('recentTicketSalesList');
    if (!salesList) return;

    if (!attendees || attendees.length === 0) {
        salesList.innerHTML = '<p style="text-align: center; color: var(--client-text-muted); padding: 2rem;">No ticket sales yet.</p>';
        return;
    }

    salesList.innerHTML = attendees.map(attendee => {
        let paymentMethod = 'Paystack';
        try {
            if (attendee.paystack_response && typeof attendee.paystack_response === 'string') {
                const parsed = JSON.parse(attendee.paystack_response);
                if (parsed.data && parsed.data.channel) paymentMethod = parsed.data.channel.toUpperCase();
            }
        } catch (e) {}

        return `
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f4f8;">
            <div style="display: flex; gap: 12px; align-items: center;">
                <img src="${getProfileImg(attendee.profile_pic, attendee.name)}" 
                     style="width: 35px; height: 35px; border-radius: 50%;">
                <div>
                    <div style="font-size: 0.85rem; font-weight: 600;">${attendee.name}</div>
                    <div style="font-size: 0.75rem; color: var(--client-text-muted);">${attendee.event_name} <span style="opacity:0.5;">• Standard Ticket</span></div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.85rem; font-weight: 700; color: ${attendee.event_price == 0 ? '#722f37' : '#722f37'};">
                    ${attendee.price_display}
                </div>
                <div style="font-size: 0.7rem; color: var(--client-text-muted);">
                    ${paymentMethod} • ${new Date(attendee.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                </div>
            </div>
        </div>
    `}).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function timeAgo(dateString) {
    if (!dateString) return 'recently';
    // Ensure proper parsing cross-browser
    const validDateString = dateString.replace(' ', 'T');
    
    // Convert SQL date (assuming UTC or Local) to milliseconds
    const date = new Date(validDateString).getTime();
    const now = new Date().getTime();
    
    // Calculate seconds diff, allowing a small 60s buffer for minor server-client timezone skews natively
    let diffMs = now - date;
    let seconds = Math.floor(diffMs / 1000);
    
    // If the date is wildly in the future (due to a heavy timezone offset without 'Z'), we adjust it
    // Usually, this means the DB stored it in local time, but the browser thinks it's UTC and subtracts the offset
    if (seconds < -60) {
        // Fallback: Date seems to be in the future, let's treat the parsed date as local inherently
        // by stripping any assumed timezone, or just returning 'recently' for safety if it's very close
        const offsetDate = new Date(validDateString + 'Z').getTime();
        diffMs = now - offsetDate;
        seconds = Math.floor(diffMs / 1000);
    }
    
    if (seconds < 0) {
        seconds = 0; // Final safety floor
        diffMs = 0;
    }
    
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    const weeks = Math.floor(days / 7);
    
    if (minutes < 1) {
        return seconds > 10 ? `${seconds} seconds ago` : `recently`;
    }
    
    if (minutes < 60) {
        return `${minutes} min${minutes > 1 ? 's' : ''} ago`;
    }
    
    if (hours < 24) {
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    }
    
    if (days >= 1 && days < 7) {
        if (days === 1) return '1 day ago';
        return `${days} days ago`;
    }
    
    if (weeks >= 1) {
        const actualDate = new Date(now - diffMs);
        return actualDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    return 'recently';
}
