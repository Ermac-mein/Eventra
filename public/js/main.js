// Event data - will be loaded from API
let eventsData = {
  hot: [],
  trending: [],
  featured: [],
  upcoming: [],
  nearby: [],
  all: []
};

let allEvents = [];  // Store all events for filtering

// Load events from API
async function loadEvents() {
  try {
    const response = await fetch('../../api/events/get-events.php');
    const result = await response.json();
    
    if (result.success && result.events) {
      const publishedEvents = result.events.filter(event => event.status === 'published');
      
      // Store all events for search functionality
      allEvents = publishedEvents;
      if (typeof window.allEventsData !== 'undefined') {
        window.allEventsData = publishedEvents;
      }
      
      // Sort helper by creation date (newest first)
      const sortByCreation = (events) => {
        return events.sort((a, b) => {
          const dateA = new Date(a.created_at || a.event_date);
          const dateB = new Date(b.created_at || b.event_date);
          return dateB - dateA;
        });
      };
      
      // Get user location for Nearby events
      const keys = typeof getRoleKeys === 'function' ? getRoleKeys() : { user: 'user' };
      const user = storage.get(keys.user) || storage.get('user');
      const userState = user?.state?.toLowerCase();
      const userCity = user?.city?.toLowerCase();

      const now = new Date();
      const upcomingEvents = publishedEvents.filter(event => new Date(event.event_date) >= now);
      
      // Priority-based filtering
      eventsData.featured = sortByCreation([...publishedEvents.filter(e => e.priority === 'featured')]).slice(0, 6);
      eventsData.hot = sortByCreation([...publishedEvents.filter(e => e.priority === 'hot')]).slice(0, 6);
      
      // Trending: priority='trending' OR high attendee count
      eventsData.trending = sortByCreation([...publishedEvents
        .filter(e => e.priority === 'trending' || (e.attendee_count && e.attendee_count > 50))])
        .slice(0, 6);
      
      // Upcoming: future events sorted by date (earliest first)
      eventsData.upcoming = upcomingEvents
        .sort((a, b) => new Date(a.event_date) - new Date(b.event_date))
        .slice(0, 6);
      
      // All Events: sorted by creation date
      eventsData.all = sortByCreation([...publishedEvents]);

      // Nearby: events in user's state/city if logged in
      console.log('User Location:', { state: userState, city: userCity });
      
      if (userState || userCity) {
        eventsData.nearby = publishedEvents.filter(e => {
          const eventState = e.state?.toLowerCase();
          const eventCity = e.city?.toLowerCase();
          
          const stateMatch = userState && eventState && (eventState.includes(userState) || userState.includes(eventState));
          const cityMatch = userCity && eventCity && (eventCity.includes(userCity) || userCity.includes(eventCity));
          
          return stateMatch || cityMatch;
        }).slice(0, 6);
        
        console.log('Nearby Matches by Location:', eventsData.nearby.length);
      } 
      
      // Fallback: If no location matches or not logged in, use events with priority 'nearby'
      if (eventsData.nearby.length === 0) {
        eventsData.nearby = sortByCreation([...publishedEvents.filter(e => e.priority === 'nearby')]).slice(0, 6);
        console.log('Nearby Matches by Priority:', eventsData.nearby.length);
      }
      
      // Render events
      renderEvents();
    } else {
      console.error('Failed to load events:', result.message);
      renderEvents();
    }
  } catch (error) {
    console.error('Error loading events:', error);
    renderEvents();
  }
}

// Mobile menu toggle
function initMobileMenu() {
  const menuToggle = document.querySelector('.mobile-menu-toggle');
  const navMenu = document.querySelector('.nav-menu');

  if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', () => {
      menuToggle.classList.toggle('active');
      navMenu.classList.toggle('active');
    });

    // Close menu when clicking on a link
    const navLinks = document.querySelectorAll('.nav-menu a');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        menuToggle.classList.remove('active');
        navMenu.classList.remove('active');
      });
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!menuToggle.contains(e.target) && !navMenu.contains(e.target)) {
        menuToggle.classList.remove('active');
        navMenu.classList.remove('active');
      }
    });
  }
}

