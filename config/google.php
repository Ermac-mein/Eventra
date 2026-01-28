<?php
// Google API configuration (Sign-in, Maps)
return [
    'client_id' => getenv('GOOGLE_CLIENT_ID'),
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
    'maps_api_key' => getenv('GOOGLE_MAPS_API_KEY')
];