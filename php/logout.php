<?php
// logout.php
session_start(); // ✅ ضروري لبدء الجلسة والوصول إلى $_SESSION

require_once 'db_connect.php'; // لربط قاعدة البيانات وحذف رموز "تذكرني"

// 1. منطق إلغاء خاصية "تذكرني" (حذف الكوكيز ورموز قاعدة البيانات)

// التحقق مما إذا كانت رموز "تذكرني" موجودة
if (isset($_COOKIE['remember_me_selector']) && isset($_COOKIE['remember_me_authenticator'])) {
    
    $selector = $_COOKIE['remember_me_selector'];

    // 1.1. حذف الرمز من قاعدة البيانات
    try {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->execute([$selector]);
    } catch (PDOException $e) {
        // يمكنك تسجيل هذا الخطأ، لكن لا يجب أن يمنع تسجيل الخروج
        error_log("Error deleting remember token: " . $e->getMessage());
    }

    // 1.2. إبطال صلاحية الكوكيز في متصفح المستخدم (عن طريق تعيين وقت انتهاء في الماضي)
    $cookie_options = [
        'expires' => time() - 3600, // تعيين انتهاء الصلاحية إلى ساعة ماضية
        'path' => '/',
        // 'domain' => '.syriazzle.sy', // قم بإلغاء التعليق إذا كنت تستخدم نطاقًا محددًا
        'secure' => false, // ⚠️ قم بتعيينها إلى true إذا كنت تستخدم HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    setcookie('remember_me_selector', '', $cookie_options);
    setcookie('remember_me_authenticator', '', $cookie_options);
}


// 2. تدمير الجلسة (تسجيل الخروج الفعلي)
$_SESSION = array(); // مسح جميع بيانات الجلسة

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // إبطال صلاحية كوكي الجلسة
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy(); // تدمير ملف الجلسة على الخادم

// 3. إعادة توجيه المستخدم إلى صفحة تسجيل الدخول أو الرئيسية
header('Location: ../index.php?logged_out=true');
exit;
?>
