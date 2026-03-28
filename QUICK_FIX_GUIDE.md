# Eventra - Quick Fix Guide

## Critical Fixes (Implement Today)

### Fix #1: Payment Notification User ID
**File:** `/api/payments/initialize.php`  
**Lines:** 152-153

```php
// BEFORE (WRONG):
createPaymentSuccessNotification($auth_id, $event['event_name'], 0);
createTicketIssuedNotification($auth_id, $event['event_name'], $tickets[0]['barcode']);

// AFTER (CORRECT):
$stmt = $pdo->prepare("SELECT user_auth_id FROM users WHERE id = ?");
$stmt->execute([$auth_id]);
$actual_auth_id = $stmt->fetchColumn();

createPaymentSuccessNotification($actual_auth_id, $event['event_name'], 0);
createTicketIssuedNotification($actual_auth_id, $event['event_name'], $tickets[0]['barcode']);
```

**Why:** `checkAuth('user')` returns `users.id`, but notifications need `auth_accounts.id`

---

### Fix #2: Password Reset OTP Verification Table
**File:** `/api/auth/verify-otp.php`  
**Lines:** 29-35

```php
// BEFORE (WRONG):
$stmt = $pdo->prepare("
    SELECT id FROM auth_tokens 
    WHERE auth_id = ? AND token = ? AND type = 'otp' 
    AND revoked = 0 AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$auth_id, $otp]);

// AFTER (CORRECT):
$stmt = $pdo->prepare("
    SELECT id FROM password_reset_otps
    WHERE auth_id = ? AND otp_hash = ? 
    AND expires_at > NOW()
    AND verified_at IS NULL
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$auth_id, $otp]);
$record = $stmt->fetch();

if ($record && password_verify($otp, $record['otp_hash'])) {
    // Mark as verified
    $pdo->prepare("UPDATE password_reset_otps SET verified_at = NOW() WHERE id = ?")->execute([$record['id']]);
    // ... return success
}
```

**Why:** Password reset OTPs are in `password_reset_otps` table, not `auth_tokens`

---

## High Priority Fixes (This Week)

### Fix #3: Account Lock Before Password Check
**File:** `/api/auth/login.php`  
**Lines:** 81-86

```php
// BEFORE (VULNERABLE):
if (password_verify($password, $user['password'])) {
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        // Lock check happens AFTER password verify - timing attack!
    }
}

// AFTER (SECURE):
// Check lock FIRST
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    echo json_encode(['success' => false, 'message' => 'Account is temporarily locked.']);
    exit;
}

// THEN verify password
if (password_verify($password, $user['password'])) {
    // ... success path
}
```

**Why:** Prevents timing attacks that can detect valid locked account credentials

---

### Fix #4: Add Token Revocation Check
**File:** `/api/auth/check-session.php`  
**Line:** 105

```php
// BEFORE (INCOMPLETE):
$stmt = $pdo->prepare("
    SELECT a.id, a.email, a.role, a.is_active
    FROM auth_accounts a
    JOIN auth_tokens t ON a.id = t.auth_id
    WHERE t.token = ? AND a.id = ? AND t.expires_at > NOW()
");

// AFTER (COMPLETE):
$stmt = $pdo->prepare("
    SELECT a.id, a.email, a.role, a.is_active
    FROM auth_accounts a
    JOIN auth_tokens t ON a.id = t.auth_id
    WHERE t.token = ? AND a.id = ? AND t.expires_at > NOW() AND t.revoked = 0
    //                                                            ↑ ADD THIS
");
```

**Why:** Revoked tokens (from logout/security events) should not be usable

---

## Medium Priority Fixes (Next Sprint)

### Fix #5: OTP Attempts Logic
**File:** `/api/otps/verify-otp.php`  
**Line:** 59

```php
// BEFORE (WRONG LOGIC):
if (password_verify($otp, $record['otp_hash'])) {
    $stmt = $pdo->prepare("UPDATE payment_otps SET verified_at = NOW(), attempts = attempts + 1 WHERE id = ?");
    //                                                                    ↑ Increments on SUCCESS!
}

// AFTER (CORRECT LOGIC):
if (password_verify($otp, $record['otp_hash'])) {
    $stmt = $pdo->prepare("UPDATE payment_otps SET verified_at = NOW() WHERE id = ?");
    //                                                                    ↑ Don't increment
} else {
    $stmt = $pdo->prepare("UPDATE payment_otps SET attempts = attempts + 1 WHERE id = ?");
    //                                                        ↑ Increment on FAILURE only
}
```

**Why:** Attempts counter should track failed attempts, not successful ones

---

### Fix #6: OTP Rate Limit
**File:** `/api/otps/generate-otp.php`  
**Line:** 60

```php
// BEFORE (COUNTS VERIFIED):
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM payment_otps 
    WHERE user_id = ? 
    AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
// Counts expired and verified OTPs!

// AFTER (ONLY ACTIVE):
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM payment_otps 
    WHERE user_id = ? 
    AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND verified_at IS NULL
    AND expires_at > NOW()
");
```

**Why:** Only count pending OTPs; verified/expired shouldn't trigger rate limit

---

## Testing Checklist

After implementing fixes, test:

- [ ] **Payment Notification** - Create free event order, verify notification goes to correct user
- [ ] **Password Reset** - Forgot password → enter email → receive OTP → verify OTP → reset password
- [ ] **Account Lockout** - Wrong password 5 times → account locks → correct password fails → wait 15 min
- [ ] **Token Revocation** - Login → copy token → logout → try token again (should fail)
- [ ] **OTP Attempts** - Generate OTP → enter wrong code 5 times → locked (not after success)
- [ ] **OTP Rate Limit** - Gen OTP 1 → verify it → gen OTP 2 → verify it → gen OTP 3 (should NOT hit limit)

---

## Files to Modify (In Order)

1. ✏️ `/api/payments/initialize.php` (2 lines)
2. ✏️ `/api/auth/verify-otp.php` (10 lines)
3. ✏️ `/api/auth/login.php` (5 lines)
4. ✏️ `/api/auth/check-session.php` (1 line)
5. ✏️ `/api/otps/verify-otp.php` (4 lines)
6. ✏️ `/api/otps/generate-otp.php` (3 lines)

**Total Changes:** ~25 lines of code

---

## Impact Analysis

| Fix | Risk | Effort | Impact |
|-----|------|--------|--------|
| #1 | Low | 5 min | Critical (notifications work) |
| #2 | Low | 10 min | Critical (password reset works) |
| #3 | Low | 5 min | High (security improvement) |
| #4 | Low | 1 min | High (logout works properly) |
| #5 | Low | 5 min | Medium (logic clarity) |
| #6 | Low | 3 min | Medium (UX improvement) |

**Total Implementation Time:** ~30 minutes
**Risk Level:** Very Low (simple, isolated changes)
**Testing Time:** ~15 minutes per fix

---

## Rollback Plan

If any fix causes issues:
1. Revert the single file using Git
2. Deploy immediately
3. Debug in staging before re-applying

Each fix is isolated and independent.

---

**Questions?** See VALIDATION_REPORT.md for detailed analysis.
