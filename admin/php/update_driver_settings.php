<?php
// استدعاء حارس المنطق الموحد
require_once '../auth_guard.php';

header('Content-Type: application/json');

// --- حارس البوابة 1: التحقق من الصلاحية ---
if (!hasPermission('edit_driver_financials')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل الإعدادات المالية للسائقين.']);
    exit;
}

// --- التحقق من المدخلات ---
$driver_id = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
$commission_rate = isset($_POST['commission_rate']) ? (float)$_POST['commission_rate'] : 0;
$credit_limit = isset($_POST['credit_limit']) ? (float)$_POST['credit_limit'] : 0;

if ($driver_id === 0) {
    echo json_encode(['success' => false, 'message' => 'خطأ: معرّف السائق غير صالح.']);
    exit;
}

try {
    // --- حارس البوابة 2: التحقق من صلاحية المحافظة ---
    $stmt_check = $pdo->prepare("SELECT governorate_id FROM drivers WHERE id = ?");
    $stmt_check->execute([$driver_id]);
    $driver = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        throw new Exception("السائق غير موجود.");
    }
    if (!hasPermission('super_admin_access_all') && $driver['governorate_id'] !== $admin_governorate_id) {
        throw new Exception("لا يمكنك تعديل بيانات سائق خارج محافظتك.");
    }

    // --- تنفيذ التحديث ---
    $stmt = $pdo->prepare("UPDATE drivers SET commission_rate = ?, credit_limit = ? WHERE id = ?");
    $stmt->execute([$commission_rate, $credit_limit, $driver_id]);

    echo json_encode(['success' => true, 'message' => 'تم تحديث الإعدادات المالية للسائق بنجاح.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'فشل التحديث: ' . $e->getMessage()]);
}
?>