<?php
// تحديد نوع المحتوى بأنه JSON لضمان تعامل المتصفح معه بشكل صحيح
header('Content-Type: application/json; charset=utf-8');

// استدعاء الملفات الأساسية
require_once 'db_connect.php'; 
require_once 'zone_checker.php'; 

// الاستجابة الافتراضية في حال وجود خطأ
$response = ['success' => false, 'message' => 'بيانات غير كافية.'];

// التحقق من أن الطلب من نوع POST وأنه يحتوي على جميع الإحداثيات المطلوبة
if (isset($_POST['start_lat'], $_POST['start_lon'], $_POST['end_lat'], $_POST['end_lon'])) {
    
    // تحويل المدخلات إلى أرقام عشرية (float) لضمان دقة الحسابات
    $start_lat = (float) $_POST['start_lat'];
    $start_lon = (float) $_POST['start_lon'];
    $end_lat   = (float) $_POST['end_lat'];
    $end_lon   = (float) $_POST['end_lon'];
    
    try {
        // --- الخطوة 1: جلب إعدادات التسعير الديناميكية من قاعدة البيانات ---
        $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // تحويل الإعدادات إلى متغيرات مع قيم افتراضية آمنة
        $base_fare   = (float)($settings['delivery_base_fare'] ?? 2000.0);
        $per_km_rate = (float)($settings['delivery_per_km_rate'] ?? 1000.0);
        
        // --- الخطوة 2: التحقق من منطقة خدمة الزبون ---
        $zoneInfo = checkDeliveryZone($end_lat, $end_lon, $pdo);

        // إذا كان الزبون خارج مناطق الخدمة بالكامل
        if ($zoneInfo['status'] === 'out_of_service') {
            echo json_encode([
                'success'   => true, 
                'status'    => 'out_of_service',
                'message'   => 'خارج منطقة الخدمة',
                'total_fee' => 0
            ]);
            exit;
        }

        // --- الخطوة 3: حساب المسافة بدقة من السيرفر ---
        $distance_in_km = getRouteDistanceFromServer($start_lat, $start_lon, $end_lat, $end_lon);

        if ($distance_in_km === -1) {
             echo json_encode([
                'success'   => false,
                'message'   => 'لا يمكن حساب المسافة حالياً، حاول مرة أخرى.',
                'status'    => 'error'
            ]);
            exit;
        }

        // --- الخطوة 4: تطبيق معادلة التسعير الديناميكية ---
        $calculated_fee = $base_fare + ($distance_in_km * $per_km_rate);
        
        // تقريب الرسوم لأقرب 500 ل.س
        $base_fee_rounded = round($calculated_fee / 500) * 500;
        
        // التأكد من أن الأجرة لا تقل عن الرسم الأساسي
        $final_base_fee = max($base_fare, $base_fee_rounded);

        $surcharge = $zoneInfo['surcharge'];
        $total_fee = $final_base_fee + $surcharge;

        // --- الخطوة 5: إرسال الاستجابة النهائية الكاملة ---
        echo json_encode([
            'success'   => true,
            'status'    => 'in_service',
            'message'   => 'في منطقة الخدمة',
            'total_fee' => $total_fee
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'حدث خطأ داخلي في الخادم.']);
        error_log("Calculate Delivery Error: " . $e->getMessage()); // تسجيل الخطأ للمطور
    }

} else {
    echo json_encode($response);
}


/**
 * دالة احترافية لاستدعاء OpenRouteService API من السيرفر باستخدام cURL.
 * هذا يضمن الأمان والدقة الكاملة.
 *
 * @param float $start_lat خط عرض نقطة البداية (المتجر)
 * @param float $start_lon خط طول نقطة البداية (المتجر)
 * @param float $end_lat خط عرض نقطة النهاية (الزبون)
 * @param float $end_lon خط طول نقطة النهاية (الزبون)
 * @return float المسافة بالكيلومترات، أو -1 في حال حدوث خطأ.
 */
function getRouteDistanceFromServer($start_lat, $start_lon, $end_lat, $end_lon) {
    // مفتاح API الخاص بك (من الأفضل تخزينه في ملف إعدادات منفصل مستقبلاً)
    $api_key = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImViYzQxYzEyYjM0MzQxYzViODYxM2Q1YjNlMWRkZjI4IiwiaCI6Im11cm11cjY0In0=';
    $url = 'https://api.openrouteservice.org/v2/directions/driving-car/json';

    $coordinates = [
        [$start_lon, $start_lat],
        [$end_lon, $end_lat]
    ];
    
    $post_data = json_encode(['coordinates' => $coordinates]);

    $headers = [
        'Authorization: ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json, application/geo+json, application/gpx+xml, img/png; charset=utf-8'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // إضافة مهلة زمنية 10 ثوانٍ

    $response_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response_json, true);
        if (isset($response_data['routes'][0]['summary']['distance'])) {
            $distance_in_meters = $response_data['routes'][0]['summary']['distance'];
            return $distance_in_meters / 1000;
        }
    }
    
    // تسجيل الخطأ إذا فشل الاتصال بـ ORS
    error_log("OpenRouteService API call failed with HTTP code: " . $http_code);
    return -1;
}

?>