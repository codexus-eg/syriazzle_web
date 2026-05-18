<?php
// php/send_reset_otp.php
header('Content-Type: application/json');
session_start();

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطلب غير مسموح.']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'رقم الهاتف مطلوب.']);
    exit;
}

try {
    // 1. التحقق إذا كان الرقم مسجلاً لدينا
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch() === false) {
        // الرقم غير موجود، لا تخبر المهاجم بذلك صراحة
        // أرسل رسالة عامة لزيادة الأمان
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'إذا كان هذا الرقم مسجلاً، فسيصلك رمز تحقق.']);
        exit;
    }

    // 2. الرقم موجود، قم بتوليد وإرسال الرمز
    $otp = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    
    // استخدم مفاتيح جلسة مختلفة عن التسجيل لتجنب التضارب
    $_SESSION['reset_otp_phone'] = $phone;
    $_SESSION['reset_otp_code'] = $otp;
    $_SESSION['reset_otp_expiry'] = time() + (5 * 60); // صالح لـ 5 دقائق

    // 3. أرسل الرمز عبر Node.js (نفس الكود السابق)
    $nodeJsServerUrl = 'https://f126-129-224-206-151.ngrok-free.app/send-otp'; // استبدل بالرابط الصحيح
    $messageContent = "رمز التحقق لإعادة تعيين كلمة المرور هو: {$otp}.";
    $postData = json_encode(['phone' => $phone, 'otp' => $otp, 'message' => $messageContent]);

    // ... كود cURL الخاص بك هنا ...
    // للتجربة، سنفترض أنه نجح
    $ch = curl_init($nodeJsServerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // مهلة للطلب

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    $errorMessage = 'فشل الاتصال بخادم واتساب. (خطأ cURL: ' . $error . ')';
    error_log("فشل الاتصال بـ Node.js: " . $error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

if ($httpCode !== 200) {
    $errorMessage = 'خادم واتساب استجاب بخطأ.';
    $nodeResponse = json_decode($response, true);
    if ($nodeResponse && isset($nodeResponse['message'])) {
        $errorMessage = $nodeResponse['message'];
    }
    error_log("خادم Node.js استجاب بخطأ HTTP " . $httpCode . " مع الرسالة: " . $response);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

$nodeResponse = json_decode($response, true);

if (!isset($nodeResponse['status']) || $nodeResponse['status'] !== 'success') {
    $errorMessage = $nodeResponse['message'] ?? 'فشل إرسال رمز التحقق. خطأ غير معروف.';
    error_log("خادم Node.js أبلغ عن خطأ: " . ($nodeResponse['message'] ?? 'خطأ غير معروف'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

    echo json_encode(['success' => true, 'message' => 'تم إرسال الرمز.']);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم.']);
}
?>