# Eventra - Post-Audit Deployment Checklist

## Before Going Live

### 1. Environment Configuration
- [ ] Copy `.env.example` to `.env`
- [ ] Set all required variables in `.env`:
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`
  - [ ] `JWT_SECRET` - Generate: `openssl rand -hex 32`
  - [ ] `QR_SECRET` - Generate: `openssl rand -hex 32`
  - [ ] `CRON_SECRET` - Generate: `openssl rand -hex 32`
  - [ ] `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`
  - [ ] `TERMII_API_KEY`, `TERMII_SECRET_KEY`, `TERMII_SENDER_ID`
  - [ ] `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY` (live keys, not test)
  - [ ] `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
  - [ ] `MAIL_*` settings for email notifications

### 2. API Keys Rotation (CRITICAL)
- [ ] Rotate Paystack keys in production config
- [ ] Rotate Google OAuth credentials
- [ ] Rotate Termii SMS credentials
- [ ] Rotate Google Maps API key
- [ ] Generate new JWT_SECRET
- [ ] Generate new QR_SECRET
- [ ] Generate new CRON_SECRET
- [ ] **Do NOT commit .env file to version control**

### 3. Database Setup
- [ ] Verify MySQL/MariaDB is running
- [ ] Database `eventra_db` exists and is accessible
- [ ] Run migrations for new tables:
  ```sql
  -- New tables for authentication and scheduling
  - sessions (DB-backed session storage)
  - otp_requests (OTP with rate limiting)
  - job_queue (Persistent job scheduling)
  - rate_limits (Rate limiting tracking)
  ```
- [ ] Verify all new indexes are created
- [ ] Test database connection from app

### 4. File System Permissions
- [ ] `uploads/` directory exists with 0755 permissions
- [ ] `logs/` directory exists with 0755 permissions
- [ ] `sessions/` directory exists with 0700 permissions (private)
- [ ] Verify web server can write to these directories
- [ ] No world-writable directories (0777) - should all be 0755

### 5. Security Validation
- [ ] Verify `.env` is in `.gitignore` (not committed)
- [ ] Run: `grep -r "7de26102-2709-4da5" .` - should return 0 results
- [ ] Run: `grep -r "console\.log" client/ admin/ public/` - should return 0 results
- [ ] No database credentials visible in any file
- [ ] `.env.example` is present and documented

### 6. Session Management
- [ ] Test login/logout workflow
- [ ] Verify sessions persist across page reloads
- [ ] Test session expiry (default 2 hours)
- [ ] Verify sessions work in load-balanced environment (once DB-backed)
- [ ] Check `sessions` table for new records after login

### 7. OTP & Authentication
- [ ] Test forgot password flow:
  - [ ] User enters email
  - [ ] OTP sent to registered phone via SMS
  - [ ] User can verify OTP
  - [ ] Password reset completes successfully
- [ ] Verify OTP rate limiting:
  - [ ] Max 3 OTP requests per 15 minutes
  - [ ] Max 5 verification attempts per 15 minutes
  - [ ] Rate limit errors return proper messages
- [ ] Check `otp_requests` table for new records

### 8. File Uploads
- [ ] Test image upload (jpg, png, webp) - should work
- [ ] Test invalid file upload (php, exe) - should fail
- [ ] Verify MIME type validation
- [ ] Check file permissions on uploaded files (should be 0644)
- [ ] Verify no PHP files can be uploaded
- [ ] Test upload size limits

### 9. Data Exports
- [ ] Test user export - emails should be populated
- [ ] Test event export - all fields populated
- [ ] Test ticket export - correct data shown
- [ ] Verify export filenames have timestamps
- [ ] Check CSV encoding (UTF-8)

### 10. Monitoring & Health
- [ ] Test `/api/health` endpoint
- [ ] Should return HTTP 200 with healthy status
- [ ] Should show all checks passed
- [ ] Set up uptime monitoring (Pingdom, Uptime Robot, etc.)
- [ ] Configure email/SMS alerts for health check failures

### 11. API Endpoints
- [ ] POST `/api/auth/login` - working
- [ ] POST `/api/auth/forgot-password` - sends OTP
- [ ] POST `/api/auth/verify-forgot-password-otp` - verifies OTP
- [ ] POST `/api/auth/reset-password` - sets new password
- [ ] POST `/api/media/upload-media` - validates files
- [ ] GET `/api/export/export-data` - returns data
- [ ] GET `/api/health` - returns health status

