# Eventra Codebase Analysis - Image Handling & Modal Structure

## 1. KEY MODAL HANDLING FILES

### Files Involved:
- `client/js/modals.js` (1,219 lines)
- `client/js/payments.js` (628 lines)
- `client/js/events.js` (986 lines)
- `admin/js/admin-main.js` (61,961 lines)
- `admin/js/ticket-preview-helper.js` (102 lines)
- `client/pages/events.html`
- `client/pages/payments.html`
- `client/pages/tickets.html`
- `admin/pages/events.html`
- `admin/pages/payments.html`
- `admin/pages/tickets.html`

### Current Implementation:

**Event Modal** (`showEventPreviewModal()` in modals.js, line 391):
- Displays event details with hero image section
- Image handling: `event.image_path` with fallback URL routing
- Path logic: If image starts with `http` use directly, else prepend `../../`
- Uses event data from API: `/api/events/get-event-details.php`

**Ticket Modal** (`showTicketPreviewModal()` in modals.js, line 1031):
- Displays individual ticket with event image hero section
- Shows barcode using JsBarcode library
- Image handling: Same logic as event modal - prepends `../../` for relative paths
- Supports both uploaded images and fallback gradient

**User Modal** (`showUserPreviewModal()` in modals.js, line 1109):
- Shows user profile with avatar
- Avatar: Uses uploaded `profile_pic` or falls back to `https://ui-avatars.com` API

**Admin Ticket Preview** (`showTicketDesignPreview()` in admin/js/ticket-preview-helper.js, line 5):
- Mock ticket preview showing design
- Image handling: Fetches from `image_path` property or via API
- Path prepending: Only if not starting with `http` or `/`

### Issues Found:
- **Inconsistent path handling**: Some modals prepend `../../`, others check for `/` prefix
- **No image validation**: No checks for broken image paths before display
- **Hardcoded relative paths**: Makes code fragile across different URL contexts
- **No error callbacks**: Missing image error handling (would show broken image icons)
- **Mixed path formats**: Some paths use `/uploads/`, others use relative routes

---

## 2. IMAGE HANDLING IN MODALS

### Event Modal Image Handling:
**File**: `client/js/modals.js:432-450`
```
const eventImage = event.image_path || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';
src="${eventImage.startsWith('http') ? eventImage : (eventImage.startsWith('/') ? '../..' + eventImage : '../../' + eventImage)}"
```

**Transaction/Payment Modal**:
- No dedicated modal found in current code
- Payment transactions rendered in table format, not modals
- Detail view uses row data, not modal popups

