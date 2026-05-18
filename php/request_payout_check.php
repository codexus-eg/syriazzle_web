<?php
// ========================================================================
// Syriazzle - Payout Notification Handler
// ========================================================================
require_once 'db_connect.php';
require_once 'NotificationManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];

try {
    // 1. جلب اسم السائق وقيمة دينه الحالي
    $stmt = $pdo->prepare("SELECT full_name, commission_balance FROM drivers WHERE id = ?");
    $stmt->execute([$driver_id]);
    $driver = $stmt.fetch();

    if (!$driver) throw new Exception("Driver not found");

    // 2. إرسال إشعار للأدمن (User ID = 1 كأدمن افتراضي، أو نرسل للكل)
    // هنا سنرسل إشعاراً لجدول site_notifications ليراه الأدمن في لوحة تحكمه
    $title = "طلب تسوية رصيد 💰";
    $body = "الكابتن {$driver['full_name']} يدعي تسديد مستحقاته. الرصيد الحالي: " . number_format(abs($driver['commission_balance'])) . " ل.س";
    
    // إرسال الإشعار للأدمن (نفترض أن الأدمن هو User ID: 1)
    NotificationManager::sendNotification(1, 'user', $title, $body, "admin/manage_drivers.php");

    echo json_encode([
        'success' => true, 
        'message' => 'تم إبلاغ المحاسب بنجاح. يرجى الانتظار حتى يتم تدقيق الحوالة وتصفير رصيدك.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'فشل في إرسال الطلب.']);
}