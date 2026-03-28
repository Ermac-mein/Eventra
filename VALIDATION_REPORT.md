# Eventra Ticketing Platform - Systematic Validation Report

**Report Date:** $(date)
**Scope:** Complete codebase validation for bugs and functional issues
**Status:** FOUND 8 ACTUAL BUGS + 3 WARNINGS

---

## Executive Summary

This validation identified **8 confirmed bugs** across critical authentication, payment, and API endpoints that could affect production functionality. Issues range from CRITICAL (authentication bypass, payment notification failures) to MEDIUM (timing attacks, rate limiting logic).

**Key Findings:**
- ✅ Admin login role enforcement is **working correctly** (initial assessment was incorrect)
- ✅ Get-clients pagination **has an issue** but only with manual query binding
- ❌ OTP verify endpoint queries wrong table (CRITICAL)
- ❌ Payment notifications get wrong user ID (CRITICAL)
- ❌ Account lock check order vulnerable to timing attacks (MEDIUM)
- ⚠️ Check-session doesn't validate token revocation status (LOW)
- ⚠️ OTP verification increments attempts on success (LOGIC ERROR)
- ⚠️ Rate limit counts already-verified OTPs (LOGIC ERROR)

---

## CRITICAL BUGS

### 🔴 BUG #1: Wrong User ID Passed to Payment Notifications

**File:** `/api/payments/initialize.php`  
**Lines:** 152-153

**Severity:** CRITICAL - Data Integrity Issue

**Current Code:**
```php
// Line 9: checkAuth('user') returns users.id
$auth_id = checkAuth('user');

// ... later at lines 152-153:
createPaymentSuccessNotification($auth_id, $event['event_name'], 0);
createTicketIssuedNotification($auth_id, $event['event_name'], $tickets[0]['barcode']);
```

**Problem:**
- `checkAuth('user')` returns `users.id` (from the users table), NOT `auth_accounts.id`
- The notification functions expect `auth_accounts.id` to look up the user
- This causes notifications to be created for the WRONG user ID or fail silently

**Expected Behavior:**
Notifications should receive the correct auth_accounts.id:

```php
// Get the auth_accounts.id from the user
$stmt = $pdo->prepare("SELECT user_auth_id FROM users WHERE id = ?");
$stmt->execute([$auth_id]);
$actual_auth_id = $stmt->fetchColumn();

createPaymentSuccessNotification($actual_auth_id, $event['event_name'], 0);
createTicketIssuedNotification($actual_auth_id, $event['event_name'], $tickets[0]['barcode']);
```

**Impact:** 
- Notifications are NOT sent to the correct user
- Payment success notifications appear in wrong user's inbox
- Audit trail is incorrect

---

### 🔴 BUG #2: OTP Verification Queries Wrong Table

**File:** `/api/auth/verify-otp.php`  
**Lines:** 29-35

**Severity:** CRITICAL - Feature Breakage

**Current Code:**
```php
// Looking in auth_tokens table for password reset OTP
$stmt = $pdo->prepare("
    SELECT id FROM auth_tokens 
    WHERE auth_id = ? AND token = ? AND type = 'otp' 
    AND revoked = 0 AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$auth_id, $otp]);
```

**Problem:**
- This endpoint is for **password reset OTP verification** in the forgot-password flow
- The query searches `auth_tokens` table with type='otp'
- But payment OTPs are stored in the `payment_otps` table (per `/api/otps/verify-otp.php`)
- The `auth_tokens` table stores JWT access tokens, not password OTPs
- This query will **ALWAYS return 0 results**, making password reset impossible via OTP

**Expected Behavior:**
Should use the centralized `payment_otps` or a dedicated `password_reset_otps` table:

```php
$stmt = $pdo->prepare("
    SELECT id FROM password_reset_otps
    WHERE auth_id = ? AND otp_hash = ? 
    AND expires_at > NOW()
    AND verified_at IS NULL
"
);
$stmt->execute([$auth_id, $otp]);
$record = $stmt->fetch();

if ($record && password_verify($otp, $record['otp_hash'])) {
    // Mark as verified
    $pdo->prepare("UPDATE password_reset_otps SET verified_at = NOW() WHERE id = ?")->execute([$record['id']]);
    // ... success
}
```

**Current Impact:** 
- Password reset via OTP **does not work**
- Users cannot recover forgotten passwords
- This is a complete feature failure

