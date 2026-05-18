<?php
// ========================================================================
// Syriazzle - High-Speed Live Tracking API (V9.5 - Production Ready)
// ========================================================================

require_once __DIR__ . '/db_connect.php';

// ضبط الترويسة لرد JSON نظيف وسريع
header('Content-Type: application/json; charset=utf-8');

// 1. التحقق الأمني: هل المستخدم مسجل دخول؟
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح للوصول، يرجى تسجيل الدخول.']);
    exit;
}

// 2. التحقق من وجود رقم الطلب في الرابط
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($order_id === 0) {
    echo json_encode(['error' => 'رقم الطلب غير صحيح.']);
    exit;
}

try {
    // 3. استعلام جلب البيانات الموحد (الطلب + السائق)
    // نتحقق من أن الطلب يخص المستخدم الحالي لضمان الخصوصية
    $sql = "
        SELECT 
            o.status as order_status,
            d.current_latitude as lat, 
            d.current_longitude as lng, 
            d.last_seen,
            d.full_name as driver_name,
            d.phone as driver_phone
        FROM orders o
        LEFT JOIN drivers d ON o.driver_id = d.id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id, $user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. معالجة النتائج
    if (!$data) {
        echo json_encode(['error' => 'الطلب غير موجود أو لا تملك صلاحية تتبعه.']);
        exit;
    }

    // حساب حالة الاتصال (Heartbeat Logic)
    // إذا لم يقم السائق بتحديث موقعه منذ أكثر من 60 ثانية، نعتبره "ضعيف الإشارة"
    $is_offline = true;
    $last_seen_formatted = null;

    if (!empty($data['last_seen'])) {
        $last_seen_time = strtotime($data['last_seen']);
        $diff_seconds = time() - $last_seen_time;
        
        if ($diff_seconds <= 60) {
            $is_offline = false;
        }
        $last_seen_formatted = date('H:i', $last_seen_time);
    }

    // 5. الرد النهائي بالبيانات اللحظية
    // نستخدم JSON_NUMERIC_CHECK لضمان إرسال الإحداثيات كأرقام (Float) وليس نصوص
    echo json_encode([
        'status' => $data['order_status'],
        'lat' => $data['lat'] ? (float)$data['lat'] : null,
        'lng' => $data['lng'] ? (float)$data['lng'] : null,
        'is_offline' => $is_offline,
        'last_seen' => $last_seen_formatted,
        'driver_info' => [
            'name' => $data['driver_name'],
            'phone' => $data['driver_phone']
        ],
        'server_timestamp' => time()
    ], JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    // تسجيل الخطأ في السيرفر فقط للأمان
    error_log("Tracking API DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في خادم البيانات.']);
}

exit;