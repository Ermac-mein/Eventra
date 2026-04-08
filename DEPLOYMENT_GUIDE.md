# Eventra Deployment Guide for InfinityFree

## Overview
This guide documents all fixes applied to resolve critical authentication and session issues on the InfinityFree shared hosting platform.

## Issues Fixed

### 1. Login Redirect Loop (FIXED)
**Problem**: After successful login, users were immediately redirected back to login page.
**Root Cause**: Session data cleared during regeneration before being properly set.
**Solution**: 
- Modified session regeneration to preserve CSRF token
- Re-set all auth variables after regeneration
- Call `session_write_close()` to persist session immediately

**Files Changed**:
- `api/auth/login.php` (lines 142-182)
- `api/auth/google-handler.php` (lines 225-268)
- `config/session-config.php` (enhanced path detection)

### 2. API 500 Errors - getallheaders() Not Available (FIXED)
**Problem**: All API endpoints returned 500 errors with "Call to undefined function getallheaders()".
**Root Cause**: InfinityFree uses FastCGI/CGI, not Apache mod_php.
**Solution**: Implemented getallheaders() polyfill with fallback to $_SERVER parsing.

**Files Changed**:
- `includes/middleware/auth.php` (lines 1-40)
- `api/auth/check-session.php` (polyfill added)
- `api/emails/send-email.php` (polyfill added)

### 3. Session Persistence - Users Logged Out on Navigation (FIXED)
**Problem**: Clicking sidebar navigation logged out clients with "Client profile not found" error.
**Root Cause**: 
- Session variables not set when Bearer token used
- Profile-specific IDs not properly looked up and stored
- checkAuth() returning wrong type of ID

**Solution**:
- Enhanced checkAuth() to set ALL session variables when Bearer token validated
- Stored both auth_id and role-specific ID (client_id, admin_id, user_id) in session
- Added fallback profile creation if profile record missing
- Returns correct profile-specific ID, not auth_id

**Files Changed**:
- `includes/middleware/auth.php` (enhanced checkAuth function)
- `client/js/clientLogin.js` (added window.storage.setToken() call)
- `admin/js/adminLogin.js` (added window.storage.setToken() calls for both auth methods)
- `public/js/auth-guard.js` (added 8-second timeout with local auth fallback)
- `api/auth/login.php` (fallback profile creation)
- `api/auth/google-handler.php` (fallback profile creation)

### 4. Missing Profile Records (FIXED)
**Problem**: Some users authenticated but had no corresponding client/admin/user record.
**Root Cause**: Profile lookups returning false instead of ID, not re-attempting creation.
**Solution**: Added fallback logic to create missing profile records during login.

**Files Changed**:
- `api/auth/login.php` (lines 156-197)
- `api/auth/google-handler.php` (lines 244-288)

### 5. API Endpoint Errors (PARTIALLY FIXED)
**Problem**: 
- `verify-identity.php` returning 404
- `get-tickets.php` returning "Client profile not found"
- `get-notifications.php` returning "Too many connections"

**Solutions Applied**:
- Simplified verify-identity.php to use checkAuth() directly
- Enhanced error handling in get-tickets.php
- Session flushing mechanism to ensure data persistence

**Files Changed**:
- `api/clients/verify-identity.php` (simplified auth logic)
- `api/tickets/get-tickets.php` (better error handling)

## Files Ready for Upload

### CRITICAL (Must Upload)
1. **includes/middleware/auth.php** - Core auth logic with getallheaders() polyfill
2. **api/auth/login.php** - Fixed session regeneration and profile fallback
3. **api/auth/google-handler.php** - OAuth fixes with profile fallback
4. **client/js/clientLogin.js** - Token storage for browser
5. **admin/js/adminLogin.js** - Token storage for admin panel

### RECOMMENDED (Upload)
6. **public/js/auth-guard.js** - Enhanced timeout handling
7. **config/session-config.php** - Improved shared hosting compatibility
8. **api/auth/check-session.php** - getallheaders() polyfill added
9. **api/clients/verify-identity.php** - Simplified auth check
10. **api/tickets/get-tickets.php** - Better error handling

### OPTIONAL (Upload for improvements)
11. **api/emails/send-email.php** - getallheaders() polyfill added

## Deployment Steps

### Step 1: Backup Current Files
Before uploading new files, backup your current versions in case you need to rollback.

### Step 2: Upload Critical Files (Priority)
Upload these 5 files first as they form the foundation:
1. includes/middleware/auth.php
2. api/auth/login.php
3. api/auth/google-handler.php
4. client/js/clientLogin.js
5. admin/js/adminLogin.js

### Step 3: Clear Browser Cache
After uploading JavaScript files, tell users to:
- Hard refresh their browser (Ctrl+Shift+R or Cmd+Shift+R)
- Clear cookies for eventra.lovestoblog.com (optional but recommended)
- Close and reopen browser

### Step 4: Upload Recommended Files
Upload remaining API and utility files to complete the fixes.