// User icon and profile logic
function initUserIcon() {
  const userIcon = document.querySelector('.user-icon');
  const userProfileBtn = document.getElementById('userProfileBtn');
  const profileDropdown = document.getElementById('profileDropdown');
  const viewProfile = document.getElementById('viewProfile');
  const logoutBtn = document.getElementById('logoutBtn');
  const profileSideModal = document.getElementById('profileSideModal');
  const closeProfileModal = document.getElementById('closeProfileModal');
  const profileEditForm = document.getElementById('profileEditForm');
  const loginModal = document.getElementById('loginModal');
  const closeLoginModal = document.getElementById('closeLoginModal');
  
  // Check if logged in and update display
  const defaultUserIcon = document.getElementById('defaultUserIcon');
  const userProfileImg = document.getElementById('userProfileImg');
  const userOnlineStatus = document.querySelector('.user-online-status');

  if (isAuthenticated()) {
    const keys = typeof getRoleKeys === 'function' ? getRoleKeys() : { user: 'user' };
    const user = storage.get(keys.user) || storage.get('user');
    if (user) {
      // Show profile image, hide default SVG
      if (userProfileImg) {
          userProfileImg.src = user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=FF5A5F&color=fff&size=128`;
          userProfileImg.title = `Logged in as ${user.name}`;
          userProfileImg.style.display = 'block';
      }
      if (defaultUserIcon) defaultUserIcon.style.display = 'none';
      
      // Show active status
      if (userOnlineStatus) userOnlineStatus.style.display = 'block';
    }
    
    // Toggle dropdown
    if (userProfileBtn) {
      userProfileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
      });
    }

    // Close dropdown on click outside
    document.addEventListener('click', () => {
      if (profileDropdown) profileDropdown.classList.remove('show');
    });

    // Logout logic
    if (logoutBtn) {
      logoutBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const result = await Swal.fire({
          title: 'Are you sure?',
          text: "You will be logged out of your session!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#ff5a5f',
          cancelButtonColor: '#9ca3af',
          confirmButtonText: 'Yes, logout!',
          background: 'rgba(30, 41, 59, 0.95)',
          color: '#fff'
        });

        if (!result.isConfirmed) return;

        try {
          const response = await fetch('../../api/auth/logout.php');
          const result = await response.json();
          if (result.success) {
            // Clear all possible user keys
            storage.remove('user');
            storage.remove('auth_token');
            storage.remove('client_user');
            storage.remove('client_auth_token');
            storage.remove('admin_user');
            storage.remove('admin_auth_token');
            location.reload();
          }
        } catch (error) {
          console.error('Logout error:', error);
          storage.remove('user');
          storage.remove('auth_token');
          location.reload();
        }
      });
    }

    // Modal logic
    if (viewProfile) {
      viewProfile.addEventListener('click', (e) => {
        e.preventDefault();
        profileDropdown.classList.remove('show');
        
        // Populate modal with user data
        const user = storage.get(keys.user) || storage.get('user');
        document.getElementById('modalProfilePic').src = user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=FF5A5F&color=fff&size=128`;
        document.getElementById('profileName').value = user.name || '';
        document.getElementById('profileEmail').value = user.email || '';
        document.getElementById('profilePhone').value = user.phone || '';
        document.getElementById('profileState').value = user.state || '';
        document.getElementById('profileCity').value = user.city || '';
        document.getElementById('profileAddress').value = user.address || '';
        
        profileSideModal.classList.add('open');
      });
    }

    if (closeProfileModal) {
      closeProfileModal.addEventListener('click', () => {
        profileSideModal.classList.remove('open');
      });
    }

    if (profileEditForm) {
      profileEditForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(profileEditForm);
        
        try {
          const response = await fetch('../../api/users/update-profile.php', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          
          if (result.success) {
            storage.set(keys.user, result.user);
            showNotification('Profile updated successfully!', 'success');
            profileSideModal.classList.remove('open');
            initUserIcon(); // Refresh icons
          } else {
            showNotification(result.message || 'Error updating profile', 'error');
          }
        } catch (error) {
          console.error('Update profile error:', error);
          showNotification('System error occurred', 'error');
        }
      });
    }
    
    } else {
        // If not logged in, clicking should show the centered login modal
        if (userProfileBtn) {
            userProfileBtn.addEventListener('click', () => {
                if (loginModal) {
                    loginModal.style.display = 'flex';
                    setTimeout(() => loginModal.classList.add('show'), 10);
                }
            });
        }
        
        if (closeLoginModal) {
            closeLoginModal.addEventListener('click', () => {
                if (loginModal) {
                    loginModal.classList.remove('show');
                    setTimeout(() => loginModal.style.display = 'none', 300);
                }
            });
        }
        
        // Close on backdrop click
        window.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                loginModal.classList.remove('show');
                setTimeout(() => loginModal.style.display = 'none', 300);
            }
        });
    }
}

