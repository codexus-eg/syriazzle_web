<?php
// تفعيل كل آليات الحماية والصلاحيات
require_once 'auth_guard.php';

// --- حارس البوابة 1: التحقق من صلاحية "حذف سائق" ---
if (!hasPermission('delete_driver')) {
    $_SESSION['admin_message'] = "ليس لديك الصلاحية اللازمة لحذف السائقين.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: manage_drivers.php');
    exit;
}

// --- التحقق من أن الطلب POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_drivers.php'); exit;
}

$driver_id = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
if ($driver_id === 0) {
    $_SESSION['admin_message'] = "خطأ: لم يتم تحديد معرّف السائق.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: manage_drivers.php');
    exit;
}

try {
    // --- حارس البوابة 2: التحقق من صلاحية المحافظة ---
    $stmt_check = $pdo->prepare("SELECT full_name, governorate_id FROM drivers WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$driver_id]);
    $driver = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        throw new Exception("السائق غير موجود أو تم حذفه بالفعل.");
    }
    
    // إذا لم يكن سوبر أدمن، تأكد من أن السائق يتبع لمحافظته
    if (!hasPermission('super_admin_access_all') && $driver['governorate_id'] !== $_SESSION['admin_governorate_id']) {
        throw new Exception("لا يمكنك إدارة سائقين خارج محافظتك.");
    }

    // --- بدء عملية الحذف الآمنة (Transaction) ---
    $pdo->beginTransaction();

    // 1. تطبيق الحذف الناعم (Soft Delete)
    $stmt_soft_delete = $pdo->prepare("UPDATE drivers SET deleted_at = NOW() WHERE id = ?");
    $stmt_soft_delete->execute([$driver_id]);
    
    // 2. (إجراء وقائي حاسم) إلغاء تعيين السائق من أي طلبات نشطة
    // هذا يعيد الطلب إلى "قائمة الانتظار" ليأخذه سائق آخر.
    $stmt_unassign = $pdo->prepare("
        UPDATE orders 
        SET driver_id = NULL, status = 'ready_for_pickup' 
        WHERE driver_id = ? AND status IN ('accepted', 'picked_up')
    ");
    $stmt_unassign->execute([$driver_id]);

    $pdo->commit();

    $_SESSION['admin_message'] = "تم حذف السائق '" . htmlspecialchars($driver['full_name']) . "' بنجاح.";
    $_SESSION['admin_message_type'] = "success";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['admin_message'] = "فشل حذف السائق: " . $e->getMessage();
    $_SESSION['admin_message_type'] = 'error';
    error_log("Driver deletion error for ID $driver_id: " . $e->getMessage());
}

header('Location: manage_drivers.php');
exit;
?>