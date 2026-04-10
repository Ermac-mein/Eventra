# Eventra Image Path Handling - Technical Details

## Image Path Transformation Logic Across Components

### 1. EVENT MODAL (client/js/modals.js:432-450)

**Source**: `showEventPreviewModal()` function
**Image Path Variable**: `event.image_path`

```javascript
const eventImage = event.image_path || 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop';

// Transformation logic:
src="${eventImage.startsWith('http') ? eventImage : (eventImage.startsWith('/') ? '../..' + eventImage : '../../' + eventImage)}"

// Decision tree:
// 1. If starts with 'http' → Use as-is (external URL)
// 2. Else if starts with '/' → Prepend '../..' (to escape from client/pages/)
// 3. Else → Prepend '../../' (relative path from current location)
```

**From**: `/client/pages/events.html`
**Relative path to root**: `../../`
**Examples**:
- Database: `/uploads/events/flyer.jpg` → `../../../uploads/events/flyer.jpg`
- Database: `uploads/events/flyer.jpg` → `../../uploads/events/flyer.jpg`
- Database: `https://example.com/image.jpg` → `https://example.com/image.jpg` (unchanged)

### 2. TICKET MODAL (client/js/modals.js:1032-1035)

**Source**: `showTicketPreviewModal()` function
**Image Path Variable**: `ticket.event_image`

```javascript
const imgSrc = ticket.event_image
    ? (ticket.event_image.startsWith('http') ? ticket.event_image : '../../' + ticket.event_image)
    : null;

const heroBg = imgSrc ? `url("${imgSrc.replace(/"/g, '%22')}")` : 'linear-gradient(135deg, #6366f1 0%, #2ecc71 100%)';

// Simpler logic - only 2 levels:
// 1. If starts with 'http' → Use as-is
// 2. Else → Always prepend '../../'
// Note: Does NOT check for '/' prefix
```

**From**: `/client/pages/tickets.html`
**Relative path to root**: `../../`
**Issue**: Inconsistent with event modal - doesn't handle `/uploads/` paths correctly

**Examples**:
- Database: `/uploads/events/flyer.jpg` → `../..//uploads/events/flyer.jpg` (double slash!)
- Database: `uploads/events/flyer.jpg` → `../../uploads/events/flyer.jpg`

### 3. USER PROFILE PICTURE (client/js/modals.js:24, 1112)

**Source**: Various modals
**Image Path Variable**: `user.profile_pic`

```javascript
// In profile edit modal:
src="${user.profile_pic || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random&size=160`}"

// In user preview modal:
const hasValidUrl = user.profile_pic && user.profile_pic.startsWith('http');
const profileImage = user.profile_pic 
    ? (hasValidUrl ? user.profile_pic : `../../${user.profile_pic}`)
    : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=random&size=150`;
```

**Issue**: No validation - if profile_pic is invalid, will fail silently

### 4. ADMIN TICKET PREVIEW (admin/js/ticket-preview-helper.js:5-28)

**Source**: `showTicketDesignPreview()` function  
**Image Path Variable**: `row.dataset.image` or fetched from API

```javascript
let eventImage = row.dataset.image;

if (!eventImage) {
    // Fallback: Fetch from API
    fetch(`/api/events/get-event.php?id=${eventId}`)
        .then(r => r.json())
        .then(result => {
            if (result.success && result.event) {
                eventImage = result.event.image_path || 'https://images.unsplash.com/...';
            }
        })
} else if (!eventImage.startsWith('http') && !eventImage.startsWith('/')) {
    // Only prepend '/' if no protocol and no leading slash
    eventImage = '/' + eventImage;
}

// Final image used in: <img src="${eventImage}" ...>
```

**From**: `/admin/pages/events.html`
**Issue**: Different logic than client - uses single `/` prefix instead of `../../`

---

## Database Storage vs Display

### Storage Format (api/events/create-event.php):
```php
// Files saved to disk:
$target_path = __DIR__ . '/../../uploads/events/' . $new_filename;
move_uploaded_file($_FILES['event_image']['tmp_name'], $target_path);

// Stored in database:
$image_path = "/uploads/events/" . basename($target_path);  // e.g., "/uploads/events/abc123.jpg"
```

### Display Format (JavaScript modals):
```javascript
// Event modal expects: /uploads/events/abc123.jpg → transforms to ../..//uploads/events/abc123.jpg
// Ticket modal expects: /uploads/events/abc123.jpg → transforms to ../..//uploads/events/abc123.jpg
// Admin preview expects: /uploads/events/abc123.jpg → transforms to //uploads/events/abc123.jpg
```

---

## Media Table Storage (api/media/get-media.php):

```php
// When syncing from events table:
$path = $img['image_path'];  // Contains: "/uploads/events/abc.jpg"
$pdo->prepare("INSERT INTO media (..., file_path, ...) VALUES (?, ?, ...)")
    ->execute([$client_id, $event_assets_folder_id, $name, $path, ...]);
    
// Returned to frontend:
'file_path' => '/uploads/events/abc.jpg'  // Same as events table

// Frontend then needs to transform this path again!
```

---

## Configuration Values

From `.env`:
```
APP_URL=http://localhost:8000/
APP_NAME=Eventra
```

From `config/app.php`:
```php
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8000');
// No other path constants defined!
```

---

## Current Hardcoded Paths

### In PHP APIs:
- `/uploads/events/` - for event images
- `/uploads/media/` - for media files  
- `/uploads/clients/` - potentially for client images

### In JavaScript:
- `../../` - from client/pages/ to root
- `../../../` - from client/pages/ with leading slash
- `/` - from admin/pages/ (single level)
- `https://ui-avatars.com/api/` - external service
- `https://images.unsplash.com/` - external service

---

## Fallback/Default Images

1. **Event Modal**: `https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop`
2. **Ticket Modal**: Linear gradient `linear-gradient(135deg, #6366f1 0%, #2ecc71 100%)`
3. **User Avatar**: `https://ui-avatars.com/api/?name=...&background=random`
4. **Admin Ticket Preview**: `https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&fit=crop`

---

## Missing Error Handling

### Image Tags Without Error Handlers:
- All `<img>` tags in modals
- All background-image styles in modals
- No `onerror` callbacks
- No fallback on 404

### Potential Issues:
- Broken image shown for missing files
- No logging of broken images
- No user feedback on load failure
- Cascading failures in dependent UIs

---

## Path Construction Issues Summary

| Component | Location | Method | Issues |
|-----------|----------|--------|--------|
| Event Modal | client/pages | 3-level check | Overcomplicated, inconsistent |
| Ticket Modal | client/pages | 2-level check | Missing `/` handling, double slashes |
| Admin Preview | admin/pages | Single check | Different logic from client |
| User Avatar | Any | 2-level check | No validation |
| Media Page | client/pages | Cache + API | Returns raw paths, needs transformation |