// Google Auth Logic for Homepage
async function initGoogleAuth() {
    if (isAuthenticated()) return;

    try {
        const response = await fetch('../../api/config/get-google-config.php');
        const data = await response.json();

        if (data.success && data.client_id) {
            // Check if google is defined
            if (typeof google !== 'undefined') {
                google.accounts.id.initialize({
                    client_id: data.client_id,
                    callback: handleGoogleCredentialResponse,
                    auto_select: false,
                    cancel_on_tap_outside: true,
                });

                const container = document.getElementById('googleSignInContainer');
                if (container) {
                    google.accounts.id.renderButton(container, {
                        type: 'standard',
                        theme: 'outline',
                        size: 'large',
                        text: 'signin_with',
                        shape: 'rectangular',
                        logo_alignment: 'left',
                        width: '320'
                    });
                }
            } else {
                console.error('Google GSI script not loaded');
            }
        } else {
            console.error('Failed to load Google config:', data.message);
        }
    } catch (error) {
        console.error('Google Auth Init Error:', error);
    }
}

async function handleGoogleCredentialResponse(response) {
    try {
        // Show loading state
        const container = document.getElementById('googleSignInContainer');
        if (container) container.innerHTML = '<div class="spinner"></div> Signing in...';

        const res = await fetch('../../api/auth/google-handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                credential: response.credential,
                intent: 'user'
            })
        });

        const result = await res.json();

        if (result.success) {
            const keys = typeof getRoleKeys === 'function' ? getRoleKeys() : { user: 'user', token: 'auth_token' };
            storage.set(keys.user, result.user);
            storage.set(keys.token, result.user.token);
            
            showNotification('Google Sign-in successful!', 'success');
            
            setTimeout(() => {
                location.reload(); // Refresh to update UI
            }, 1000);
        } else {
            showNotification(result.message || 'Login failed', 'error');
            // Reset button
            initGoogleAuth();
        }
    } catch (error) {
        console.error('Google Response Error:', error);
        showNotification('An error occurred during Google Sign-in', 'error');
        initGoogleAuth();
    }
}

// Search functionality
function initSearch() {
  const searchButton = document.querySelector('.search-button');
  const searchInput = document.querySelector('.search-input');

  if (searchButton && searchInput) {
    searchButton.addEventListener('click', handleSearch);
    searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        handleSearch();
      }
    });
  }
}

function handleSearch() {
  const searchInput = document.querySelector('.search-input');
  const query = searchInput.value.trim().toLowerCase();
  
  const allCards = document.querySelectorAll('.event-card');
  
  allCards.forEach(card => {
    const title = card.querySelector('.event-title').textContent.toLowerCase();
    const location = card.querySelector('.event-location').textContent.toLowerCase();
    const isFavorite = card.querySelector('.favorite-icon').classList.contains('active');
    
    const matchesQuery = query === '' || title.includes(query) || location.includes(query);
    const matchesFavorite = query === 'favorites' || query === 'favorite' ? isFavorite : true;
    
    if (matchesQuery && matchesFavorite) {
      card.style.display = 'block';
    } else if (query === 'favorites' || query === 'favorite') {
        card.style.display = isFavorite ? 'block' : 'none';
    } else {
      card.style.display = 'none';
    }
  });
}

