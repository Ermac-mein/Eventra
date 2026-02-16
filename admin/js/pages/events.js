document.addEventListener('DOMContentLoaded', async () => {
    const eventsTableBody = document.querySelector('table tbody');
    const statsValues = document.querySelectorAll('.stat-value');
    let allEvents = [];
    let filteredEvents = [];
    let sortConfig = { key: null, direction: 'asc' };
    
    // Filter elements
    const statusFilter = document.getElementById('statusFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const priceFilter = document.getElementById('priceFilter');
    const attendeeFilter = document.getElementById('attendeeFilter');

    async function loadEvents() {
        try {
            const response = await apiFetch('../../api/admin/get-all-events.php');
            const result = await response.json();

            if (result.success) {
                allEvents = result.events;
                applyFilters(); // Apply current filters
                updateStats(result.stats);
            } else {
                console.error('Failed to load events:', result.message);
            }
        } catch (error) {
            console.error('Error fetching events:', error);
        }
    }

    function renderEvents(events) {
        if (!eventsTableBody) return;
        
        if (events.length === 0) {
            eventsTableBody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 2rem; color: #999;">No events found</td></tr>';
            return;
        }

        eventsTableBody.innerHTML = events.map(event => {
            // Determine display status (handle soft-deleted)
            let displayStatus = event.status;
            let statusClass = event.status;
            
            if (event.deleted_at) {
                displayStatus = 'deleted';
                statusClass = 'cancelled'; // Mapping to existing CSS class
            }

            const dateStr = window.formatDateLong ? formatDateLong(event.event_date) : new Date(event.event_date).toLocaleDateString();
            return `
                <tr data-id="${event.id}">
                    <td>${event.event_name}</td>
                    <td><span class="priority-badge ${event.priority || 'low'}">${(event.priority || 'Low').toUpperCase()}</span></td>
                    <td>${dateStr}</td>
                    <td>${event.event_time.substring(0, 5)}</td>
                    <td>${event.event_type}</td>
                    <td>${event.phone || 'N/A'}</td>
                    <td>${event.price > 0 ? '₦' + parseFloat(event.price).toLocaleString() : 'Free'}</td>
                    <td class="text-center">${event.attendee_count || 0}</td>
                    <td><span class="tag-badge">${event.tag || 'None'}</span></td>
                    <td><a href="${event.link || '#'}" target="_blank" class="link-btn"><i data-lucide="external-link"></i></a></td>
                    <td><span class="status-badge status-${statusClass}">${displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1)}</span></td>
                </tr>
            `;
        }).join('');

        // Re-initialize previews for new rows
        if (window.initPreviews) {
            window.initPreviews();
        }
    }

    function applyFilters() {
        filteredEvents = allEvents.filter(event => {
            // Status Filter
            const statusMatch = statusFilter.value === 'all' || 
                              (statusFilter.value === 'cancelled' ? event.deleted_at : event.status === statusFilter.value);
            
            // Category Filter
            const categoryMatch = categoryFilter.value === 'all' || event.event_type === categoryFilter.value;
            
            // Price Filter
            let priceMatch = true;
            const price = parseFloat(event.price) || 0;
            if (priceFilter.value === 'free') priceMatch = price === 0;
            if (priceFilter.value === 'paid') priceMatch = price > 0;
            if (priceFilter.value === 'premium') priceMatch = price > 50000;
            
            // Attendee Filter
            let attendeeMatch = true;
            const count = parseInt(event.attendee_count) || 0;
            if (attendeeFilter.value === '0-50') attendeeMatch = count <= 50;
            if (attendeeFilter.value === '51-200') attendeeMatch = count > 50 && count <= 200;
            if (attendeeFilter.value === '201+') attendeeMatch = count > 200;
            
            return statusMatch && categoryMatch && priceMatch && attendeeMatch;
        });

        // Maintain sorting if active
        if (sortConfig.key) {
            const currentConfig = { ...sortConfig };
            sortConfig.key = null; // Reset to force sort
            sortEvents(currentConfig.key, currentConfig.direction);
        } else {
            renderEvents(filteredEvents);
        }
    }

    function sortEvents(key, forcedDirection = null) {
        if (forcedDirection) {
            sortConfig.key = key;
            sortConfig.direction = forcedDirection;
        } else if (sortConfig.key === key) {
            sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortConfig.key = key;
            sortConfig.direction = 'asc';
        }

        // Update UI headers
        document.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
            if (th.dataset.sort === key) {
                th.classList.add(sortConfig.direction);
            }
        });

        const targetList = filteredEvents.length > 0 || anyFilterActive() ? filteredEvents : allEvents;
        const sortedEvents = [...targetList].sort((a, b) => {
            let valA = a[key];
            let valB = b[key];

            // Handle price and attendee_count as numbers
            if (key === 'price' || key === 'attendee_count') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            } else {
                valA = (valA || '').toString().toLowerCase();
                valB = (valB || '').toString().toLowerCase();
            }

            if (valA < valB) return sortConfig.direction === 'asc' ? -1 : 1;
            if (valA > valB) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });

        renderEvents(sortedEvents);
    }

    function anyFilterActive() {
        return statusFilter.value !== 'all' || 
               categoryFilter.value !== 'all' || 
               priceFilter.value !== 'all' || 
               attendeeFilter.value !== 'all';
    }

    // Initialize sort listeners
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            sortEvents(th.dataset.sort);
        });
    });

    // Initialize filter listeners
    [statusFilter, categoryFilter, priceFilter, attendeeFilter].forEach(el => {
        el.addEventListener('change', applyFilters);
    });

    function updateStats(stats) {
        if (!stats || statsValues.length < 5) return;

        // stats from API: total, published, deleted, scheduled, restored
        statsValues[0].textContent = stats.total || 0;
        statsValues[1].textContent = stats.published || 0;
        statsValues[2].textContent = stats.deleted || 0;
        statsValues[3].textContent = stats.scheduled || 0;
        statsValues[4].textContent = stats.restored || 0;
    }

    // Initial load
    await loadEvents();

    // Task 3: Real-time synchronization (10s polling)
    setInterval(loadEvents, 10000);
});
