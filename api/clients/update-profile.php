<?php
/**
 * Update Client Profile API
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/payment.php';
require_once '../../includes/middleware/auth.php';

// Check authentication
$auth_id = $_SESSION['auth_id'] ?? checkAuth('client');
$client_id = $_SESSION['client_id']; // This is clients.id starting from 1
$name = $_POST['name'];
$business_name = $_POST['business_name'];
$phone = $_POST['phone'];
$address = $_POST['address'];
$city = $_POST['city'];
$state = $_POST['state'];
$country = $_POST['country'];
$job_title = $_POST['job_title'];
$company = $_POST['company'];
$dob = $_POST['dob'];
$gender = $_POST['gender'];

$nin = $_POST['nin'];
$bvn = $_POST['bvn'];
$account_number = $_POST['account_number'];
$bank_code = $_POST['bank_code']; 
$bank_name = $_POST['bank_name'];

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if trying to update business name and if it exists
    if (!empty($business_name)) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE business_name = ? AND client_auth_id != ? AND deleted_at IS NULL");
        $stmt->execute([$business_name, $auth_id]);
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

    // Fetch existing data for comparison and filling missing fields
    $stmt_existing = $pdo->prepare("
        SELECT c.business_name, a.email, c.nin, c.bvn, c.nin_verified, c.bvn_verified, c.subaccount_code, c.account_number, c.bank_code, c.verification_status 
        FROM clients c
        JOIN auth_accounts a ON c.client_auth_id = a.id
        WHERE c.client_auth_id = ?
    ");
    $stmt_existing->execute([$auth_id]);
    $existing = $stmt_existing->fetch();

    if (empty($business_name) && $existing) {
        $business_name = $existing['business_name'];
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

    $account_name = null;
    $auth_email = $existing['email'] ?? '';

    // ── Paystack Subaccount Automation ─────────────────────────────────────
    if (!empty($bank_code) && !empty($account_number)) {
        // Resolve Account Name first if we don't have it (optional but good for business_name)
        $resolveRes = paystackRequest('GET', "/bank/resolve?account_number={$account_number}&bank_code={$bank_code}");
        $resolved_account_name = $resolveRes['body']['data']['account_name'] ?? ($business_name ?: $name);

        $subRes = ensureSubaccount(
            $pdo, 
            $auth_id, 
            $bank_code, 
            $account_number, 
            $resolved_account_name, 
            $auth_email, 
            $existing['subaccount_code']
        );

        if (!$subRes['success']) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $subRes['message']]);
            exit;
        }
        $account_name = $resolved_account_name;
    }

    // Prepare Update Query
    $query = "UPDATE clients SET name = ?, business_name = ?, phone = ?, address = ?, city = ?, state = ?, country = ?, job_title = ?, company = ?, dob = ?, gender = ?, nin = ?, bvn = ?, nin_verified = ?, bvn_verified = ?, account_name = ?, account_number = ?, bank_name = ?, bank_code = ?, verification_status = ?, updated_at = NOW()";
    $params = [
        $name, $business_name, $phone, $address, $city, $state, $country, $job_title, $company, $dob, $gender,
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
    $params[] = $auth_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    // Fetch updated client data to return
    $stmt = $pdo->prepare("
        SELECT * 
        FROM clients 
        WHERE client_auth_id = ?
    ");
    $stmt->execute([$auth_id]);
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
    createNotification($auth_id, "Your profile has been updated successfully.", 'profile_updated', $auth_id, 'client', 'client');
    
    // Notify admin about profile change for review
    $admin_id = getAdminUserId();
    if ($admin_id) {
        $client_name = $updated_client['business_name'] ?? $updated_client['name'];
        createClientProfileUpdatedNotification($admin_id, $auth_id, $client_name);
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
