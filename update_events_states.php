<?php
require_once __DIR__ . '/config/database.php';

try {
    // 36 Nigerian States
    $states = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa',
        'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo',
        'Ekiti', 'Enugu', 'Gombe', 'Imo', 'Jigawa', 'Kaduna',
        'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
        'Lasgidi', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun',
        'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'
    ];
    
    // Also create some free events
    $stmt = $pdo->prepare("SELECT id FROM events WHERE deleted_at IS NULL ORDER BY id ASC");
    $stmt->execute();
    $events = $stmt->fetchAll();
    
    echo "Updating " . count($events) . " events with states and prices...\n";
    
    foreach ($events as $index => $event) {
        $state = $states[$index % count($states)];
        
        // Make every 15th event free
        $price = (($index + 1) % 15 == 0) ? 0 : ($index % 7 * 500 + 1000); // 1000, 1500, 2000, 2500, 3000, 3500, 0
        
        $stmt = $pdo->prepare("UPDATE events SET state = ?, price = ? WHERE id = ?");
        $stmt->execute([$state, $price, $event['id']]);
        
        if (($index + 1) % 30 == 0) {
            echo "   Updated " . ($index + 1) . " events...\n";
        }
    }
    
    echo "✅ All events updated with states and prices\n";
    
    // Show distribution
    echo "\nState Distribution:\n";
    $stmt = $pdo->prepare("SELECT state, COUNT(*) as count FROM events WHERE deleted_at IS NULL GROUP BY state ORDER BY state");
    $stmt->execute();
    $results = $stmt->fetchAll();
    foreach ($results as $row) {
        echo "   {$row['state']}: {$row['count']} events\n";
    }
    
    // Show price distribution
    echo "\nPrice Distribution:\n";
    $stmt = $pdo->prepare("SELECT price, COUNT(*) as count FROM events WHERE deleted_at IS NULL GROUP BY price ORDER BY price DESC");
    $stmt->execute();
    $prices = $stmt->fetchAll();
    foreach ($prices as $row) {
        $priceLabel = $row['price'] == 0 ? 'FREE' : '₦' . $row['price'];
        echo "   {$priceLabel}: {$row['count']} events\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
