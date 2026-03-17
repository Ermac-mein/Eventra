<?php
/**
 * Eventra — Entity ID Generator
 * Provides unique, human-readable custom IDs for all entities.
 */

/**
 * Generate a unique User ID: USR-XXXXXX (random alphanumeric).
 */
function generateUserId(PDO $pdo): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $id = 'USR-';
        for ($i = 0; $i < 6; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Client ID: CLI-XXXXXX (6-digit numeric).
 */
function generateClientId(PDO $pdo): string
{
    do {
        $id = 'CLI-' . str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Payment ID: txn_xxxxxxxxxxxx (12-char hex).
 */
function generatePaymentId(PDO $pdo): string
{
    do {
        $id = 'txn_' . bin2hex(random_bytes(6));
        $stmt = $pdo->prepare('SELECT id FROM payments WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Ticket ID: TIC-YYYYMMDD-#### (daily sequential counter).
 * Uses the ticket_daily_sequence table for safe atomic increments.
 */
function generateTicketId(PDO $pdo): string
{
    $today = date('Y-m-d');
    $displayDate = date('Ymd');

    // Upsert daily sequence
    $pdo->prepare("
        INSERT INTO ticket_daily_sequence (seq_date, seq_value)
        VALUES (?, 1)
        ON DUPLICATE KEY UPDATE seq_value = seq_value + 1
    ")->execute([$today]);

    $stmt = $pdo->prepare('SELECT seq_value FROM ticket_daily_sequence WHERE seq_date = ?');
    $stmt->execute([$today]);
    $seq = (int) $stmt->fetchColumn();

    return 'TIC-' . $displayDate . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate an Event ULID (time-sortable, lexicographically sortable identifier).
 * Format: 26-char Crockford Base32 string.
 */
function generateEventUlid(): string
{
    $now = (int)(microtime(true) * 1000); // milliseconds
    $base32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    // 10 chars for timestamp
    $ts = '';
    $t = $now;
    for ($i = 9; $i >= 0; $i--) {
        $ts = $base32[$t % 32] . $ts;
        $t = intdiv($t, 32);
    }

    // 16 chars for random part
    $rand = '';
    for ($i = 0; $i < 16; $i++) {
        $rand .= $base32[random_int(0, 31)];
    }

    return $ts . $rand;
}
