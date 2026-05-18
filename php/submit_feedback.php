<?php
// submit_notes.php
require_once 'auth_check.php';
// تعيين رأس (Header) الاستجابة كـ JSON
header('Content-Type: application/json');

// السماح بالوصول من أي نطاق (للتطوير). في بيئة الإنتاج، يجب تقييد هذا.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// معالجة طلبات OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // قراءة محتوى الطلب (البيانات المرسلة من JavaScript)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true); // فك تشفير JSON

    // استخراج الملاحظات
    $noteContent = $data['note_content'] ?? null;

    // تحقق أساسي من البيانات
    if (empty($noteContent)) {
        echo json_encode(['success' => false, 'message' => 'محتوى الملاحظة فارغ.']);
        exit();
    }

    // تفاصيل اتصال قاعدة البيانات
    $servername = "localhost"; // عادةً "localhost" إذا كانت قاعدة البيانات على نفس الخادم
    $username = "syriazzle"; // *** استبدل بهذا باسم مستخدم قاعدة البيانات الخاص بك ***
    $password = "Drj$,iEVQ_Bg"; // *** استبدل بهذا بكلمة مرور قاعدة البيانات الخاصة بك ***
    $dbname = "syriazzle_online"; // *** استبدل بهذا باسم قاعدة البيانات الخاصة بك ***

     // إنشاء اتصال بقاعدة البيانات
    $conn = new mysqli($servername, $username, $password, $dbname);

    // التحقق من الاتصال
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات: ' . $conn->connect_error]);
        exit();
    }
$stmt = $conn->prepare("INSERT INTO important_notes (note_content, created_at) VALUES (?, NOW())");

    // ربط المعاملات
    $stmt->bind_param("s", $noteContent); // "s" تعني string

    // تنفيذ البيان
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'تم حفظ الملاحظة بنجاح.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في حفظ الملاحظة: ' . $stmt->error]);
    }
$stmt->close();
    $conn->close();

} else {
 
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صالحة.']);
}
?>