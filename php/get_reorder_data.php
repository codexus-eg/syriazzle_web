<?php
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = (int)$_SESSION['user_id'];

try {
    // 1. التأكد من ملكية الطلب
    $stmt_check = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt_check->execute([$order_id, $user_id]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        exit;
    }

    // 2. جلب المنتجات مع البيانات الحالية من المنيو (صورة وسعر حالي)
    // نربط جدول عناصر الطلب مع جدول المنيو
    $sql = "
        SELECT 
            oi.item_id as id, 
            oi.quantity,
            bmi.item_name as name, 
            bmi.price, 
            bmi.image_path as image
        FROM order_items oi
        JOIN business_menu_items bmi ON oi.item_id = bmi.id
        WHERE oi.order_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'المنتجات لم تعد متوفرة في المنيو']);
        exit;
    }

    echo json_encode(['success' => true, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error']);
}
?>