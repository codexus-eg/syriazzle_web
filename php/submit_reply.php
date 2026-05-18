<?php
session_start();
require_once 'db_connect.php';

// 1. التحقق من الصلاحيات والمدخلات
if (!isset($_SESSION['user_id'])) {
    die("Access denied.");
}
$current_user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../manage_reviews_user.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}

$review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
$reply_text = trim($_POST['reply_text'] ?? '');

if ($review_id === 0 || empty($reply_text)) {
    $_SESSION['message'] = "خطأ: البيانات المرسلة غير كاملة.";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_reviews_user.php');
    exit;
}

try {
    // 2. خطوة أمنية هامة: التحقق من أن هذا المستخدم يملك صلاحية الرد على هذه المراجعة
    $sql_check = "
        SELECT b.user_id 
        FROM business_reviews r
        JOIN businesses b ON r.business_id = b.id
        WHERE r.id = ?
    ";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$review_id]);
    $owner_id = $stmt_check->fetchColumn();

    if ($owner_id !== $current_user_id) {
        // إذا كان المستخدم لا يملك النشاط التجاري، لا تسمح له بالرد
        throw new Exception("ليس لديك صلاحية للرد على هذه المراجعة.");
    }
    
    // 3. تحديث قاعدة البيانات بالرد الجديد
    $sql_update = "UPDATE business_reviews SET reply_text = ?, replied_at = NOW() WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$reply_text, $review_id]);

    $_SESSION['message'] = "تم إرسال ردك بنجاح!";
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    $_SESSION['message'] = "فشل إرسال الرد: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header('Location: ../manage_reviews_user.php');
exit;
?>