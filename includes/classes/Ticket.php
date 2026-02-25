class Ticket {
public static function validateAndUse($pdo, $barcode) {
$pdo->beginTransaction();
try {
// Join with payments to check status
$stmt = $pdo->prepare("
SELECT t.*, p.status as payment_status, e.event_name, e.event_date, c.business_name as client_name, u.name as
buyer_name, u.email as buyer_email
FROM tickets t
JOIN payments p ON t.payment_id = p.id
JOIN events e ON p.event_id = e.id
JOIN clients c ON e.client_id = c.id
JOIN users u ON p.user_id = u.id
WHERE t.barcode = ? FOR UPDATE
");
$stmt->execute([$barcode]);
$ticket = $stmt->fetch();

if (!$ticket) {
$pdo->rollBack();
return ['success' => false, 'message' => 'Invalid barcode.'];
}

if ($ticket['payment_status'] !== 'paid') {
$pdo->rollBack();
return ['success' => false, 'message' => 'Payment for this ticket is not confirmed.'];
}

if ($ticket['used'] == 1) {
$pdo->rollBack();
return [
'success' => false,
'message' => 'Ticket already used.',
'details' => [
'used_at' => $ticket['used_at'],
'event' => $ticket['event_name']
]
];
}

// Mark as used
$stmt = $pdo->prepare("UPDATE tickets SET used = 1, used_at = NOW() WHERE id = ?");
$stmt->execute([$ticket['id']]);

$pdo->commit();
return [
'success' => true,
'message' => 'Ticket validated successfully.',
'data' => $ticket
];
} catch (Exception $e) {
$pdo->rollBack();
throw $e;
}
}
}