<?php
// ========================================================================
// Syriazzle - Driver Availability Management (Online/Offline Logic - V2.0)
// ========================================================================

require_once __DIR__ . '/db_connect.php';

// ضبط الترويسة لرد JSON
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق الأمني: هل السائق مسجل دخول؟
if (!isset($_SESSION['driver_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح للوصول، يرجى تسجيل الدخول.']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];

// 2. استقبال الحالة المطلوبة (1 للمتاح، 0 لغير المتاح)
$is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 0;

try {
    // 3. التحقق من حالة حساب السائق في قاعدة البيانات (Security Check)
    $stmt_status = $pdo->prepare("SELECT status FROM drivers WHERE id = ?");
    $stmt_status->execute([$driver_id]);
    $driver_status = $stmt_status->fetchColumn();

    if (!$driver_status || $driver_status !== 'approved') {
        echo json_encode([
            'success' => false, 
            'message' => 'لا يمكنك تغيير حالتك، حسابك غير مفعل أو محظور.',
            'action' => 'logout'
        ]);
        exit;
    }

    // 4. تنفيذ تحديث الحالة
    if ($is_available === 1) {
        // --- وضع "بدء استقبال الطلبات" (Online) ---
        $stmt_upd = $pdo->prepare("UPDATE drivers SET is_available = 1 WHERE id = ?");
        $stmt_upd->execute([$driver_id]);
        
        $message = "أنت الآن متصل وتستقبل الطلبات القريبة.";
    } else {
        // --- وضع "إيقاف العمل" (Offline) ---
        // ملاحظة المهندس: نقوم بتصفير الإحداثيات (NULL) لكي يختفي السائق فوراً من الخريطة
        $stmt_upd = $pdo->prepare("
            UPDATE drivers 
            SET 
                is_available = 0, 
                current_latitude = NULL, 
                current_longitude = NULL 
            WHERE id = ?
        ");
        $stmt_upd->execute([$driver_id]);
        
        $message = "تم إيقاف استقبال الطلبات بنجاح.";
    }

    // 5. الرد النهائي
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'current_state' => $is_available
    ]);

} catch (PDOException $e) {
    error_log("Update Driver Availability DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ فني أثناء تحديث الحالة، يرجى المحاولة لاحقاً.'
    ]);
}

exit;