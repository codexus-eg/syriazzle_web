<?php
// --- الخطوة 1: بدء الجلسة للوصول إليها ---
// يجب أن تكون هذه هي أول عبارة في الملف
session_start();

// --- الخطوة 2: إلغاء تعيين كل متغيرات الجلسة ---
// هذا يضمن أن كل البيانات (admin_id, role, etc.) قد تم مسحها
$_SESSION = array();

// --- الخطوة 3: حذف "كعكة" الجلسة من المتصفح ---
// هذا يضمن أن المتصفح لن يحاول استخدام معرف الجلسة القديم مرة أخرى
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// --- الخطوة 4: تدمير الجلسة بالكامل من جهة الخادم ---
// هذه هي الخطوة النهائية التي تمسح كل شيء
session_destroy();

// --- الخطوة 5: إعادة التوجيه إلى صفحة تسجيل الدخول ---
header('Location: index.php?status=loggedout');
exit;
?>