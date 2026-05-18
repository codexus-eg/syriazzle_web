<?php
// ========================================================================
// Syriazzle Admin - Delete Notification Handler
// ========================================================================
require_once '../../php/db_connect.php';
require_once '../auth_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$notif_id = isset($_POST['notif_id']) ? (int)$_POST['notif_id'] : 0;

if ($notif_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    // حذف الإشعار من الجدول الموحد
    // نتحقق أن الإشعار يخص هذا الأدمن للأمان
    $stmt = $pdo->prepare("DELETE FROM site_notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $_SESSION['admin_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'لم يتم العثور على السجل أو تم حذفه مسبقاً.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات.']);
}