#!/bin/bash

# Eventra Local Server Starter
# This script starts a local PHP server with a router to handle COOP headers and clean paths.

PORT=8000

echo "------------------------------------------------"
echo "Starting Eventra Local Server on http://localhost:$PORT"
echo "Using router.php to handle Google Auth headers (COOP)"
echo "------------------------------------------------"

# Check if PHP is installed
if ! command -v php &> /dev/null
then
    echo "ERROR: PHP is not installed. Please install PHP to run this server."
    exit 1
fi

# Start the server
php -S localhost:$PORT .agents/scripts/router.php
