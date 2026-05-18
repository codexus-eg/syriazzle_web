<?php
// --- الخطوة 1: تضمين العقل المدبر و تفعيل كل آليات الحماية ---
require_once 'auth_guard.php';

// --- الخطوة 2: التحقق من صلاحية الدور ---
// نستخدم 'edit_business' لأن تغيير الحالة هو نوع من التعديل.
if (!hasPermission('edit_business')) {
    $_SESSION['admin_message'] = "ليس لديك الصلاحية لتغيير حالة المتاجر.";
    $_SESSION['admin_message_type'] = "error";
    header('Location: dashboard.php');
    exit;
}

// --- الخطوة 3: التحقق من نوع الطلب وصحة المدخلات ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); // تجاهل الطلبات غير المرسلة كـ POST
    exit;
}

$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
$allowed_statuses = ['pending', 'approved', 'rejected'];

if ($business_id === 0 || !in_array($new_status, $allowed_statuses)) {
    $_SESSION['admin_message'] = "خطأ: طلب غير صالح أو بيانات غير مكتملة.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}

try {
    // --- الخطوة 4: حارس بوابة المحافظة ---
    // جلب بيانات المتجر للتحقق من وجوده وصلاحية الوصول إليه
    $stmt_check = $pdo->prepare("SELECT id, governorate_id FROM businesses WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$business_id]);
    $business = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$business) {
        throw new Exception("النشاط التجاري غير موجود أو تم حذفه.");
    }

    // إذا لم يكن المستخدم سوبر أدمن، تأكد من أن المتجر يتبع لمحافظته
    if (!hasPermission('super_admin_access_all') && $business['governorate_id'] !== $_SESSION['admin_governorate_id']) {
        throw new Exception("لا يمكنك تغيير حالة هذا المتجر لأنه لا يتبع لمحافظتك.");
    }

    // --- الخطوة 5: تطبيق المنطق الذكي لتحديث الحالة (هذا الجزء ممتاز من تصميمك الأصلي) ---
    if ($new_status === 'approved') {
        // جلب الإعدادات العامة الحالية من قاعدة البيانات
        $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $default_commission = (float)($settings['business_commission_rate'] ?? 10.0);
        $default_credit_limit = (float)($settings['business_credit_limit'] ?? 1000000.0);

        // تحديث حالة المتجر مع "تجميد" الإعدادات المالية في لحظة الموافقة
        $stmt_update = $pdo->prepare(
            "UPDATE businesses SET status = :status, commission_rate = :commission_rate, credit_limit = :credit_limit WHERE id = :id"
        );
        $stmt_update->execute([
            ':status' => $new_status,
            ':commission_rate' => $default_commission,
            ':credit_limit' => $default_credit_limit,
            ':id' => $business_id
        ]);
        $_SESSION['admin_message'] = "تمت الموافقة على المتجر وتعيين الإعدادات المالية الافتراضية له بنجاح.";
    } else {
        // للحالات الأخرى (رفض، تعليق)، فقط قم بتحديث الحالة
        $stmt_update = $pdo->prepare("UPDATE businesses SET status = ? WHERE id = ?");
        $stmt_update->execute([$new_status, $business_id]);
        $_SESSION['admin_message'] = "تم تحديث حالة النشاط التجاري بنجاح.";
    }
    
    $_SESSION['admin_message_type'] = "success";

} catch (Exception $e) {
    $_SESSION['admin_message'] = "فشل تحديث الحالة: " . $e->getMessage();
    $_SESSION['admin_message_type'] = 'error';
    // لتتبع الأخطاء في الخلفية دون إظهارها للمستخدم
    error_log("Business status change error for business ID $business_id: " . $e->getMessage());
}

// --- الخطوة 6: إعادة التوجيه ---
header('Location: dashboard.php');
exit;
?>