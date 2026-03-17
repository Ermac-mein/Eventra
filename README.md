# Eventra - Event Management System

An event web-based platform

## Project Setup

1. Configure your database in `config/database.php`.
2. Ensure the `logs/` directory is writable by the web server.
3. Run `database/schema.sql` to initialize the database.

## Automated Tasks (Cron Job)

To handle auto-publishing scheduled events and sending pre-event notifications (5-10 minutes before start), add the following to your crontab:

```bash
* * * * * php /home/mein/Documents/Eventra/scripts/publish-scheduled-events.php >> /home/mein/Documents/Eventra/logs/scheduler.log 2>&1
```

## Features
- Real-time notifications
- Custom ID generation (`USR-`, `CLI-`, `TIC-`, `TXN-`)
- Paystack Payment Integration
- PDF/Excel/CSV Data Export with row selection
- Dynamic Swiper.js Event Carousel on homepage
- Automated Heartbeat & Online Status Tracking
