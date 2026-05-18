<?php
// استدعاء حارس المنطق الموحد
require_once 'auth_guard.php';

// --- حارس البوابة 1: التحقق من صلاحية "حذف مستخدم" ---
if (!hasPermission('delete_user')) {
    $_SESSION['msg'] = "ليس لديك الصلاحية اللازمة لحذف المستخدمين.";
    $_SESSION['msg_type'] = 'error';
    header('Location: manage_users.php');
    exit;
}

// --- التحقق من المدخلات ---
// ** التصحيح هنا: استخدام $_POST بدلاً من $_GET لأن النموذج يرسل عبر POST **
$user_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($user_id === 0) {
    $_SESSION['msg'] = "خطأ: لم يتم تحديد معرّف المستخدم.";
    $_SESSION['msg_type'] = 'error';
    header('Location: manage_users.php');
    exit;
}

try {
    // --- حارس البوابة 2: التأكد من أن المستخدم موجود وليس محذوفًا ---
    $stmt_check = $pdo->prepare("SELECT username FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$user_id]);
    $user = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("المستخدم غير موجود أو تم حذفه بالفعل.");
    }
    
    // --- تطبيق الحذف الناعم (Soft Delete) ---
    //$stmt_soft_delete = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
    //$stmt_soft_delete->execute([$user_id]);
    
    $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt_delete->execute([$user_id]);

    // ملاحظة: لا نحتاج لإلغاء طلبات المستخدم النشطة هنا.
    
    // استخدام msg بدلاً من admin_message ليتوافق مع manage_users.php
    $_SESSION['msg'] = "تم حذف المستخدم '" . htmlspecialchars($user['username']) . "' بنجاح.";
    $_SESSION['msg_type'] = "success";

} catch (Exception $e) {
    $_SESSION['msg'] = "فشل حذف المستخدم: " . $e->getMessage();
    $_SESSION['msg_type'] = 'error';
    error_log("User deletion error for ID $user_id: " . $e->getMessage());
}

header('Location: manage_users.php');
exit;
?>