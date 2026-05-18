<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$u_id = $_SESSION['user_id'] ?? $_SESSION['driver_id'] ?? $_SESSION['business_id'] ?? 0;
$u_type = isset($_SESSION['driver_id']) ? 'driver' : (isset($_SESSION['business_id']) ? 'business' : 'user');

if ($u_id > 0) {
    // نجعل كل الإشعارات مقروءة بضغطة واحدة
    $stmt = $pdo->prepare("UPDATE site_notifications SET is_read = 1 WHERE user_id = ? AND user_type = ?");
    $stmt->execute([$u_id, $u_type]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}