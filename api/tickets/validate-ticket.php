<?php

/**
 * Proof of Purchase API
 * Shows proof that a user bought a ticket when the QR code is scanned.
 */

require_once '../../config.php';
require_once '../../config/database.php';

$barcode = $_GET['barcode'] ?? null;

if (!$barcode) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Barcode required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.*, e.event_name, e.event_date, e.event_time, e.location, e.address, u.name as user_name 
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON t.user_id = u.id
        WHERE t.barcode = ?
    ");
    $stmt->execute([$barcode]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid ticket barcode']);
        exit;
    }

    // If request wants JSON (e.g. from an app), return JSON
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'status' => $ticket['status'],
            'event_name' => $ticket['event_name'],
            'user_name' => $ticket['user_name'],
            'barcode' => $barcode
        ]);
        exit;
    }

    // Otherwise, return a beautiful HTML Proof of Purchase page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ticket Verification — Eventra</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary: #2ecc71;
                --primary-dark: #27ae60;
                --bg: #f8fafc;
                --text: #1e293b;
                --text-light: #64748b;
            }
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background-color: var(--bg);
                color: var(--text);
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .proof-card {
                background: white;
                border-radius: 24px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.05);
                width: 100%;
                max-width: 400px;
                padding: 40px;
                text-align: center;
                border: 1px solid #e2e8f0;
            }
            .status-icon {
                width: 80px;
                height: 80px;
                background: rgba(46, 204, 113, 0.1);
                color: var(--primary);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                margin: 0 auto 24px;
            }
            h1 { font-size: 24px; font-weight: 800; margin: 0 0 8px; }
            p { color: var(--text-light); margin: 0 0 32px; font-size: 15px; }
            .detail-box {
                background: #f1f5f9;
                border-radius: 16px;
                padding: 20px;
                text-align: left;
                margin-bottom: 24px;
            }
            .detail-item { margin-bottom: 12px; }
            .detail-item:last-child { margin-bottom: 0; }
            .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-light); font-weight: 700; display: block; margin-bottom: 4px; }
            .value { font-size: 16px; font-weight: 600; color: var(--text); }
            .footer { font-size: 12px; color: var(--text-light); }
            .badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                margin-top: 8px;
            }
            .badge-valid { background: var(--primary); color: white; }
        </style>
    </head>
    <body>
        <div class="proof-card">
            <div class="status-icon">✓</div>
            <h1>Proof of Purchase</h1>
            <p>This ticket is verified and valid.</p>
            
            <div class="detail-box">
                <div class="detail-item">
                    <span class="label">Event</span>
                    <span class="value"><?php echo htmlspecialchars($ticket['event_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Ticket Holder</span>
                    <span class="value"><?php echo htmlspecialchars($ticket['user_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Ticket ID</span>
                    <span class="value"><?php echo htmlspecialchars($barcode); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Status</span>
                    <span class="badge badge-valid">Confirmed</span>
                </div>
            </div>

            <div class="footer">
                &copy; <?php echo date('Y'); ?> Eventra — Verified Secure
            </div>
        </div>
    </body>
    </html>
    <?php

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

