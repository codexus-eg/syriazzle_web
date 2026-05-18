<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';

// --- حارس البوابة 1: التحقق من صلاحية "الموافقة على سائق" ---
if (!hasPermission('approve_driver')) {
    $_SESSION['admin_message'] = "ليس لديك الصلاحية لتغيير حالة السائقين.";
    $_SESSION['admin_message_type'] = "error";
    header('Location: ../manage_drivers.php');
    exit;
}

// التحقق من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../manage_drivers.php');
    exit;
}

// --- استقبال وتنقية البيانات ---
$driver_id = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
$new_status = $_POST['new_status'] ?? '';
$allowed_statuses = ['approved', 'pending', 'blocked'];

if ($driver_id === 0 || !in_array($new_status, $allowed_statuses)) {
    $_SESSION['admin_message'] = "بيانات غير صالحة أو طلب خاطئ.";
    $_SESSION['admin_message_type'] = "error";
    header('Location: ../manage_drivers.php');
    exit;
}

try {
    // --- حارس البوابة 2: التحقق من صلاحية المحافظة (لغير السوبر أدمن) ---
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM drivers WHERE id = ? AND governorate_id = ?");
        $stmt_check->execute([$driver_id, $admin_governorate_id]);
        if ($stmt_check->fetchColumn() == 0) {
            throw new Exception("لا يمكنك تغيير حالة هذا السائق لأنه لا يتبع لمحافظتك.");
        }
    }

    // --- المنطق الذكي لتحديث الحالة ---
    if ($new_status === 'approved') {
        // جلب الإعدادات العامة الحالية
        $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $default_commission = (float)($settings['driver_commission_rate'] ?? 20.0);
        $default_credit_limit = (float)($settings['driver_credit_limit'] ?? 50000.0);

        // تحديث حالة السائق مع "تجميد" الإعدادات المالية
        $stmt = $pdo->prepare(
            "UPDATE drivers SET status = :status, commission_rate = :commission_rate, credit_limit = :credit_limit WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $new_status,
            ':commission_rate' => $default_commission,
            ':credit_limit' => $default_credit_limit,
            ':id' => $driver_id
        ]);
        $_SESSION['admin_message'] = "تم قبول السائق وتعيين الإعدادات الافتراضية له.";
        $_SESSION['admin_message_type'] = "success";
    } else {
        // الحالات الأخرى (مراجعة، حظر)
        $stmt = $pdo->prepare("UPDATE drivers SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $driver_id]);
        $_SESSION['admin_message'] = "تم تحديث حالة السائق بنجاح.";
        $_SESSION['admin_message_type'] = "success";
    }

} catch (Exception $e) {
    $_SESSION['admin_message'] = "فشل تحديث الحالة: " . $e->getMessage();
    $_SESSION['admin_message_type'] = "error";
    error_log("Driver status change error: " . $e->getMessage());
}

header('Location: ../manage_drivers.php');
exit;
?>