<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة الخاص بالصفحة ---
// تأكد من أن المستخدم لديه صلاحية "إدارة إعدادات النظام" (للسوبر أدمن فقط)
if (!hasPermission('manage_system_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل سعر الصرف.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.']);
    exit;
}

// --- استقبال وتنقية البيانات ---
$new_rate = filter_input(INPUT_POST, 'usd_rate', FILTER_VALIDATE_FLOAT);

if (!$new_rate || $new_rate <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'سعر صرف غير صالح. يجب أن يكون رقماً أكبر من صفر.']);
    exit;
}

try {
    // --- تنفيذ التحديث في قاعدة البيانات ---
    $stmt = $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value) 
         VALUES ('usd_to_syp_rate', :new_rate) 
         ON DUPLICATE KEY UPDATE setting_value = :new_rate"
    );
    $stmt->execute([':new_rate' => $new_rate]);

    echo json_encode(['success' => true, 'message' => 'تم تحديث سعر الصرف بنجاح.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Update USD Rate Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'فشل تحديث سعر الصرف بسبب خطأ في الخادم.']);
}
?>