### 12. Cron Jobs
- [ ] Verify `cron/scheduler.php` runs every 5 minutes
- [ ] Verify `scripts/publish-scheduled-events.php` publishes on schedule
- [ ] Check cron logs for errors
- [ ] Verify cronjob can execute PHP scripts
- [ ] Sample crontab entry:
  ```
  */5 * * * * /usr/bin/php /path/to/eventra/cron/scheduler.php >> /path/to/logs/cron.log 2>&1
  ```

### 13. Logging & Monitoring
- [ ] Verify error logs are being written
- [ ] Set up log rotation (logrotate or similar)
- [ ] Monitor error logs for issues
- [ ] Configure application-level monitoring
- [ ] Verify debug logs are disabled in production

### 14. SSL/TLS
- [ ] HTTPS certificate installed
- [ ] Redirect HTTP to HTTPS
- [ ] Set `SESSION_SECURE_COOKIE=true` in .env
- [ ] Update `APP_URL` to use HTTPS

### 15. Performance
- [ ] Database indexes are created (query performance)
- [ ] Test with multiple concurrent users
- [ ] Monitor server load and memory
- [ ] Check database query performance (slow query log)
- [ ] Consider caching strategy if needed

### 16. Backup & Disaster Recovery
- [ ] Database backups configured
- [ ] Backup schedule: daily minimum
- [ ] Verify backup restoration works
- [ ] Backup encryption enabled
- [ ] Off-site backup storage configured

### 17. Documentation
- [ ] Environment setup documented
- [ ] API endpoints documented
- [ ] Deployment procedure documented
- [ ] Troubleshooting guide available
- [ ] Team trained on new authentication flow

---

## Testing Scenarios

### Scenario 1: Forgot Password Flow
```
1. User visits forgot password page
2. Enters email address
3. OTP sent to registered phone via SMS
4. User enters OTP code
5. System shows password reset form
6. User enters new password (8+ chars, 1 number, 1 special char)
7. Password reset succeeds
8. User logs in with new password
```

### Scenario 2: File Upload Security
```
1. User uploads JPG image - ✅ Success
2. User uploads PHP file - ❌ Rejected
3. User uploads EXE file - ❌ Rejected
4. User uploads PDF - ✅ Success (if configured)
5. Verify file permissions are 0644
6. Verify no PHP execution in uploads directory
```

### Scenario 3: Rate Limiting
```
1. Request OTP from same IP 4 times in 15 minutes - 4th fails
2. Attempt to verify OTP 6 times with wrong code - 6th fails
3. Wait 15 minutes, retry succeeds
4. Different IP can still request OTP
```

---

## Post-Deployment Verification

After deployment, verify:
- [ ] Application loads without errors
- [ ] Login works correctly
- [ ] Forgot password OTP flow works end-to-end
- [ ] File uploads are validated
- [ ] Exports generate correctly
- [ ] Health endpoint responds
- [ ] Database tables exist and have data
- [ ] Cron jobs are running
- [ ] Email notifications work (if configured)
- [ ] SMS OTP delivery works

---

## Rollback Plan

If issues occur:
1. Have database backup ready
2. Have previous `.env` file saved
3. Can rollback to previous commit: `git revert <commit-sha>`
4. Restore database from backup
5. Verify application is stable

---

## Support & Troubleshooting

### Common Issues:

**OTP not sending:**
- [ ] Verify Termii credentials in `.env`
- [ ] Check SMS logs table for errors
- [ ] Verify phone number format (10-15 digits)
- [ ] Check rate limiting isn't blocking requests

**File upload fails:**
- [ ] Verify uploads directory is writable
- [ ] Check file size doesn't exceed limit (default 15MB)
- [ ] Verify file MIME type is allowed
- [ ] Check file permissions are set correctly

**Health endpoint returns error:**
- [ ] Verify database is accessible
- [ ] Check file system permissions
- [ ] Verify all required directories exist
- [ ] Check database tables exist

**Sessions not persisting:**
- [ ] Verify sessions table exists in database
- [ ] Check database connection is stable
- [ ] Verify session timeout not exceeded
- [ ] Check browser cookies are enabled

---

**Deployment Date**: _________________  
**Deployed By**: _________________  
**Verified By**: _________________  
**Status**: ☐ Pending ☐ In Progress ☐ Complete ☐ Issues Found

All items above should be verified before marking deployment as complete.