### Step 5: Test Complete Flow

#### Client Portal Test:
1. Go to https://eventra.lovestoblog.com/client/pages/clientLogin.html
2. Login with test client credentials
3. Verify redirected to dashboard (NOT login page)
4. Click sidebar buttons (Dashboard, Tickets, Media, etc.)
5. Verify NOT logged out after each navigation
6. Check browser console for errors

#### Admin Portal Test:
1. Go to https://eventra.lovestoblog.com/admin/pages/adminLogin.html
2. Login with admin credentials
3. Verify redirected to dashboard
4. Click sidebar buttons
5. Verify session persists

#### Test API Calls:
Open browser DevTools (F12) → Network tab
- Verify API calls return 200 OK (not 500)
- Check for Bearer tokens in request headers
- Verify response contains expected data

## Testing Checklist

- [ ] Login page loads without JavaScript errors
- [ ] Login succeeds with correct credentials
- [ ] Redirected to dashboard (not login) after login
- [ ] Can navigate sidebar without logout
- [ ] API calls return 200 (check Network tab)
- [ ] Can create/view events
- [ ] Can view tickets
- [ ] Can access media/documents
- [ ] Profile information loads
- [ ] No 500 errors in console
- [ ] No "Client profile not found" errors
- [ ] Session persists for 30+ minutes

## Troubleshooting

### Users Still Redirected to Login After Login
**Check**: 
1. Verify session file created: `/sessions/EVENTRA_CLIENT_SESS_*`
2. Verify login.php calling session_write_close() on line 182
3. Check error logs for database errors
4. Clear session folder and try again

### "Call to undefined function getallheaders()"
**Check**:
1. Verify includes/middleware/auth.php contains polyfill (lines 1-40)
2. Verify check-session.php has polyfill (lines 12-38)
3. Verify send-email.php has polyfill (if using)

### 500 Errors on API Calls
**Check**:
1. Browser console for error details
2. Server error logs: check InfinityFree error logs
3. Verify Bearer token being sent (DevTools Network tab)
4. Verify session file exists and readable

### "Client profile not found"
**Check**:
1. Verify user's profile record exists in database
2. Verify client_auth_id matches auth_accounts.id
3. Test with newly created user (should auto-create profile)

### Premature Logout on Navigation
**Check**:
1. Verify session timeout not too aggressive (default: 30 min)
2. Verify token not expiring (default: 24 hours)
3. Check for browser privacy mode (blocks some storage)
4. Clear cookies and try again

## Session Configuration Details

### Session Names (Role-Based)
- Clients: `EVENTRA_CLIENT_SESS`
- Admins: `EVENTRA_ADMIN_SESS`
- Users: `EVENTRA_USER_SESS`

### Session Variables Set During Login
- `auth_id` - auth_accounts.id (global)
- `client_id` - clients.id (for clients)
- `admin_id` - admins.id (for admins)
- `user_id` - users.id (for users)
- `role` - user's role
- `user_role` - user's role (legacy)
- `auth_token` - Bearer token for API calls
- `last_activity` - timestamp for activity tracking

### Bearer Token Storage
JavaScript stores token in browser localStorage after login:
- Location: window.storage (via storage-manager.js)
- Retrieved in API calls via auth-guard.js
- Validates token format and expiry

## Performance Considerations

### Database Connection Pooling
InfinityFree has limited connections. If seeing "Too many connections":
1. Check for connection leaks (ensure mysqli_close() or PDO::close())
2. Monitor active connections
3. Consider reducing polling frequency
4. Set connection timeout

### Session File Size
Large sessions can slow down I/O:
1. Monitor sessions folder size
2. Consider implementing session cleanup
3. Set reasonable session timeout (30 min default)

## Security Notes

1. **Session Regeneration**: All logins regenerate session ID for security
2. **CSRF Token**: Preserved during regeneration, validated on state-changing operations
3. **Bearer Tokens**: Stored in database with expiry, revoked on logout
4. **Password Hashing**: bcrypt with cost factor 12
5. **Email Verification**: Available but optional

## Git Commit References

Key commits implementing these fixes:
- d8abb8f - Fix login redirect loop on shared hosting
- 82a5853 - Fix getallheaders() unavailability on InfinityFree
- 781b31a - Add getallheaders() polyfill to all endpoints
- 8016ab1 - Fix permanent session issues (prevent premature logout)
- 0c677f4 - Fix profile ID lookup failures with fallback creation

## Support

If issues persist after deployment:
1. Check InfinityFree control panel for errors/logs
2. Enable PHP error logging in .htaccess
3. Verify file permissions (644 for PHP files)
4. Check database user privileges
5. Monitor server resource usage (CPU, memory, connections)

## Next Steps After Deployment

1. **Monitor logs** for errors over 24 hours
2. **Gather user feedback** on stability
3. **Test with multiple users** simultaneously
4. **Verify performance** under load
5. **Consider optimizations** based on real usage
