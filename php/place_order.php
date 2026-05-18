<?php
// ========================================================================
// Syriazzle - Place Order API (Multi-Currency Support - Final)
// ========================================================================

session_start();
require_once 'db_connect.php'; 
require_once 'zone_checker.php'; 

header('Content-Type: application/json; charset=utf-8');

// 1. التحقق من الجلسة وصلاحية الوصول
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به، يرجى تسجيل الدخول.']);
    exit;
}

try {
    // بدء المعاملة لضمان سلامة البيانات
    $pdo->beginTransaction();

    // --- 2. استقبال وتنظيف البيانات الأساسية ---
    $current_user_id = (int)$_SESSION['user_id'];
    $business_id = filter_input(INPUT_POST, 'business_id', FILTER_VALIDATE_INT);
    
    // فك تشفير السلة
    $cart_data = json_decode($_POST['cart_data'] ?? '[]', true);
    
    // الإحداثيات
    $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
    $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
    
    // التحقق من وجود البيانات الضرورية
    if (!$business_id || empty($cart_data) || $latitude === null || $longitude === null) {
        throw new Exception("بيانات الطلب غير مكتملة (المتجر، السلة، أو الموقع مفقود).");
    }

    // --- 3. جلب إعدادات المتجر (العملة) وسعر الصرف ---
    $stmt_biz = $pdo->prepare("SELECT currency FROM businesses WHERE id = ?");
    $stmt_biz->execute([$business_id]);
    $business_currency = $stmt_biz->fetchColumn();

    if (!$business_currency) {
        throw new Exception("المتجر غير موجود.");
    }

    // جلب سعر الصرف الحالي من النظام
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    $current_exchange_rate = (float)($stmt_rate->fetchColumn() ?: 15000); // القيمة الافتراضية إذا لم توجد
    
    // --- 4. التحقق الأمني من منطقة الخدمة ---
    $zoneInfo = checkDeliveryZone($latitude, $longitude, $pdo);
    if ($zoneInfo['status'] === 'out_of_service') {
        throw new Exception("عذراً، الموقع المحدد خارج نطاق خدمة التوصيل حالياً.");
    }

    // --- 5. استقبال البيانات المالية والإضافية ---
    // رسوم التوصيل (يجب أن تكون قيمة موجبة) - دائماً بالليرة السورية
    $delivery_fee_client = filter_input(INPUT_POST, 'delivery_fee', FILTER_VALIDATE_FLOAT);
    if ($delivery_fee_client < 0) {
        throw new Exception("خطأ في رسوم التوصيل.");
    }

    // الإكرامية - دائماً بالليرة السورية
    $tip_amount = filter_input(INPUT_POST, 'tip_amount', FILTER_VALIDATE_FLOAT) ?? 0.0;
    
    $promo_code_client = htmlspecialchars(trim($_POST['promo_code'] ?? ''));
    $delivery_address_details = htmlspecialchars(trim($_POST['delivery_address_details'] ?? ''));
    
    // التوقيت
    $delivery_time_preference = htmlspecialchars($_POST['delivery_time_preference'] ?? 'asap');
    $scheduled_delivery_time = null;
    if ($delivery_time_preference === 'scheduled' && !empty($_POST['scheduled_delivery_time'])) {
        $scheduled_delivery_time = date('Y-m-d H:i:s', strtotime($_POST['scheduled_delivery_time']));
    }
    
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? 'cash');

    // --- 6. التحقق الأمني من أسعار المنتجات (بعملة المتجر) ---
    
    $menu_item_ids = [];
    $deal_ids = [];
    
    foreach ($cart_data as $item) {
        if (isset($item['type']) && $item['type'] === 'deal') {
            $deal_ids[] = (int)$item['id'];
        } else {
            $menu_item_ids[] = (int)$item['id'];
        }
    }
    
    $server_prices = []; 
    
    // جلب أسعار المنتجات العادية
    if (!empty($menu_item_ids)) {
        $placeholders = implode(',', array_fill(0, count($menu_item_ids), '?'));
        // التصحيح: استخدام item_name بدلاً من name
        $stmt_menu = $pdo->prepare("SELECT id, price, item_name FROM business_menu_items WHERE id IN ($placeholders) AND business_id = ?");
        $stmt_menu->execute(array_merge($menu_item_ids, [$business_id]));
        while ($row = $stmt_menu->fetch(PDO::FETCH_ASSOC)) {
            $server_prices['menu-' . $row['id']] = [
                'price' => (float)$row['price'], // السعر هنا يكون بعملة المتجر المخزنة
                'name'  => $row['item_name']
            ];
        }
    }
    
    // جلب أسعار العروض (Deals)
    if (!empty($deal_ids)) {
        $placeholders = implode(',', array_fill(0, count($deal_ids), '?'));
        $stmt_deals = $pdo->prepare("SELECT id, new_price, deal_name FROM business_deals WHERE id IN ($placeholders) AND business_id = ?");
        $stmt_deals->execute(array_merge($deal_ids, [$business_id]));
        while ($row = $stmt_deals->fetch(PDO::FETCH_ASSOC)) {
            $server_prices['deal-' . $row['id']] = [
                'price' => (float)$row['new_price'],
                'name'  => $row['deal_name']
            ];
        }
    }

    // حساب مجموع المنتجات
    $items_total_price_server = 0;
    
    foreach ($cart_data as $item) {
        $item_type = $item['type'] ?? 'menu';
        $key = $item_type . '-' . $item['id'];
        
        if (isset($server_prices[$key])) {
            $real_price = $server_prices[$key]['price'];
            $qty = (int)$item['quantity'];
            $items_total_price_server += $real_price * $qty;
        } else {
            throw new Exception("عذراً، أحد المنتجات في السلة لم يعد متاحاً أو تغير سعره.");
        }
    }

    // --- 7. التحقق من كود الحسم وحسابه ---
    $promo_discount_server = 0;
    $promo_id_to_update = null;
    
    if (!empty($promo_code_client)) {
        $stmt_promo = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1 AND applicable_to IN ('all', 'marketplace_only')");
        $stmt_promo->execute([$promo_code_client]);
        $promo = $stmt_promo->fetch(PDO::FETCH_ASSOC);
        
        if ($promo) {
            $is_expired = ($promo['expiry_date'] && strtotime($promo['expiry_date']) < time());
            $is_limit_reached = ($promo['max_uses'] !== null && $promo['times_used'] >= $promo['max_uses']);
            
            if (!$is_expired && !$is_limit_reached) {
                if ($promo['discount_type'] === 'percentage') {
                    $promo_discount_server = $items_total_price_server * ($promo['discount_value'] / 100);
                } else { 
                    $promo_discount_server = (float)$promo['discount_value']; 
                }
                
                $promo_discount_server = min($items_total_price_server, round($promo_discount_server, 2));
                $promo_id_to_update = $promo['id'];
            }
        }
    }

    // --- 8. الحساب النهائي (المنطق الذكي للعملات) ---
    
    $final_items_total = $items_total_price_server - $promo_discount_server;
    $total_price_for_db = 0;

    if ($business_currency === 'USD') {
        // إذا كان المتجر بالدولار: المجموع في قاعدة البيانات هو صافي قيمة المنتجات بالدولار فقط
        // رسوم التوصيل والإكرامية تُحفظ في أعمدتها الخاصة بالليرة
        $total_price_for_db = $final_items_total;
    } else {
        // إذا كان المتجر بالليرة: المجموع في قاعدة البيانات يشمل كل شيء (منتجات + توصيل + إكرامية)
        $total_price_for_db = $final_items_total + $delivery_fee_client + $tip_amount;
    }

    // --- 9. جلب بيانات المستخدم ---
    $stmt_user = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
    $stmt_user->execute([$current_user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info) throw new Exception("تعذر العثور على بيانات المستخدم.");

    // --- 10. إدراج الطلب في جدول orders (مع العملة وسعر الصرف) ---
    $stmt_order = $pdo->prepare(
        "INSERT INTO orders (
            business_id, user_id, customer_name, customer_phone, 
            customer_address, customer_latitude, customer_longitude, 
            total_price, delivery_fee, tip_amount, 
            promo_code, promo_discount, 
            status, payment_method, 
            delivery_time_preference, scheduled_delivery_time, 
            created_at, currency, exchange_rate
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?, ?, ?, NOW(), ?, ?)"
    );
    
    $stmt_order->execute([
        $business_id, 
        $current_user_id, 
        $user_info['username'], 
        $user_info['phone'], 
        $delivery_address_details, 
        $latitude, 
        $longitude, 
        $total_price_for_db, // القيمة المحسوبة بناءً على العملة
        $delivery_fee_client, // دائماً بالليرة
        $tip_amount, // دائماً بالليرة
        ($promo_id_to_update ? $promo_code_client : null), 
        $promo_discount_server, 
        $payment_method,
        $delivery_time_preference, 
        $scheduled_delivery_time,
        $business_currency, // تخزين عملة المتجر
        $current_exchange_rate // تخزين سعر الصرف لحظة الطلب
    ]);
    
    $order_id = $pdo->lastInsertId();

    // --- 11. إدراج تفاصيل المنتجات ---
    $stmt_items_insert = $pdo->prepare("INSERT INTO order_items (order_id, item_id, item_type, item_name, quantity, price_per_item, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($cart_data as $item) {
        $item_type = $item['type'] ?? 'menu';
        $key = $item_type . '-' . $item['id'];
        
        $real_price = $server_prices[$key]['price'];
        $real_name = $server_prices[$key]['name'];
        $requests = htmlspecialchars($item['requests'] ?? '');
        
        $stmt_items_insert->execute([
            $order_id, 
            (int)$item['id'], 
            $item_type, 
            $real_name, 
            (int)$item['quantity'], 
            $real_price, 
            $requests
        ]);
    }

    // --- 12. تحديث الكوبون ---
    if ($promo_id_to_update) {
        $pdo->prepare("UPDATE promo_codes SET times_used = times_used + 1 WHERE id = ?")->execute([$promo_id_to_update]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم إرسال الطلب بنجاح!', 
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Place Order Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>