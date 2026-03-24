class Payment {
public static function createPending($pdo, $event_id, $user_id, $amount) {
$reference = 'EVT-' . strtoupper(bin2hex(random_bytes(6)));
$stmt = $pdo->prepare("INSERT INTO payments (event_id, user_id, reference, amount, status) VALUES (?, ?, ?, ?,
'pending')");
$stmt->execute([$event_id, $user_id, $reference, $amount]);
return $reference;
}

public static function verifyAndComplete($pdo, $reference, $paystack_response) {
$pdo->beginTransaction();
try {
$stmt = $pdo->prepare("SELECT * FROM payments WHERE reference = ? FOR UPDATE");
$stmt->execute([$reference]);
$payment = $stmt->fetch();

if (!$payment || $payment['status'] === 'paid') {
$pdo->rollBack();
return false;
}

// Update payment status
$stmt = $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW(), paystack_response = ? WHERE id = ?");
$stmt->execute([json_encode($paystack_response), $payment['id']]);

// Generate Ticket
require_once __DIR__ . /api/utils/barcode-helper.php';
$barcode = generateTicketBarcode($payment['id'], $payment['user_id'], $payment['event_id']);

$stmt = $pdo->prepare("INSERT INTO tickets (payment_id, barcode) VALUES (?, ?)");
$stmt->execute([$payment['id'], $barcode]);

// Increment attendee count
$stmt = $pdo->prepare("UPDATE events SET attendee_count = attendee_count + 1 WHERE id = ?");
$stmt->execute([$payment['event_id']]);

$pdo->commit();
return $payment;
} catch (Exception $e) {
$pdo->rollBack();
throw $e;
}
}
}