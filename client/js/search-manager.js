/**
 * Smart Search Functionality
 * Implements search across events with filtering
 */

let allEvents = [];

async function initializeSearch() {
    const searchInput = document.querySelector('.header-search input');
    if (!searchInput) return;

    // Load all events for search
    await loadAllEventsForSearch();

    // Add search event listener with debounce
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(e.target.value);
        }, 300);
    });
}

async function loadAllEventsForSearch() {
    try {
        const user = storage.get('user');
        const response = await fetch(`../../api/events/get-events.php?client_id=${user.id}&limit=1000`);
        const result = await response.json();

        if (result.success && result.events) {
            allEvents = result.events;
        }
    } catch (error) {
        console.error('Error loading events for search:', error);
    }
}

function performSearch(query) {
    if (!query || query.trim().length < 2) {
        hideSearchResults();
        return;
    }

    const searchTerm = query.toLowerCase().trim();
    
    // Search across multiple fields
    const results = allEvents.filter(event => {
        return (
            event.event_name?.toLowerCase().includes(searchTerm) ||
            event.description?.toLowerCase().includes(searchTerm) ||
            event.state?.toLowerCase().includes(searchTerm) ||
            event.event_type?.toLowerCase().includes(searchTerm) ||
            event.tag?.toLowerCase().includes(searchTerm)
        );
    });

    displaySearchResults(results, query);
}

function displaySearchResults(results, query) {
    // Remove existing results
    let resultsContainer = document.getElementById('searchResults');
    
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.id = 'searchResults';
        resultsContainer.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 8px;
        `;
        
        const searchContainer = document.querySelector('.header-search');
        searchContainer.style.position = 'relative';
        searchContainer.appendChild(resultsContainer);
    }

    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div style="padding: 1.5rem; text-align: center; color: var(--client-text-muted);">
                No results found for "${query}"
            </div>
        `;
        resultsContainer.style.display = 'block';
        return;
    }

    resultsContainer.innerHTML = results.map(event => `
        <div class="search-result-item" onclick="goToEvent(${event.id})" style="
            padding: 1rem;
            border-bottom: 1px solid #f1f4f8;
            cursor: pointer;
            transition: background 0.2s;
        " onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
            <div style="font-weight: 600; margin-bottom: 0.25rem;">${highlightText(event.event_name, query)}</div>
            <div style="font-size: 0.875rem; color: var(--client-text-muted); margin-bottom: 0.25rem;">
                ${event.event_type} • ${event.state} • ${event.status}
            </div>
            <div style="font-size: 0.75rem; color: var(--client-text-muted);">
                ${event.event_date} at ${event.event_time}
            </div>
        </div>
    `).join('');

    resultsContainer.style.display = 'block';

    // Close results when clicking outside
    document.addEventListener('click', function closeResults(e) {
        if (!resultsContainer.contains(e.target) && !document.querySelector('.header-search input').contains(e.target)) {
            hideSearchResults();
            document.removeEventListener('click', closeResults);
        }
    });
}

function hideSearchResults() {
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        resultsContainer.style.display = 'none';
    }
}

function highlightText(text, query) {
    if (!query) return text;
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark style="background: #fef3c7; padding: 0 2px;">$1</mark>');
}

function goToEvent(eventId) {
    // If on events page, scroll to event
    // Otherwise, navigate to events page
    window.location.href = `events.html?highlight=${eventId}`;
    hideSearchResults();
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeSearch();
});

// Make functions globally available
window.performSearch = performSearch;
window.goToEvent = goToEvent;
