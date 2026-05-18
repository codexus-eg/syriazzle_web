<?php
require_once '../auth_guard.php'; // تأكد من أن المسار صحيح

header('Content-Type: application/json');

if (!hasPermission('view_bookings')) {
    echo json_encode(['success' => false, 'message' => 'وصول مرفوض.']);
    exit;
}

try {
    // جلب آخر 5 إشعارات غير مقروءة
    $stmt = $pdo->prepare("SELECT id, message, link, created_at FROM admin_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تحديث حالة هذه الإشعارات إلى "مقروءة" بمجرد عرضها
    if (!empty($notifications)) {
        $ids_to_mark_as_read = array_column($notifications, 'id');
        $placeholders = implode(',', array_fill(0, count($ids_to_mark_as_read), '?'));
        $update_stmt = $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id IN ($placeholders)");
        $update_stmt->execute($ids_to_mark_as_read);
    }

    echo json_encode(['success' => true, 'notifications' => $notifications]);

} catch (PDOException $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بالخادم.']);
}
?>