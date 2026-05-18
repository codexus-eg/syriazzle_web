<?php
// ========================================================================
// Syriazzle - Driver Task Update API (النسخة النهائية والآمنة)
// ========================================================================

require_once 'db_connect.php'; 
header('Content-Type: application/json; charset=UTF-8');

// --- دالة موحدة لإرسال الرد ---
function send_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 1. حارس البوابة: التحقق من جلسة السائق ---
if (!isset($_SESSION['driver_logged_in']) || !isset($_SESSION['driver_id'])) {
    http_response_code(401); // Unauthorized
    send_response(false, 'وصول غير مصرح به. يرجى تسجيل الدخول أولاً.');
}
$driver_id = (int)$_SESSION['driver_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    send_response(false, 'طريقة الطلب غير صحيحة.');
}

// --- 2. استقبال البيانات والتحقق منها ---
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

// قائمة الحالات المسموح للسائق بتغييرها (للأمان)
$allowed_statuses = ['processing', 'delivered']; 

if ($order_id === 0 || !in_array($new_status, $allowed_statuses)) {
    http_response_code(400); // Bad Request
    send_response(false, 'بيانات الطلب غير صالحة أو الحالة غير مسموح بها.');
}

try {
    // --- 3. التحقق من الملكية: هل هذا الطلب مخصص لهذا السائق؟ ---
    $stmt_check = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND driver_id = ?");
    $stmt_check->execute([$order_id, $driver_id]);
    $current_status = $stmt_check->fetchColumn();

    if ($current_status === false) {
        http_response_code(403); // Forbidden
        send_response(false, 'ليس لديك صلاحية لتحديث هذا الطلب.');
    }

    // --- 4. تنفيذ التحديث ---
    $stmt_update = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND driver_id = ?");
    $stmt_update->execute([$new_status, $order_id, $driver_id]);
    
    // (اختياري) يمكنك هنا إضافة إشعار للزبون عند التسليم
    // if ($new_status === 'delivered') { ... }

    send_response(true, 'تم تحديث حالة الطلب بنجاح!');

} catch (Exception $e) {
    http_response_code(500);
    error_log("Driver Task Update Error: " . $e->getMessage());
    send_response(false, 'حدث خطأ فني في الخادم.');
}
?>