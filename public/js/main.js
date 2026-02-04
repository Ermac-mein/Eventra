// Event data - will be loaded from API
let eventsData = {
  hot: [],
  trending: [],
  featured: [],
  upcoming: [],
  nearby: []
};

// Load events from API
async function loadEvents() {
  try {
    const response = await fetch('../../api/events/get-events.php?status=published&limit=100');
    const result = await response.json();

    if (result.success && result.events) {
      // Filter events by priority
      eventsData.hot = result.events.filter(e => e.priority === 'hot');
      eventsData.trending = result.events.filter(e => e.priority === 'trending');
      eventsData.featured = result.events.filter(e => e.priority === 'featured');
      eventsData.upcoming = result.events.filter(e => e.priority === 'upcoming');
      eventsData.nearby = result.events.filter(e => e.priority === 'nearby');

      renderEvents();
    }
  } catch (error) {
    console.error('Error loading events:', error);
    // Fallback to empty arrays
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
  if (isAuthenticated()) {
    const user = storage.get('user');
    if (user && userIcon) {
      // Use user's profile pic or fallback to avatar with their name
      userIcon.src = user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=FF5A5F&color=fff&size=128`;
      userIcon.title = `Logged in as ${user.name}`;
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
        try {
          const response = await fetch('../../api/auth/logout.php');
          const result = await response.json();
          if (result.success) {
            storage.remove('user');
            storage.remove('auth_token');
            location.reload(); // Reload to reset state while remaining on home page
          }
        } catch (error) {
          console.error('Logout error:', error);
          // Fallback logout if API fails
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
        const user = storage.get('user');
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
            storage.set('user', result.user);
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
            storage.set('user', result.user);
            storage.set('auth_token', result.user.token);
            
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
function createEventCard(event) {
  const price = event.price ? `₦${parseFloat(event.price).toLocaleString()}` : 'Free';
  const actionText = 'Buy Ticket';
  const isFavorite = event.is_favorite ? 'active' : '';
  
  return `
    <div class="event-card" data-id="${event.id}">
      <div class="priority-label" style="position: absolute; top: 10px; left: 10px; z-index: 2; padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; color: white; background: ${event.priority === 'hot' ? '#ff4757' : event.priority === 'trending' ? '#3742fa' : '#2ed573'}; text-transform: uppercase;">
        ${event.priority || 'Event'}
      </div>
      <img src="${event.image_path || ''}" alt="${event.event_name}" class="event-image" loading="lazy">
      <div class="event-info">
        <div class="event-header">
          <div>
            <h3 class="event-title">${event.event_name}</h3>
          </div>
          <div class="event-actions">
            <span class="action-icon favorite-icon ${isFavorite}" onclick="toggleFavorite(event, ${event.id})" title="Favorite">❤</span>
            <span class="action-icon share-icon" onclick="shareEvent(event, ${event.id})" title="Share">↗</span>
          </div>
        </div>
        <div class="event-details">
          <p class="event-location">📍 ${event.state}</p>
          <div style="display: flex; align-items: center; margin-top: 5px;">
              <div style="display: flex; margin-right: 8px;">
                  ${[...Array(Math.min(parseInt(event.attendee_count || 0), 4))].map((_, i) => `
                      <img src="https://ui-avatars.com/api/?name=User+${i}&background=random" 
                           style="width: 20px; height: 20px; border-radius: 50%; border: 1px solid white; margin-left: ${i === 0 ? '0' : '-8px'};">
                  `).join('')}
              </div>
              <span style="font-size: 0.75rem; color: #666;">${event.attendee_count || 0} attending</span>
          </div>
          <p class="event-price">${price}</p>
        </div>
        <div class="event-footer">
          <button class="event-status-btn" onclick="viewEventDetails('${event.tag}')">View Details</button>
        </div>
      </div>
    </div>
  `;
}

// Redirect to new event details page
function viewEventDetails(tag) {
  if (!tag) {
      showNotification('Event tag missing', 'error');
      return;
  }
  window.location.href = `pages/event-details.html?event=${tag}`;
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

  if (hotGrid) {
    hotGrid.innerHTML = eventsData.hot.length > 0 
      ? eventsData.hot.map(createEventCard).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No hot events at the moment</p>';
  }

  if (trendingGrid) {
    trendingGrid.innerHTML = eventsData.trending.length > 0 
      ? eventsData.trending.map(createEventCard).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No trending events at the moment</p>';
  }

  if (featuredGrid) {
    featuredGrid.innerHTML = eventsData.featured.length > 0 
      ? eventsData.featured.map(createEventCard).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No featured events at the moment</p>';
  }

  if (upcomingGrid) {
    upcomingGrid.innerHTML = eventsData.upcoming.length > 0 
      ? eventsData.upcoming.map(createEventCard).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No upcoming events at the moment</p>';
  }

  const nearbyGrid = document.getElementById('nearby-events-grid');
  if (nearbyGrid) {
    nearbyGrid.innerHTML = eventsData.nearby.length > 0 
      ? eventsData.nearby.map(createEventCard).join('') 
      : '<p style="text-align: center; color: #666; padding: 2rem;">No nearby events at the moment</p>';
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
  initSearch();
  initSmoothScroll();
  initHeaderScroll();
  initGoogleAuth();
}


// Run when DOM is loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

// Make shareEvent available globally
window.shareEvent = shareEvent;
