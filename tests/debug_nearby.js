
// Mock Data
const user = {
    state: 'Lagos',
    city: 'Ikeja'
};

const events = [
    { id: 1, event_name: 'Lagos Event', state: 'Lagos', city: 'Lekki' },
    { id: 2, event_name: 'Abuja Event', state: 'FCT', city: 'Abuja' },
    { id: 3, event_name: 'Ikeja Event', state: 'Lagos', city: 'Ikeja' },
    { id: 4, event_name: 'No State Event', state: null, city: null },
    { id: 5, event_name: 'Mismatch Event', state: 'Ogun', city: 'Abeokuta' }
];

// Logic from main.js
const userState = user?.state?.toLowerCase();
const userCity = user?.city?.toLowerCase();

console.log('User State:', userState);
console.log('User City:', userCity);

let nearbyEvents = [];

if (userState || userCity) {
    nearbyEvents = events.filter(e => 
        (userState && e.state?.toLowerCase() === userState) || 
        (userCity && e.city?.toLowerCase() === userCity)
    );
}

console.log('Nearby Events found:', nearbyEvents.length);
nearbyEvents.forEach(e => console.log(`- ${e.event_name} (${e.state}, ${e.city})`));

// Test Case 2: User with no location
const userNoLoc = {};
const userState2 = userNoLoc?.state?.toLowerCase();
const userCity2 = userNoLoc?.city?.toLowerCase();

let nearbyEvents2 = [];
if (userState2 || userCity2) {
    nearbyEvents2 = events.filter(e => 
        (userState2 && e.state?.toLowerCase() === userState2) || 
        (userCity2 && e.city?.toLowerCase() === userCity2)
    );
} else {
    // Fallback?
    console.log('User has no location, nearby is empty.');
}
