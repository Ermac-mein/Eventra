<?php

/**
 * Eventra — Entity ID Generator
 * Provides unique, human-readable, purely random custom IDs for all entities.
 */

/**
 * Generate a unique User ID: USR- followed by 8 random hex characters.
 */
function generateUserId(PDO $pdo): string
{
    do {
        $id = 'USR-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM users WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Client ID: CLI- followed by 8 random hex characters.
 */
function generateClientId(PDO $pdo): string
{
    do {
        $id = 'CLI-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Payment ID: txn_ followed by 12 random hex characters.
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
 * Generate a unique Ticket ID: TIC- followed by 8 random hex characters.
 */
function generateTicketId(PDO $pdo): string
{
    do {
        $id = 'TIC-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM tickets WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Event ID: EVT- followed by 10 random hex characters.
 */
function generateEventId(PDO $pdo): string
{
    do {
        $id = 'EVT-' . strtoupper(bin2hex(random_bytes(5)));
        $stmt = $pdo->prepare('SELECT id FROM events WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate a unique Transaction ID: TRX- followed by 8 random hex characters.
 */
function generateTransactionId(PDO $pdo): string
{
    do {
        $id = 'TRX-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('SELECT id FROM payment_transactions WHERE custom_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}
