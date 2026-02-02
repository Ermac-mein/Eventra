document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const eventTag = urlParams.get('event');
    const clientName = urlParams.get('client');

    // Capture referral if client is in URL
    if (clientName) {
        sessionStorage.setItem('referral_client', clientName);
        console.log('Referral captured:', clientName);
    }

    if (!eventTag) {
        showNotification('Event not specified', 'error');
        setTimeout(() => window.location.href = 'index.html', 2000);
        return;
    }

    await loadEventDetails(eventTag);
});

async function loadEventDetails(tag) {
    try {
        const response = await fetch(`../../api/events/get-event-by-tag.php?tag=${tag}`);
        const result = await response.json();

        if (result.success) {
            const event = result.event;
            renderEvent(event);
        } else {
            showNotification(result.message || 'Event not found', 'error');
            setTimeout(() => window.location.href = 'index.html', 2000);
        }
    } catch (error) {
        console.error('Error loading event:', error);
        showNotification('System error occurred', 'error');
    }
}

function renderEvent(event) {
    document.title = `${event.event_name} - Eventra`;
    document.getElementById('eventTitle').textContent = event.event_name;
    document.getElementById('eventSummary').textContent = event.event_type;
    document.getElementById('eventDescription').textContent = event.description;
    document.getElementById('eventAddress').textContent = `${event.address || 'N/A'}, ${event.state}`;
    document.getElementById('eventDate').textContent = formatDate(event.event_date);
    document.getElementById('eventTime').textContent = event.event_time;
    document.getElementById('clientName').textContent = event.client_name || 'Anonymous Organiser';
    document.getElementById('eventPrice').textContent = `₦${parseFloat(event.price).toLocaleString()}`;
    
    const hero = document.getElementById('eventHero');
    if (event.image_path) {
        hero.style.backgroundImage = `url(${event.image_path})`;
    }

    // Priority badge style
    const badge = document.getElementById('priorityBadge');
    badge.textContent = event.priority || 'Event';
    if (event.priority === 'hot') badge.style.background = '#ff4757';
    if (event.priority === 'trending') badge.style.background = '#3742fa';
    if (event.priority === 'recommended') badge.style.background = '#2ed573';

    // Attendee stacking logic
    const stack = document.getElementById('attendeeStack');
    const count = event.attendee_count || 0;
    const iconsCount = Math.min(count, 5);
    
    stack.innerHTML = '';
    for (let i = 0; i < iconsCount; i++) {
        const icon = document.createElement('img');
        icon.className = 'attendee-icon';
        icon.src = `https://ui-avatars.com/api/?name=User+${i}&background=random`;
        stack.appendChild(icon);
    }
    
    document.getElementById('attendeeCountDisplay').textContent = `${count} people attending`;

    // Booking logic
    document.getElementById('bookNowBtn').onclick = () => {
        handleBooking(event.id);
    };
}

async function handleBooking(eventId) {
    if (!isAuthenticated()) {
        handleAuthRedirect(window.location.href);
        return;
    }

    const quantity = document.getElementById('ticketQuantity').value;
    const referral = sessionStorage.getItem('referral_client');

    try {
        const response = await fetch('../../api/tickets/purchase-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                event_id: eventId,
                quantity: parseInt(quantity),
                referred_by_client: referral // Pass the referral name/id
            })
        });

        const result = await response.json();
        if (result.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Your tickets have been booked successfully.',
                icon: 'success'
            }).then(() => {
                window.location.href = '../../client/pages/tickets.html'; // Or wherever tickets are viewed
            });
        } else {
            showNotification(result.message, 'error');
        }
    } catch (error) {
        console.error('Booking error:', error);
        showNotification('Booking failed', 'error');
    }
}
