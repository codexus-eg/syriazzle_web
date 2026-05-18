<?php
// ========================================================================
// Syriazzle Bookings - Resource Management API (النسخة النهائية 1.0)
// يعالج إنشاء وتعديل الأصول (غرف، طاولات، الخ)
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

function send_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// --- Layer 1: Security & Auth ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_response(false, 'طريقة الطلب غير مسموح بها.'); }
$request_data = json_decode(file_get_contents('php://input'), true);
if (!isset($_SESSION['user_id'])) { send_response(false, 'جلسة المستخدم غير صالحة.'); }

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($request_data['business_id']) ? (int)$request_data['business_id'] : 0;
$resource_id = isset($request_data['resource_id']) && !empty($request_data['resource_id']) ? (int)$request_data['resource_id'] : 0;

// --- Layer 2: Ownership Verification ---
try {
    $stmt_check = $pdo->prepare("SELECT user_id FROM businesses WHERE id = ?");
    $stmt_check->execute([$business_id]);
    if ($stmt_check->fetchColumn() !== $current_user_id) {
        send_response(false, 'وصول غير مصرح به.');
    }
} catch (PDOException $e) { send_response(false, 'خطأ في التحقق من الصلاحيات.'); }

// --- Layer 3: Data Preparation ---
$name = trim($request_data['name'] ?? '');
$resource_type = $request_data['resource_type'] ?? 'room'; // القيمة الافتراضية
$status = $request_data['status'] ?? 'available';

// تجميع البيانات الوصفية في كائن meta_data
    // --- Layer 3: Data Preparation ---
    $name = trim($request_data['name'] ?? '');
    $resource_type = $request_data['resource_type'] ?? 'default';
    $status = $request_data['status'] ?? 'available';

    // **الإصلاح الحاسم هنا: تجميع البيانات الوصفية بشكل ديناميكي**
    $meta_data = [];
    $allowed_meta_keys = ['capacity_adults', 'capacity_children', 'floor', 'view', 'location', 'capacity']; // قائمة بكل المفاتيح الممكنة
    foreach($allowed_meta_keys as $key) {
        if (isset($request_data[$key])) {
            $meta_data[$key] = trim($request_data[$key]);
        }
    }
    $meta_data_json = json_encode($meta_data);

    if (empty($name)) { send_response(false, 'اسم الأصل (الغرفة/الطاولة) مطلوب.'); }

// --- Layer 4: Database Execution ---
try {
    if ($resource_id > 0) { // تحديث
        $sql = "UPDATE business_resources SET name = ?, resource_type = ?, status = ?, meta_data = ? WHERE id = ? AND business_id = ?";
        $params = [$name, $resource_type, $status, $meta_data_json, $resource_id, $business_id];
    } else { // إنشاء
        $sql = "INSERT INTO business_resources (business_id, name, resource_type, status, meta_data) VALUES (?, ?, ?, ?, ?)";
        $params = [$business_id, $name, $resource_type, $status, $meta_data_json];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    send_response(true, 'تم حفظ بيانات الأصل بنجاح!');
} catch (PDOException $e) {
    error_log("Resource Management Error: " . $e->getMessage());
    send_response(false, 'حدث خطأ فني أثناء حفظ البيانات.');
}
?>