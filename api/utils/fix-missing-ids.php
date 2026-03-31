<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/id-generator.php';

echo "Starting custom_id fix script..." . PHP_EOL;

try {
    // 1. Fix Events
    $stmt = $pdo->query("SELECT id FROM events WHERE custom_id IS NULL");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($events) . " events missing custom_id." . PHP_EOL;
    foreach ($events as $event) {
        $new_id = generateEventId($pdo);
        $update = $pdo->prepare("UPDATE events SET custom_id = ? WHERE id = ?");
        $update->execute([$new_id, $event['id']]);
    }

    // 2. Fix Tickets
    $stmt = $pdo->query("SELECT id FROM tickets WHERE custom_id IS NULL");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($tickets) . " tickets missing custom_id." . PHP_EOL;
    foreach ($tickets as $ticket) {
        $new_id = generateTicketId($pdo);
        $update = $pdo->prepare("UPDATE tickets SET custom_id = ? WHERE id = ?");
        $update->execute([$new_id, $ticket['id']]);
    }

    // 3. Fix Payments
    $stmt = $pdo->query("SELECT id FROM payments WHERE custom_id IS NULL");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($payments) . " payments missing custom_id." . PHP_EOL;
    foreach ($payments as $payment) {
        $new_id = generatePaymentId($pdo);
        $update = $pdo->prepare("UPDATE payments SET custom_id = ? WHERE id = ?");
        $update->execute([$new_id, $payment['id']]);
    }

    // 4. Fix Clients
    $stmt = $pdo->query("SELECT id FROM clients WHERE custom_id IS NULL");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($clients) . " clients missing custom_id." . PHP_EOL;
    foreach ($clients as $client) {
        $new_id = generateClientId($pdo);
        $update = $pdo->prepare("UPDATE clients SET custom_id = ? WHERE id = ?");
        $update->execute([$new_id, $client['id']]);
    }

    echo "Done! All missing custom_ids have been populated." . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
