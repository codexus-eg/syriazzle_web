<?php
// ========================================================================
// Syriazzle Admin - Save New User (النسخة النهائية)
// هذا الملف مسؤول عن إضافة مستخدم جديد من خلال لوحة التحكم
// ========================================================================

// استدعاء حارس المنطق الموحد (يضمن اتصال قاعدة البيانات والتحقق من جلسة الأدمن)
require_once 'auth_guard.php';

// --- حارس البوابة: التحقق من الصلاحية ---
// نتحقق إذا كان الأدمن يملك صلاحية عرض المستخدمين (أو add_user إذا كانت معرفة)
if (!hasPermission('view_users')) {
    $_SESSION['msg'] = "ليس لديك الصلاحية لإضافة مستخدمين.";
    $_SESSION['msg_type'] = 'error';
    header('Location: manage_users.php');
    exit;
}

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استقبال البيانات وتنظيفها
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // التحقق من الحقول الإلزامية
    if (empty($username) || empty($phone) || empty($password)) {
        $_SESSION['msg'] = "الرجاء تعبئة الحقول المطلوبة (الاسم، الهاتف، كلمة المرور).";
        $_SESSION['msg_type'] = 'error';
        header('Location: manage_users.php');
        exit;
    }

    try {
        // 1. التحقق من عدم وجود الهاتف أو الإيميل مسبقاً
        // نتحقق من الهاتف، وإذا كان الإيميل مدخلاً نتحقق منه أيضاً
        $check_sql = "SELECT id FROM users WHERE phone = ?";
        $params = [$phone];

        if (!empty($email)) {
            $check_sql .= " OR email = ?";
            $params[] = $email;
        }

        $stmt_check = $pdo->prepare($check_sql);
        $stmt_check->execute($params);
        
        if ($stmt_check->rowCount() > 0) {
            $_SESSION['msg'] = "رقم الهاتف أو البريد الإلكتروني مستخدم بالفعل لمستخدم آخر.";
            $_SESSION['msg_type'] = 'error';
            header('Location: manage_users.php');
            exit;
        }

        // 2. تشفير كلمة المرور
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. الإدخال في قاعدة البيانات
        // is_verified = 1 (المستخدم مفعل تلقائياً لأنه أضيف من قبل الأدمن)
        // يتم إرسال NULL للإيميل إذا كان فارغاً لتجنب مشاكل القيود في قاعدة البيانات
        $sql = "INSERT INTO users (username, email, phone, password, is_verified, created_at) 
                VALUES (?, ?, ?, ?, 1, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $username, 
            empty($email) ? null : $email, 
            $phone, 
            $hashed_password
        ]);

        $_SESSION['msg'] = "تم إضافة المستخدم الجديد '{$username}' بنجاح.";
        $_SESSION['msg_type'] = 'success';

    } catch (PDOException $e) {
        // تسجيل الخطأ وعرض رسالة للمستخدم
        error_log("Add User Error: " . $e->getMessage());
        $_SESSION['msg'] = "حدث خطأ في قاعدة البيانات: " . $e->getMessage();
        $_SESSION['msg_type'] = 'error';
    }

} else {
    // إذا حاول أحد الوصول للملف مباشرة دون POST
    $_SESSION['msg'] = "طريقة الوصول غير صحيحة.";
    $_SESSION['msg_type'] = 'error';
}

// إعادة التوجيه إلى لوحة إدارة المستخدمين
header('Location: manage_users.php');
exit;
?>