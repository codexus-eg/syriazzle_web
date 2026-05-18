<?php
// ========================================================================
// Syriazzle Mall - Update Order Status (النسخة النهائية - إعادة المخزون)
// ========================================================================
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// التحقق من صلاحية السائق
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$new_status = isset($_POST['status']) ? $_POST['status'] : '';

// الحالات المسموح للسائق بتغييرها
$allowed_statuses = ['delivered', 'canceled']; 

if ($order_id > 0 && in_array($new_status, $allowed_statuses)) {
    try {
        $pdo->beginTransaction();
        
        // 1. التحقق من أن الطلب مخصص لهذا السائق
        $stmt_check = $pdo->prepare("SELECT status FROM mall_orders WHERE id = ? AND assigned_driver_id = ?");
        $stmt_check->execute([$order_id, $driver_id]);
        $current_status = $stmt_check->fetchColumn();

        if ($current_status) {
            // لا تقم بالتحديث إذا كانت الحالة هي نفسها
            if ($current_status === $new_status) {
                $pdo->rollBack();
                echo json_encode(['success' => true, 'message' => 'الطلب محدث بالفعل.']);
                exit;
            }

            // 2. تحديث حالة الطلب
            $stmt_update = $pdo->prepare("UPDATE mall_orders SET status = ?, status_last_updated = NOW() WHERE id = ?");
            $stmt_update->execute([$new_status, $order_id]);
            
            // 3. منطق إدارة المخزون (Inventory Logic)
            if ($new_status === 'canceled') {
                // *** حالة الإلغاء: يجب إعادة المنتجات للمخزون ***
                
                // جلب المنتجات في الطلب
                $stmt_items = $pdo->prepare("SELECT mall_product_id, quantity FROM mall_order_items WHERE mall_order_id = ?");
                $stmt_items->execute([$order_id]);
                $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                // استعلام إعادة الكمية
                $stmt_return_stock = $pdo->prepare("UPDATE mall_product_inventory SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
                
                foreach($order_items as $item) {
                    $stmt_return_stock->execute([$item['quantity'], $item['mall_product_id']]);
                }
            } 
            // ملاحظة هامة: في حالة 'delivered' لا نفعل شيئاً للمخزون، لأنه تم خصمه مسبقاً عند إنشاء الطلب.
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الطلب بنجاح.']);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'هذا الطلب غير مخصص لك أو غير موجود.']);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Mall Status Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة.']);
}
?>