#!/bin/bash
# Cron job runner for processing ticket queue
# Add to crontab: */5 * * * * /home/mein/Documents/Eventra/scripts/process-tickets.sh

cd /home/mein/Documents/Eventra || exit 1
php api/utils/process-ticket-queue.php
