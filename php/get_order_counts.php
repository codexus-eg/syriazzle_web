<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    $sql = "
        SELECT o.status, COUNT(o.id) as count
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE b.user_id = ?
        GROUP BY o.status
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // التأكد من أن كل الحالات موجودة حتى لو كانت صفر
    $counts = array_fill_keys(['pending_approval', 'preparing', 'ready_for_pickup', 'picked_up', 'delivered', 'canceled'], 0);
    foreach($results as $status => $count) {
        if(isset($counts[$status])) {
            $counts[$status] = $count;
        }
    }
    
    echo json_encode($counts);

} catch (PDOException $e) {
    echo json_encode([]);
}
?>