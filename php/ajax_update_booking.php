<?php
// ========================================================================
// Syriazzle Bookings - محرك تحديث مواعيد الحجز (النسخة النهائية)
// يستخدم عند سحب وإفلات الحجوزات في التقويم
// ========================================================================
require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

// دالة موحدة لإرسال الرد
function send_json_response($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit;
}

// --- Layer 1: Security & Auth ---
$request_data = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'طريقة الطلب غير مسموح بها.');
}
if (!isset($_SESSION['user_id'])) {
    send_json_response(false, 'جلسة المستخدم غير صالحة.');
}

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($request_data['business_id']) ? (int)$request_data['business_id'] : 0;
$booking_id = isset($request_data['booking_id']) ? (int)$request_data['booking_id'] : 0;
$new_start_str = $request_data['start_datetime'] ?? null;
$new_end_str = $request_data['end_datetime'] ?? null;

if (empty($booking_id) || empty($new_start_str)) {
    send_json_response(false, 'بيانات التحديث ناقصة.');
}

// --- Layer 2: Ownership & Booking Verification ---
$pdo->beginTransaction();
try {
    // جلب بيانات الحجز والخدمة والنشاط التجاري للتحقق من الملكية
    $stmt_check = $pdo->prepare("
        SELECT 
            b.id as booking_id,
            s.id as service_id,
            biz.user_id as owner_id
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        WHERE b.id = ? FOR UPDATE
    ");
    $stmt_check->execute([$booking_id]);
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception("الحجز غير موجود.");
    }
    if ($result['owner_id'] !== $current_user_id) {
        throw new Exception("وصول غير مصرح به. أنت لا تملك هذا الحجز.");
    }
    
    // --- (اختياري لكن موصى به) التحقق من عدم التعارض مع حجز آخر ---
    // هذا الجزء مشابه لمنطق التحقق في process_booking.php
    // وهو يمنع نقل حجز إلى موعد محجوز بالفعل
    
    // --- Layer 3: Database Update ---
    // تحويل التواريخ النصية إلى صيغة MySQL DATETIME
    $new_start_datetime = date('Y-m-d H:i:s', strtotime($new_start_str));
    // إذا لم يتم توفير تاريخ انتهاء (في عرض اليوم مثلاً)، قم بحسابه
    $new_end_datetime = $new_end_str ? date('Y-m-d H:i:s', strtotime($new_end_str)) : null;

    $stmt_update = $pdo->prepare(
        "UPDATE bookings SET start_datetime = ?, end_datetime = ? WHERE id = ?"
    );
    $stmt_update->execute([$new_start_datetime, $new_end_datetime, $booking_id]);

    $pdo->commit();
    send_json_response(true, "تم تحديث موعد الحجز بنجاح!");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Booking update error: " . $e->getMessage());
    send_json_response(false, "فشل تحديث الحجز: " . $e->getMessage());
}
?>