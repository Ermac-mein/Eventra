/**
 * Admin Dashboard JavaScript
 * Handles admin dashboard data loading and display
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = storage.get('user');
    
    if (!user || user.role !== 'admin') {
        window.location.href = '../../public/pages/login.html';
        return;
    }

    // Load admin profile
    await loadAdminProfile();

    // Load dashboard stats
    await loadDashboardStats();

    // Load recent events
    await loadRecentEvents();

    // Load recent users
    await loadRecentUsers();

    // Load active clients
    await loadActiveClients();

    // Initialize search
    initAdminSearch();
});

async function loadAdminProfile() {
    try {
        const user = storage.get('user');
        const response = await fetch(`../../api/users/get-profile.php?user_id=${user.id}`);
        const result = await response.json();

        if (result.success) {
            const adminUser = result.user;
            
            // Update profile display
            const profileName = document.querySelector('.user-profile div[style*="font-weight: 700"]');
            const profileAvatar = document.querySelector('.user-avatar');

            if (profileName) profileName.textContent = adminUser.name;
            
            // Set profile picture
            if (profileAvatar && adminUser.profile_pic) {
                profileAvatar.style.backgroundImage = `url(${adminUser.profile_pic})`;
                profileAvatar.style.backgroundSize = 'cover';
                profileAvatar.style.backgroundPosition = 'center';
            }

            // Store updated user data
            storage.set('user', adminUser);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

async function loadDashboardStats() {
    try {
        // Load event stats
        const eventsResponse = await fetch('../../api/events/get-events.php?limit=1');
        const eventsResult = await eventsResponse.json();

        if (eventsResult.success && eventsResult.stats) {
            const eventsCard = document.querySelector('.stat-card:nth-child(1) .stat-value');
            if (eventsCard) {
                eventsCard.textContent = eventsResult.stats.total_events || 0;
            }
        }

        // Load user stats
        const usersResponse = await fetch('../../api/users/get-users.php?limit=1');
        const usersResult = await usersResponse.json();

        if (usersResult.success && usersResult.stats) {
            const usersCard = document.querySelector('.stat-card:nth-child(2) .stat-value');
            const clientsCard = document.querySelector('.stat-card:nth-child(3) .stat-value');
            
            if (usersCard) {
                usersCard.textContent = usersResult.stats.total_regular_users || 0;
            }
            if (clientsCard) {
                clientsCard.textContent = usersResult.stats.total_clients || 0;
            }
        }

        // Load ticket stats
        const ticketsResponse = await fetch('../../api/tickets/get-tickets.php?limit=1');
        const ticketsResult = await ticketsResponse.json();

        if (ticketsResult.success && ticketsResult.stats) {
            const revenueCard = document.querySelector('.stat-card:nth-child(4) .stat-value');
            if (revenueCard && ticketsResult.stats.total_revenue) {
                revenueCard.textContent = '₦' + parseFloat(ticketsResult.stats.total_revenue).toLocaleString();
            } else if (revenueCard) {
                revenueCard.textContent = '₦0';
            }
        }

    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadRecentEvents() {
    try {
        const response = await fetch('../../api/events/get-events.php?limit=5');
        const result = await response.json();

        if (result.success && result.events) {
            const eventsList = document.querySelector('.events-slider');
            if (!eventsList) return;

            if (result.events.length === 0) {
                eventsList.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No events yet.</p>';
                return;
            }

            eventsList.innerHTML = result.events.map(event => `
                <div class="event-slide">
                    <img src="${event.image_path || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop'}" 
                         alt="${event.event_name}">
                    <div class="event-slide-info">
                        <h4>${event.event_name}</h4>
                        <p style="font-size: 0.85rem; color: #666;">${event.state} • ${formatDate(event.event_date)}</p>
                        <p style="font-size: 0.8rem; margin-top: 5px;">
                            <span style="color: ${getStatusColor(event.status)};">● ${event.status}</span>
                            <span style="margin-left: 10px;">${event.attendee_count || 0} attendees</span>
                        </p>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading events:', error);
    }
}

async function loadRecentUsers() {
    try {
        const response = await fetch('../../api/users/get-users.php?role=user&limit=5');
        const result = await response.json();

        if (result.success && result.users) {
            const usersList = document.querySelector('.top-users-list');
            if (!usersList) return;

            if (result.users.length === 0) {
                usersList.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No users yet.</p>';
                return;
            }

            usersList.innerHTML = result.users.map(user => `
                <div class="user-item">
                    <img src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`}" 
                         alt="${user.name}">
                    <div class="user-info">
                        <div class="user-name">${user.name}</div>
                        <div class="user-email">${user.email}</div>
                    </div>
                    <span class="user-status ${user.status}">${user.status}</span>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

async function loadActiveClients() {
    try {
        const response = await fetch('../../api/users/get-users.php?role=client&limit=5');
        const result = await response.json();

        if (result.success && result.users) {
            const clientsList = document.querySelector('.active-clients-list');
            if (!clientsList) return;

            if (result.users.length === 0) {
                clientsList.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No clients yet.</p>';
                return;
            }

            clientsList.innerHTML = result.users.map(client => `
                <div class="client-item">
                    <img src="${client.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(client.name)}&background=random`}" 
                         alt="${client.name}">
                    <div class="client-info">
                        <div class="client-name">${client.name}</div>
                        <div class="client-company">${client.company || client.email}</div>
                    </div>
                    <span class="client-status ${client.status}">${client.status}</span>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading clients:', error);
    }
}

function initAdminSearch() {
    const searchInput = document.querySelector('.header-search input');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(handleAdminSearch, 300));
    }
}

async function handleAdminSearch(e) {
    const query = e.target.value.trim();
    if (query.length < 2) {
        // Reset to default view
        await loadRecentEvents();
        await loadRecentUsers();
        await loadActiveClients();
        return;
    }

    try {
        // Search events
        const eventsResponse = await fetch(`../../api/events/search-events.php?query=${encodeURIComponent(query)}`);
        const eventsResult = await eventsResponse.json();

        if (eventsResult.success) {
            updateEventsDisplay(eventsResult.events);
        }

        // Search users
        const usersResponse = await fetch(`../../api/users/get-users.php?limit=50`);
        const usersResult = await usersResponse.json();

        if (usersResult.success) {
            const filteredUsers = usersResult.users.filter(user => 
                user.name.toLowerCase().includes(query.toLowerCase()) ||
                user.email.toLowerCase().includes(query.toLowerCase())
            );
            updateUsersDisplay(filteredUsers.filter(u => u.role === 'user').slice(0, 5));
            updateClientsDisplay(filteredUsers.filter(u => u.role === 'client').slice(0, 5));
        }

    } catch (error) {
        console.error('Search error:', error);
    }
}

function updateEventsDisplay(events) {
    const eventsList = document.querySelector('.events-slider');
    if (!eventsList) return;

    if (events.length === 0) {
        eventsList.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">No events found.</p>';
        return;
    }

    eventsList.innerHTML = events.slice(0, 5).map(event => `
        <div class="event-slide">
            <img src="${event.image_path || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop'}" 
                 alt="${event.event_name}">
            <div class="event-slide-info">
                <h4>${event.event_name}</h4>
                <p style="font-size: 0.85rem; color: #666;">${event.state} • ${formatDate(event.event_date)}</p>
                <p style="font-size: 0.8rem; margin-top: 5px;">
                    <span style="color: ${getStatusColor(event.status)};">● ${event.status}</span>
                    <span style="margin-left: 10px;">${event.attendee_count || 0} attendees</span>
                </p>
            </div>
        </div>
    `).join('');
}

function updateUsersDisplay(users) {
    const usersList = document.querySelector('.top-users-list');
    if (!usersList) return;

    if (users.length === 0) {
        usersList.innerHTML = '<p style="text-align: center; color: #999; padding: 1rem;">No users found.</p>';
        return;
    }

    usersList.innerHTML = users.map(user => `
        <div class="user-item">
            <img src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`}" 
                 alt="${user.name}">
            <div class="user-info">
                <div class="user-name">${user.name}</div>
                <div class="user-email">${user.email}</div>
            </div>
            <span class="user-status ${user.status}">${user.status}</span>
        </div>
    `).join('');
}

function updateClientsDisplay(clients) {
    const clientsList = document.querySelector('.active-clients-list');
    if (!clientsList) return;

    if (clients.length === 0) {
        clientsList.innerHTML = '<p style="text-align: center; color: #999; padding: 1rem;">No clients found.</p>';
        return;
    }

    clientsList.innerHTML = clients.map(client => `
        <div class="client-item">
            <img src="${client.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(client.name)}&background=random`}" 
                 alt="${client.name}">
            <div class="client-info">
                <div class="client-name">${client.name}</div>
                <div class="client-company">${client.company || client.email}</div>
            </div>
            <span class="client-status ${client.status}">${client.status}</span>
        </div>
    `).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function getStatusColor(status) {
    const colors = {
        'published': '#10b981',
        'scheduled': '#3b82f6',
        'draft': '#ef4444',
        'cancelled': '#999'
    };
    return colors[status] || '#000';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
