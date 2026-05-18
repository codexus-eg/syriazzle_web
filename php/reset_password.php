<?php
// php/reset_password.php
header('Content-Type: application/json');
session_start();

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطلب غير مسموح.']);
    exit;
}

$otp_input = trim($_POST['otp_input'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 1. التحقق من المدخلات
if (empty($otp_input) || empty($password) || empty($confirm_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'يرجى ملء جميع الحقول.']);
    exit;
}
if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'كلمتا المرور غير متطابقتين.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.']);
    exit;
}

// 2. التحقق من جلسة الـ OTP
if (!isset($_SESSION['reset_otp_code'], $_SESSION['reset_otp_phone'], $_SESSION['reset_otp_expiry'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'انتهت صلاحية الجلسة، يرجى البدء من جديد.']);
    exit;
}

// 3. التحقق من صحة الرمز
if ($_SESSION['reset_otp_expiry'] < time() || $_SESSION['reset_otp_code'] !== $otp_input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'رمز التحقق غير صحيح أو انتهت صلاحيته.']);
    exit;
}

// 4. كل شيء صحيح، قم بتحديث كلمة المرور
try {
    $phone_to_update = $_SESSION['reset_otp_phone'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE phone = ?");
    $stmt->execute([$hashedPassword, $phone_to_update]);

    // 5. امسح بيانات الجلسة وأرسل رد نجاح
    unset($_SESSION['reset_otp_code'], $_SESSION['reset_otp_phone'], $_SESSION['reset_otp_expiry']);
    
    echo json_encode(['success' => true, 'message' => 'تم تحديث كلمة المرور بنجاح.']);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
}
?>