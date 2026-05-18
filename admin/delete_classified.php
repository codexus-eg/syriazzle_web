<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once 'auth_guard.php';

// --- حارس البوابة 1: التحقق من صلاحية "حذف إعلان" ---
if (!hasPermission('delete_classified')) {
    $_SESSION['admin_message'] = "ليس لديك الصلاحية لحذف الإعلانات.";
    $_SESSION['admin_message_type'] = "error";
    header('Location: manage_classifieds.php');
    exit;
}

// --- استقبال وتنقية معرّف الإعلان ---
$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ad_id === 0) {
    $_SESSION['admin_message'] = "خطأ: معرف الإعلان غير صالح.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: manage_classifieds.php');
    exit;
}

try {
    // --- حارس البوابة 2: التحقق من صلاحية المحافظة (لغير السوبر أدمن) ---
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        
        // أولاً، جلب الاسم النصي لمحافظة الأدمن
        $gov_stmt = $pdo->prepare("SELECT name FROM governorates WHERE id = ?");
        $gov_stmt->execute([$admin_governorate_id]);
        $governorate_name = $gov_stmt->fetchColumn();

        if ($governorate_name) {
            // ثانياً، التحقق من محافظة الإعلان المطلوب حذفه
            $ad_check_stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM form_submissions 
                WHERE id = ? AND JSON_UNQUOTE(JSON_EXTRACT(json_data, '$.المحافظة')) = ?
            ");
            $ad_check_stmt->execute([$ad_id, $governorate_name]);

            // إذا كان عدد الصفوف المطابقة صفر، فهذا يعني أن الإعلان لا يتبع لهذه المحافظة
            if ($ad_check_stmt->fetchColumn() == 0) {
                throw new Exception("لا يمكنك حذف هذا الإعلان لأنه لا يتبع لمحافظتك.");
            }
        }
    }

    // --- إذا تم تجاوز كل التحققات، قم بتنفيذ عملية الحذف ---
    $stmt = $pdo->prepare("DELETE FROM form_submissions WHERE id = ?");
    $stmt->execute([$ad_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['admin_message'] = "تم حذف الإعلان بنجاح.";
        $_SESSION['admin_message_type'] = 'success';
    } else {
        throw new Exception("لم يتم العثور على الإعلان المحدد ليتم حذفه.");
    }

} catch (Exception $e) {
    $_SESSION['admin_message'] = "فشل حذف الإعلان: " . $e->getMessage();
    $_SESSION['admin_message_type'] = 'error';
    error_log("Delete Classified Error: " . $e->getMessage());
}

header('Location: manage_classifieds.php');
exit;
?>