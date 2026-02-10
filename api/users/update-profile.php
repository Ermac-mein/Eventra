<?php
/**
 * Update User Profile API
 * Updates user or client profile information
 */
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Handle profile picture upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
            $profile_pic = '/uploads/profiles/' . $file_name;
        }
    }

    // Get POST data
    $name = $_POST['name'] ?? null;
    $phone = $_POST['phone'] ?? null;
    $job_title = $_POST['job_title'] ?? null;
    $company = $_POST['company'] ?? null;
    $address = $_POST['address'] ?? null;
    $city = $_POST['city'] ?? null;
    $state = $_POST['state'] ?? null;
    $dob = $_POST['dob'] ?? null;
    $gender = $_POST['gender'] ?? null;

    // Determine table and allowed fields based on role
    $role = $_SESSION['role'] ?? 'user';
    $table = 'users';
    $allowed_fields = ['phone', 'dob', 'gender', 'profile_pic'];
    $name_column = 'display_name';

    if ($role === 'client') {
        $table = 'clients';
        $allowed_fields = ['phone', 'company', 'address', 'city', 'state', 'profile_pic'];
        $name_column = 'business_name';
    } elseif ($role === 'admin') {
        $table = 'admins';
        $allowed_fields = ['profile_pic'];
        $name_column = 'name';
    }

    // Build update query
    $update_fields = [];
    $params = [];

    // Handle Name Field Mapping
    if ($name) {
        $update_fields[] = "$name_column = ?";
        $params[] = $name;
    }

    // Handle Standard Fields
    $fields_map = [
        'phone' => $phone,
        'job_title' => $job_title, // Note: job_title is not in schema for any table currently, ignoring or needing specific handling if added later
        'company' => $company,
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'dob' => $dob,
        'gender' => $gender,
        'profile_pic' => $profile_pic
    ];

    foreach ($fields_map as $field => $value) {
        if ($value !== null && in_array($field, $allowed_fields)) {
            $update_fields[] = "$field = ?";
            $params[] = $value;
        }
    }

    if (empty($update_fields)) {
        echo json_encode(['success' => false, 'message' => 'No matching fields to update for this role']);
        exit;
    }

    $params[] = $user_id;
    $update_sql = implode(', ', $update_fields);

    $stmt = $pdo->prepare("UPDATE $table SET $update_sql WHERE auth_id = ?"); // Changed WHERE id to WHERE auth_id
    $stmt->execute($params);

    // Get updated user
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    unset($user['password']);
    $user['role'] = $role;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $user
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>