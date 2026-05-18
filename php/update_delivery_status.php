<?php
// ========================================================================
// Syriazzle - Delivery Status & Financial Logic (Corrected V9.0)
// المنطق المالي الجديد: الدين يزيد بالموجب (+)، السداد ينقص بالسالب (-)
// ========================================================================

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/NotificationManager.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق الأمني
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['driver_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مصرح للوصول.']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

if ($order_id === 0 || !in_array($new_status, ['picked_up', 'delivered'])) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. التحقق من الطلب وارتباطه بالسائق
    $stmt_check = $pdo->prepare("
        SELECT o.*, 
               b.name as business_name, b.user_id as merchant_id, b.commission_rate as b_rate, 
               d.commission_rate as d_rate 
        FROM orders o 
        JOIN businesses b ON o.business_id = b.id 
        JOIN drivers d ON o.driver_id = d.id 
        WHERE o.id = ? AND o.driver_id = ? 
        FOR UPDATE
    ");
    $stmt_check->execute([$order_id, $driver_id]);
    $order = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("الطلب غير موجود أو غير مرتبط بك.");
    }

    // 3. التحقق من تسلسل الحالة
    if ($new_status === 'picked_up' && $order['status'] !== 'accepted') {
        throw new Exception("يجب أن يكون الطلب 'مقبولاً' قبل استلامه.");
    }
    if ($new_status === 'delivered' && $order['status'] !== 'picked_up') {
        throw new Exception("لا يمكن إنهاء الطلب قبل استلامه.");
    }

    // 4. تحديث الحالة
    $stmt_upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt_upd->execute([$new_status, $order_id]);

    // 5. المعالجة المالية (فقط عند التسليم النهائي)
    if ($new_status === 'delivered') {
        $currency = $order['currency'] ?? 'SYP';
        $total_price = (float)$order['total_price'];
        $delivery_fee = (float)$order['delivery_fee'];
        $tip_amount = (float)$order['tip_amount'];
        
        // حساب سعر المنتجات الصافي
        $items_price = ($currency === 'USD') ? $total_price : ($total_price - $delivery_fee - $tip_amount);
        if ($items_price < 0) $items_price = 0;

        // حساب العمولات (قيم موجبة)
        $biz_commission = $items_price * ((float)$order['b_rate'] / 100);
        $drv_commission = $delivery_fee * ((float)$order['d_rate'] / 100);

        // أ- زيادة مبيعات المتجر (Balance) - يزاد بنفس عملة المتجر
        $stmt_b_bal = $pdo->prepare("UPDATE businesses SET balance = balance + ? WHERE id = ?");
        $stmt_b_bal->execute([$items_price, $order['business_id']]);

        // ب- تسجيل الدين على المتجر (Commission Balance)
        // المنطق الجديد: الدين يزداد بالموجب (+)، لذا نجمع العمولة
        if ($biz_commission > 0) {
            $stmt_b_comm = $pdo->prepare("UPDATE businesses SET commission_balance = commission_balance + ? WHERE id = ?");
            $stmt_b_comm->execute([$biz_commission, $order['business_id']]);
        }

        // ج- تسجيل الدين على السائق (Commission Balance)
        // المنطق الجديد: الدين يزداد بالموجب (+)، لذا نجمع العمولة
        if ($drv_commission > 0) {
            $stmt_d_comm = $pdo->prepare("UPDATE drivers SET commission_balance = commission_balance + ? WHERE id = ?");
            $stmt_d_comm->execute([$drv_commission, $driver_id]);
        }

        // د- توثيق المعاملات في السجل (Transactions)
        // في السجل، نظهرها سالبة (-) لتدل على أنها "مطلوبة من الشريك" أو "اقتطاع"
        $sql_trans = "INSERT INTO transactions (order_id, user_id, user_type, transaction_type, amount, description, created_at) VALUES (?, ?, ?, 'commission', ?, ?, NOW())";
        $stmt_trans = $pdo->prepare($sql_trans);
        
        if ($biz_commission > 0) {
            // نسجلها سالبة في السجل فقط للعرض المحاسبي
            $stmt_trans->execute([
                $order_id, $order['business_id'], 'business', 
                -$biz_commission, 
                "عمولة مستحقة على طلب #$order_id ($currency)"
            ]);
        }
        
        if ($drv_commission > 0) {
            // نسجلها سالبة في السجل فقط للعرض المحاسبي
            $stmt_trans->execute([
                $order_id, $driver_id, 'driver', 
                -$drv_commission, 
                "عمولة مستحقة على توصيل طلب #$order_id"
            ]);
        }

        // هـ- تحرير السائق
        $stmt_free = $pdo->prepare("UPDATE drivers SET is_available = 1 WHERE id = ?");
        $stmt_free->execute([$driver_id]);
    }

    $pdo->commit();

    // 6. الإشعارات
    if ($new_status === 'picked_up') {
        NotificationManager::sendNotification((int)$order['user_id'], 'user', "الكابتن في الطريق! 🛵", "تم استلام طلبك وهو في الطريق إليك.", "track_order.php?order_id=$order_id");
    } elseif ($new_status === 'delivered') {
        NotificationManager::sendNotification((int)$order['user_id'], 'user', "تم التسليم بنجاح ✅", "شكراً لاستخدامك Syriazzle!", "account.php");
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'تم التحديث بنجاح.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    error_log("Update Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>