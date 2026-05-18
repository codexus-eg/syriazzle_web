<?php
require_once '../auth_guard.php';
header('Content-Type: application/json');

if (!hasPermission('edit_driver_info')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل معلومات السائقين.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموح بها.']);
    exit;
}

$driver_id = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$vehicle_type = trim($_POST['vehicle_type'] ?? 'Motorcycle');
$password = $_POST['password'] ?? '';
$governorate_id = $is_super_admin ? (isset($_POST['governorate_id']) ? (int)$_POST['governorate_id'] : null) : null;

if (!$driver_id || empty($full_name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'خطأ: حقول الاسم الكامل ورقم الهاتف مطلوبة.']);
    exit;
}

try {
    $stmt_check = $pdo->prepare("SELECT governorate_id FROM drivers WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$driver_id]);
    $driver = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$driver) { throw new Exception("السائق غير موجود أو تم حذفه."); }
    if (!$is_super_admin && $driver['governorate_id'] !== $admin_governorate_id) {
        throw new Exception("لا يمكنك تعديل بيانات سائق خارج محافظتك.");
    }

    $sql_parts = ['full_name = :full_name', 'phone = :phone', 'vehicle_type = :vehicle_type'];
    $params = [
        ':full_name' => $full_name, ':phone' => $phone,
        ':vehicle_type' => $vehicle_type, ':driver_id' => $driver_id
    ];
    
    if (!empty($password)) {
        $sql_parts[] = 'password = :password';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($governorate_id !== null && $is_super_admin) {
        $sql_parts[] = 'governorate_id = :governorate_id';
        $params[':governorate_id'] = $governorate_id;
    }

    $sql = "UPDATE drivers SET " . implode(', ', $sql_parts) . " WHERE id = :driver_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'تم تحديث بيانات السائق بنجاح.']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'فشل التحديث: ' . $e->getMessage()]);
    exit;
}
?>