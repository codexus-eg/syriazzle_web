<?php
require_once '../../php/db_connect.php';
require_once '../auth_guard.php';

if (!hasPermission('edit_order')) { 
    die("وصول غير مصرح به.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    try {
        $stmt = $pdo->prepare("UPDATE mall_orders SET status = 'preparing', status_last_updated = NOW() WHERE id = ? AND status = 'pending_approval'");
        $stmt->execute([$order_id]);
                
        $_SESSION['admin_message'] = "تمت الموافقة على الطلب رقم #{$order_id} وهو الآن قيد التحضير.";
        $_SESSION['admin_message_type'] = "success";
        header('Location: ../manage_mall_orders.php?status=pending');
        exit;
    } catch (PDOException $e) {
        die("فشل تحديث حالة الطلب: " . $e->getMessage());
    }
}
?>