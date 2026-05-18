<?php
// ========================================================================
// Syriazzle - Driver Real-time Location Updater (Marketplace)
// ========================================================================

require_once 'db_connect.php';

// منع الوصول المباشر أو غير المصرح به
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['driver_id'])) {
    http_response_code(403);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];

// استقبال وتمرير الإحداثيات مع التحقق من النوع (Float)
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

// التحقق من صحة البيانات المرسلة
if ($latitude === false || $longitude === false || $latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'إحداثيات غير صالحة.']);
    exit;
}

try {
    // تحديث الموقع الجغرافي ووقت آخر ظهور
    $stmt = $pdo->prepare("
        UPDATE drivers 
        SET 
            current_latitude = ?, 
            current_longitude = ?, 
            last_seen = NOW() 
        WHERE id = ? AND status = 'approved'
    ");
    
    $success = $stmt->execute([$latitude, $longitude, $driver_id]);

    if ($success && $stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        // إذا لم يتم التحديث (ربما الحساب لم يعد approved)
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'تعذر تحديث الموقع.']);
    }

} catch (PDOException $e) {
    error_log("Location Update DB Error (Driver $driver_id): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في خادم قاعدة البيانات.']);
}
exit;