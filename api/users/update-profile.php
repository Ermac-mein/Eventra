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

    // Build update query
    $update_fields = [];
    $params = [];

    if ($name) {
        $update_fields[] = "name = ?";
        $params[] = $name;
    }
    if ($phone) {
        $update_fields[] = "phone = ?";
        $params[] = $phone;
    }
    if ($job_title) {
        $update_fields[] = "job_title = ?";
        $params[] = $job_title;
    }
    if ($company) {
        $update_fields[] = "company = ?";
        $params[] = $company;
    }
    if ($address) {
        $update_fields[] = "address = ?";
        $params[] = $address;
    }
    if ($city) {
        $update_fields[] = "city = ?";
        $params[] = $city;
    }
    if ($state) {
        $update_fields[] = "state = ?";
        $params[] = $state;
    }
    if ($dob) {
        $update_fields[] = "dob = ?";
        $params[] = $dob;
    }
    if ($gender) {
        $update_fields[] = "gender = ?";
        $params[] = $gender;
    }
    if ($profile_pic) {
        $update_fields[] = "profile_pic = ?";
        $params[] = $profile_pic;
    }

    if (empty($update_fields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }

    $params[] = $user_id;
    $update_sql = implode(', ', $update_fields);

    // Determine table
    $role = $_SESSION['role'] ?? 'user';
    $table = 'users';
    if ($role === 'client')
        $table = 'clients';
    if ($role === 'admin')
        $table = 'admins';

    $stmt = $pdo->prepare("UPDATE $table SET $update_sql WHERE id = ?");
    $stmt->execute($params);

    // Get updated user
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    unset($user['password']);

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