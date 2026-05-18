<?php
// ========================================================================
// Syriazzle Admin - Update Settings API (Final Comprehensive Version)
// ========================================================================

// --- استدعاء حارس البوابة الموحد ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة الخاص بالصفحة ---
// تأكد من أن المستخدم لديه صلاحية "إدارة إعدادات النظام"
if (!hasPermission('manage_system_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل إعدادات النظام.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.']);
    exit;
}

// --- قائمة بكل الإعدادات المسموح بتحديثها (تم تحديثها لتشمل المول والحجوزات) ---
$allowed_settings = [
    // 1. إعدادات التوصيل (المتاجر)
    'delivery_base_fare',
    'delivery_per_km_rate',
    'driver_commission_rate',
    'business_commission_rate',
    'driver_credit_limit',
    'business_credit_limit',
    
    // 2. إعدادات العملة والمالية (مهم جداً)
    'usd_to_syp_rate',

    // 3. إعدادات الحجوزات
    'booking_commission_rate',
    'booking_credit_limit',
    'booking_grace_period_minutes',

    // 4. إعدادات المول (الجديدة)
    'mall_base_delivery_fee',
    'mall_price_per_km',
    'mall_latitude',
    'mall_longitude'
];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value) 
         VALUES (:setting_key, :setting_value) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );

    foreach ($allowed_settings as $key) {
        if (isset($_POST[$key])) {
            // تنظيف المدخلات كأرقام عشرية
            $value = filter_var($_POST[$key], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            // التأكد من أن القيمة ليست فارغة
            // ملاحظة: نسمح بالصفر، ونسمح بالإحداثيات (التي قد تكون موجبة)
            if ($value !== false && $value !== '') {
                $stmt->bindValue(':setting_key', $key);
                $stmt->bindValue(':setting_value', $value);
                $stmt->execute();
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'تم حفظ الإعدادات بنجاح.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Update Settings Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'فشل حفظ الإعدادات بسبب خطأ في الخادم.']);
}
?>