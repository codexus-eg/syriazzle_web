<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

try {
    // جلب بيانات الطلب وموقع السائق المخصص له
    $sql = "
        SELECT 
            o.status, 
            d.full_name as driver_name, d.phone as driver_phone,
            d.current_latitude as driver_latitude, d.current_longitude as driver_longitude
        FROM mall_orders o
        LEFT JOIN drivers d ON o.assigned_driver_id = d.id
        WHERE o.id = ? AND o.user_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id, $current_user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error']);
}
?>