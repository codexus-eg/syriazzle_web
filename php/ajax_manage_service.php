<?php
// ========================================================================
// Syriazzle Bookings - Service Management API (النسخة النهائية 3.0 - متوافقة مع الأصول)
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

// دالة موحدة لإرسال الرد
function send_response($success, $message, $data = null) {
    if (ob_get_level()) ob_end_clean();
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response);
    exit;
}

// --- Layer 1: Security & Request Setup ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, 'طريقة الطلب غير مسموح بها.');
}

$request_data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    send_response(false, 'جلسة المستخدم غير صالحة.');
}

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($request_data['business_id']) ? (int)$request_data['business_id'] : 0;
$service_id = isset($request_data['service_id']) && !empty($request_data['service_id']) ? (int)$request_data['service_id'] : 0; 

// --- Layer 2: Ownership & Authorization Verification ---
try {
    $stmt_check = $pdo->prepare("SELECT user_id FROM businesses WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$business_id]);
    $owner_id = $stmt_check->fetchColumn();

    if (!$owner_id || $owner_id !== $current_user_id) {
        send_response(false, 'وصول غير مصرح به. هذا النشاط لا يخصك.');
    }
    
    if ($service_id > 0) {
        $stmt_service_check = $pdo->prepare("SELECT COUNT(*) FROM business_services WHERE id = ? AND business_id = ?");
        $stmt_service_check->execute([$service_id, $business_id]);
        if ($stmt_service_check->fetchColumn() == 0) {
             send_response(false, 'وصول غير مصرح به. هذه الخدمة لا تتبع لنشاطك التجاري.');
        }
    }
} catch (PDOException $e) {
    error_log("Service Auth Error: " . $e->getMessage());
    send_response(false, 'خطأ في التحقق من الصلاحيات.');
}

// --- Layer 3: Data Sanitization & Preparation ---
$name = trim($request_data['name'] ?? '');
$description = trim($request_data['description'] ?? '');
$booking_model = $request_data['booking_model'] ?? '';
$price_type = $request_data['price_type'] ?? 'fixed';
$price = isset($request_data['price']) ? (float)$request_data['price'] : 0;
$deposit_percentage = isset($request_data['deposit_required_percentage']) ? (int)$request_data['deposit_required_percentage'] : 0;
$is_active = isset($request_data['is_active']) ? 1 : 0;

// الحقول الخاصة بالأنواع المختلفة
$duration_minutes = isset($request_data['duration_minutes']) && !empty($request_data['duration_minutes']) ? (int)$request_data['duration_minutes'] : null;
$resource_id = isset($request_data['resource_id']) && !empty($request_data['resource_id']) ? (int)$request_data['resource_id'] : null;


// التحقق من صحة البيانات (Server-side Validation)
if (empty($name) || empty($booking_model) || $price <= 0) {
    send_response(false, 'الرجاء ملء الحقول الإلزامية (الاسم، نظام الحجز، السعر).');
}

// إذا كان نظام أصول، تأكد من وجود resource_id
if ($booking_model === 'asset' && empty($resource_id)) {
    send_response(false, 'الرجاء اختيار الأصل (الغرفة/الطاولة) الذي ينطبق عليه هذا السعر.');
}

// --- Layer 4: Database Execution Logic ---
try {
    if ($service_id > 0) { // تحديث خدمة موجودة
        $sql = "UPDATE business_services SET 
                    name=?, description=?, booking_model=?, price_type=?, price=?, 
                    deposit_required_percentage=?, duration_minutes=?, is_active=?, resource_id=?
                WHERE id = ? AND business_id = ?";
        $params = [
            $name, $description, $booking_model, $price_type, $price, 
            $deposit_percentage, $duration_minutes, $is_active, $resource_id,
            $service_id, $business_id
        ];
    } else { // إنشاء خدمة جديدة
        $sql = "INSERT INTO business_services 
                    (business_id, name, description, booking_model, price_type, price, 
                    deposit_required_percentage, duration_minutes, is_active, resource_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $params = [
            $business_id, $name, $description, $booking_model, $price_type, $price, 
            $deposit_percentage, $duration_minutes, $is_active, $resource_id
        ];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    send_response(true, 'تم حفظ بيانات الخدمة بنجاح!');

} catch (PDOException $e) {
    error_log("Service Save/Update Error: " . $e->getMessage());
    // التحقق من خطأ تكرار القيمة (لضمان عدم ربط سعرين بنفس الأصل)
    if ($e->errorInfo[1] == 1062) {
        send_response(false, 'خطأ: هذا الأصل (الغرفة/الطاولة) مرتبط بالفعل بسعر آخر. لا يمكن ربط سعرين بنفس الأصل.');
    }
    send_response(false, 'حدث خطأ فني أثناء حفظ البيانات.');
}
?>