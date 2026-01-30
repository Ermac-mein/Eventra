// Event data - will be loaded from API
let eventsData = {
  hot: [],
  trending: [],
  featured: [],
  upcoming: []
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
      eventsData.upcoming = result.events.filter(e => e.priority === 'normal' || !e.priority);

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

// User icon click handler
function initUserIcon() {
  const userIcon = document.querySelector('.user-icon');
  if (userIcon) {
    userIcon.addEventListener('click', () => {
      if (isAuthenticated()) {
        const user = storage.get('user');
        window.location.href = user.role === 'admin' ? '/admin/index.html' : '/client/index.html';
      } else {
        window.location.href = 'login.html';
      }
    });
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
  
  if (query) {
    console.log('Searching for:', query);
    // Add search functionality here
    // For now, just filter visible events
    filterEvents(query);
  }
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
  
  return `
    <div class="event-card">
      <img src="${event.image_path || 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600&h=400&fit=crop'}" alt="${event.event_name}" class="event-image" loading="lazy">
      <div class="event-info">
        <div class="event-header">
          <div>
            <h3 class="event-title">${event.event_name}</h3>
          </div>
          <span class="share-icon" onclick="shareEvent(${event.id})" title="Share">⋮</span>
        </div>
        <div class="event-details">
          <p class="event-location">${event.state}</p>
          <p class="event-price">${price}</p>
        </div>
        <div class="event-footer">
          <button class="event-status-btn" onclick="buyTicket(${event.id})">${actionText}</button>
        </div>
      </div>
    </div>
  `;
}

// Buy ticket handler
function buyTicket(eventId) {
  if (handleAuthRedirect()) {
    // Proceed to buy ticket
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
}


// Share event function
function shareEvent(eventId) {
  console.log('Sharing event:', eventId);
  // Add share functionality here
  if (navigator.share) {
    navigator.share({
      title: 'Check out this event!',
      text: 'I found this amazing event on Eventra',
      url: window.location.href
    }).catch(err => console.log('Error sharing:', err));
  } else {
    alert('Share functionality coming soon!');
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
}


// Run when DOM is loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

// Make shareEvent available globally
window.shareEvent = shareEvent;
