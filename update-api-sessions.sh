#!/bin/bash
# Script to remove session_start() from all API files
# Since session is now handled centrally in database.php

API_FILES=(
    "/home/mein/Documents/Eventra/api/stats/get-dashboard-stats.php"
    "/home/mein/Documents/Eventra/api/events/get-event.php"
    "/home/mein/Documents/Eventra/api/events/get-events.php"
    "/home/mein/Documents/Eventra/api/events/create-event.php"
    "/home/mein/Documents/Eventra/api/events/publish-event.php"
    "/home/mein/Documents/Eventra/api/users/update-profile.php"
    "/home/mein/Documents/Eventra/api/users/get-profile.php"
    "/home/mein/Documents/Eventra/api/media/get-media.php"
    "/home/mein/Documents/Eventra/api/media/create-folder.php"
    "/home/mein/Documents/Eventra/api/media/delete-media.php"
    "/home/mein/Documents/Eventra/api/media/upload-media.php"
    "/home/mein/Documents/Eventra/api/media/upload-file.php"
    "/home/mein/Documents/Eventra/api/notifications/create-notification.php"
    "/home/mein/Documents/Eventra/api/auth/google-handler.php"
    "/home/mein/Documents/Eventra/api/auth/logout.php"
    "/home/mein/Documents/Eventra/api/auth/login.php"
    "/home/mein/Documents/Eventra/api/tickets/purchase-ticket.php"
    "/home/mein/Documents/Eventra/api/auth/google-signin.php"
)

echo "Removing session_start() from API files..."
for file in "${API_FILES[@]}"; do
    if [ -f "$file" ]; then
        # Remove the line containing session_start();
        sed -i '/^session_start();$/d' "$file"
        echo "✓ Updated: $file"
    else
        echo "✗ File not found: $file"
    fi
done

echo ""
echo "Done! All API files updated."
