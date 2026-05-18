<?php
// ========================================================================
// Syriazzle Admin - Fetch Dropdown Notifications (Geographic Aware)
// ========================================================================

// 1. استدعاء الملفات الأساسية وحارس البوابة
require_once '../../php/db_connect.php';
require_once '../auth_guard.php';

// ضبط الاستجابة لتكون بصيغة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من صلاحية الجلسة للأدمن
if (!isset($_SESSION['admin_id'])) {
    echo json_encode([]);
    exit;
}

$current_admin_id = (int)$_SESSION['admin_id'];

try {
    // ============================================================
    // 2. تطبيق "نمط الاستعلام القياسي" (Standard Query Pattern)
    // ============================================================
    
    $where_conditions = [];
    $params = [];

    // الشرط 1: جلب الإشعارات الموجهة لهذا الأدمن تحديداً (أو السوبر أدمن)
    // في نظامنا، الإشعارات تُرسل لـ user_id المعين في الجلسة
    $where_conditions[] = "user_id = ?";
    $params[] = $current_admin_id;

    // الشرط 2: نوع المستخدم هو 'user' (لأن الأدمن في هذا الجدول يُصنف كـ user)
    $where_conditions[] = "user_type = 'user'";

    // 3. تجميع الاستعلام
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(' AND ', $where_conditions);
    }

    // 4. تنفيذ الاستعلام مع حساب الوقت بصيغة عربية (Time Ago)
    // نستخدم TIMESTAMPDIFF لضمان دقة التوقيت
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
                ELSE DATE_FORMAT(created_at, '%d/%m %H:%i')
            END as time_ago 
        FROM site_notifications 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. إرسال النتيجة
    echo json_encode($notifications);

} catch (PDOException $e) {
    // تسجيل الخطأ فني في سجلات السيرفر
    error_log("Admin Notifications Fetch Error: " . $e->getMessage());
    echo json_encode([]);
}
exit;