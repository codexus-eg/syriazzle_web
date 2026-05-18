<?php
require_once '../auth_guard.php';

if (!hasPermission('delete_business')) {
    die("وصول غير مصرح به.");
}

$business_id = (int)($_GET['id'] ?? 0);
if ($business_id === 0) {
    die("لم يتم تحديد النشاط.");
}

try {
    // التحقق من وجود حجوزات مستقبلية مؤكدة
    $check_stmt = $pdo->prepare("
        SELECT COUNT(b.id) 
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        WHERE s.business_id = ? AND b.status = 'confirmed' AND b.start_datetime > NOW()
    ");
    $check_stmt->execute([$business_id]);
    $future_bookings_count = $check_stmt->fetchColumn();

    if ($future_bookings_count > 0) {
        $_SESSION['admin_message'] = "خطأ: لا يمكن حذف النشاط لوجود حجوزات مستقبلية مؤكدة. يرجى إلغاء الحجوزات أولاً.";
        $_SESSION['admin_message_type'] = 'error';
    } else {
        // تنفيذ الحذف الناعم
        $delete_stmt = $pdo->prepare("UPDATE businesses SET deleted_at = NOW() WHERE id = ?");
        $delete_stmt->execute([$business_id]);
        $_SESSION['admin_message'] = "تم حذف النشاط بنجاح ونقله إلى سلة المحذوفات.";
        $_SESSION['admin_message_type'] = 'success';
    }

} catch (PDOException $e) {
    $_SESSION['admin_message'] = "حدث خطأ أثناء محاولة الحذف: " . $e->getMessage();
    $_SESSION['admin_message_type'] = 'error';
}

header('Location: ../dashboard.php');
exit;
?>