<?php
// ========================================================================
// Syriazzle - Fetch Site Notifications API (Unified)
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق من هوية المستخدم (زبون، سائق، أو متجر)
$user_id = 0;
$user_type = '';

if (isset($_SESSION['driver_id'])) {
    $user_id = (int)$_SESSION['driver_id'];
    $user_type = 'driver';
} elseif (isset($_SESSION['business_id'])) {
    $user_id = (int)$_SESSION['business_id'];
    $user_type = 'business';
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $user_type = 'user';
}

if ($user_id === 0) {
    echo json_encode([]);
    exit;
}

try {
    // 2. استعلام جلب الإشعارات مع حساب الوقت بصيغة عربية
    // نستخدم TIMESTAMPDIFF لحساب الفوارق الزمنية
    $sql = "
        SELECT 
            id, 
            title, 
            message, 
            link, 
            is_read,
            created_at,
            CASE 
                WHEN TIMESTAMPDIFF(SECOND, created_at, NOW()) < 60 THEN 'الآن'
                WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 THEN CONCAT('منذ ', TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' دقيقة')
                WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN CONCAT('منذ ', TIMESTAMPDIFF(HOUR, created_at, NOW()), ' ساعة')
                WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) < 7 THEN CONCAT('منذ ', TIMESTAMPDIFF(DAY, created_at, NOW()), ' أيام')
                ELSE DATE_FORMAT(created_at, '%Y/%m/%d')
            END as time_ago
        FROM site_notifications 
        WHERE user_id = ? AND user_type = ?
        ORDER BY created_at DESC 
        LIMIT 15
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_type]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($notifications);

} catch (PDOException $e) {
    error_log("Fetch Notifications Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}