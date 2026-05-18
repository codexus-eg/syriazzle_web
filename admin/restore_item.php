<?php
// استدعاء حارس المنطق الموحد (للأمان والتحقق من الصلاحيات)
require_once 'auth_guard.php';

// --- حارس البوابة 1: التحقق من الصلاحية ---
// هذا الإجراء حساس، لذا نجعله مقتصراً على السوبر أدمن حالياً.
if (!hasPermission('super_admin_access_all')) {
    $_SESSION['admin_message'] = "ليس لديك الصلاحية اللازمة للقيام بهذا الإجراء.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: recycling_bin.php');
    exit;
}

// --- حارس البوابة 2: التحقق من أن الطلب من نوع POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: recycling_bin.php'); 
    exit;
}

// --- تنقية وتدقيق المدخلات ---
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$item_type = isset($_POST['item_type']) ? $_POST['item_type'] : '';

// تحديث مصفوفة الأنواع المسموح بها لتشمل 'user'
$allowed_types = ['business', 'driver', 'user'];

if ($item_id === 0 || !in_array($item_type, $allowed_types)) {
    $_SESSION['admin_message'] = "خطأ: طلب استعادة غير صالح أو بيانات غير مكتملة.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: recycling_bin.php');
    exit;
}

try {
    $table_name = '';
    $item_name_for_message = '';

    // تحديد اسم الجدول والاسم المستخدم في الرسالة بناءً على النوع
    if ($item_type === 'business') {
        $table_name = 'businesses';
        $item_name_for_message = 'المتجر';
    } elseif ($item_type === 'driver') {
        $table_name = 'drivers';
        $item_name_for_message = 'السائق';
    } elseif ($item_type === 'user') {
        $table_name = 'users';
        $item_name_for_message = 'المستخدم';
    }

    // --- تنفيذ عملية الاستعادة ---
    // العملية بسيطة: إعادة حقل `deleted_at` إلى القيمة NULL
    $stmt = $pdo->prepare("UPDATE `{$table_name}` SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$item_id]);

    // التحقق من أن عملية التحديث قد أثرت على صف واحد (للتأكد من أن العنصر كان محذوفاً وتمت استعادته)
    if ($stmt->rowCount() > 0) {
        $_SESSION['admin_message'] = "تمت استعادة '{$item_name_for_message}' بنجاح وعاد إلى القائمة النشطة.";
        $_SESSION['admin_message_type'] = "success";
    } else {
        // هذه الحالة قد تحدث إذا حاول المستخدم استعادة عنصر تم استعادته بالفعل في تبويب آخر
        throw new Exception("لم يتم العثور على العنصر في سلة المحذوفات أو فشلت عملية الاستعادة.");
    }

} catch (Exception $e) {
    $_SESSION['admin_message'] = "فشل الاستعادة: " . $e->getMessage();
    $_SESSION['admin_message_type'] = 'error';
    // تسجيل الخطأ للمراجعة لاحقاً دون إظهاره للمستخدم
    error_log("Item restore error for type `{$item_type}`, ID `{$item_id}`: " . $e->getMessage());
}

// إعادة التوجيه دائماً إلى سلة المحذوفات لعرض النتيجة
header('Location: recycling_bin.php');
exit;
?>