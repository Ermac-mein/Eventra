# Testing the Payment Verification Fix

## Quick Test Checklist

### 1. Manual Testing (Local Development)

#### Setup
```bash
# Ensure jobs directory exists
mkdir -p /home/mein/Documents/Eventra/jobs
chmod 755 /home/mein/Documents/Eventra/jobs

# Run verification script
php verify-fix.php
```

#### Test Scenario A: Complete Payment Flow
1. Navigate to a paid event checkout
2. Complete the payment with Paystack
3. Get redirected to payment verification page
4. Expected results:
   - ✓ Page should show "Verifying Payment..." immediately (not taking long)
   - ✓ Within 5 seconds, should see "Payment Successful!" 
   - ✓ Order should be marked as 'success' in database
   - ✓ Tickets should exist in the database (even if QR codes aren't generated yet)

#### Test Scenario B: Manual Job Processing
```bash
# Check if job was created
ls -la /home/mein/Documents/Eventra/jobs/

# Manually run the processor
php /home/mein/Documents/Eventra/api/utils/process-ticket-queue.php

# Check if job was processed
ls -la /home/mein/Documents/Eventra/jobs/
# (Should be empty or fewer jobs)

# Check uploads
ls -la /home/mein/Documents/Eventra/uploads/tickets/pdfs/
ls -la /home/mein/Documents/Eventra/uploads/tickets/qrcodes/
```

#### Test Scenario C: Polling with Incomplete Tickets
1. Complete a payment
2. During the first 10-30 seconds (while PDF is generating):
   - Call `GET /api/payments/get-order.php?reference=XXX`
   - You should get `status: "pending"` with no barcode
   - This is the intermediate state during async processing

3. Wait 30+ seconds
   - Call the same endpoint again
   - You should get `status: "success"` with barcode
   - PDF generation is complete

### 2. Performance Testing

#### Response Time Benchmark
```bash
# Before fix: verify-payment.php response time
time curl -X GET "http://localhost:8000/api/payments/verify-payment.php?reference=EVT-143-E415B038"

# Expected after fix: <500ms
# Expected before fix: 5-30+ seconds
```

#### Concurrent Payments Test
```bash
# Simulate 5 concurrent payment verifications
for i in {1..5}; do
  php /home/mein/Documents/Eventra/api/utils/process-ticket-queue.php &
done
wait

# Should not error or timeout
```

### 3. Error Handling Tests

#### Test: Missing Reference
```bash
curl "http://localhost:8000/api/payments/verify-payment.php"
# Expected: 400 Bad Request with "reference is required"
```

#### Test: Invalid Reference
```bash
curl "http://localhost:8000/api/payments/verify-payment.php?reference=INVALID"
# Expected: 404 with "Order not found"
```

#### Test: Duplicate Processing (Idempotency)
1. Complete a payment (this creates order and queues job)
2. Call verify-payment.php again with same reference
   - Expected: Should return existing order (idempotent)
   - No duplicate tickets should be created
   - No duplicate notifications

#### Test: Database Connection Failure
If database goes down during job processing:
```bash
# The job file should remain in jobs/ for retry
ls -la /home/mein/Documents/Eventra/jobs/

# When DB comes back, manually run
php /home/mein/Documents/Eventra/api/utils/process-ticket-queue.php
# Should successfully process the pending jobs
```

### 4. Email & SMS Verification

After completing a payment:
1. Check user's email - should receive ticket PDF
2. Check user's SMS (if phone number provided)
3. Expected in email:
   - PDF attachment with QR code
   - Ticket barcode
   - Event details
   - Entry instructions

### 5. Database Verification

```sql
-- Check order was created and marked success
SELECT id, payment_status, transaction_reference FROM orders 
WHERE transaction_reference = 'EVT-143-E415B038';

-- Check payment record
SELECT id, reference, status, created_at FROM payments 
WHERE reference = 'EVT-143-E415B038';

-- Check tickets were created
SELECT id, barcode, qr_code_path, status FROM tickets 
WHERE payment_id = (SELECT id FROM payments WHERE reference = 'EVT-143-E415B038');

-- Check attendee count incremented
SELECT id, event_name, attendee_count FROM events 
WHERE id = (SELECT event_id FROM orders WHERE transaction_reference = 'EVT-143-E415B038');
```

### 6. Cron Job Setup (Production)

```bash
# Add to crontab
crontab -e

# Add this line to run every 5 minutes:
*/5 * * * * /home/mein/Documents/Eventra/scripts/process-tickets.sh >> /var/log/eventra-jobs.log 2>&1

# Verify it's running:
crontab -l | grep process-tickets

# Test logs:
tail -f /var/log/eventra-jobs.log
```

### 7. Log Verification

```bash
# Check PHP error logs for process-ticket-queue
grep "process-ticket-queue" /var/log/php*.log

# Look for expected log messages:
# - "[process-ticket-queue.php] Processing job for reference: XXX"
# - "[process-ticket-queue.php] Successfully processed job for reference: XXX"

# Look for errors (unexpected):
# - "[process-ticket-queue.php] Notification failed"
# - "[process-ticket-queue.php] Fatal error"
```

## Success Criteria

✓ Payment verification returns in <500ms (vs 5-30+ seconds before)
✓ User sees immediate success confirmation
✓ Order and tickets are created in database quickly
✓ PDFs and QR codes are generated asynchronously
✓ Emails and SMS are sent after processing
✓ No 500 errors on payment verification
✓ Idempotent processing (no duplicates on retry)
✓ Failed jobs remain in queue for manual retry
✓ Concurrent payments don't block each other
✓ Cron job successfully processes job queue

## Troubleshooting

### Issue: Jobs not being processed
**Solution**: Ensure cron job is running
```bash
crontab -l  # Verify it's scheduled
ps aux | grep php  # Check if processes are running
```

### Issue: PDF/QR files not found
**Solution**: Check directory permissions
```bash
ls -la /home/mein/Documents/Eventra/uploads/tickets/
chmod 755 /home/mein/Documents/Eventra/uploads/tickets/*
```

### Issue: Emails not being sent
**Solution**: Check email configuration
```bash
grep -r "sendTicketEmailFull" /home/mein/Documents/Eventra/includes/
# Verify SMTP/email settings in config
```

### Issue: Jobs accumulating in queue
**Solution**: Manually process them
```bash
php /home/mein/Documents/Eventra/api/utils/process-ticket-queue.php
```

## Rollback Plan (If Issues)

If you need to rollback the optimization:

```bash
# Revert verify-payment.php to synchronous version
git checkout HEAD api/payments/verify-payment.php

# Remove async processor
rm api/utils/process-ticket-queue.php
```

Then restart your PHP-FPM service.
