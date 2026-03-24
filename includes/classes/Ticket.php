<?php
/**
 * Ticket — Core validation and scan logic
 * Validates tickets with: payment check, used status, event expiry, single-entry enforcement
 */

require_once __DIR__ . '/../../config/app.php';

class Ticket
{
    /**
     * Validate a ticket and mark it as used (atomic, locked transaction).
     *
     * Checks (in order):
     *   1. Ticket exists
     *   2. Payment is confirmed (paid)
     *   3. Event has not expired (event_date >= today)
     *   4. Ticket has not already been used (single-entry)
     *   5. Marks ticket as used atomically
     *
     * @param PDO    $pdo
     * @param string $barcode  Raw barcode string OR base64-encoded signed QR payload
     * @return array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public static function validateAndUse(PDO $pdo, string $barcode): array
    {
        // 1. Try to decode as a signed QR payload
        $resolvedBarcode = self::resolveBarcode($barcode);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                SELECT t.*,
                       p.status  AS payment_status,
                       p.user_id AS payment_user_id,
                       e.event_name,
                       e.event_date,
                       e.id      AS event_id,
                       c.business_name AS client_name,
                       u.name AS buyer_name,
                       u.email AS buyer_email
                FROM tickets t
                JOIN payments p ON t.payment_id = p.id
                JOIN events   e ON p.event_id   = e.id
                JOIN clients  c ON e.client_id  = c.id
                JOIN users    u ON t.user_id     = u.id
                WHERE t.barcode = ?
                FOR UPDATE
            ");
            $stmt->execute([$resolvedBarcode]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check: ticket exists
            if (!$ticket) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Invalid ticket. Barcode not found.'];
            }

            // Check: payment must be confirmed
            if ($ticket['payment_status'] !== 'paid') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Payment for this ticket has not been confirmed.'];
            }

            // Check: event must not have expired
            if (!empty($ticket['event_date']) && strtotime($ticket['event_date']) < strtotime(date('Y-m-d'))) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "This event ({$ticket['event_name']}) has already passed. Ticket is no longer valid.",
                    'status'  => 'expired'
                ];
            }

            // Check: single-entry enforcement (ticket must not be already used)
            if ($ticket['used'] == 1) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Ticket already used. Entry denied.',
                    'status'  => 'already_used',
                    'details' => [
                        'used_at' => $ticket['used_at'],
                        'event'   => $ticket['event_name']
                    ]
                ];
            }

            // All checks passed — mark as used (atomic)
            $stmt = $pdo->prepare("UPDATE tickets SET used = 1, used_at = NOW() WHERE id = ?");
            $stmt->execute([$ticket['id']]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Ticket validated successfully. Entry granted.',
                'status'  => 'used',
                'data'    => $ticket
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Resolve the barcode from either a raw string or a signed QR payload.
     * If the input is a valid base64 JSON with a tid field, returns the tid (barcode).
     * Otherwise returns the raw barcode.
     */
    private static function resolveBarcode(string $input): string
    {
        $decoded = base64_decode($input, true);
        if ($decoded) {
            $payload = json_decode($decoded, true);
            if ($payload && !empty($payload['tid']) && !empty($payload['sig'])) {
                // Verify HMAC signature to prevent forgery
                $dataStr = implode('|', [
                    $payload['tid'],
                    $payload['eid'] ?? '',
                    $payload['uid'] ?? '',
                    $payload['ps']  ?? '',
                    $payload['iat'] ?? ''
                ]);
                $expectedSig = hash_hmac('sha256', $dataStr, QR_SECRET);
                if (hash_equals($expectedSig, $payload['sig'])) {
                    return $payload['tid'];
                }
                // Signature invalid — reject entirely
                return '__INVALID_SIGNATURE__';
            }
        }

        // Raw barcode fallback
        return $input;
    }
}