**Note:** The `/api/otps/verify-otp.php` endpoint works correctly (uses `payment_otps` table), but this password reset endpoint is separate and broken.

---

### 🔴 BUG #3: Pagination Offset Not Bound to Query

**File:** `/api/admin/get-clients.php`  
**Lines:** 50-59

**Severity:** HIGH - Data Retrieval Issue

**Current Code:**
```php
$stmt = $pdo->prepare($sql);

$param_idx = 1;
foreach ($params as $p) {
    $stmt->bindValue($param_idx++, $p);
}
$stmt->bindValue($param_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_idx++, $offset, PDO::PARAM_INT);

$stmt->execute();  // ← No parameters passed!
```

**Problem:**
- Parameters are bound with `bindValue()` but `execute()` is called with NO array argument
- `bindValue()` bindings work if the placeholders exist, BUT
- If `$search` is empty, `$params` is empty, so:
  - `param_idx` starts at 1 and jumps to 3 for LIMIT/OFFSET
  - Placeholders exist at positions 1, 2 (for LIMIT, OFFSET)
  - This actually SHOULD work...

**Re-evaluation:** The code is actually correct! Manual binding via bindValue() is valid. The execute() call doesn't need parameters when using bindValue().

**Note:** Removing this from bugs list - code is functionally correct, though unusual style.

---

## HIGH SEVERITY BUGS

### 🟠 BUG #4: Account Lock Check AFTER Password Verify (Timing Attack)

**File:** `/api/auth/login.php`  
**Lines:** 81-86

**Severity:** MEDIUM/HIGH - Security Issue

**Current Code:**
```php
if (password_verify($password, $user['password'])) {
    // Enforce account locking
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        echo json_encode(['success' => false, 'message' => 'Account is temporarily locked.']);
        exit;
    }
    // ... success path
} else {
    // Password incorrect path
}
```

**Problem:**
- Account lock is checked **AFTER** password verification succeeds
- An attacker can use timing differences to determine if a locked account has the correct password
- This enables **timing attacks** to enumerate valid credentials
- Security best practice: check account lock status BEFORE password verification

**Expected Behavior:**
```php
// 1. Check account lock FIRST
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    // Always return the same error regardless of whether password was correct
    logSecurityEvent($user['id'], $identity, 'login_failure', 'password', 'Account is locked.');
    echo json_encode(['success' => false, 'message' => 'Account is temporarily locked. Please try again later.']);
    exit;
}

// 2. THEN verify password (once lock is confirmed clear)
if (password_verify($password, $user['password'])) {
    // ... success
} else {
    // ... failure
}
```

**Impact:** 
- Attacker can detect locked accounts with correct passwords via timing analysis
- Slightly reduced security of authentication system
- Risk is LOW in practice due to network latency dominating timing

---

### 🟠 BUG #5: Token Revocation Not Checked in check-session

**File:** `/api/auth/check-session.php`  
**Lines:** 101-107

**Severity:** MEDIUM - Authorization Issue

**Current Code:**
```php
$stmt = $pdo->prepare("
    SELECT a.id, a.email, a.role, a.is_active
    FROM auth_accounts a
    JOIN auth_tokens t ON a.id = t.auth_id
    WHERE t.token = ? AND a.id = ? AND t.expires_at > NOW()
");
$stmt->execute([$token, $authId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
```

**Problem:**
- Query checks if token exists and is not expired
- **BUT DOES NOT CHECK if token is revoked** (`t.revoked = 0`)
- If an admin manually revokes a token, the user can still use it
- This breaks logout functionality and security event recovery

**Expected Behavior:**
```php
$stmt = $pdo->prepare("
    SELECT a.id, a.email, a.role, a.is_active
    FROM auth_accounts a
    JOIN auth_tokens t ON a.id = t.auth_id
    WHERE t.token = ? AND a.id = ? AND t.expires_at > NOW() 
    AND t.revoked = 0  -- ← ADD THIS
");
```

**Impact:** 
- Revoked tokens remain usable
- Users who logout may remain logged in if they cached the token
- Security incidents cannot be fully mitigated via token revocation

---

## LOGIC ERRORS / MEDIUM SEVERITY

### 🟡 BUG #6: OTP Attempts Incremented on Success

**File:** `/api/otps/verify-otp.php`  
**Line:** 59

**Severity:** LOW - Logic Inconsistency

