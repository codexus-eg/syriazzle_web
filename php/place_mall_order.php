<?php
// ========================================================================
// Syriazzle Mall - Place Order (Multi-City Logic)
// ========================================================================

require_once 'db_connect.php';
require_once 'zone_checker.php'; 

header('Content-Type: application/json; charset=utf-8');

function send_json_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    send_json_response(false, 'وصول غير مصرح به.');
}

try {
    $pdo->beginTransaction();
    
    // 1. البيانات
    $current_user_id = (int)$_SESSION['user_id'];
    $customer_name   = trim($_POST['customer_name'] ?? '');
    $customer_phone  = trim($_POST['customer_phone'] ?? '');
    $address_details = trim($_POST['address_details'] ?? '');
    $latitude        = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
    $longitude       = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
    $promo_code      = trim($_POST['promo_code'] ?? '');
    $mall_cart       = json_decode($_POST['mall_cart'] ?? '[]', true);
    
    if (empty($customer_name) || empty($customer_phone) || empty($address_details) || $latitude === null || empty($mall_cart)) {
        throw new Exception("البيانات غير مكتملة.");
    }

    // 2. التحقق من المنطقة
    $zoneInfo = checkDeliveryZone($latitude, $longitude, $pdo);
    if ($zoneInfo['status'] === 'out_of_service') {
        throw new Exception("عذراً، الموقع المحدد خارج نطاق خدمة التوصيل لدينا حالياً.");
    }

    // 3. إعداد نقطة الانطلاق (الذكاء الجغرافي)
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mall_price_per_km', 'mall_base_delivery_fee', 'mall_latitude', 'mall_longitude', 'usd_to_syp_rate')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

    $price_per_km = (float)($settings['mall_price_per_km'] ?? 1000);
    $base_fee     = (float)($settings['mall_base_delivery_fee'] ?? 3000);
    
    // الافتراضي: موقع المول الرئيسي
    $start_lat = (float)($settings['mall_latitude'] ?? 33.5138);
    $start_lon = (float)($settings['mall_longitude'] ?? 36.2765);

    // محاولة جلب مركز المنطقة الحالية من قاعدة البيانات
    // checkDeliveryZone تعيد اسم المنطقة، نستخدمه لجلب المركز
    if (!empty($zoneInfo['zone_name'])) {
        $stmt_zone = $pdo->prepare("SELECT center_latitude, center_longitude FROM delivery_zones WHERE zone_name = ? LIMIT 1");
        $stmt_zone->execute([$zoneInfo['zone_name']]);
        $zoneData = $stmt_zone->fetch(PDO::FETCH_ASSOC);

        if ($zoneData && !empty($zoneData['center_latitude'])) {
            // تم العثور على مركز للمنطقة، نعتمد عليه
            $start_lat = (float)$zoneData['center_latitude'];
            $start_lon = (float)$zoneData['center_longitude'];
        }
    }

    // 4. حساب المسافة والسعر (OSRM)
    $url = "http://router.project-osrm.org/route/v1/driving/{$start_lon},{$start_lat};{$longitude},{$latitude}?overview=false";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $delivery_fee_server = $base_fee + $zoneInfo['surcharge']; // السعر المبدئي

    if ($http_code === 200) {
        $osrm_data = json_decode($response, true);
        if (isset($osrm_data['routes'][0]['distance'])) {
            $distance_km = $osrm_data['routes'][0]['distance'] / 1000;
            $calc_fee = $base_fee + ($distance_km * $price_per_km) + $zoneInfo['surcharge'];
            $delivery_fee_server = ceil($calc_fee / 500) * 500; // تقريب
        }
    }

    // 5. معالجة المخزون
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    $usd_rate = (float)($stmt_rate->fetchColumn() ?: 15000); 

    $items_total_price_server = 0;
    $processed_items = [];

    $stmt_check_stock = $pdo->prepare("SELECT stock_quantity FROM mall_product_inventory WHERE product_id = ? FOR UPDATE");
    $stmt_update_stock = $pdo->prepare("UPDATE mall_product_inventory SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
    $stmt_product_info = $pdo->prepare("SELECT id, name, price_usd, fixed_price_syp FROM mall_products WHERE id = ?");

    foreach ($mall_cart as $item) {
        $product_id = (int)$item['id'];
        $qty_requested = (int)$item['quantity'];

        if ($qty_requested <= 0) continue;

        $stmt_product_info->execute([$product_id]);
        $product_db = $stmt_product_info->fetch(PDO::FETCH_ASSOC);
        if (!$product_db) throw new Exception("منتج غير موجود.");

        $stmt_check_stock->execute([$product_id]);
        $stock_data = $stmt_check_stock->fetch(PDO::FETCH_ASSOC);
        $current_stock = $stock_data ? (int)$stock_data['stock_quantity'] : 0;

        if ($current_stock < $qty_requested) {
            throw new Exception("الكمية غير متوفرة للمنتج: {$product_db['name']}");
        }

        $stmt_update_stock->execute([$qty_requested, $product_id]);

        $price_syp = !empty($product_db['fixed_price_syp']) && $product_db['fixed_price_syp'] > 0 
            ? (float)$product_db['fixed_price_syp'] 
            : (float)$product_db['price_usd'] * $usd_rate;
        
        $items_total_price_server += $price_syp * $qty_requested;

        $processed_items[] = [
            'id' => $product_id, 'name' => $product_db['name'], 
            'quantity' => $qty_requested, 'price' => $price_syp
        ];
    }

    // 6. كود الحسم
    $promo_discount_server = 0;
    $promo_id_to_update = null;
    if (!empty($promo_code)) {
        $stmt_promo = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
        $stmt_promo->execute([$promo_code]);
        $promo = $stmt_promo->fetch(PDO::FETCH_ASSOC);

        if ($promo && (!isset($promo['max_uses']) || $promo['times_used'] < $promo['max_uses'])) {
             if (!$promo['expiry_date'] || strtotime($promo['expiry_date']) >= time()) {
                 if (in_array($promo['applicable_to'], ['all', 'mall_only'])) {
                    if ($promo['discount_type'] === 'percentage') {
                        $promo_discount_server = $items_total_price_server * ((float)$promo['discount_value'] / 100);
                    } else {
                        $promo_discount_server = (float)$promo['discount_value'];
                    }
                    $promo_discount_server = min($items_total_price_server, round($promo_discount_server, 2));
                    $promo_id_to_update = $promo['id'];
                 }
             }
        }
    }

    $total_price_server = ($items_total_price_server - $promo_discount_server) + $delivery_fee_server;

    // 7. حفظ الطلب
    $stmt_order = $pdo->prepare(
        "INSERT INTO mall_orders (user_id, customer_name, customer_phone, address_details, latitude, longitude, total_price, delivery_fee, promo_code, promo_discount, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', NOW())"
    );
    $stmt_order->execute([
        $current_user_id, $customer_name, $customer_phone, $address_details, 
        $latitude, $longitude, $total_price_server, $delivery_fee_server,
        ($promo_id_to_update ? $promo_code : null), $promo_discount_server
    ]);
    $order_id = $pdo->lastInsertId();

    $stmt_items_insert = $pdo->prepare("INSERT INTO mall_order_items (mall_order_id, mall_product_id, product_name, quantity, price_per_item) VALUES (?, ?, ?, ?, ?)");
    foreach ($processed_items as $p_item) {
        $stmt_items_insert->execute([$order_id, $p_item['id'], $p_item['name'], $p_item['quantity'], $p_item['price']]);
    }

    if ($promo_id_to_update) {
        $pdo->prepare("UPDATE promo_codes SET times_used = times_used + 1 WHERE id = ?")->execute([$promo_id_to_update]);
    }

    $pdo->commit();
    send_json_response(true, 'تم إرسال الطلب بنجاح!', ['order_id' => $order_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Order Error: " . $e->getMessage());
    send_json_response(false, $e->getMessage());
}
?>