<?php
// منع أي إخراج غير مرغوب فيه قبل إرسال الترويسات
ob_start();

require_once 'db_connect.php'; // سيقوم ببدء الجلسة والاتصال بقاعدة البيانات

// تحديد ترويسة المحتوى لضمان التعامل معه كـ JSON ويدعم اللغة العربية
header('Content-Type: application/json; charset=utf-8');

// التحقق الأمني: التأكد من أن المستخدم مسجل دخوله
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'الوصول مرفوض. يرجى تسجيل الدخول.']);
    ob_end_flush();
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

// التحقق من صحة معرّف الطلب المستلم
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'معرف الطلب غير صالح.']);
    ob_end_flush();
    exit;
}

try {
    $sql = "
        SELECT 
            o.status, o.cancellation_reason, o.user_id,
            b.name as business_name, b.latitude as business_lat, b.longitude as business_lon,
            o.customer_latitude, o.customer_longitude,
            d.id as driver_id, d.full_name as driver_name, d.vehicle_type,
            d.current_latitude as driver_lat, d.current_longitude as driver_lon
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        LEFT JOIN drivers d ON o.driver_id = d.id
        WHERE o.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['user_id'] !== $current_user_id) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'لا تملك صلاحية الوصول إلى هذا الطلب.']);
        ob_end_flush();
        exit;
    }
    
    // --- 2. تجهيز مصفوفة الاستجابة الأولية ---
    $response_data = [
        'status' => $order['status'],
        'cancellation_reason' => $order['cancellation_reason'],
        'locations' => [
            'business' => [
                'lat' => (float)$order['business_lat'],
                'lng' => (float)$order['business_lon']
            ],
            'customer' => [
                'lat' => (float)$order['customer_latitude'],
                'lng' => (float)$order['customer_longitude']
            ]
        ],
        'driver' => null, // سيتم ملؤه لاحقاً إذا وجد
        'eta' => null     // الوقت التقريبي للوصول (ETA)
    ];

    $trackable_statuses = ['picked_up', 'on_the_way'];
    
    if (in_array($order['status'], $trackable_statuses) && $order['driver_id'] && $order['driver_lat']) {
        // إضافة معلومات السائق الأساسية
        $response_data['driver'] = [
            'name' => $order['driver_name'],
            'location' => [
                'lat' => (float)$order['driver_lat'],
                'lng' => (float)$order['driver_lon']
            ]
        ];
        
        // --- حساب الوقت التقريبي للوصول (ETA) ---
        // سنقوم باستدعاء OpenRouteService من هنا لحساب الوقت المتبقي من موقع السائق الحالي إلى موقع الزبون
        
        $api_key = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImViYzQxYzEyYjM0MzQxYzViODYxM2Q1YjNlMWRkZjI4IiwiaCI6Im11cm11cjY0In0=';
        $url = 'https://api.openrouteservice.org/v2/directions/driving-car/json';

        $coordinates = [
            [$order['driver_lon'], $order['driver_lat']],      // موقع السائق الحالي
            [$order['customer_longitude'], $order['customer_latitude']] // موقع الزبون
        ];
        
        $post_data = json_encode(['coordinates' => $coordinates]);
        $headers = ['Authorization: ' . $api_key, 'Content-Type: application/json'];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $data = json_decode($api_response, true);
            if (isset($data['routes'][0]['summary']['duration'])) {
                $duration_in_seconds = (float) $data['routes'][0]['summary']['duration'];
                
                // حساب الدقائق بشكل صحيح
                $eta_minutes = ceil($duration_in_seconds / 60);
                
                // --- تنسيق النص بشكل احترافي ---
                if ($eta_minutes <= 1) {
                    $response_data['eta'] = "أقل من دقيقة";
                } elseif ($eta_minutes < 60) {
                    $response_data['eta'] = "~ " . $eta_minutes . " دقيقة";
                } else {
                    // إذا كان الوقت أكثر من ساعة
                    $hours = floor($eta_minutes / 60);
                    $minutes = $eta_minutes % 60;
                    $response_data['eta'] = "~ " . $hours . " ساعة و " . $minutes . " دقيقة";
                }

            } else {
                $response_data['eta'] = null; // لم يتم العثور على المدة
            }
        }
    }
    
    // تنظيف أي مخرجات قديمة وإرسال الاستجابة النهائية
    ob_end_clean();
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500); // Internal Server Error
    error_log("Get Order Status Error: " . $e->getMessage()); // تسجيل الخطأ للمطور
    echo json_encode(['error' => 'حدث خطأ في قاعدة البيانات.']);
}
exit;
?>