<?php
session_start();

// تدمير كل متغيرات الجلسة
$_SESSION = [];

// تدمير الجلسة نفسها
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header('Location: ../driver_login.php');
exit;
?>