### Ticket Modal Image Handling:
**File**: `client/js/modals.js:1032-1035`
```
const imgSrc = ticket.event_image
    ? (ticket.event_image.startsWith('http') ? ticket.event_image : '../../' + ticket.event_image)
    : null;
const heroBg = imgSrc ? `url("${imgSrc.replace(/"/g, '%22')}")` : 'linear-gradient(135deg, #6366f1 0%, #2ecc71 100%)';
```

### Admin Ticket Preview Image:
**File**: `admin/js/ticket-preview-helper.js:13-28`
```
let eventImage = row.dataset.image;
if (!eventImage.startsWith('http') && !eventImage.startsWith('/')) {
    eventImage = '/' + eventImage;
}
```
- Falls back to unsplash URL if no image
- Supports fetching from API if data-image not set

### Issues Found:
- **Event modal**: 3-level path transformation needed (`../../`)
- **Ticket modal**: Uses `../../` directly, inconsistent with event modal logic
- **Admin preview**: Uses single `/` prefix only
- **No validation**: Doesn't verify image exists before rendering
- **Quote escaping issue**: `imgSrc.replace(/"/g, '%22')` assumes quotes in URL (fragile)
- **Missing onerror handlers**: Images fail silently

---

## 3. MEDIA PAGE STRUCTURE

### Files Involved:
- `client/pages/media.html` (10,812 lines)
- `client/js/media-manager.js` (33,785 lines)
- `api/media/get-media.php` (136 lines)
- `api/media/upload-media.php` (varies)

### Current Implementation:

**Media API** (`api/media/get-media.php`):
- Auto-creates "Event Assets" folder on first access
- Syncs images from `events` table to media storage
- Returns media list with: `file_name`, `file_path`, `file_size`, `file_type`
- Supports folder navigation and trash view
- Tracks: total_folders, total_files, total_size, total_deleted

**Media Manager** (`client/js/media-manager.js`):
- Loads media via `apiFetch('/api/media/get-media.php')`
- Caches media list in localStorage with status (active/trash)
- Displays in grid format with card UI
- Supports sorting: by date, name, or size
- Features: folder browsing, search highlighting, drag-drop upload

**Media Upload** (`api/media/upload-media.php`):
- Saves files to `/uploads/media/` directory
- Creates folder structure for organization
- File size tracking in database

### Issues Found:
- **No image URL generation**: Returns file_path but no full URL assembly
- **Static folder structure**: No nested subfolder support (no parent_id column)
- **Mixed path references**: Uses both `file_path` and `image_path` terminology
- **No image preview URLs**: Media listing doesn't include thumbnail/preview data
- **Sync logic limitation**: Only syncs existing event images to media table

---

## 4. EVENT MANAGEMENT

### Files Involved:
- `api/events/get-events.php` (6,249 bytes)
- `api/events/get-event-details.php`
- `api/events/delete-event.php` (soft delete)
- `api/events/restore-event.php`
- `api/events/force-delete-event.php` (permanent)
- `api/events/create-event.php`
- `api/events/update-event.php`

### Current Implementation:

**Event Creation** (`api/events/create-event.php`):
- Handles file upload via `$_FILES['event_image']`
- Saves to `/uploads/events/` directory
- Stores path as `/uploads/events/filename.ext` in database
- Logs file operations with upload timestamp

**Event Modal Form** (`client/js/modals.js:703-1028`):
- Edit form includes image upload input
- Preview shows current image with upload button overlay
- Image field: `event_image` in form data
- Stores in media database after creation

**Delete/Restore Functionality**:
- **Soft delete** (`delete-event.php`): Sets `deleted_at = NOW()`
- **Restore** (`restore-event.php`): Clears `deleted_at` to NULL
- **Force delete** (`force-delete-event.php`): Permanent removal from database
- Permissions: Clients can only delete/restore own events, admins can do any

**Event Visibility**:
- Filters in `get-events.php`: Only show published + public events to non-admins
- Query check: `e.deleted_at IS NULL` everywhere

### Issues Found:
- **No image cleanup**: Deleted events' images remain in `/uploads/events/`
- **Path inconsistency**: Created events use `/uploads/events/`, but event_image field in modal references relative `../../`
- **No image validation**: Doesn't verify image dimensions, format, or MIME type
- **Soft delete orphans media**: Event images not linked in media table, so deleting event leaves orphaned files
- **Media sync one-way**: Event images synced to media on first load, but not updated when event image changes

---

## 5. CONFIGURATION

### Files Involved:
- `.env` (71 lines)
- `config/app.php` (17 lines)
- `config/database.php` (72 lines)
- `config/env-loader.php`

### APP_URL Configuration:
```
APP_URL=http://localhost:8000/
APP_NAME=Eventra
APP_ENV=local
APP_DEBUG=true
```

### Upload Configuration:
```
UPLOAD_MAX_SIZE=15M
UPLOAD_ALLOWED_TYPES=jpg,jpeg,png,webp,avif
```

### Current Upload Paths:
- Events: `/uploads/events/` (hardcoded in API)
- Media: `/uploads/media/` (hardcoded in API)
- No BASE_PATH variable - paths are relative to root

### Database Paths:
- Events store path as: `/uploads/events/filename.ext`
- Media table stores: relative `file_path`
- No absolute URL generation in API responses

### Issues Found:
- **No BASE_PATH config**: Making deployment across subdirectories impossible
- **Hardcoded /uploads paths**: Can't change upload location without code changes
- **No APP_ROOT constant**: Can't dynamically construct full URLs
- **Missing image URL constant**: No configuration for serving image URLs from API
- **No CDN support**: Can't configure external image hosting

---

## SUMMARY TABLE

| Area | Implementation | Key Issues |
|------|---|---|
| **Event Modal** | Template in modals.js, renders event details | Inconsistent path handling, no error handlers |
| **Ticket Modal** | Shows ticket with barcode + event image hero | 3-level relative path traversal, fragile |
| **Payment Modal** | Not implemented as modal, uses table rows | N/A |
| **Image Paths** | Mixed relative (`../../`) and absolute (`/uploads/`) | No centralized URL building |
| **Media Storage** | Flat folder structure in `/uploads/media/` | No nested folders, sync limited to event creation |
| **Event Images** | Stored in `/uploads/events/` at creation | No cleanup on delete, orphaned files remain |
| **Configuration** | .env only, no BASE_PATH or URL constants | Can't work on subdirectories or with CDN |
| **Delete/Restore** | Soft delete (set deleted_at), restore, force delete | Image files not cleaned up on permanent delete |

---

## RECOMMENDED NEXT STEPS

1. **Centralize image URL generation** - Create utility function to build full URLs
2. **Add APP_ROOT and BASE_PATH to .env** - Enable flexible deployment
3. **Image validation** - Add MIME type, size, dimension checks
4. **Cleanup on delete** - Delete image files when events are permanently removed
5. **Error handling** - Add onerror callbacks to all img tags
6. **Consistent path handling** - Single method for all path transformations
7. **Update media sync** - Trigger sync when event images change, not just at creation
