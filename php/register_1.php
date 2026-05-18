<?php
/**
 * register_1.php
 * 
 * هذا الملف هو المرحلة النهائية في عملية إنشاء الحساب.
 * يستقبل جميع بيانات المستخدم مع رمز التحقق (OTP) عبر طلب AJAX.
 * يقوم بالتحقق من صحة الرمز، ثم يتحقق من عدم تكرار المستخدم،
 * وأخيراً، يقوم بحفظ بيانات المستخدم الجديد في قاعدة البيانات.
 * يجب أن يعيد دائماً استجابة بصيغة JSON.
 */

// 1. الإعدادات الأولية
// =====================
header('Content-Type: application/json'); // تحديد نوع المحتوى كـ JSON
session_start(); // بدء أو استئناف الجلسة للوصول إلى بيانات OTP

// استدعاء ملف الاتصال بقاعدة البيانات
require_once 'db_connect.php'; // تأكد من أن المسار صحيح

// 2. التحقق من نوع الطلب
// ======================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموح بها.']);
    exit;
}

// 3. استقبال وتنظيف البيانات من طلب POST
// ===========================================
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? ''); // سيصل بالصيغة الدولية الكاملة، مثال: +963...
$password  = $_POST['password'] ?? ''; // لا نستخدم trim لكلمات المرور للسماح بالمسافات
$otp_input = trim($_POST['otp_input'] ?? '');

// 4. التحقق من صحة المدخلات الأساسية
// ====================================

// التأكد من أن الحقول الإجبارية ليست فارغة
if (empty($username) || empty($phone) || empty($password) || empty($otp_input)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'بيانات غير مكتملة. يرجى ملء جميع الحقول الإجبارية.']);
    exit;
}

// التحقق من طول كلمة المرور
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.']);
    exit;
}

// التحقق من صحة البريد الإلكتروني (إن وجد)
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'صيغة البريد الإلكتروني غير صحيحة.']);
    exit;
}

// 5. التحقق من رمز التحقق (OTP) من الجلسة
// =========================================

// التحقق من وجود بيانات OTP في الجلسة أصلاً
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_expiry']) || !isset($_SESSION['otp_phone'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'انتهت صلاحية جلسة التحقق. يرجى البدء من جديد من صفحة التسجيل.']);
    exit;
}

// التحقق من تطابق رقم الهاتف بين النموذج والجلسة (إجراء أمني)
if ($_SESSION['otp_phone'] !== $phone) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'حدث عدم تطابق في رقم الهاتف أثناء التحقق. يرجى المحاولة مرة أخرى.']);
    exit;
}

// التحقق من انتهاء صلاحية الرمز
if (time() > $_SESSION['otp_expiry']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'انتهت صلاحية رمز التحقق. يرجى طلب رمز جديد.']);
    exit;
}

// التحقق من صحة الرمز المدخل
if ($otp_input !== $_SESSION['otp_code']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'رمز التحقق غير صحيح.']);
    exit;
}

// ✅ نجح التحقق. نقوم بمسح بيانات OTP من الجلسة فوراً لمنع إعادة استخدامها.
unset($_SESSION['otp_code']);
unset($_SESSION['otp_expiry']);
unset($_SESSION['otp_phone']);

// 6. التفاعل مع قاعدة البيانات
// =============================
try {
    // التحقق من عدم وجود حساب بنفس رقم الهاتف
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'رقم الهاتف هذا مسجّل لدينا بالفعل.']);
        exit;
    }

    // التحقق من عدم وجود حساب بنفس البريد الإلكتروني (إن وجد)
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'هذا البريد الإلكتروني مسجّل لدينا بالفعل.']);
            exit;
        }
    }

    // تشفير كلمة المرور بقوة
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // إدخال بيانات المستخدم الجديد في قاعدة البيانات
    $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)");
    
    // إذا كان الإيميل فارغاً، نرسل NULL إلى قاعدة البيانات
    $emailForDb = empty($email) ? null : $email;
    
    $stmt->execute([$username, $emailForDb, $phone, $hashedPassword]);
    
    // إرسال استجابة نجاح
    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'تم إنشاء الحساب بنجاح!']);

} catch (PDOException $e) {
    // في حالة حدوث أي خطأ في قاعدة البيانات
    error_log("Database Error on registration: " . $e->getMessage()); // تسجيل الخطأ في سجلات الخادم
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'حدث خطأ فني في الخادم. يرجى المحاولة لاحقاً.']);
}

?>