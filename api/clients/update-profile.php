<?php

/**
 * Update Client Profile API
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';

// Check authentication using proper middleware
// checkAuth('client') returns the client profile ID, but we need the auth_id
$client_id = checkAuth('client');

// Get the client_auth_id from the client_id
$stmt = $pdo->prepare("SELECT client_auth_id FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client_auth_id = $stmt->fetchColumn();

if (!$client_auth_id) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Client profile not found']);
    exit;
}

$required_fields = [
    'name' => 'Name',
    'business_name' => 'Business/Organization Name',
    'phone' => 'Phone Number',
    'address' => 'Address',
    'city' => 'City',
    'state' => 'State',
    'country' => 'Country',
    'job_title' => 'Job Title',
    'company' => 'Company',
    'dob' => 'Date of Birth',
    'gender' => 'Gender',
    'nin' => 'NIN',
    'bvn' => 'BVN',
    'account_number' => 'Account Number',
    'bank_code' => 'Settlement Bank',
    'account_name' => 'Account Holder Name'
];

foreach ($required_fields as $field => $label) {
    if (empty(trim($_POST[$field] ?? ''))) {
        echo json_encode(['success' => false, 'message' => "$label is required"]);
        exit;
    }
}

// Strict length validations
$nin = trim($_POST['nin']);
if (strlen($nin) !== 11 || !ctype_digit($nin)) {
    echo json_encode(['success' => false, 'message' => "NIN must be exactly 11 digits"]);
    exit;
}

$bvn = trim($_POST['bvn']);
if (strlen($bvn) !== 11 || !ctype_digit($bvn)) {
    echo json_encode(['success' => false, 'message' => "BVN must be exactly 11 digits"]);
    exit;
}

$account_number = trim($_POST['account_number']);
if (strlen($account_number) !== 10 || !ctype_digit($account_number)) {
    echo json_encode(['success' => false, 'message' => "Account Number must be exactly 10 digits"]);
    exit;
}

$name = trim($_POST['name']);
$business_name = trim($_POST['business_name']);
$phone = trim($_POST['phone']);
$address = trim($_POST['address']);
$city = trim($_POST['city']);
$state = trim($_POST['state']);
$country = trim($_POST['country']);
$job_title = trim($_POST['job_title']);
$company = trim($_POST['company']);
$dob = trim($_POST['dob']);
$gender = trim($_POST['gender']);
$nin = trim($_POST['nin']);
$bvn = trim($_POST['bvn']);
$account_number = trim($_POST['account_number']);
$bank_code = trim($_POST['bank_code']);
$bank_name = trim($_POST['bank_name'] ?? '');
$account_name = trim($_POST['account_name']);

try {
    $pdo->beginTransaction();

    // Fetch existing data for comparison and filling missing fields
    $stmt_existing = $pdo->prepare("
        SELECT c.business_name, c.custom_id, a.email, c.nin, c.bvn, c.nin_verified, c.bvn_verified, c.subaccount_code, c.account_number, c.bank_code, c.verification_status, c.account_name 
        FROM clients c
        JOIN auth_accounts a ON c.client_auth_id = a.id
        WHERE c.client_auth_id = ?
    ");
    $stmt_existing->execute([$client_auth_id]);
    $existing = $stmt_existing->fetch();

    if (empty($business_name) && $existing) {
        $business_name = $existing['business_name'];
    }

    // Check if trying to update business name and if it exists
    if (!empty($business_name) && $existing && $business_name !== $existing['business_name']) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE business_name = ? AND client_auth_id != ? AND deleted_at IS NULL");
        $stmt->execute([$business_name, $client_auth_id]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Business name already in use']);
            exit;
        }
    }

    // Handle Profile Picture Upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = 'client_' . $client_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $profile_pic = 'uploads/profiles/' . $new_filename;
            }
        }
    }

    // Preserve existing nin_verified / bvn_verified by default
    $nin_verified = $existing['nin_verified'] ?? 0;
    $bvn_verified = $existing['bvn_verified'] ?? 0;

    // Only reset verification_status to 'pending' if sensitive identity/payment fields changed.
    // Regular profile edits (name, address, phone) should NOT revoke verification.
    $sensitive_changed = (
        ($nin       !== ($existing['nin']            ?? '')) ||
        ($bvn       !== ($existing['bvn']            ?? '')) ||
        ($account_number !== ($existing['account_number'] ?? '')) ||
        ($bank_code !== ($existing['bank_code']      ?? ''))
    );

    if ($sensitive_changed) {
        $new_verification_status = 'pending';
        // Sensitive fields changed — reset identity verification flags
        $nin_verified = 0;
        $bvn_verified = 0;
    } else {
        // Keep the current verification status (don't regress verified clients)
        $new_verification_status = $existing['verification_status'] ?? 'pending';
    }

    $account_name = $existing['account_name'] ?? null;
    $auth_email = $existing['email'] ?? '';

    // Check if bank details have changed or subaccount is missing
    $bank_changed = (
        $account_number !== ($existing['account_number'] ?? '') ||
        $bank_code !== ($existing['bank_code'] ?? '') ||
        empty($existing['subaccount_code'])
    );

    // ── Bank Details: Subaccount Resolution ────────────────
    if (!empty($bank_code) && !empty($account_number)) {
        if ($bank_changed) {
            $subResult = ensureSubaccount(
                $pdo,
                $client_auth_id,
                $bank_code,
                $account_number,
                $business_name ?: $name,
                $auth_email
            );

            if (!$subResult['success']) {
                // In production, we might want to fail the update if subaccount creation fails.
                // However, we'll log it and continue if it's a non-critical failure, 
                // but let's be strict as per user request.
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Payment Setup Failed: ' . $subResult['message']]);
                exit;
            }

            // Use provided account_name if given, else fall back to a test placeholder
            $provided_account_name = trim($_POST['account_name'] ?? '');
            $account_name = !empty($provided_account_name)
                ? $provided_account_name
                : ($subResult['account_name'] ?? 'Test Account');
        } else {
            $account_name = $existing['account_name'] ?? ($business_name ?: $name);
        }
    }

    // Generate custom_id for existing clients who don't have one
    $customId = $existing['custom_id'] ?? null;
    if (empty($customId)) {
        require_once __DIR__ . '/../../api/utils/id-generator.php';
        $customId = generateClientId($pdo);
    }

    // Prepare Update Query
    $query = "UPDATE clients SET custom_id = ?, name = ?, business_name = ?, phone = ?, address = ?, city = ?, state = ?, country = ?, job_title = ?, company = ?, dob = ?, gender = ?, nin = ?, bvn = ?, nin_verified = ?, bvn_verified = ?, account_name = ?, account_number = ?, bank_name = ?, bank_code = ?, verification_status = ?, updated_at = NOW()";
    $params = [
        $customId, $name, $business_name, $phone, $address, $city, $state, $country, $job_title, $company, $dob, $gender,
        $nin, $bvn,
        $nin_verified,
        $bvn_verified,
        $account_name, $account_number, $bank_name, $bank_code, $new_verification_status
    ];

    if ($profile_pic) {
        $query .= ", profile_pic = ?";
        $params[] = $profile_pic;
    }

    $query .= " WHERE client_auth_id = ?";
    $params[] = $client_auth_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    // Fetch updated client data to return
    $stmt = $pdo->prepare("
        SELECT * 
        FROM clients 
        WHERE client_auth_id = ?
    ");
    $stmt->execute([$client_auth_id]);
    $updated_client = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format for frontend
    if ($updated_client) {
        $updated_client['role'] = 'client';
        if ($updated_client['profile_pic']) {
            $updated_client['profile_pic'] = '/' . $updated_client['profile_pic'];
        }
        // Remove password hash from array before sending
        unset($updated_client['password']);
    }

    // Refresh session activity to ensure profile updates count as user activity
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['last_activity'] = time();
    }

    // Notify user about profile update using helper
    require_once '../utils/notification-helper.php';
    createNotification($client_auth_id, "Your profile has been updated successfully.", 'profile_updated', $client_auth_id, 'client', 'client');

    // Notify admin about profile change for review
    $admin_id = getAdminUserId();
    if ($admin_id) {
        $client_name = $updated_client['business_name'] ?? $updated_client['name'];
        createClientProfileUpdatedNotification($admin_id, $client_auth_id, $client_name);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $updated_client
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