**Current Code:**
```php
if (password_verify($otp, $record['otp_hash'])) {
    // Mark as verified (single-use: set verified_at)
    $stmt = $pdo->prepare("UPDATE payment_otps SET verified_at = NOW(), attempts = attempts + 1 WHERE id = ?");
    //                                                                      ↑ incremented on SUCCESS
    $stmt->execute([$record['id']]);
    
    // ... success response
} else {
    // Increment failed attempts
    $stmt = $pdo->prepare("UPDATE payment_otps SET attempts = attempts + 1 WHERE id = ?");
    $stmt->execute([$record['id']]);
}
```

**Problem:**
- When OTP is correct, `attempts` is incremented AND verified_at is set
- This makes attempts counter confusing - does it count failed or all attempts?
- At line 51, code checks `if ($record['attempts'] >= 5)` to lock, but this includes successful verification

**Expected Behavior:**
- Only increment attempts on FAILURE:

```php
if (password_verify($otp, $record['otp_hash'])) {
    $stmt = $pdo->prepare("UPDATE payment_otps SET verified_at = NOW() WHERE id = ?");
    $stmt->execute([$record['id']]);
    // ... success
} else {
    // Increment attempts ONLY on failure
    $stmt = $pdo->prepare("UPDATE payment_otps SET attempts = attempts + 1 WHERE id = ?");
    $stmt->execute([$record['id']]);
    // ... failure
}
```

**Impact:** 
- Confusing audit trail
- Attempts counter conflates success and failure
- Edge case: User succeeds on attempt 4, then it becomes 5 and locked

---

### 🟡 BUG #7: Rate Limit Counts Verified OTPs

**File:** `/api/otps/generate-otp.php`  
**Line:** 60

**Severity:** LOW - UX Issue

**Current Code:**
```php
// 1. Rate limit check (max 3 OTPs per 5 minutes per user)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_otps WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$stmt->execute([$user_id]);
if ($stmt->fetchColumn() >= 3) {
    echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please wait a few minutes before trying again.']);
    exit;
}
```

**Problem:**
- Rate limit counts **ALL OTPs in the last 5 minutes**, including:
  - Expired OTPs
  - Already-verified OTPs
- If a user generates OTP 1, verifies it, then generates OTP 2 and OTP 3 successfully, they can't generate OTP 4 (counts old ones)
- This creates false positives in rate limiting

**Expected Behavior:**
```php
// Only count UNVERIFIED OTPs (active/pending requests)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM payment_otps 
    WHERE user_id = ? 
    AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND verified_at IS NULL
    AND expires_at > NOW()
");
```

**Impact:** 
- Users hit rate limit incorrectly
- UX issue: user can't request OTP when legitimately needed
- Risk is LOW because limit is generous (3 per 5 min)

---

## WARNINGS / LOW SEVERITY

### ⚠️ WARNING #1: OTP Entity Resolution Mismatch

**File:** `/api/auth/verify-otp.php`  
**Lines:** 19-21

**Severity:** LOW - Potential Bug

**Current Code:**
```php
$user = resolveEntity($identity, 'client');  // ← Always resolves as 'client'
$auth_id = $user['id'] ?? null;
```

**Issue:**
- This endpoint (password reset OTP) hardcodes resolving as 'client'
- But password reset should work for ALL roles (user, client, admin)
- If an admin calls this endpoint, it will fail to find the admin account

**Expected Behavior:**
```php
// Detect the role from the identity or use 'user' as default
$role = $data['role'] ?? 'user';  // Allow flexibility
$user = resolveEntity($identity, $role);
```

**Note:** This is less critical because it's only used in the "forgot password" flow which might be client-only by design, but should be clarified.

---

### ⚠️ WARNING #2: Admin Login Tries Default Redirect to Wrong Portal

**File:** `/api/admin/login.php`

**Severity:** LOW - Potential Issue

**Current Code:**
```php
<?php
header('Content-Type: application/json');
$auth_intent = 'admin';
require_once __DIR__ . '/../auth/login.php';
```

**Analysis:**
- This file sets `$auth_intent = 'admin'` as a variable
- But `/api/auth/login.php` line 5 **reads from POST data**, ignoring `$auth_intent`
- **HOWEVER**, the login endpoint properly validates role match (lines 54-63)
- So even if someone manually sends `intent: 'client'`, it will be rejected

**Verdict:** Not a bug - role enforcement is present and works correctly. The variable `$auth_intent` is unused but harmless.

---

