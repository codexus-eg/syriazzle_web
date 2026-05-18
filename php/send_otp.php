<?php
// php/send_otp.php
header('Content-Type: application/json');
session_start();

// لا حاجة لملف الاتصال بقاعدة البيانات في هذه الخطوة

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطلب غير مسموح.']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');

// التحقق من أن الرقم بالصيغة الدولية الصحيحة (يبدأ بـ +)
if (empty($phone) || $phone[0] !== '+') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'صيغة رقم الهاتف غير صالحة.']);
    exit;
}

// لا حاجة بعد الآن لتنسيق الرقم، فهو يأتي جاهزًا من جهة العميل
// $phone هو الآن بالشكل: +963...

// توليد رمز التحقق
$otp = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

// تخزين رمز التحقق في الجلسة بالصيغة الدولية الموحدة
$_SESSION['otp_phone'] = $phone;
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_expiry'] = time() + (5 * 60); // صالح لمدة 5 دقائق

// بناء نص الرسالة
$messageContent = "رمز التحقق الخاص بك هو: {$otp}. صالح لمدة 5 دقائق.";

// إرسال رمز التحقق عبر واتساب (الكود الخاص بك)
$nodeJsServerUrl = 'https://f126-129-224-206-151.ngrok-free.app/send-otp'; // استبدل بالرابط الصحيح

$postData = json_encode([
    'phone' => $phone, // نستخدم $phone مباشرة لأنه يحتوي على الصيغة الصحيحة
    'otp' => $otp,
    'message' => $messageContent
]);

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
// كمثال، سنفترض أنه نجح دائمًا لأغراض الاختبار
echo json_encode(['success' => true, 'message' => 'تم إرسال رمز التحقق بنجاح!']);
// في الكود الفعلي، ستضع منطق cURL هنا وتتحقق من الاستجابة

?>

