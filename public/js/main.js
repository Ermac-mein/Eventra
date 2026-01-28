// Event data
const eventsData = {
  trending: [
    {
      id: 1,
      title: 'Music Festival',
      location: 'Accra',
      price: '₦ 250',
      status: 'Buy Ticket',
      image: 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600&h=400&fit=crop'
    },
    {
      id: 2,
      title: 'Naming Ceremony',
      location: 'Tema',
      price: 'Free',
      status: 'Register',
      image: 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=600&h=400&fit=crop'
    },
    {
      id: 3,
      title: 'Technology Seminar',
      location: 'Accra',
      price: '₦ 250',
      status: 'Buy Ticket',
      image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&h=400&fit=crop'
    }
  ],
  upcoming: [
    {
      id: 4,
      title: 'Church Launching',
      location: 'Kumasi',
      price: 'Free',
      status: 'More Info',
      image: 'https://images.unsplash.com/photo-1438232992991-995b7058bbb3?w=600&h=400&fit=crop'
    },
    {
      id: 5,
      title: 'Campaign',
      location: 'Takoradi',
      price: 'Free',
      status: 'Save Slot',
      image: 'https://images.unsplash.com/photo-1557804506-669a67965ba0?w=600&h=400&fit=crop'
    },
    {
      id: 6,
      title: 'Cultural Day',
      location: 'Cape Coast',
      price: 'Free',
      status: 'More Info',
      image: 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600&h=400&fit=crop'
    }
  ],
  nearby: [
    {
      id: 7,
      title: 'Synergy Summit',
      location: 'East Legon',
      price: 'Free',
      status: 'Register',
      image: 'https://images.unsplash.com/photo-1466611653911-95081537e5b7?w=600&h=400&fit=crop'
    },
    {
      id: 8,
      title: 'Wonderfest',
      location: 'Osu',
      price: '₦ 200',
      status: 'Buy Ticket',
      image: 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&h=400&fit=crop'
    },
    {
      id: 9,
      title: 'Enchanted Evening',
      location: 'Labadi',
      price: '₦ 200',
      status: 'Buy Ticket',
      image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&h=400&fit=crop'
    }
  ]
};

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
        window.location.href = user.role === 'admin' ? '/Eventra/admin/index.html' : '/Eventra/client/index.html';
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
  const actionText = event.status || 'Buy Ticket';
  const isBuyAction = actionText.toLowerCase().includes('buy') || actionText.toLowerCase().includes('register');
  
  return `
    <div class="event-card">
      <img src="${event.image}" alt="${event.title}" class="event-image" loading="lazy">
      <div class="event-info">
        <div class="event-header">
          <div>
            <h3 class="event-title">${event.title}</h3>
          </div>
          <span class="share-icon" onclick="shareEvent(${event.id})" title="Share">⋮</span>
        </div>
        <div class="event-details">
          <p class="event-location">${event.location}</p>
          <p class="event-price">${event.price}</p>
        </div>
        <div class="event-footer">
          <button class="event-status-btn" onclick="${isBuyAction ? `buyTicket(${event.id})` : ''}">${actionText}</button>
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
  const trendingGrid = document.getElementById('trending-events');
  const upcomingGrid = document.getElementById('upcoming-events');
  const nearbyGrid = document.getElementById('nearby-events');

  if (trendingGrid) {
    trendingGrid.innerHTML = eventsData.trending.map(createEventCard).join('');
  }

  if (upcomingGrid) {
    upcomingGrid.innerHTML = eventsData.upcoming.map(createEventCard).join('');
  }

  if (nearbyGrid) {
    nearbyGrid.innerHTML = eventsData.nearby.map(createEventCard).join('');
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
  renderEvents();
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
