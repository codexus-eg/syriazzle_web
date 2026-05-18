<?php
// ========================================================================
// Syriazzle - Zone Saver API (Full Version)
// ========================================================================

header('Content-Type: application/json; charset=utf-8');

// 1. بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. الاتصال بقاعدة البيانات (مع مسار احتياطي)
$path_to_db = __DIR__ . '/../../php/db_connect.php';
if (file_exists($path_to_db)) {
    require_once $path_to_db;
} else {
    require_once __DIR__ . '/../php/db_connect.php';
}

// 3. التحقق من الصلاحيات (مرن لضمان العمل)
$is_logged_in = (
    (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
    isset($_SESSION['admin_id'])
);

if (!$is_logged_in) {
    echo json_encode(['success' => false, 'message' => 'انتهت الجلسة، يرجى تحديث الصفحة.']);
    exit;
}

// 4. معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $zone_id = isset($_POST['zone_id']) ? (int)$_POST['zone_id'] : 0;
    $polygon_json = isset($_POST['polygon_data']) ? $_POST['polygon_data'] : '';
    
    // استقبال المركز (قد يكون فارغاً إذا تم مسح المنطقة)
    $center_lat = isset($_POST['center_lat']) && $_POST['center_lat'] !== 'null' ? (float)$_POST['center_lat'] : null;
    $center_lng = isset($_POST['center_lng']) && $_POST['center_lng'] !== 'null' ? (float)$_POST['center_lng'] : null;

    if ($zone_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'رقم المنطقة غير صحيح.']);
        exit;
    }

    // التحقق من صحة JSON
    $decoded = json_decode($polygon_json);
    if ($decoded === null) {
        echo json_encode(['success' => false, 'message' => 'بيانات الخريطة غير صالحة.']);
        exit;
    }

    try {
        // إذا كانت المصفوفة فارغة، نصفر المركز أيضاً
        if (empty($decoded)) {
            $center_lat = null;
            $center_lng = null;
        }

        // تحديث المضلع والمركز
        $stmt = $pdo->prepare("UPDATE delivery_zones SET zone_polygon = ?, center_latitude = ?, center_longitude = ? WHERE id = ?");
        $stmt->execute([$polygon_json, $center_lat, $center_lng, $zone_id]);

        echo json_encode(['success' => true, 'message' => 'تم حفظ المنطقة وتحديد مركز الانطلاق بنجاح!']);

    } catch (PDOException $e) {
        error_log("Zone Save Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>