<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'الوصول مرفوض. يجب تسجيل الدخول أولاً.']);
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'معرف الطلب غير صالح.']);
    exit;
}
try {
    $stmt = $pdo->prepare("
        SELECT o.*, b.name as business_name
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE o.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$order_id, $current_user_id]);
    $order_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order_details) {
        http_response_code(404); 
        echo json_encode(['error' => 'الطلب غير موجود أو ليس لديك صلاحية لعرضه.']);
        exit;
    }
    echo json_encode($order_details, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم أثناء جلب البيانات.']);
}
?>