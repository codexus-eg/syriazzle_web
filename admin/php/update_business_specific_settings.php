<?php
// ========================================================================
// Syriazzle Admin API - تحديث الإعدادات المخصصة للنشاط (النسخة 2.1 - مع إعادة البيانات)
// ========================================================================
require_once '../auth_guard.php';

header('Content-Type: application/json; charset=UTF-8');

$business_id = (int)($_POST['business_id'] ?? 0);
if ($business_id === 0) {
    echo json_encode(['success' => false, 'message' => 'لم يتم تحديد النشاط.']);
    exit;
}

try {
    $type_stmt = $pdo->prepare("SELECT business_type FROM businesses WHERE id = ?");
    $type_stmt->execute([$business_id]);
    $business_type = $type_stmt->fetchColumn();

    if (!$business_type) {
        throw new Exception("النشاط التجاري غير موجود.");
    }

    $updates = [];
    $params = [];

    if (($business_type === 'delivery' || $business_type === 'hybrid') && hasPermission('edit_business')) {
        if (isset($_POST['commission_rate'])) {
            $updates[] = "commission_rate = ?";
            $params[] = (float)$_POST['commission_rate'];
        }
        if (isset($_POST['credit_limit'])) {
            $updates[] = "credit_limit = ?";
            $params[] = (int)$_POST['credit_limit'];
        }
    }
    
    if (($business_type === 'booking' || $business_type === 'hybrid') && hasPermission('edit_booking_settings')) {
        if (isset($_POST['booking_commission_rate'])) {
            $updates[] = "booking_commission_rate = ?";
            $params[] = (float)$_POST['booking_commission_rate'];
        }
        if (isset($_POST['booking_credit_limit'])) {
            $updates[] = "booking_credit_limit = ?";
            $params[] = (int)$_POST['booking_credit_limit'];
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل هذه الإعدادات.']);
        exit;
    }

    $sql = "UPDATE businesses SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $business_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // **الخطوة الجديدة: جلب البيانات المحدثة وإعادتها**
    $fetch_stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $fetch_stmt->execute([$business_id]);
    $updatedData = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'message' => 'تم تحديث الإعدادات بنجاح.',
        'newData' => $updatedData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>