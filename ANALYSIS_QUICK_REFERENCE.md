# Eventra Codebase Analysis - Quick Reference

## File Location Summary

### Modal Files
```
client/js/modals.js                    - All client-side modals (1,219 lines)
admin/js/ticket-preview-helper.js      - Admin ticket design preview (102 lines)
admin/js/admin-main.js                 - Main admin JS (61,961 lines)
```

### Page Files
```
client/pages/events.html               - Client events page
client/pages/tickets.html              - Client tickets page
client/pages/payments.html             - Client payments page
client/pages/media.html                - Client media manager
admin/pages/events.html                - Admin events page
admin/pages/payments.html              - Admin payments page
admin/pages/tickets.html               - Admin tickets page
```

### JavaScript Handlers
```
client/js/events.js                    - Event CRUD operations (986 lines)
client/js/payments.js                  - Payment listing & operations (628 lines)
client/js/media-manager.js             - Media upload/browsing (33,785 lines)
admin/js/payments.js                   - Admin payments (20,804 lines)
```

### API Endpoints
```
api/events/get-events.php              - Get events list
api/events/get-event-details.php       - Get single event
api/events/create-event.php            - Create new event
api/events/update-event.php            - Update event
api/events/delete-event.php            - Soft delete (move to trash)
api/events/restore-event.php           - Restore from trash
api/events/force-delete-event.php      - Permanent delete
api/media/get-media.php                - Get media files
api/media/upload-media.php             - Upload new media
```

### Config Files
```
.env                                   - Environment config (APP_URL, DB, UPLOAD settings)
config/app.php                         - App constants (APP_URL, QR_SECRET)
config/database.php                    - Database connection setup
```

---

## Modal Functions Reference

### Client-Side (modals.js)

| Function | Line | Purpose |
|----------|------|---------|
| `showProfileEditModal()` | 7 | Edit user profile |
| `showEventPreviewModal(eventId)` | 391 | View event details |
| `showEditEventModal(event)` | 703 | Edit event details |
| `showTicketPreviewModal(ticket)` | 1031 | View ticket with barcode |
| `showUserPreviewModal(user)` | 1109 | View user profile |

### Admin-Side (ticket-preview-helper.js)

| Function | Line | Purpose |
|----------|------|---------|
| `showTicketDesignPreview(eventId)` | 5 | Preview ticket design mockup |

---

## Image Handling Quick Lookup

### Event Modal Image Path
```javascript
// File: client/js/modals.js:432-450
// Variable: event.image_path
// From DB: "/uploads/events/filename.jpg"
// Transform: ../../../uploads/events/filename.jpg
// Logic: 3 levels up from client/pages/, handles leading slash
```

### Ticket Modal Image Path
```javascript
// File: client/js/modals.js:1032-1035
// Variable: ticket.event_image  
// From DB: "/uploads/events/filename.jpg"
// Transform: ../..//uploads/events/filename.jpg (DOUBLE SLASH!)
// Logic: Simple 2-level check, NO leading slash handling
```

### Admin Preview Image Path
```javascript
// File: admin/js/ticket-preview-helper.js:13-28
// Variable: row.dataset.image
// From DB: "/uploads/events/filename.jpg"
// Transform: //uploads/events/filename.jpg (SINGLE SLASH!)
// Logic: Different from client - only prepends "/" if no protocol/slash
```

---

## Database Table References

### events table
```sql
- id (PK)
- client_id (FK)
- event_name
- image_path          -- Stored as "/uploads/events/filename.jpg"
- deleted_at          -- NULL = active, timestamp = soft deleted
- status              -- "published", "draft", "scheduled", "cancelled"
- event_visibility    -- "public", "private"
```

### media table
```sql
- id (PK)
- client_id (FK)
- folder_id (FK)
- file_name           -- Display name
- file_path           -- Stored as "/uploads/media/filename.jpg" 
- file_size
- file_type           -- Extension: jpg, png, etc.
- is_deleted          -- 0 = active, 1 = trash
```