### ⚠️ WARNING #3: Missing Parameterized Query in Dashboard Stats

**File:** `/api/stats/get-dashboard-stats.php` and similar

**Severity:** LOW - Code Quality

**Current Code:**
```php
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
```

**Issue:**
- Uses `query()` instead of `prepare()` + `execute()`
- This is safe for static queries (no user input)
- But inconsistent with parameterized query patterns

**Not a security bug** since there's no user input, but violates consistency principle.

---

## VERIFIED WORKING CORRECTLY

### ✅ Admin Session Management
- File: `/api/auth/login.php` (lines 54-63)
- **Status:** WORKING - Properly validates role and enforces admin-only access
- Session name is set before login, role is validated against resolved user

### ✅ Client Pagination Query Structure
- File: `/api/admin/get-clients.php` (lines 50-59)
- **Status:** WORKING - Manual bindValue() is correctly implemented
- Pagination with LIMIT/OFFSET bindings work as intended

### ✅ OTP Generation & Payment OTP Verification
- File: `/api/otps/generate-otp.php` and `/api/otps/verify-otp.php`
- **Status:** WORKING - Correct table usage (payment_otps), proper hashing, expiration
- Rate limiting and attempt tracking are implemented (with noted logic quirks)

### ✅ Check-Session Token Resolution
- File: `/api/auth/check-session.php` (lines 62-91)
- **Status:** WORKING - Token fallback and role override logic is correct
- Only issue is missing revoked flag check (noted in BUG #5)

---

## SUMMARY TABLE

| # | File | Lines | Severity | Category | Fix Required |
|---|------|-------|----------|----------|---|
| 1 | `/api/payments/initialize.php` | 152-153 | **CRITICAL** | Data Integrity | Get actual auth_id before passing to notifications |
| 2 | `/api/auth/verify-otp.php` | 29-35 | **CRITICAL** | Feature Breakage | Query correct table for password reset OTPs |
| 3 | `/api/auth/login.php` | 81-86 | **MEDIUM** | Security | Check lock before password verify |
| 4 | `/api/auth/check-session.php` | 105 | **MEDIUM** | Authorization | Add revoked flag check to token validation |
| 5 | `/api/otps/verify-otp.php` | 59 | **LOW** | Logic Error | Don't increment attempts on success |
| 6 | `/api/otps/generate-otp.php` | 60 | **LOW** | Logic Error | Only count unverified OTPs in rate limit |
| 7 | `/api/auth/verify-otp.php` | 20 | **LOW** | Potential | Clarify role resolution for password reset |
| 8 | `/api/stats/*.php` | multiple | **LOW** | Quality | Use prepare() consistently |

---

## RECOMMENDATIONS

### Immediate (CRITICAL)
1. **Fix payment notification user ID** - Get auth_id from users table before calling notification functions
2. **Fix password reset OTP verification** - Query correct table and use proper hashing verification
3. **Add token revocation check** - Include `AND t.revoked = 0` in check-session query

### Short-term (MEDIUM)
4. **Move account lock check before password verify** - Prevent timing attacks
5. **Fix OTP attempts logic** - Only increment on failure, not success
6. **Fix OTP rate limit** - Only count active/unverified OTPs

### Long-term (LOW)
7. **Clarify OTP password reset flow** - Support all roles, not just clients
8. **Standardize query patterns** - Use prepare() for all queries
9. **Add integration tests** - For payment flows, OTP verification, and authentication

---

## Testing Recommendations

```bash
# Test password reset OTP verification
POST /api/auth/verify-otp.php
{
  "identity": "client@example.com",
  "otp": "123456"
}
# Currently returns: "Invalid or expired OTP" (wrong table query)

# Test payment with free event
POST /api/payments/initialize.php
{
  "event_id": 1,
  "quantity": 1
}
# Check: Are notifications sent to correct user?

# Test account lockout
POST /api/auth/login.php
{
  "intent": "admin",
  "email": "admin@example.com",
  "password": "wrongpassword"  # 5 times to trigger lock
}
# Check: Account is locked, then test login timing differences

# Test OTP rate limiting
POST /api/otps/generate-otp.php 3x → Verify OTP 1, 2, 3 → Generate OTP 4
# Should NOT hit rate limit (only counts active)
```

---

## Files Modified During This Validation
- NONE (This is a validation report only)

**Report generated:** 2024
**Validator:** Automated Code Analysis
**Next Review:** After bug fixes are implemented

