<?php
session_start();

// 1. الاتصال بقاعدة البيانات (نخرج خطوة للوراء من admin وندخل php)
require_once '../php/db_connect.php';

// ضبط الترويسة لإرجاع JSON دائماً
header('Content-Type: application/json');

// 2. التحقق من الصلاحيات (حماية الملف من الوصول المباشر)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'جلسة العمل منتهية، يرجى تسجيل الدخول مرة أخرى.']);
    exit;
}

// 3. التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموح بها.']);
    exit;
}

// 4. استلام البيانات وتنظيفها
$username = trim($_POST['username'] ?? '');
$phone    = trim($_POST['phone'] ?? ''); // هذا الرقم يجب أن يأتي كاملاً مع الكود الدولي
$password = $_POST['password'] ?? '';
$email    = trim($_POST['email'] ?? ''); // اختياري

// 5. التحقق من صحة المدخلات
if (empty($username) || empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'البيانات ناقصة. يرجى ملء الاسم، الهاتف، وكلمة المرور.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.']);
    exit;
}

try {
    // 6. التحقق من عدم تكرار رقم الهاتف (إجباري)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'رقم الهاتف هذا مسجل مسبقاً لمستخدم آخر.']);
        exit;
    }

    // التحقق من الإيميل (إذا وجد)
    $emailValue = null;
    if (!empty($email)) {
        $stmt_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_email->execute([$email]);
        if ($stmt_email->fetch()) {
            echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مسجل مسبقاً.']);
            exit;
        }
        $emailValue = $email;
    }

    // 7. تشفير كلمة المرور
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 8. إدخال المستخدم الجديد
    // نضع is_verified = 1 لأن الحساب مُنشأ بواسطة الأدمن
    $sql = "INSERT INTO users (username, email, phone, password, is_verified, created_at) 
            VALUES (?, ?, ?, ?, 1, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $emailValue, $phone, $hashed_password]);
    
    $new_user_id = $pdo->lastInsertId();

    // 9. إرجاع استجابة النجاح
    echo json_encode([
        'success'  => true,
        'id'       => $new_user_id,
        'username' => $username,
        'phone'    => $phone,
        'message'  => 'تم إنشاء المستخدم بنجاح'
    ]);

} catch (PDOException $e) {
    // تسجيل الخطأ وإرجاع رسالة فشل
    error_log("Ajax User Create Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات أثناء الحفظ.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ غير متوقع: ' . $e->getMessage()]);
}
?>