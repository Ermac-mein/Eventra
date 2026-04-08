# Quick Upload Guide - Files to Upload to InfinityFree

## CRITICAL (Upload First - 5 Files)

These 5 files FIX THE LOGIN AND SESSION ISSUES:

```
1. includes/middleware/auth.php
2. api/auth/login.php  
3. api/auth/google-handler.php
4. client/js/clientLogin.js
5. admin/js/adminLogin.js
```

**Action**: Upload these 5 files first, test, then upload remaining files.

---

## RECOMMENDED (Upload Next - 6 Files)

These files improve reliability and fix remaining issues:

```
6. public/js/auth-guard.js
7. config/session-config.php
8. api/auth/check-session.php
9. api/clients/verify-identity.php
10. api/tickets/get-tickets.php
11. api/emails/send-email.php (optional)
```

---

## Upload via FTP/File Manager

### Directory Structure to Preserve

```
eventra/
├── includes/
│   └── middleware/
│       └── auth.php                    ← FILE 1
├── api/
│   ├── auth/
│   │   ├── login.php                   ← FILE 2
│   │   ├── google-handler.php          ← FILE 3
│   │   └── check-session.php           ← FILE 8
│   ├── clients/
│   │   └── verify-identity.php         ← FILE 9
│   ├── tickets/
│   │   └── get-tickets.php             ← FILE 10
│   ├── emails/
│   │   └── send-email.php              ← FILE 11 (optional)
├── client/
│   └── js/
│       └── clientLogin.js              ← FILE 4
├── admin/
│   └── js/
│       └── adminLogin.js               ← FILE 5
├── public/
│   └── js/
│       └── auth-guard.js               ← FILE 6
└── config/
    └── session-config.php              ← FILE 7
```

---

## Testing After Upload (2 minutes)

### 1. Client Login Test
- Go to: https://eventra.lovestoblog.com/client/pages/clientLogin.html
- Enter client credentials and login
- ✅ Should redirect to DASHBOARD (NOT login page)
- ✅ Check browser console (F12) - no red errors

### 2. Navigation Test  
- Click "Dashboard" on sidebar → stays logged in
- Click "Tickets" on sidebar → stays logged in
- ✅ Should NOT redirect to login after any click

### 3. API Test
- Open DevTools (F12) → Network tab
- Reload page
- ✅ Look for `/api/tickets/get-tickets.php` 
- ✅ Should show Status: 200 (NOT 500)
- ✅ Response should be JSON

### 4. Admin Login Test
- Go to: https://eventra.lovestoblog.com/admin/pages/adminLogin.html
- Enter admin credentials and login
- ✅ Should redirect to ADMIN DASHBOARD
- ✅ Navigation should work without logout

---

## If Problems After Upload

### Problem: Still Getting Login Loop
```
Fix Steps:
1. Clear browser cookies (Settings → Privacy → Cookies for eventra.lovestoblog.com)
2. Hard refresh page (Ctrl+Shift+R or Cmd+Shift+R)
3. Try private/incognito window
4. Check InfinityFree error logs
```

### Problem: 500 Errors on API Calls
```
Fix Steps:
1. Check DevTools Network tab - what's the error?
2. Verify Bearer token in request headers
3. Try creating a NEW user account (should auto-create profile)
4. Check getallheaders() polyfill in includes/middleware/auth.php (lines 1-40)
```

### Problem: "Client profile not found"
```
Fix Steps:
1. Ensure profile exists in database for that user
2. Try logging in with a newly created user
3. Database should auto-create profile if missing
```

---

## File Permissions

After upload, set permissions via FTP File Manager:

- **PHP Files**: 644 (readable by web server)
- **Directories**: 755 (executable by web server)

InfinityFree usually sets these automatically, but verify if having issues.

---

## Session Folder

Make sure this folder exists and is writable:
```
/home/username/public_html/eventra/sessions/
```

If not exist, create it:
- Via FTP: Create folder named `sessions`
- Set permissions: 755
- Should be at same level as `public` folder

---

## After Successful Upload

✅ Commit changes to keep track:
```bash
git add -A
git commit -m "Deployed fixes to InfinityFree - session and auth fixes"
```

✅ Tell users to test:
- Clear browser cache
- Try logging in again
- Test all sidebar navigation

✅ Monitor for 24 hours:
- Watch for error logs
- Get user feedback
- No "Client profile not found" errors
- No redirect loops

---

## Emergency Rollback

If critical issues occur:

1. FTP back to your original files (you did backup, right?)
2. Re-upload original versions
3. Clear browser cache
4. Test again

Files are just PHP and JavaScript - no database changes, so completely safe to rollback.

---

## Final Checklist Before Deploying

- [ ] Backed up current files?
- [ ] Downloaded all 11 files locally?
- [ ] Verified directory structure?
- [ ] Ready to upload via FTP?
- [ ] Have InfinityFree credentials?
- [ ] Can access InfinityFree file manager?
- [ ] Understand how to test after upload?
- [ ] Know how to check browser console (F12)?
- [ ] Know how to check Network tab (DevTools)?

---

## Questions?

Refer to `DEPLOYMENT_GUIDE.md` for detailed explanations of each fix.

Refer to `FILES_CHANGED_SUMMARY.txt` for what each file does.

Good luck! 🚀
