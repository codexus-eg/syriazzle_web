<?php
// ========================================================================
// Syriazzle Bookings - Customer Note Management API (النسخة النهائية 1.0)
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

function send_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// --- Security & Auth ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_response(false, 'طريقة الطلب غير مسموح بها.'); }
$request_data = json_decode(file_get_contents('php://input'), true);
if (!isset($_SESSION['user_id'])) { send_response(false, 'جلسة المستخدم غير صالحة.'); }

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($request_data['business_id']) ? (int)$request_data['business_id'] : 0;
$customer_id = isset($request_data['customer_id']) ? (int)$request_data['customer_id'] : 0;
$notes = trim($request_data['notes'] ?? '');

// --- Ownership Verification ---
try {
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM business_customers WHERE id = ? AND business_id IN (SELECT id FROM businesses WHERE user_id = ?)");
    $stmt_check->execute([$customer_id, $current_user_id]);
    if ($stmt_check->fetchColumn() == 0) {
        send_response(false, 'وصول غير مصرح به.');
    }
} catch (PDOException $e) { send_response(false, 'خطأ في التحقق من الصلاحيات.'); }

// --- Database Execution ---
try {
    $sql = "UPDATE business_customers SET notes = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$notes, $customer_id]);
    send_response(true, 'تم حفظ الملاحظة بنجاح.');
} catch (PDOException $e) {
    error_log("Customer Note Error: " . $e->getMessage());
    send_response(false, 'حدث خطأ فني أثناء حفظ الملاحظة.');
}
?>