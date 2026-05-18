<?php
// ========================================================================
// Syriazzle - Reject/Timeout Task API (Exclusive Dispatching Logic - V1.0)
// ========================================================================

require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق الأمني من الجلسة
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['driver_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح للوصول.']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
// نوع الرفض: 'rejected' (ضغط زر تجاهل) أو 'timed_out' (انتهى الـ 45 ثانية)
$reason = isset($_POST['reason']) ? $_POST['reason'] : 'rejected'; 

if ($order_id === 0) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. تحديث حالة العرض في جدول order_offers
    // نقوم بوضع الحالة كـ rejected أو timed_out لكي لا يظهر لهذا السائق مرة أخرى في الجلسة الحالية
    $stmt_upd = $pdo->prepare("
        UPDATE order_offers 
        SET status = ?, expires_at = NOW() 
        WHERE order_id = ? AND driver_id = ? AND status = 'pending'
    ");
    $stmt_upd->execute([$reason, $order_id, $driver_id]);

    // 3. التحقق هل تم التحديث فعلاً (لضمان الدقة)
    if ($stmt_upd->rowCount() > 0) {
        $pdo->commit();
        
        // تسجيل الإجراء في سجل الأخطاء للمراقبة الإدارية
        error_log("Driver $driver_id $reason Order #$order_id");

        echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الطلب بنجاح.']);
    } else {
        // العرض ربما انتهى وقته أو قُبل من قبل
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'لم يتم العثور على عرض نشط لتعديله.']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Reject Task Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في النظام.']);
}