### clients table
```sql
- id (PK)
- client_auth_id      -- Auth ID from auth table
- profile_pic         -- Image path for client profile
```

---

## Upload Path Configuration

### Hardcoded in PHP
```
Event Images:  /uploads/events/
Media Files:   /uploads/media/
```

### From .env
```
APP_URL=http://localhost:8000/
UPLOAD_MAX_SIZE=15M
UPLOAD_ALLOWED_TYPES=jpg,jpeg,png,webp,avif
```

### Missing from Config
```
- BASE_PATH (can't change root path)
- UPLOAD_BASE (can't change upload location)
- IMAGE_SERVER (no CDN support)
- THUMBNAIL_PATH (no image optimization)
```

---

## Key Issues by Component

### Event Modal
- ❌ Path logic: 3-level transformation
- ❌ No image validation  
- ❌ No error handlers
- ✅ Handles external URLs

### Ticket Modal
- ❌ Path logic: 2-level, doesn't check `/` prefix
- ❌ Creates double slashes (`../..///uploads/`)
- ❌ No error handlers
- ✅ Has gradient fallback

### Admin Preview
- ❌ Path logic: Different from client
- ❌ Single `/` prepend only
- ✅ Has API fallback
- ✓ Async image load

### Media Manager
- ❌ Returns raw paths, no URL assembly
- ❌ No nested folder support
- ✓ Caches results locally
- ✓ Supports trash view

### Event Management
- ❌ No file cleanup on delete
- ❌ Orphaned files after soft-delete
- ❌ Path inconsistency (created vs displayed)
- ✓ Soft delete support

---

## Current Flow Diagram

```
User Uploads Event Image
    ↓
api/events/create-event.php
    ↓ Save to disk
/uploads/events/abc123.jpg
    ↓ Store in DB
events.image_path = "/uploads/events/abc123.jpg"
    ↓
showEventPreviewModal() fetches from API
    ↓
JavaScript transforms path
/uploads/events/abc123.jpg → ../../../uploads/events/abc123.jpg
    ↓
Browser loads from relative path
/uploads/events/abc123.jpg (resolves correctly)
    ↓
Image displays in modal

BUT IN TICKET MODAL:
/uploads/events/abc123.jpg → ../..//uploads/events/abc123.jpg (WRONG!)
    ↓
Browser requests: /../../uploads/events/abc123.jpg (404!)
```

---

## Deployment Context

### Local Development
- URL: `http://localhost:8000/`
- Upload path: `/uploads/events/` at document root
- All relative paths work: `../../uploads/events/`

### Production (if in subdirectory)
- URL: `http://example.com/eventra/`
- Upload path: Still `/uploads/events/` (hardcoded!)
- Relative paths break: `../../uploads/events/` goes outside app folder

### With CDN
- Not supported - no configuration for external image URLs
- Would require code changes in every modal

---

## Performance Impact

### Image Loading
- Event modal: 3 path checks + 1 string compare
- Ticket modal: 2 path checks + regex replace
- No lazy loading of images
- No thumbnail caching

### Database Queries
- `get-media.php`: 1 folder count query per file
- Media listing: O(n) queries for folder contents
- No pagination in media API

### Caching
- localStorage cache of media list (with timeout)
- No image caching headers
- No CDN cache support

---

## Testing Checklist for Image Fixes

- [ ] Event with uploaded image displays in event modal
- [ ] Ticket with event image displays correctly
- [ ] Ticket barcode renders without layout issues
- [ ] User profile pictures load from database
- [ ] Event images display in admin preview
- [ ] Media manager shows uploaded images
- [ ] Broken images don't crash application
- [ ] URLs work from different subdirectories
- [ ] External image URLs still work
- [ ] Deleted events don't leave broken images
- [ ] Trash restore shows images correctly
- [ ] Permission checks don't bypass access controls

