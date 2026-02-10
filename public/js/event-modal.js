// Event Details Modal Functions
let currentEventData = null;

function openEventDetailsModal(eventData) {
  currentEventData = eventData;
  const modal = document.getElementById('eventDetailsModal');
  
  // Create simplified modal content for users
  const modalContent = modal.querySelector('.modal-content');
  if (!modalContent) return;
  
  const eventImage = eventData.image_path || '../assets/default-event.jpg';
  const eventDate = new Date(eventData.event_date).toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
  const eventPrice = eventData.price > 0 ? `₦${parseFloat(eventData.price).toLocaleString()}` : 'Free';
  
  // Simplified modal HTML without link, tags, and attendees
  modalContent.innerHTML = `
    <button class="modal-close" onclick="closeEventDetailsModal()" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.9); border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center;">&times;</button>
    
    <div style="position: relative; height: 300px; overflow: hidden; border-radius: 16px 16px 0 0; margin: -2rem -2rem 2rem -2rem;">
      <img src="${eventImage}" style="width: 100%; height: 100%; object-fit: cover;" alt="${eventData.event_name}">
    </div>
    
    <div style="padding: 0 1rem;">
      <h2 style="font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 0.5rem; line-height: 1.2;">${eventData.event_name}</h2>
      <p style="color: #6b7280; font-size: 1rem; margin-bottom: 2rem;">Organized by ${eventData.client_name || 'Eventra'}</p>
      
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">📅</div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Date</div>
            <div style="font-weight: 700; color: #374151; font-size: 0.95rem;">${eventDate}</div>
          </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">🕒</div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Time</div>
            <div style="font-weight: 700; color: #374151; font-size: 0.95rem;">${eventData.event_time}</div>
          </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">📍</div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Location</div>
            <div style="font-weight: 700; color: #374151; font-size: 0.95rem;">${eventData.city || eventData.state || 'TBD'}</div>
          </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">🎟️</div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Price</div>
            <div style="font-weight: 700; color: #374151; font-size: 0.95rem;">${eventPrice}</div>
          </div>
        </div>
      </div>
      
      <div style="margin-bottom: 2rem;">
        <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📝 About This Event</label>
        <div style="color: #4b5563; line-height: 1.7; background: #f9fafb; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb;">${eventData.description || 'No description available.'}</div>
      </div>
      
      <div style="margin-bottom: 2rem;">
        <label style="display: block; font-size: 0.85rem; color: #111827; margin-bottom: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">📂 Category</label>
        <div style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 0.5rem 1.25rem; border-radius: 30px; font-weight: 700; font-size: 0.9rem;">${eventData.event_type || eventData.category || 'General'}</div>
      </div>
      
      <!-- Buy Ticket Button -->
      <div style="margin-top: 2.5rem; padding-top: 2rem; border-top: 2px solid #e5e7eb;">
        <button onclick="handleBuyTicket()" style="width: 100%; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; padding: 1.25rem 2rem; border-radius: 12px; font-size: 1.1rem; font-weight: 800; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); text-transform: uppercase; letter-spacing: 0.05em;">
          🎟️ Buy Ticket Now
        </button>
      </div>
    </div>
  `;
  
  // Show modal
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeEventDetailsModal() {
  const modal = document.getElementById('eventDetailsModal');
  modal.classList.remove('active');
  document.body.style.overflow = '';
  currentEventData = null;
}

function handleBuyTicket() {
  if (currentEventData) {
    if (currentEventData.tag) {
      window.location.href = `event-details.html?event=${currentEventData.tag}`;
    } else {
      window.location.href = `buy-ticket.html?id=${currentEventData.id}`;
    }
  }
}

// Close modal on backdrop click
document.addEventListener('click', (e) => {
  const modal = document.getElementById('eventDetailsModal');
  if (modal && e.target === modal) {
    closeEventDetailsModal();
  }
});

// Enhanced Search Functionality
let searchTimeout = null;
let allEventsData = [];

function initializeEnhancedSearch() {
  const searchInput = document.querySelector('.search-input');
  if (!searchInput) return;
  
  // Create search results dropdown
  const searchContainer = document.querySelector('.search-container');
  if (searchContainer && !searchContainer.querySelector('.search-results-dropdown')) {
    const dropdown = document.createElement('div');
    dropdown.className = 'search-results-dropdown';
    dropdown.id = 'searchResultsDropdown';
    searchContainer.appendChild(dropdown);
  }
  
  // Add input event listener
  searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 2) {
      hideSearchResults();
      return;
    }
    
    searchTimeout = setTimeout(() => {
      performEnhancedSearch(query);
    }, 300);
  });
  
  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (searchContainer && !searchContainer.contains(e.target)) {
      hideSearchResults();
    }
  });
}

function performEnhancedSearch(query) {
  const dropdown = document.getElementById('searchResultsDropdown');
  if (!dropdown) return;
  
  const lowerQuery = query.toLowerCase();
  
  // Filter events by name, category, location, date, description, priority, or tags
  const results = allEventsData.filter(event => {
    return (
      event.event_name.toLowerCase().includes(lowerQuery) ||
      (event.category && event.category.toLowerCase().includes(lowerQuery)) ||
      (event.event_type && event.event_type.toLowerCase().includes(lowerQuery)) ||
      (event.city && event.city.toLowerCase().includes(lowerQuery)) ||
      (event.state && event.state.toLowerCase().includes(lowerQuery)) ||
      (event.event_date && event.event_date.includes(lowerQuery)) ||
      (event.description && event.description.toLowerCase().includes(lowerQuery)) ||
      (event.priority && event.priority.toLowerCase().includes(lowerQuery)) ||
      (event.tag && event.tag.toLowerCase().includes(lowerQuery))
    );
  }).slice(0, 5); // Limit to 5 results
  
  if (results.length === 0) {
    dropdown.innerHTML = '<div class="search-result-item">No events found</div>';
  } else {
    dropdown.innerHTML = results.map(event => {
      const eventStr = JSON.stringify(event).replace(/"/g, '&quot;');
      return `
        <div class="search-result-item" onclick='openEventDetailsModal(${eventStr})'>
          <strong>${event.event_name}</strong>
          <span class="search-category-badge">${event.event_type || event.category || 'Event'}</span>
          <br>
          <small style="color: #666;">${event.city || event.state || 'Location TBD'} • ${new Date(event.event_date).toLocaleDateString()}</small>
        </div>
      `;
    }).join('');
  }
  
  dropdown.classList.add('active');
}

function hideSearchResults() {
  const dropdown = document.getElementById('searchResultsDropdown');
  if (dropdown) {
    dropdown.classList.remove('active');
  }
}

// Make functions globally available
window.openEventDetailsModal = openEventDetailsModal;
window.closeEventDetailsModal = closeEventDetailsModal;
window.handleBuyTicket = handleBuyTicket;
window.allEventsData = allEventsData;

// Initialize enhanced search when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  initializeEnhancedSearch();
});
