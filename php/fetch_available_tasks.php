<?php
// ========================================================================
// Syriazzle - Master Dispatcher Engine (V11.0 - Stable Production)
// ========================================================================

require_once __DIR__ . '/db_connect.php';

// ضبط الاستجابة لتكون JSON نظيفة تماماً
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق الأمني: هل السائق مسجل دخول؟
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['error' => 'غير مصرح للوصول.']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];

try {
    // 2. جلب بيانات السائق للتحقق من الأهلية والموقع
    $stmt_d = $pdo->prepare("SELECT current_latitude, current_longitude, status, is_available, commission_balance, credit_limit, driver_type FROM drivers WHERE id = ?");
    $stmt_d->execute([$driver_id]);
    $driver = $stmt_d->fetch(PDO::FETCH_ASSOC);

    // شروط المنع الصارمة
    if (!$driver || $driver['status'] !== 'approved' || $driver['is_available'] == 0 || $driver['driver_type'] !== 'marketplace') {
        echo json_encode([]); exit;
    }

    // التأكد من وجود إحداثيات GPS للسائق
    if (empty($driver['current_latitude']) || empty($driver['current_longitude'])) {
        echo json_encode([]); exit;
    }

    // التحقق المالي (الحد الائتماني للديون)
    if ((float)$driver['commission_balance'] >= (float)$driver['credit_limit']) {
        echo json_encode([]); exit;
    }

    // منع السائق المشغول بمهمة نشطة
    $stmt_busy = $pdo->prepare("SELECT id FROM orders WHERE driver_id = ? AND status IN ('accepted', 'picked_up', 'out_for_delivery') LIMIT 1");
    $stmt_busy->execute([$driver_id]);
    if ($stmt_busy->fetch()) {
        echo json_encode([]); exit;
    }

    // ============================================================
    // 3. إدارة نظام العروض الحصرية (Dispatching Logic)
    // ============================================================

    $pdo->beginTransaction();

    // أ- تنظيف العروض المنتهية الصلاحية (أقدم من 45 ثانية) فوراً
    // هذا يحرر الطلبات للسائقين الآخرين
    $pdo->exec("DELETE FROM order_offers WHERE expires_at < NOW() AND status = 'pending'");

    // ب- التحقق إذا كان لدى هذا السائق "عرض نشط" حالياً
    // نستخدم أسماء بارامترات فريدة (lat_a, lon_a, lat_b) لمنع خطأ PDO HY093
    $sql_active = "
        SELECT o.id as order_id, o.total_price, o.delivery_fee, o.tip_amount, o.currency,
               o.customer_address, b.name as business_name, b.latitude as b_lat, b.longitude as b_lon,
               (6371 * acos(
                    LEAST(1.0, GREATEST(-1.0, 
                        cos(radians(:lat_a)) * cos(radians(b.latitude)) * cos(radians(b.longitude) - radians(:lon_a)) + 
                        sin(radians(:lat_b)) * sin(radians(b.latitude))
                    ))
               )) AS distance
        FROM order_offers off
        JOIN orders o ON off.order_id = o.id
        JOIN businesses b ON o.business_id = b.id
        WHERE off.driver_id = :did AND off.status = 'pending' AND off.expires_at > NOW()
        LIMIT 1
    ";
    
    $stmt_active = $pdo->prepare($sql_active);
    $stmt_active->execute([
        'lat_a' => $driver['current_latitude'],
        'lon_a' => $driver['current_longitude'],
        'lat_b' => $driver['current_latitude'],
        'did'   => $driver_id
    ]);
    $active_offer = $stmt_active->fetch(PDO::FETCH_ASSOC);

    if ($active_offer) {
        $pdo->commit();
        $active_offer['distance_to_business'] = round((float)$active_offer['distance'], 2);
        $active_offer['total_earnings'] = (float)$active_offer['delivery_fee'] + (float)$active_offer['tip_amount'];
        if (empty($active_offer['currency'])) $active_offer['currency'] = 'SYP';
        echo json_encode([$active_offer], JSON_NUMERIC_CHECK);
        exit;
    }

    // ج- البحث عن "أقرب طلب متاح" (ليس له عرض نشط عند أي سائق آخر)
    $radius = 5.0; // نطاق بحث 5 كم

    $sql_find = "
        SELECT 
            o.id as order_id, o.total_price, o.delivery_fee, o.tip_amount, o.currency,
            o.customer_address, b.name as business_name, b.latitude as b_lat, b.longitude as b_lon,
            ( 
                6371 * acos( 
                    LEAST(1.0, GREATEST(-1.0, 
                        cos(radians(:lat_f1)) * cos(radians(b.latitude)) * 
                        cos(radians(b.longitude) - radians(:lon_f1)) + 
                        sin(radians(:lat_f2)) * sin(radians(b.latitude))
                    ))
                ) 
            ) AS distance_to_business
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE 
            o.status IN ('ready_for_pickup', 'pending_driver') 
            AND o.driver_id IS NULL
            AND b.latitude IS NOT NULL
            -- التأكد أن الطلب غير معروض حالياً على سائق آخر
            AND NOT EXISTS (SELECT 1 FROM order_offers WHERE order_id = o.id AND expires_at > NOW() AND status = 'pending')
            -- التأكد أن السائق الحالي لم يرفض هذا الطلب في هذه الجلسة
            AND NOT EXISTS (SELECT 1 FROM order_offers WHERE order_id = o.id AND driver_id = :did_f AND status IN ('rejected', 'timed_out'))
        HAVING distance_to_business <= :rad
        ORDER BY distance_to_business ASC
        LIMIT 1
        FOR UPDATE
    ";

    $stmt_find = $pdo->prepare($sql_find);
    $stmt_find->execute([
        'lat_f1' => $driver['current_latitude'],
        'lon_f1' => $driver['current_longitude'],
        'lat_f2' => $driver['current_latitude'],
        'did_f'  => $driver_id,
        'rad'    => $radius
    ]);
    
    $new_task = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if ($new_task) {
        // د- إنشاء العرض الحصري (45 ثانية) للسائق الحالي
        $expires = date('Y-m-d H:i:s', strtotime('+45 seconds'));
        $stmt_ins = $pdo->prepare("INSERT INTO order_offers (order_id, driver_id, expires_at, status) VALUES (?, ?, ?, 'pending')");
        $stmt_ins->execute([$new_task['order_id'], $driver_id, $expires]);
        
        $pdo->commit();

        // تنسيق البيانات
        $new_task['distance_to_business'] = round((float)$new_task['distance_to_business'], 2);
        $new_task['total_earnings'] = (float)$new_task['delivery_fee'] + (float)$new_task['tip_amount'];
        if (empty($new_task['currency'])) $new_task['currency'] = 'SYP';

        echo json_encode([$new_task], JSON_NUMERIC_CHECK);
    } else {
        $pdo->commit();
        echo json_encode([]); 
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Dispatcher Error V11: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في النظام.']);
}