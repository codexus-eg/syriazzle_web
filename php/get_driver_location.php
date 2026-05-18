<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
session_start();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) { exit; }

$current_user_id = $_SESSION['user_id'] ?? null;

try {
    // التحقق من أن المستخدم هو صاحب الطلب
    $stmt_check = $pdo->prepare("SELECT user_id, driver_id FROM orders WHERE id = ?");
    $stmt_check->execute([$order_id]);
    $order = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['user_id'] !== $current_user_id || is_null($order['driver_id'])) {
        http_response_code(403);
        exit;
    }

    // جلب آخر موقع للسائق المسؤول عن هذا الطلب
    $stmt_driver = $pdo->prepare("SELECT current_latitude, current_longitude FROM drivers WHERE id = ?");
    $stmt_driver->execute([$order['driver_id']]);
    $location = $stmt_driver->fetch(PDO::FETCH_ASSOC);

    if ($location && !is_null($location['current_latitude'])) {
        echo json_encode([
            'lat' => (float)$location['current_latitude'],
            'lng' => (float)$location['current_longitude']
        ]);
    } else {
        echo json_encode(null); // إرجاع null إذا لم يكن للسائق موقع مسجل
    }

} catch (PDOException $e) {
    http_response_code(500);
    exit;
}
?>