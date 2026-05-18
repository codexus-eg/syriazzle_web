<?php
require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$status_type = $_GET['status'] ?? 'active';

$statuses = [];
if ($status_type === 'active') {
    // ================== الإصلاح الحاسم هنا ==================
    // إضافة 'pending_approval' و 'preparing' إلى قائمة الطلبات النشطة
    $statuses = ['pending_approval', 'preparing', 'out_for_delivery'];
} else if ($status_type === 'completed') {
    $statuses = ['delivered', 'canceled'];
}

if (empty($statuses)) {
    echo json_encode(['success' => true, 'orders' => []]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($statuses), '?'));

try {
    $sql = "SELECT * FROM mall_orders WHERE user_id = ? AND status IN ($placeholders) ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$current_user_id], $statuses);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'orders' => $orders]);

} catch (PDOException $e) {
    error_log('Error fetching mall orders: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>