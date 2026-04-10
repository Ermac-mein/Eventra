# Eventra Codebase - Directory & File Map

## Project Root Structure
```
/home/mein/Documents/Eventra/
├── .env                          ← Configuration (APP_URL, DB settings, upload limits)
├── .env.backup
├── .env.example
├── .htaccess                      ← Apache routing
├── index.php                      ← Entry point
├── composer.json                  ← PHP dependencies
├── package.json                   ← Node dependencies (if any)
├── docker-compose.yml             ← Docker setup
├── Dockerfile
├── README.md
│
├── admin/                         ← Admin panel
│   ├── pages/
│   │   ├── adminDashboard.html   ← Admin dashboard
│   │   ├── adminLogin.html
│   │   ├── clients.html           ← Manage clients
│   │   ├── events.html            ← Admin event management
│   │   ├── payments.html          ← Admin payments view
│   │   ├── tickets.html           ← Admin ticket management
│   │   └── users.html             ← Manage users
│   │
│   ├── js/
│   │   ├── admin-main.js          ← Main admin logic (61,961 lines)
│   │   ├── admin-auth.js
│   │   ├── admin-chart.js
│   │   ├── adminLogin.js
│   │   ├── dashboard.js
│   │   ├── event-preview.js
│   │   ├── notification-manager.js
│   │   ├── payments.js            ← Admin payments handler
│   │   ├── profile-pic-upload.js
│   │   ├── search-manager.js
│   │   ├── ticket-preview-helper.js  ← TICKET MODAL CODE
│   │   └── toast-notification.js
│   │
│   └── css/
│       └── admin-style.css
│
├── client/                        ← Client portal
│   ├── pages/
│   │   ├── clientDashboard.html
│   │   ├── clientLogin.html
│   │   ├── events.html            ← Client event management
│   │   ├── media.html             ← Media manager
│   │   ├── payments.html          ← Client payments view
│   │   ├── scanner.html
│   │   ├── signup.html
│   │   ├── tickets.html           ← Client tickets view
│   │   └── users.html
│   │
│   ├── js/
│   │   ├── client-main.js
│   │   ├── clientLogin.js
│   │   ├── clientSignup.js
│   │   ├── create-event.js        ← Event creation form logic
│   │   ├── dashboard.js
│   │   ├── deleted-event-modal.js
│   │   ├── drawer-system.js
│   │   ├── events.js              ← Event CRUD (986 lines)
│   │   ├── export-manager.js
│   │   ├── media-manager.js       ← MEDIA MANAGER CODE (33,785 lines)
│   │   ├── modals.js              ← ALL MODALS (1,219 lines) ⭐
│   │   ├── notification-system.js
│   │   ├── payments.js            ← Payments handler (628 lines)
│   │   ├── paystack-banks.js
│   │   ├── performance-chart.js
│   │   ├── scanner.js
│   │   ├── schedule-notification-checker.js
│   │   ├── search-manager.js
│   │   ├── state-manager.js
│   │   ├── tickets.js             ← Ticket handler
│   │   └── users.js
│   │
│   └── css/
│       ├── client-style.css
│       └── modal-styles.css
│
├── api/                           ← REST API endpoints
│   ├── admin/
│   │   ├── get-clients.php
│   │   └── ... (other admin endpoints)
│   │
│   ├── auth/                      ← Authentication
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── signup.php
│   │   └── google-signin.php
│   │
│   ├── events/                    ← Event APIs ⭐
│   │   ├── create-event.php       ← IMAGE UPLOAD LOGIC
│   │   ├── delete-event.php       ← Soft delete
│   │   ├── delete-event-permanent.php
│   │   ├── force-delete-event.php ← Hard delete
│   │   ├── extract-flyer.php
│   │   ├── favorite.php
│   │   ├── get-event.php
│   │   ├── get-event-by-tag.php
│   │   ├── get-event-details.php
│   │   ├── get-events.php         ← Returns events with image_path
│   │   ├── get-trash.php
│   │   ├── get-upcoming-events.php
│   │   ├── publish-event.php
│   │   ├── restore-event.php      ← Restore from trash
│   │   ├── schedule-notification-cron.php
│   │   ├── search-events.php
│   │   └── update-event.php       ← IMAGE UPDATE LOGIC
│   │
│   ├── media/                     ← Media/File APIs ⭐
│   │   ├── create-folder.php
│   │   ├── delete-folder.php
│   │   ├── delete-media.php
│   │   ├── get-default-templates.php
│   │   ├── get-folder-contents.php
│   │   ├── get-media.php          ← MEDIA LIST WITH PATHS
│   │   ├── restore.php
│   │   ├── upload-file.php
│   │   └── upload-media.php       ← FILE UPLOAD LOGIC
│   │
│   ├── payments/
│   │   ├── get-payments.php
│   │   ├── get-refunds.php
│   │   └── process-payment.php
│   │
│   ├── tickets/
│   │   ├── get-tickets.php
│   │   ├── validate-ticket.php
│   │   └── update-ticket-status.php
│   │
│   ├── users/
│   │   ├── get-user.php
│   │   ├── update-user.php
│   │   └── upload-profile-pic.php
│   │
│   ├── utils/
│   │   ├── notification-helper.php
│   │   └── ... (other utilities)
│   │
│   ├── health.php
│   └── heartbeat.php
│
├── config/                        ← Configuration ⭐
│   ├── app.php                    ← APP_URL constant
│   ├── cors-config.php
│   ├── database.php               ← DB connection & constants
│   ├── email.php
│   ├── env-loader.php
│   ├── google.php
│   ├── payment.php
│   ├── session-config.php
│   └── sms.php
│
├── database/                      ← Database files
│   ├── migrations/
│   ├── seeds/
│   └── structure.sql
│
├── includes/                      ← Shared includes
│   ├── middleware/
│   │   ├── auth.php              ← Authentication checks
│   │   └── ... (other middleware)
│   └── ... (other shared code)
│
├── public/                        ← Public assets
│   ├── css/
│   │   ├── modals.css            ← Modal styles
│   │   ├── pagination.css
│   │   └── ... (other styles)
│   │
│   ├── js/
│   │   ├── storage.js            ← localStorage wrapper
│   │   ├── utils.js              ← Utility functions
│   │   ├── auth-controller.js    ← Auth state management
│   │   ├── auth-guard.js         ← Auth protection
│   │   └── ... (other JS)
│   │
│   ├── favicon.ico
│   └── ... (other public files)
│
├── uploads/                       ← User uploads (created at runtime)
│   ├── events/                   ← Event images go here
│   ├── media/                    ← Media files go here
│   ├── clients/                  ← Client profiles?
│   └── ... (other upload dirs)
│
├── error/                         ← Error logs
│   └── errors.log
│
├── logs/                          ← Application logs
│
├── sessions/                      ← Session storage
│
├── cron/                          ← Cron job scripts
│
├── docs/                          ← Documentation
│
├── vendor/                        ← Composer dependencies
│
└── .git/                          ← Git repository

```

