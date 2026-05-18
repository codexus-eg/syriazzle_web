<?php
require_once __DIR__ . '/../admin/auth_guard.php';
header('Content-Type: application/json; charset=UTF-8');

function send_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!hasPermission('manage_mall')) { send_response(false, 'وصول غير مصرح به.'); }

define('MALL_BUSINESS_ID', 1);
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'fetch_orders':
            // جلب الطلبات الجديدة أو التي قيد التعيين فقط
            $stmt = $pdo->prepare("
                SELECT o.id, o.customer_name, o.total_price, o.status, o.created_at
                FROM orders o
                WHERE o.business_id = ? AND o.status IN ('pending', 'processing')
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([MALL_BUSINESS_ID]);
            send_response(true, 'تم جلب الطلبات.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'fetch_order_details':
            $order_id = (int)($_GET['order_id'] ?? 0);
            if ($order_id === 0) { send_response(false, 'معرف الطلب غير صالح.'); }

            // جلب تفاصيل الطلب الرئيسية
            $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND business_id = ?");
            $stmt_order->execute([$order_id, MALL_BUSINESS_ID]);
            $order = $stmt_order->fetch(PDO::FETCH_ASSOC);
            if (!$order) { send_response(false, 'لم يتم العثور على الطلب.'); }

            // جلب المنتجات داخل الطلب
            $stmt_items = $pdo->prepare("SELECT item_name, quantity, price_per_item FROM order_items WHERE order_id = ?");
            $stmt_items->execute([$order_id]);
            $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            // جلب السائقين المتاحين (التابعين للمنصة فقط)
            // نفترض أن سائقي المول لديهم role_id معين، مثلا 3
            $stmt_drivers = $pdo->prepare("SELECT id, full_name FROM drivers WHERE role_id = 5 AND is_available = 1");
            $stmt_drivers->execute();
            $available_drivers = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);

            send_response(true, 'تم جلب التفاصيل.', ['order' => $order, 'drivers' => $available_drivers]);
            break;

        case 'assign_driver':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $driver_id = (int)($_POST['driver_id'] ?? 0);
            if ($order_id === 0 || $driver_id === 0) { send_response(false, 'بيانات غير مكتملة.'); }

            $stmt = $pdo->prepare("UPDATE orders SET driver_id = ?, status = 'out_for_delivery' WHERE id = ? AND business_id = ?");
            $stmt->execute([$driver_id, $order_id, MALL_BUSINESS_ID]);

            // هنا يمكنك إضافة كود لإرسال إشعار للسائق
            send_response(true, 'تم تعيين السائق للطلب بنجاح!');
            break;

        default:
            send_response(false, 'الإجراء المطلوب غير معروف.');
    }
} catch (Exception $e) {
    send_response(false, 'حدث خطأ فني: ' . $e->getMessage());
}
?>