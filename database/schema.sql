CREATE DATABASE IF NOT EXISTS eventra_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE eventra_db;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- AUTH ACCOUNTS (MASTER AUTH TABLE - SINGLE SOURCE OF TRUTH)
-- =============================================================================
CREATE TABLE IF NOT EXISTS auth_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    username VARCHAR(200) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    auth_provider ENUM('local', 'google') NOT NULL DEFAULT 'local',
    provider_id VARCHAR(191) DEFAULT NULL,
    role ENUM('admin', 'client', 'user') NOT NULL,
    role_locked TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_online TINYINT(1) DEFAULT 0,
    last_seen DATETIME DEFAULT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    email_verified_at DATETIME DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_email (email),
    UNIQUE KEY uq_provider_id (provider_id),
    KEY idx_auth_role_active (role, is_active),
    KEY idx_auth_deleted (deleted_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- AUTH TOKENS
-- =============================================================================
CREATE TABLE IF NOT EXISTS auth_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    auth_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(255) NOT NULL,
    type ENUM(
        'access',
        'refresh',
        'reset_password',
        'email_verification',
        'otp'
    ) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    KEY idx_token_auth (auth_id),
    CONSTRAINT fk_token_auth FOREIGN KEY (auth_id) REFERENCES auth_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = INNODB DEFAULT CHARSET = UTF8MB4 COLLATE = UTF8MB4_UNICODE_CI;

-- =============================================================================
-- AUTH LOGS
-- =============================================================================
CREATE TABLE IF NOT EXISTS auth_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    auth_id BIGINT UNSIGNED DEFAULT NULL,
    email VARCHAR(191) DEFAULT NULL,
    username VARCHAR(200) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    auth_method VARCHAR(50) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_logs_auth (auth_id),
    CONSTRAINT fk_auth_logs_auth FOREIGN KEY (auth_id) REFERENCES auth_accounts (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- ADMINS PROFILE (NO AUTH LOGIC HERE)
-- =============================================================================
CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_auth_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_auth (admin_auth_id),
    UNIQUE KEY uq_admin_username (username),
    CONSTRAINT fk_admin_auth FOREIGN KEY (admin_auth_id) REFERENCES auth_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- CLIENTS PROFILE
-- =============================================================================
CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_auth_id BIGINT UNSIGNED NOT NULL,
    business_name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL,
    name VARCHAR(150) NOT NULL,
    password VARCHAR(200) NOT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    company VARCHAR(150) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    nin VARCHAR(20) DEFAULT NULL,
    bvn VARCHAR(20) DEFAULT NULL,
    nin_verified TINYINT(1) DEFAULT 0,
    bvn_verified TINYINT(1) DEFAULT 0,
    account_name VARCHAR(150) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    subaccount_code VARCHAR(100) DEFAULT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    bank_code VARCHAR(20) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_client_auth (client_auth_id),
    KEY idx_client_deleted (deleted_at),
    CONSTRAINT fk_client_auth FOREIGN KEY (client_auth_id) REFERENCES auth_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- USERS PROFILE
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_auth_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_auth (user_auth_id),
    CONSTRAINT fk_user_auth FOREIGN KEY (user_auth_id) REFERENCES auth_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- EVENTS
-- =============================================================================
CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    phone_contact_1 VARCHAR(30) NOT NULL,
    phone_contact_2 VARCHAR(30),
    address VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    event_date DATE DEFAULT NULL,
    event_time TIME DEFAULT NULL,
    visibility ENUM(
        'all states',
        'specific state'
    ) DEFAULT 'all states',
    price DECIMAL(12, 2) DEFAULT 0.00,
    image_path VARCHAR(500) DEFAULT NULL,
    external_link VARCHAR(200),
    tag VARCHAR(100),
    category VARCHAR(100) DEFAULT NULL,
    max_capacity INT UNSIGNED DEFAULT NULL,
    attendee_count INT UNSIGNED NOT NULL DEFAULT 0,
    priority ENUM(
        'nearby',
        'hot',
        'trending',
        'upcoming',
        'featured'
    ) DEFAULT 'nearby',
    status ENUM(
        'draft',
        'scheduled',
        'published',
        'cancelled',
        'archived'
    ) DEFAULT 'draft',
    scheduled_notification_at DATETIME DEFAULT NULL,
    scheduled_publish_time DATETIME NOT NULL,
    notification_sent TINYINT(1) DEFAULT 0,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_event_client (client_id),
    CONSTRAINT fk_event_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- ORDERS
-- =============================================================================
CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    organizer_id BIGINT UNSIGNED NOT NULL,
    subaccount_code VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    transaction_reference VARCHAR(191) NOT NULL,
    payment_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_reference (transaction_reference),
    KEY idx_order_user (user_id),
    KEY idx_order_event (event_id),
    KEY idx_order_organizer (organizer_id),
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_organizer FOREIGN KEY (organizer_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =============================================================================
-- PAYMENTS
-- =============================================================================
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(191) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM(
        'pending',
        'paid',
        'failed',
        'refunded'
    ) DEFAULT 'pending',
    paystack_response JSON DEFAULT NULL,
    payment_id VARCHAR(100) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_reference (reference),
    CONSTRAINT fk_payment_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- PAYMENTS OTPS
-- =============================================================================

CREATE TABLE IF NOT EXISTS payment_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_reference VARCHAR(100) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    channel ENUM('email', 'sms') NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT DEFAULT 0,
    verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (payment_reference)
);

-- =============================================================================
-- PAYMENTS TRANSACTIONS
-- =============================================================================
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_id VARCHAR(30) DEFAULT NULL,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    payment_reference VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM(
        'pending',
        'success',
        'failed'
    ) DEFAULT 'pending',
    provider_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_trx_custom_id (custom_id),
    INDEX (user_id),
    INDEX (event_id)
);

-- =============================================================================
-- TICKETS
-- =============================================================================
CREATE TABLE IF NOT EXISTS tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    barcode VARCHAR(255) NOT NULL,
    ticket_code VARCHAR(100) DEFAULT NULL,
    qr_code_path VARCHAR(255) DEFAULT NULL,
    status ENUM('valid', 'used', 'cancelled') DEFAULT 'valid',
    used TINYINT(1) DEFAULT 0,
    used_at DATETIME DEFAULT NULL,
    reminder_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticket_barcode (barcode),
    UNIQUE KEY uq_ticket_code (ticket_code),
    INDEX idx_tickets_user (user_id),
    INDEX idx_tickets_event (event_id),
    CONSTRAINT fk_ticket_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = INNODB DEFAULT CHARSET = UTF8MB4;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FAVORITES
-- =============================================================================

CREATE TABLE IF NOT EXISTS favorites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_event (user_id, event_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES auth_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Add coordinates to events table for map integration
ALTER TABLE events
ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER location;

ALTER TABLE events
ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude;

-- =============================================================================
-- NOTIFICATIONS
-- =============================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_auth_id BIGINT UNSIGNED NOT NULL,
    sender_auth_id BIGINT UNSIGNED DEFAULT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    metadata JSON DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_recipient (recipient_auth_id),
    CONSTRAINT fk_notif_recipient FOREIGN KEY (recipient_auth_id) REFERENCES auth_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_sender FOREIGN KEY (sender_auth_id) REFERENCES auth_accounts (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- =============================================================================
-- MEDIA FOLDERS
-- =============================================================================
CREATE TABLE IF NOT EXISTS media_folders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    restoration_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_folder_client (client_id),
    CONSTRAINT fk_folder_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE = INNODB DEFAULT CHARSET = UTF8MB4;

-- =============================================================================
-- MEDIA
-- =============================================================================
CREATE TABLE IF NOT EXISTS media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED DEFAULT NULL,
    folder_name VARCHAR(100) DEFAULT 'default',
    file_name VARCHAR(255) NOT NULL,
    file_extension VARCHAR(20) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM(
        'image',
        'video',
        'document',
        'pdf',
        'word',
        'excel',
        'powerpoint',
        'archive',
        'other'
    ) DEFAULT 'other',
    file_size BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_client (client_id),
    KEY idx_media_folder (folder_id),
    CONSTRAINT fk_media_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_media_folder FOREIGN KEY (folder_id) REFERENCES media_folders (id) ON DELETE SET NULL
) ENGINE = INNODB DEFAULT CHARSET = UTF8MB4;

-- =============================================================================
-- SMS LOGS (TWILIO INTEGRATION + OTP + REMINDERS + RECEIPTS)
-- =============================================================================
CREATE TABLE IF NOT EXISTS sms_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

-- Who triggered this SMS (nullable for system events)
auth_id BIGINT UNSIGNED DEFAULT NULL,
user_id BIGINT UNSIGNED DEFAULT NULL,
client_id BIGINT UNSIGNED DEFAULT NULL,

-- Phone & Message Info
phone_number VARCHAR(20) NOT NULL,
message_type ENUM(
    'otp',
    'event_reminder',
    'payment_confirmation',
    'ticket_confirmation',
    'admin_notification'
) NOT NULL,
message_body TEXT NOT NULL,

-- Twilio Response Data
twilio_sid VARCHAR(100) DEFAULT NULL,
twilio_status VARCHAR(50) DEFAULT NULL,
twilio_error_code VARCHAR(50) DEFAULT NULL,
twilio_error_message VARCHAR(255) DEFAULT NULL,

-- Delivery Tracking
status ENUM(
    'queued',
    'sent',
    'delivered',
    'failed',
    'undelivered'
) DEFAULT 'queued',
sent_at DATETIME DEFAULT NULL,
delivered_at DATETIME DEFAULT NULL,

-- Cost tracking (important for financial monitoring)

price DECIMAL(10,5) DEFAULT NULL,
    price_unit VARCHAR(10) DEFAULT NULL,

    metadata JSON DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_sms_auth (auth_id),
    KEY idx_sms_user (user_id),
    KEY idx_sms_client (client_id),
    KEY idx_sms_status (status),
    KEY idx_sms_type (message_type),

    CONSTRAINT fk_sms_auth FOREIGN KEY (auth_id)
        REFERENCES auth_accounts(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT fk_sms_user FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT fk_sms_client FOREIGN KEY (client_id)
        REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


UPDATE auth_accounts
SET
    is_active = 1,
    is_online = 0
WHERE
    is_active = 0
    AND deleted_at IS NULL;

-- Step 2: Mark all accounts as offline (they are not currently connected)
UPDATE auth_accounts SET is_online = 0;

-- Step 3: Clear any expired auth tokens that may be blocking re-login
DELETE FROM auth_tokens WHERE expires_at < NOW();

-- Confirmation
-- Confirmation
SELECT
    COUNT(*) AS total_accounts,
    SUM(is_active) AS active_accounts,
    SUM(is_online) AS online_accounts
FROM auth_accounts
WHERE
    deleted_at IS NULL;

-- =============================================================================
-- SEED DEFAULT SYSTEM ADMIN (LOCAL AUTH ONLY)
-- =============================================================================

INSERT INTO
    auth_accounts (
        email,
        username,
        password_hash,
        auth_provider,
        provider_id,
        role,
        role_locked,
        is_active,
        email_verified_at
    )
VALUES (
        'admin@eventra.com',
        'admin',
        '$2y$10$iPiJGuc.fOdzO109eUDsvefK44TZwvQlCICiVxbD1KHYRx1lxwrVS',
        'local',
        NULL,
        'admin',
        1,
        1,
        NOW()
    );

INSERT INTO
    admins (
        admin_auth_id,
        name,
        username,
        password,
        profile_pic,
        status,
        metadata
    )
VALUES (
        LAST_INSERT_ID(),
        'System Administrator',
        'admin',
        '$2y$10$iPiJGuc.fOdzO109eUDsvefK44TZwvQlCICiVxbD1KHYRx1lxwrVS',
        '/public/assets/imgs/admin.png',
        'active',
        JSON_OBJECT(
            'created_by',
            'system',
            'immutable',
            true,
            'note',
            'Default system administrator account'
        )
    );

DELETE FROM clients WHERE id = 1;

SELECT * FROM admins;

SELECT * FROM clients;

SELECT * FROM events;

SELECT * FROM users;

delete from users where id = 1;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS custom_id VARCHAR(20) DEFAULT NULL AFTER id,
ADD UNIQUE KEY IF NOT EXISTS uq_user_custom_id (custom_id);

-- Clients
ALTER TABLE clients
ADD COLUMN IF NOT EXISTS custom_id VARCHAR(20) DEFAULT NULL AFTER id,
ADD UNIQUE KEY IF NOT EXISTS uq_client_custom_id (custom_id);

-- Events
ALTER TABLE events
ADD COLUMN IF NOT EXISTS custom_id VARCHAR(30) DEFAULT NULL AFTER id,
ADD UNIQUE KEY IF NOT EXISTS uq_event_custom_id (custom_id);

-- Payments
ALTER TABLE payments
ADD COLUMN IF NOT EXISTS custom_id VARCHAR(30) DEFAULT NULL AFTER id,
ADD UNIQUE KEY IF NOT EXISTS uq_payment_custom_id (custom_id);

-- Payment Transactions
ALTER TABLE payment_transactions
ADD COLUMN IF NOT EXISTS custom_id VARCHAR(30) DEFAULT NULL AFTER id,
ADD UNIQUE KEY IF NOT EXISTS uq_payment_trx_custom_id (custom_id);

-- Tickets custom_id: TIC-YYYYMMDD-####
ALTER TABLE tickets
ADD COLUMN IF NOT EXISTS custom_id VARCHAR(30) DEFAULT NULL AFTER id,
ADD UNIQUE KEY IF NOT EXISTS uq_ticket_custom_id (custom_id);

-- ─── 2. TICKET DAILY SEQUENCE TABLE ─────────────────────────────────────────
-- Used to generate TIC-YYYYMMDD-#### sequential numbers
CREATE TABLE IF NOT EXISTS ticket_daily_sequence (
    seq_date DATE NOT NULL,
    seq_value INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (seq_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ─── 3. ONLINE STATUS INDEX ──────────────────────────────────────────────────
-- Speed up "who is online in the last 5 min" queries
ALTER TABLE auth_accounts
ADD INDEX IF NOT EXISTS idx_auth_last_seen (last_seen);

-- ─── 4. BACKFILL EXISTING ROWS  ──────────────────────────────────────────────
-- Generate random custom_ids for existing users (USR-XXXXXX)
UPDATE users
SET
    custom_id = CONCAT(
        'USR-',
        UPPER(SUBSTRING(MD5(CONCAT(id, RAND(), NOW())), 1, 8))
    )
WHERE
    custom_id IS NULL OR custom_id LIKE 'USR-%' AND LENGTH(custom_id) <= 10;

-- Generate random custom_ids for existing clients
UPDATE clients
SET
    custom_id = CONCAT(
        'CLI-',
        UPPER(SUBSTRING(MD5(CONCAT(id, RAND(), NOW())), 1, 8))
    )
WHERE
    custom_id IS NULL OR custom_id NOT LIKE 'CLI-%' OR LENGTH(custom_id) <= 10;

-- Generate random custom_ids for existing events
UPDATE events
SET
    custom_id = CONCAT(
        'EVT-',
        UPPER(SUBSTRING(MD5(CONCAT(id, RAND(), NOW())), 1, 10))
    )
WHERE
    custom_id IS NULL OR LENGTH(custom_id) <= 14;

-- Generate random custom_ids for existing payments
UPDATE payments
SET
    custom_id = CONCAT(
        'txn_',
        LOWER(SUBSTRING(MD5(CONCAT(id, reference, RAND())), 1, 12))
    )
WHERE
    custom_id IS NULL OR custom_id NOT LIKE 'txn_%' OR LENGTH(custom_id) <= 12;

-- Generate random custom_ids for existing payment_transactions
UPDATE payment_transactions
SET
    custom_id = CONCAT(
        'TRX-',
        UPPER(SUBSTRING(MD5(CONCAT(id, payment_reference, RAND())), 1, 8))
    )
WHERE
    custom_id IS NULL;

-- Generate random custom_ids for existing tickets
UPDATE tickets
SET
    custom_id = CONCAT(
        'TIC-',
        UPPER(SUBSTRING(MD5(CONCAT(id, barcode, RAND())), 1, 8))
    )
WHERE
    custom_id IS NULL OR custom_id LIKE 'TIC-%-%';

-- ─── 5. RESET STALE is_online FLAGS ─────────────────────────────────────────
-- Mark users offline if last_seen was more than 5 minutes ago (or never)
UPDATE auth_accounts
SET
    is_online = 0
WHERE
    is_online = 1
    AND (
        last_seen IS NULL
        OR last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    );

-- ─── 6. PERFORMANCE INDEXES & ALTERATIONS ──────────────────────────────────────
ALTER TABLE tickets ADD COLUMN referred_by_id BIGINT UNSIGNED DEFAULT NULL;
ALTER TABLE tickets ADD CONSTRAINT fk_ticket_referred FOREIGN KEY (referred_by_id) REFERENCES clients (id) ON DELETE SET NULL;

ALTER TABLE events ADD INDEX idx_events_status_date (status, event_date);
ALTER TABLE payments ADD INDEX idx_payments_status_date (status, paid_at);
ALTER TABLE auth_accounts ADD INDEX idx_auth_role_online (role, is_online, last_seen);

SELECT 'Migration complete.' AS status;