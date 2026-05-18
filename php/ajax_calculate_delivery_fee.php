<?php
// ========================================================================
// Syriazzle Mall - Delivery Fee Calculator (Smart Multi-City Version)
// ========================================================================

require_once 'db_connect.php';
// نحتاج لاستدعاء دالة فحص المناطق
require_once 'zone_checker.php';

header('Content-Type: application/json; charset=utf-8');

function send_json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Method Not Allowed']);
}

// استقبال البيانات
$request_data = json_decode(file_get_contents('php://input'), true);
$lat = filter_var($request_data['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$lon = filter_var($request_data['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false) {
    send_json_response(['success' => false, 'message' => 'إحداثيات غير صالحة.']);
}

try {
    // 1. فحص المنطقة (هل الزبون داخل أي مضلع مرسوم؟)
    $zoneInfo = checkDeliveryZone($lat, $lon, $pdo);
    
    if ($zoneInfo['status'] === 'out_of_service') {
        send_json_response(['success' => false, 'message' => 'عذراً، هذه المنطقة خارج نطاق التوصيل حالياً.']);
    }

    // 2. جلب الإعدادات العامة
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mall_price_per_km', 'mall_base_delivery_fee', 'mall_latitude', 'mall_longitude')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

    $price_per_km = (float)($settings['mall_price_per_km'] ?? 1000);
    $base_fee = (float)($settings['mall_base_delivery_fee'] ?? 3000);
    
    // نقطة الانطلاق الافتراضية (المول الرئيسي)
    $start_lat = (float)($settings['mall_latitude'] ?? 33.5138); 
    $start_lon = (float)($settings['mall_longitude'] ?? 36.2765);

    // 3. الذكاء الجغرافي: تحديد نقطة الانطلاق بناءً على المنطقة
    // نستخدم اسم المنطقة الذي عاد من checkDeliveryZone لجلب مركزها
    if (!empty($zoneInfo['zone_name'])) {
        $stmt_zone = $pdo->prepare("SELECT center_latitude, center_longitude FROM delivery_zones WHERE zone_name = ? LIMIT 1");
        $stmt_zone->execute([$zoneInfo['zone_name']]);
        $zoneData = $stmt_zone->fetch(PDO::FETCH_ASSOC);

        if ($zoneData && !empty($zoneData['center_latitude'])) {
            // وجدنا مركزاً للمنطقة (مثلاً مركز حلب)، نعتمد عليه
            $start_lat = (float)$zoneData['center_latitude'];
            $start_lon = (float)$zoneData['center_longitude'];
        }
    }

    // 4. حساب المسافة الدقيقة عبر OSRM
    $url = "http://router.project-osrm.org/route/v1/driving/{$start_lon},{$start_lat};{$lon},{$lat}?overview=false";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Syriazzle Mall Calculator');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        throw new Exception("خدمة الخرائط غير متاحة حالياً.");
    }
    
    $data = json_decode($response, true);

    if (isset($data['routes'][0]['distance'])) {
        $distance_meters = $data['routes'][0]['distance'];
        $distance_km = $distance_meters / 1000;
        
        // المعادلة: الأساسي + (المسافة * سعر الكيلو) + رسوم المنطقة الإضافية (إن وجدت)
        // ملاحظة: zoneInfo['surcharge'] ستكون 0 لأننا صفرناها في قاعدة البيانات، لكن نتركها للكود ليدعمها مستقبلاً
        $delivery_fee = $base_fee + ($distance_km * $price_per_km) + $zoneInfo['surcharge'];
        
        // تقريب لأقرب 500 ليرة
        $delivery_fee = ceil($delivery_fee / 500) * 500;
        
        send_json_response([
            'success' => true, 
            'delivery_fee' => $delivery_fee, 
            'distance_km' => round($distance_km, 2),
            'zone_name' => $zoneInfo['zone_name'] // نعيد اسم المنطقة ليظهر للزبون
        ]);
    } else {
        throw new Exception("تعذر حساب المسافة للطريق المحدد.");
    }

} catch (Exception $e) {
    error_log("Fee Calc Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'حدث خطأ أثناء الحساب، يرجى المحاولة مرة أخرى.']);
}
?>