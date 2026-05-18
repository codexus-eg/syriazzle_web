<?php
require_once '../../php/db_connect.php';
require_once '../auth_guard.php'; // تفعيل الحماية

if (!hasPermission('edit_order')) { // استخدام صلاحية مناسبة
    die("وصول غير مصرح به.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $driver_id = (int)($_POST['driver_id'] ?? 0);

    if ($order_id === 0 || $driver_id === 0) {
        $_SESSION['admin_message'] = "خطأ: بيانات غير مكتملة.";
        $_SESSION['admin_message_type'] = "error";
        header('Location: ../manage_mall_orders.php?status=preparing');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE mall_orders SET assigned_driver_id = ?, status = 'out_for_delivery', status_last_updated = NOW() WHERE id = ? AND status = 'preparing'");
        $stmt->execute([$driver_id, $order_id]);
        
        $_SESSION['admin_message'] = "تم تعيين السائق للطلب رقم #{$order_id} بنجاح.";
        $_SESSION['admin_message_type'] = "success";

        // ================== الإصلاح الحاسم هنا ==================
        // إعادة التوجيه إلى نفس التبويب الذي كنت فيه (قيد التحضير)
        header('Location: ../manage_mall_orders.php?status=preparing');
        exit;

    } catch (PDOException $e) {
        $_SESSION['admin_message'] = "فشل تعيين السائق: " . $e->getMessage();
        $_SESSION['admin_message_type'] = "error";
        header('Location: ../manage_mall_orders.php?status=preparing');
        exit;
    }
}
?>