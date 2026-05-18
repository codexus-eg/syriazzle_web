<?php
// ajax_get_driver_tasks.php (النسخة النهائية الآمنة والديناميكية)
require_once 'db_connect.php'; 
header('Content-Type: application/json; charset=UTF-8');

// --- حارس البوابة: التحقق من جلسة السائق ---
if (!isset($_SESSION['driver_logged_in']) || !isset($_SESSION['driver_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}
// جلب ID السائق من الجلسة الآمنة
$driver_id = (int)$_SESSION['driver_id'];

$action = $_GET['action'] ?? '';

try {
    if ($action === 'fetch_tasks') {
        $stmt = $pdo->prepare("
            SELECT id, customer_name, total_price, status 
            FROM orders 
            WHERE driver_id = ? AND status IN ('out_for_delivery', 'reached_customer')
            ORDER BY created_at ASC
        ");
        $stmt->execute([$driver_id]);
        echo json_encode(['success' => true, 'tasks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } 
    elseif ($action === 'fetch_task_details') {
        $order_id = (int)($_GET['order_id'] ?? 0);
        if ($order_id === 0) {
            echo json_encode(['success' => false, 'message' => 'معرف الطلب غير صالح.']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT id, customer_name, customer_phone, customer_address, total_price, 
                   customer_latitude, customer_longitude 
            FROM orders 
            WHERE id = ? AND driver_id = ?
        ");
        $stmt->execute([$order_id, $driver_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($details) {
            echo json_encode(['success' => true, 'details' => $details]);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم العثور على الطلب أو أنه غير مخصص لك.']);
        }
    }
    else {
        echo json_encode(['success' => false, 'message' => 'الإجراء المطلوب غير معروف.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Driver API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم.']);
}
?>