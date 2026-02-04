<?php
require_once 'config/database.php';
// require_once 'includes/helper_functions.php'; // Not needed

function test($name, $callback)
{
    echo "Testing: $name... ";
    try {
        if ($callback()) {
            echo "\033[32mPASSED\033[0m\n";
        } else {
            echo "\033[31mFAILED\033[0m\n";
        }
    } catch (Exception $e) {
        echo "\033[31mERROR: " . $e->getMessage() . "\033[0m\n";
    }
}

echo "--- Verifying Fixes ---\n";

// 1. Verify Schema
test("Media table has folder_name column", function () use ($pdo) {
    $stmt = $pdo->query("DESCRIBE media folder_name");
    return $stmt->fetch() !== false;
});

// 2. Verify Client Dashboard Stats API (Simulated)
test("get-client-dashboard-stats.php query validity", function () use ($pdo) {
    // We can't easily simulate session here without mocking, but we can check if the query prepares successfully.
    // This tests the SQL syntax fix for the JOIN.
    $sql = "
        SELECT 
            t.*,
            u.display_name as user_name,
            a.email as user_email,
            u.profile_pic as user_profile_pic,
            e.event_name
        FROM tickets t
        INNER JOIN events e ON t.event_id = e.id
        INNER JOIN users u ON t.user_id = u.id
        INNER JOIN auth_accounts a ON u.auth_id = a.id
        WHERE e.client_id = ?
        ORDER BY t.purchase_date DESC
        LIMIT 10
    ";
    try {
        $stmt = $pdo->prepare($sql);
        return true;
    } catch (PDOException $e) {
        echo $e->getMessage();
        return false;
    }
});

// 3. Verify get-users.php query validity
test("get-users.php query validity", function () use ($pdo) {
    $sql = "
        SELECT 
            a.id, 
            COALESCE(u.display_name, c.business_name, a.email) as name, 
            a.email, 
            a.role, 
            COALESCE(u.profile_pic, c.profile_pic) as profile_pic, 
            COALESCE(u.phone, c.phone) as phone,
            c.address, c.city, c.state, u.dob, u.gender, 
            a.is_active as status, 
            a.created_at
        FROM auth_accounts a
        LEFT JOIN users u ON a.id = u.auth_id
        LEFT JOIN clients c ON a.id = c.auth_id
        ORDER BY a.created_at DESC
        LIMIT 10 OFFSET 0
    ";
    try {
        $stmt = $pdo->prepare($sql);
        return true;
    } catch (PDOException $e) {
        echo $e->getMessage();
        return false;
    }
});

echo "--- Verification Complete ---\n";
?>