function filterEvents(query) {
  const allCards = document.querySelectorAll('.event-card');
  
  allCards.forEach(card => {
    const title = card.querySelector('.event-title').textContent.toLowerCase();
    const location = card.querySelector('.event-location').textContent.toLowerCase();
    
    if (title.includes(query) || location.includes(query)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
}

// Create event card
function createEventCard(event, index) {
  const price = !event.price || parseFloat(event.price) === 0 ? 'Free' : `₦${parseFloat(event.price).toLocaleString()}`;
  const eventImage = event.image_path || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop';
  const eventDate = new Date(event.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const eventTime = event.event_time || 'TBA';
  const isFavorite = event.is_favorite ? 'active' : '';
  
  // Modern HTML structure with staggered animation delay
  return `
    <div class="event-card" data-id="${event.id}" data-tag="${event.tag || event.id}" style="animation-delay: ${index * 0.1}s">
      <div class="event-image-container">
        <img src="${eventImage}" alt="${event.event_name}" class="event-image">
        <div class="event-badges">
          <div class="event-category-badge">${event.category || 'Event'}</div>
          ${event.priority ? `
            <div class="event-status-badge">
              <span class="status-dot"></span>
              ${event.priority.toUpperCase()}
            </div>
          ` : ''}
        </div>
      </div>
      <div class="event-content">
        <div class="event-date-time">${eventDate} • ${eventTime}</div>
        <h3 class="event-title">${event.event_name}</h3>
        <div class="event-location">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          ${event.city || ''} ${event.state || 'Nigeria'}
        </div>
        <p class="event-description">${(event.description || '').substring(0, 100)}${event.description && event.description.length > 100 ? '...' : ''}</p>
        <div class="event-organizer">Organized by ${event.organizer_name || event.client_name || 'Eventra'}</div>
        
        <div class="event-footer">
          <span class="event-price">${price}</span>
          <div class="event-card-actions">
            <button class="card-action-btn favorite-btn ${isFavorite}" onclick="toggleFavorite(event, ${event.id})" title="Favorite">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </button>
            <button class="card-action-btn share-btn" onclick="shareEvent(event, ${event.id})" title="Share">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
}

// Redirect to new event details page using ID
function viewEventDetails(id) {
  if (!id) {
      showNotification('Event ID missing', 'error');
      return;
  }
  // We are in /public/pages/index.html, so event-details.html is in the same directory
  window.location.href = `event-details.html?id=${id}`;
}

// Buy ticket handler (legacy, but updated)
function buyTicket(eventId) {
  // If we have the tag, use it. Otherwise fallback.
  const card = document.querySelector(`.event-card[data-id="${eventId}"]`);
  if (card && card.dataset.tag) {
      viewEventDetails(card.dataset.tag);
  } else {
      window.location.href = `pages/buy-ticket.html?id=${eventId}`;
  }
}

// Render events
function renderEvents() {
  const hotGrid = document.getElementById('hot-events-grid');
  const trendingGrid = document.getElementById('trending-events-grid');
  const featuredGrid = document.getElementById('featured-events-grid');
  const upcomingGrid = document.getElementById('upcoming-events-grid');

  const allGrid = document.getElementById('all-events-grid');

  if (allGrid) {
    allGrid.innerHTML = eventsData.all.length > 0 
      ? eventsData.all.map((e, i) => createEventCard(e, i)).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No events available at the moment</p>';
  }

  if (hotGrid) {
    hotGrid.innerHTML = eventsData.hot.length > 0 
      ? eventsData.hot.map((e, i) => createEventCard(e, i)).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No hot events at the moment</p>';
  }

  if (trendingGrid) {
    trendingGrid.innerHTML = eventsData.trending.length > 0 
      ? eventsData.trending.map((e, i) => createEventCard(e, i)).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No trending events at the moment</p>';
  }

  if (featuredGrid) {
    featuredGrid.innerHTML = eventsData.featured.length > 0 
      ? eventsData.featured.map((e, i) => createEventCard(e, i)).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No featured events at the moment</p>';
  }

  if (upcomingGrid) {
    upcomingGrid.innerHTML = eventsData.upcoming.length > 0 
      ? eventsData.upcoming.map((e, i) => createEventCard(e, i)).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No upcoming events at the moment</p>';
  }

  const nearbyGrid = document.getElementById('nearby-events-grid');
  if (nearbyGrid) {
    nearbyGrid.innerHTML = eventsData.nearby.length > 0 
      ? eventsData.nearby.map((e, i) => createEventCard(e, i)).join('') 
      : '<div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 3rem; background: rgba(0,0,0,0.02); border-radius: 20px; border: 2px dashed rgba(0,0,0,0.05);">' +
        '<div style="font-size: 3rem; margin-bottom: 1rem;">📍</div>' +
        '<h3 style="margin-bottom: 0.5rem; color: #4b5563;">Check back soon!</h3>' +
        '<p style="color: #6b7280;">No events found in your area at the moment.</p>' +
        '</div>';
  }
}


// Share event function
function shareEvent(e, eventId) {
  if(e) e.stopPropagation();
  
  const shareUrl = `${window.location.origin}${window.location.pathname}?event=${eventId}`;
  
  if (navigator.share) {
    navigator.share({
      title: 'Check out this event!',
      text: 'I found this amazing event on Eventra',
      url: shareUrl
    }).catch(err => console.log('Error sharing:', err));
  } else {
    // Fallback: Copy to clipboard
    navigator.clipboard.writeText(shareUrl).then(() => {
        showNotification('Share link copied to clipboard!', 'success');
    });
  }
}

// Favorite toggle function
async function toggleFavorite(e, eventId) {
    if(e) e.stopPropagation();
    
    if (!isAuthenticated()) {
        showNotification('Please login to favorite events', 'info');
        return;
    }

    try {
        const response = await fetch('../../api/events/favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId })
        });
        const result = await response.json();

        if (result.success) {
            const card = document.querySelector(`.event-card[data-id="${eventId}"]`);
            if (card) {
                const favIcon = card.querySelector('.favorite-icon');
                if (result.is_favorite) {
                    favIcon.classList.add('active');
                } else {
                    favIcon.classList.remove('active');
                }
            }
            showNotification(result.message, 'success');
        }
    } catch (error) {
        console.error('Favorite toggle error:', error);
        showNotification('Failed to update favorite', 'error');
    }
}

// Smooth scroll for navigation
function initSmoothScroll() {
  const links = document.querySelectorAll('a[href^="#"]');
  
  links.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href !== '#') {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth' });
        }
      }
    });
  });
}

// Header scroll effect
function initHeaderScroll() {
  const header = document.querySelector('.header');
  let lastScroll = 0;

  window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;

    if (currentScroll > 100) {
      header.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
    } else {
      header.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.08)';
    }

    lastScroll = currentScroll;
  });
}

// Initialize all functions
function init() {
  loadEvents();
  initMobileMenu();
  initUserIcon();
  initEnhancedSearch();  // New enhanced search
  initEventModal();  // New event modal handler
  initSmoothScroll();
  initHeaderScroll();
  initGoogleAuth();
}

// Enhanced search with filters
function initEnhancedSearch() {
  const searchInput = document.getElementById('searchInput');
  const searchButton = document.querySelector('.search-button-modern');
  const categoryFilter = document.getElementById('categoryFilter');
  const locationFilter = document.getElementById('locationFilter');

  if (searchButton) {
    searchButton.addEventListener('click', performSearch);
  }

  if (searchInput) {
    searchInput.addEventListener('keyup', performSearch);
    searchInput.addEventListener('input', performSearch);
  }

  if (categoryFilter) {
    categoryFilter.addEventListener('change', performSearch);
  }

  if (locationFilter) {
    locationFilter.addEventListener('change', performSearch);
  }
}

function performSearch() {
  const searchInput = document.getElementById('searchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const locationFilter = document.getElementById('locationFilter');

  const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const category = categoryFilter ? categoryFilter.value : '';
  const location = locationFilter ? locationFilter.value : '';

  const allCards = document.querySelectorAll('.event-card');

  allCards.forEach(card => {
    const title = card.querySelector('.event-title')?.textContent.toLowerCase() || '';
    const cardLocation = card.querySelector('.event-location')?.textContent.toLowerCase() || '';
    const description = card.querySelector('.event-description')?.textContent.toLowerCase() || '';

    // Get event from allEvents array based on card data-id
    const eventId = card.dataset.id;
    const event = allEvents.find(e => e.id == eventId);

    const matchesQuery = !query || title.includes(query) || description.includes(query) || cardLocation.includes(query);
    const matchesCategory = !category || (event && event.category && event.category.toLowerCase() === category.toLowerCase());
    const matchesLocation = !location || cardLocation.includes(location.toLowerCase());

    if (matchesQuery && matchesCategory && matchesLocation) {
      card.style.display = 'block';
      card.style.animation = 'fadeIn 0.5s ease-in-out';
    } else {
      card.style.display = 'none';
    }
  });
}

// Event modal functionality
function initEventModal() {
  const modal = document.getElementById('eventDetailsModal');
  const closeBtn = document.getElementById('closeEventModal');

  if (closeBtn) {
    closeBtn.addEventListener('click', closeEventModal);
  }

  if (modal) {
    // Close on backdrop click
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeEventModal();
      }
    });
  }

  // Add click event to all event cards (delegated)
  document.addEventListener('click', (e) => {
    const eventCard = e.target.closest('.event-card');
    if (eventCard && !e.target.closest('.favorite-icon')) {
      const eventId = eventCard.dataset.id;
      showEventModal(eventId);
    }
  });
}

function showEventModal(eventId) {
  const event = allEvents.find(e => e.id == eventId);
  if (!event) {
    console.error('Event not found:', eventId);
    return;
  }

  // Populate modal
  const modal = document.getElementById('eventDetailsModal');
  document.getElementById('modalEventImage').src = event.image_path || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&h=500&fit=crop';
  document.getElementById('modalEventTitle').textContent = event.event_name;
  document.getElementById('modalEventOrganizer').textContent = `Organized by ${event.organizer_name || event.client_name || 'Eventra'}`;
  document.getElementById('modalEventDate').textContent = new Date(event.event_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  document.getElementById('modalEventTime').textContent = event.event_time || 'TBA';
  document.getElementById('modalEventLocation').textContent = `${event.city || ''} ${event.state || 'Nigeria'}`.trim();
  document.getElementById('modalEventDescription').textContent = event.description || 'No description available';
  document.getElementById('modalEventCategory').textContent = event.category || 'General';
  const modalPrice = !event.price || parseFloat(event.price) === 0 ? 'Free' : `₦${parseFloat(event.price).toLocaleString()}`;
  document.getElementById('modalEventPrice').textContent = modalPrice;

  // Priority badge
  const priorityBadge = document.getElementById('modalPriorityBadge');
  if (event.priority) {
    priorityBadge.textContent = event.priority.toUpperCase();
    priorityBadge.style.display = 'block';
    if (event.priority === 'hot') {
      priorityBadge.style.background = 'linear-gradient(135deg, #ff4757, #ff6348)';
      priorityBadge.style.color = 'white';
    } else if (event.priority === 'trending') {
      priorityBadge.style.background = 'linear-gradient(135deg, #3742fa, #5f27cd)';
      priorityBadge.style.color = 'white';
    } else if (event.priority === 'featured') {
      priorityBadge.style.background = 'linear-gradient(135deg, #2ed573, #1abc9c)';
      priorityBadge.style.color = 'white';
    }
  } else {
    priorityBadge.style.display = 'none';
  }

  // Buy ticket button
  const buyTicketBtn = document.getElementById('modalBuyTicketBtn');
  buyTicketBtn.onclick = () => {
    viewEventDetails(event.id);
  };

  // Show modal
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';  // Prevent background scrolling
}

function closeEventModal() {
  const modal = document.getElementById('eventDetailsModal');
  modal.classList.remove('active');
  document.body.style.overflow = '';  // Re-enable scrolling
}

// Update the viewEventDetails function to work with modal
function viewEventDetails(tag) {
  if (!tag) {
      showNotification('Event tag missing', 'error');
      return;
  }
  closeEventModal();  // Close modal first
  window.location.href = `pages/event-details.html?event=${tag}`;
}


// Run when DOM is loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

// Make shareEvent available globally
window.shareEvent = shareEvent;
window.viewEventDetails = viewEventDetails;