---

## File Size Summary (Key Files)

| File | Size | Purpose |
|------|------|---------|
| admin/js/admin-main.js | 61,961 | Main admin logic + event management |
| client/js/modals.js | 1,219 | **ALL MODALS** (events, tickets, users, profiles) |
| client/js/media-manager.js | 33,785 | Media upload/browsing UI |
| client/js/events.js | 986 | Event CRUD operations |
| admin/js/payments.js | 20,804 | Admin payment dashboard |
| client/js/payments.js | 628 | Client payment listing |
| api/events/create-event.php | ~400 | Event creation + image upload |
| api/events/update-event.php | ~500 | Event update + image handling |
| api/media/get-media.php | 136 | Media list with file paths |
| api/media/upload-media.php | ~300 | Media upload handler |

---

## Entry Points

### Client Side
```
index.php
  ↓
client/pages/clientLogin.html
  ↓
client/js/clientLogin.js → Login validation
  ↓
client/pages/clientDashboard.html (if authenticated)
  ↓
Other pages as user navigates
```

### Admin Side
```
index.php?role=admin
  ↓
admin/pages/adminLogin.html
  ↓
admin/js/adminLogin.js → Login validation
  ↓
admin/pages/adminDashboard.html (if authenticated)
  ↓
Other pages as admin navigates
```

### API Requests
```
All requests → /api/[endpoint].php
  ↓
Middleware check (auth.php)
  ↓
Execute logic
  ↓
Return JSON response
```

---

## Image File Paths in Code

### Stored in Database
```
/uploads/events/filename.jpg          (from create-event.php)
/uploads/media/filename.jpg           (from upload-media.php)
/uploads/clients/profile.jpg          (user profile pictures)
```

### Referenced in JavaScript
```
Event Modal:       ../../../uploads/events/filename.jpg
Ticket Modal:      ../..//uploads/events/filename.jpg (DOUBLE SLASH!)
Admin Preview:     //uploads/events/filename.jpg
Media Manager:     ../../uploads/media/filename.jpg
User Avatar:       ../../[profile_pic_path]
```

### External URLs
```
https://ui-avatars.com/api/?name=...  (default user avatars)
https://images.unsplash.com/...       (default event images)
```

---

## Configuration Chain

```
.env
  ↓ (loaded by)
config/env-loader.php
  ↓ (used by)
config/app.php
config/database.php
config/cors-config.php
  ↓ (required by)
All API endpoints
All page logic
```

---

## Key Dependencies

### External Libraries (from CDN in HTML)
```
sweetalert2@11                        (Modal alerts)
lucide@latest                         (Icons)
jsbarcode@3.11.5                      (Barcode rendering)
jspdf@2.5.1                           (PDF export)
jspdf-autotable@3.5.31                (Table to PDF)
xlsx@0.18.5                           (Excel export)
ui-avatars.com                        (User avatars)
```

### Internal Utilities
```
public/js/storage.js                  (localStorage wrapper)
public/js/utils.js                    (Helper functions)
public/js/auth-controller.js          (Auth state)
includes/middleware/auth.php          (Auth checks)
```

