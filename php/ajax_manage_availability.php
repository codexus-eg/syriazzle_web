<?php
// ========================================================================
// Syriazzle Bookings - Availability Management API (النسخة النهائية 1.0)
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

function send_response($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// --- Layer 1: Security & Auth ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, 'طريقة الطلب غير مسموح بها.');
}
$request_data = json_decode(file_get_contents('php://input'), true);
if (!isset($_SESSION['user_id'])) {
    send_response(false, 'جلسة المستخدم غير صالحة.');
}

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($request_data['business_id']) ? (int)$request_data['business_id'] : 0;
$availability_data = $request_data['availability'] ?? [];

// --- Layer 2: Ownership Verification ---
try {
    $stmt_check = $pdo->prepare("SELECT user_id FROM businesses WHERE id = ?");
    $stmt_check->execute([$business_id]);
    if ($stmt_check->fetchColumn() !== $current_user_id) {
        send_response(false, 'وصول غير مصرح به.');
    }
} catch (PDOException $e) {
    send_response(false, 'خطأ في التحقق من الصلاحيات.');
}

if (empty($availability_data)) {
    send_response(false, 'لم يتم إرسال أي بيانات للتوافر.');
}

// --- Layer 3: Database Execution ---
$pdo->beginTransaction();
try {
    // جلب كل IDs الخدمات التي يملكها المستخدم للتحقق منها
    $stmt_services = $pdo->prepare("SELECT id FROM business_services WHERE business_id = ?");
    $stmt_services->execute([$business_id]);
    $owned_service_ids = $stmt_services->fetchAll(PDO::FETCH_COLUMN);

    foreach ($availability_data as $service_id => $days) {
        $service_id = (int)$service_id;
        // التأكد من أن المستخدم يملك هذه الخدمة قبل تعديلها
        if (!in_array($service_id, $owned_service_ids)) {
            throw new Exception("محاولة تعديل خدمة لا تملكها (ID: {$service_id}).");
        }

        // 1. حذف كل سجلات التوافر القديمة لهذه الخدمة
        $delete_stmt = $pdo->prepare("DELETE FROM service_availability WHERE service_id = ?");
        $delete_stmt->execute([$service_id]);

        // 2. إدراج السجلات الجديدة
        $insert_stmt = $pdo->prepare("INSERT INTO service_availability (service_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
        
        foreach ($days as $day_data) {
            $day_of_week = (int)$day_data['day_of_week'];
            $start_time = $day_data['start_time'] ?? null;
            $end_time = $day_data['end_time'] ?? null;
            
            // فقط قم بالإدراج إذا كان وقت البداية والنهاية موجودين وصحيحين
            if ($start_time && $end_time && $start_time < $end_time) {
                $insert_stmt->execute([$service_id, $day_of_week, $start_time, $end_time]);
            }
        }
    }

    $pdo->commit();
    send_response(true, 'تم تحديث جداول التوافر بنجاح!');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Availability Save Error: " . $e->getMessage());
    send_response(false, 'حدث خطأ فني أثناء حفظ البيانات.');
}
?>