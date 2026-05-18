<?php
session_start();
require_once 'db_connect.php'; // تأكد من المسار الصحيح لملف الاتصال
require_once 'auth_check.php';

header('Content-Type: text/html; charset=UTF-8'); // لضمان عرض الرسائل بشكل صحيح عند إعادة التوجيه

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../settings.php?status=password_error&message=' . urlencode('الطلب غير مسموح.'));
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: ../login.html?message=' . urlencode('يرجى تسجيل الدخول لتغيير كلمة المرور.'));
    exit;
}

// استلام كلمات السر من النموذج
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? '';

// التحقق من صحة المدخلات
if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
    header('Location: ../settings.php?status=password_error&message=' . urlencode('جميع حقول كلمة المرور مطلوبة.'));
    exit;
}

if ($new_password !== $confirm_new_password) {
    header('Location: ../settings.php?status=password_error&message=' . urlencode('كلمة المرور الجديدة وتأكيدها غير متطابقين.'));
    exit;
}

// إضافة تحقق من الحد الأدنى لطول كلمة المرور (مثال: 6 أحرف)
if (strlen($new_password) < 6) { 
    header('Location: ../settings.php?status=password_error&message=' . urlencode('يجب أن تكون كلمة المرور الجديدة 6 أحرف على الأقل.'));
    exit;
}

try {
    // جلب كلمة السر المشفرة الحالية للمستخدم من قاعدة البيانات
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // إذا لم يتم العثور على المستخدم (لا ينبغي أن يحدث إذا كان user_id صحيحًا)
        session_destroy(); // إنهاء الجلسة
        header('Location: ../login.html?message=' . urlencode('جلسة غير صالحة. يرجى تسجيل الدخول مرة أخرى.'));
        exit;
    }

    // التحقق من صحة كلمة السر الحالية
    if (!password_verify($current_password, $user['password'])) {
        header('Location: ../settings.php?status=password_error&message=' . urlencode('كلمة السر الحالية غير صحيحة.'));
        exit;
    }

    // تشفير كلمة السر الجديدة
    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

    // تحديث كلمة السر في قاعدة البيانات
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_new_password, $user_id]);

    // إعادة التوجيه إلى صفحة الإعدادات مع رسالة نجاح
    header('Location: ../settings.php?status=password_success&message=' . urlencode('تم تغيير كلمة المرور بنجاح!'));
    exit;

} catch (PDOException $e) {
    // التعامل مع أخطاء قاعدة البيانات
    error_log("Database error changing password: " . $e->getMessage());
    header('Location: ../settings.php?status=password_error&message=' . urlencode('حدث خطأ في قاعدة البيانات أثناء تغيير كلمة المرور. يرجى المحاولة لاحقاً.'));
    exit;
}