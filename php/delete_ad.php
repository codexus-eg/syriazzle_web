<?php
session_start();
header("Content-Type: application/json");
require_once 'auth_check.php';

$response = ['success' => false, 'error' => ''];

// 1. التحقق من صلاحية المستخدم
if (!isset($_SESSION['user_id'])) {
    $response['error'] = "غير مصرح لك بالوصول.";
    echo json_encode($response);
    exit;
}

// 2. التحقق من استقبال معرف الإعلان للحذف
if (!isset($_POST['ad_id']) || empty($_POST['ad_id'])) {
    $response['error'] = "معرف الإعلان المطلوب حذفه غير موجود.";
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$ad_id_to_delete = (int)$_POST['ad_id']; // تأكد من تحويله إلى عدد صحيح

// 3. الاتصال بقاعدة البيانات
$conn = new mysqli("localhost", "syriazzle", "Drj$,iEVQ_Bg", "syriazzle_online");
if ($conn->connect_error) {
    $response['error'] = "خطأ في الاتصال بقاعدة البيانات: " . $conn->connect_error;
    error_log("DB Connection Error (delete_ad.php): " . $conn->connect_error);
    echo json_encode($response);
    exit;
}

// 4. تحضير واستعلام الحذف
// هام: يجب التأكد أن المستخدم يحاول حذف إعلانه الخاص فقط!
$stmt = $conn->prepare("DELETE FROM form_submissions WHERE id = ? AND user_id = ?");

if ($stmt === false) {
    $response['error'] = "خطأ في تحضير الاستعلام للحذف: " . $conn->error;
    error_log("Prepare Statement Error (delete_ad.php): " . $conn->error);
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ii", $ad_id_to_delete, $user_id); // 'i' لـ id و 'i' لـ user_id

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = "تم حذف الإعلان بنجاح.";
        // يمكنك هنا إضافة منطق لحذف الصور المرتبطة بالإعلان من مجلد الـ uploads
        // سيتطلب ذلك جلب مسارات الصور من json_data قبل الحذف
    } else {
        $response['error'] = "فشل في حذف الإعلان. قد يكون الإعلان غير موجود أو لا تملك صلاحية حذفه.";
    }
} else {
    $response['error'] = "خطأ في تنفيذ استعلام الحذف: " . $stmt->error;
    error_log("Execute Statement Error (delete_ad.php): " . $stmt->error);
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>