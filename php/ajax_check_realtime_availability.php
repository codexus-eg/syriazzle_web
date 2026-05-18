<?php
// ========================================================================
// Syriazzle - API: التحقق الفوري من التوافر (النسخة النهائية 1.0)
// هذه الواجهة هي حارس البوابة لمنع الحجوزات المزدوجة.
// ========================================================================
require_once 'db_connect.php';

// --- إعدادات الأمان الأساسية ---
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['available' => false, 'message' => 'طريقة الطلب غير صحيحة.']);
    exit;
}

// --- استقبال البيانات وفك تشفيرها ---
$request_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['available' => false, 'message' => 'بيانات الطلب غير صالحة.']);
    exit;
}

// --- تنقية البيانات والتحقق من وجودها ---
$service_id = isset($request_data['service_id']) ? (int)$request_data['service_id'] : 0;
$resource_id = isset($request_data['resource_id']) ? (int)$request_data['resource_id'] : null; // قد يكون null
$start_datetime = $request_data['start_datetime'] ?? null;
$end_datetime = $request_data['end_datetime'] ?? null;
$booking_to_exclude = isset($request_data['exclude_booking_id']) ? (int)$request_data['exclude_booking_id'] : 0; // لاستخدامه عند تعديل حجز

if (empty($service_id) || empty($start_datetime) || empty($end_datetime)) {
    http_response_code(400);
    echo json_encode(['available' => false, 'message' => 'معلومات غير مكتملة للتحقق من التوافر.']);
    exit;
}

try {
    // --- بناء الاستعلام الديناميكي بناءً على البيانات المستقبلة ---
    $sql_conditions = [];
    $params = [];

    // الشرط الأساسي: التحقق من تداخل الفترات الزمنية
    $sql_conditions[] = "(start_datetime < ? AND end_datetime > ?)";
    $params[] = $end_datetime;
    $params[] = $start_datetime;

    // الشرط الثاني: البحث في الحجوزات المؤكدة أو التي قيد المراجعة/الدفع فقط
    $sql_conditions[] = "status IN ('confirmed', 'pending_confirmation', 'pending_payment')";

    // الشرط الثالث: تحديد الأصل أو الخدمة
    if ($resource_id !== null) {
        // إذا كان الحجز مرتبطًا بأصل محدد (غرفة 101)، فهذا هو الشرط الأدق
        $sql_conditions[] = "resource_id = ?";
        $params[] = $resource_id;
    } else {
        // إذا كان الحجز لخدمة عامة (موعد عيادة)، نتحقق من الخدمة نفسها
        $sql_conditions[] = "service_id = ?";
        $params[] = $service_id;
    }
    
    // الشرط الرابع: استثناء الحجز الحالي (مفيد عند تعديل حجز لاحقاً)
    if ($booking_to_exclude > 0) {
        $sql_conditions[] = "id != ?";
        $params[] = $booking_to_exclude;
    }

    $sql = "SELECT COUNT(id) FROM bookings WHERE " . implode(' AND ', $sql_conditions);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflicting_bookings_count = $stmt->fetchColumn();

    // --- جلب الكمية المتاحة من الخدمة نفسها للمقارنة ---
    $service_stmt = $pdo->prepare("SELECT quantity_available FROM business_services WHERE id = ?");
    $service_stmt->execute([$service_id]);
    $quantity_available = $service_stmt->fetchColumn();
    
    // إذا لم يتم العثور على الخدمة، اعتبرها غير متاحة
    if ($quantity_available === false) {
        $quantity_available = 0; 
    }

    // --- القرار النهائي ---
    $is_available = ($conflicting_bookings_count < (int)$quantity_available);

    echo json_encode(['available' => $is_available]);

} catch (PDOException $e) {
    error_log("Realtime Availability Check Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['available' => false, 'message' => 'حدث خطأ فني في الخادم.']);
}
?>