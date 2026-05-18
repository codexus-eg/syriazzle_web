<?php
// ========================================================================
// Syriazzle Bookings - حارس النظام: إلغاء الحجوزات منتهية الصلاحية (v3.0 - ديناميكي)
// ========================================================================

// --- التأكد من أن الكود يعمل فقط من سطر الأوامر (CLI) للأمان ---
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi') {
    http_response_code(403);
    die("Access Denied. This script is for server execution only.");
}

// تحديد المسار الجذري للمشروع بشكل ديناميكي
$root_path = dirname(dirname(dirname(__FILE__)));

// تضمين ملف الاتصال بقاعدة البيانات
require_once $root_path . '/php/db_connect.php';

echo "[" . date('Y-m-d H:i:s') . "] Cron Job Started: Canceling expired bookings...\n";

try {
    // **الخطوة الجديدة: قراءة فترة السماح ديناميكيًا من قاعدة البيانات**
    $settings_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'booking_grace_period_minutes'");
    // قيمة افتراضية 30 دقيقة في حال فشل القراءة أو عدم وجود الإعداد
    $grace_period_minutes = $settings_stmt ? (int)$settings_stmt->fetchColumn() : 30; 

    if ($grace_period_minutes <= 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Info: Grace period is set to 0 or less. Automatic cancellation is disabled. Exiting.\n";
        exit;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Using grace period: {$grace_period_minutes} minutes.\n";
    
    // استخدام المتغير الديناميكي في الاستعلام
    $sql = "UPDATE bookings 
            SET status = 'cancelled_by_system', cancellation_reason = 'انتهى الوقت المتاح لدفع العربون' 
            WHERE status = 'pending_payment' AND created_at < NOW() - INTERVAL ? MINUTE";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$grace_period_minutes]);

    $affected_rows = $stmt->rowCount();
    
    if ($affected_rows > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Success: Cancelled {$affected_rows} expired bookings.\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Info: No expired bookings found to cancel.\n";
    }

} catch (PDOException $e) {
    $error_message = "[" . date('Y-m-d H:i:s') . "] Cron Job FATAL ERROR: " . $e->getMessage() . "\n";
    echo $error_message;
    
    // تسجيل الخطأ في ملف logs
    $log_file = $root_path . '/logs/cron_errors.log';
    if (!is_dir(dirname($log_file))) { mkdir(dirname($log_file), 0755, true); }
    error_log($error_message, 3, $log_file);
}

echo "[" . date('Y-m-d H:i:s') . "] Cron Job Finished.\n";
?>