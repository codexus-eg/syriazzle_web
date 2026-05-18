<?php
// ========================================================================
// Syriazzle - API: جلب تفاصيل حجز محدد (النسخة 2.0 - تدعم الأصول)
// ========================================================================
require_once 'db_connect.php';

// --- إعدادات الأمان الأساسية ---
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$request_data = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($request_data['booking_id']) ? (int)$request_data['booking_id'] : 0;

if (empty($booking_id)) {
    echo json_encode(['success' => false, 'message' => 'لم يتم تحديد رقم الحجز.']);
    exit;
}

try {
    // --- الاستعلام المطور لجلب كل التفاصيل بشكل صحيح ---
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            biz.name as business_name, 
            biz.phone as business_phone, 
            biz.whatsapp as business_whatsapp,
            biz.city, 
            biz.latitude, 
            biz.longitude,
            -- استخدام COALESCE لاختيار اسم الأصل أولاً، ثم اسم الخدمة
            COALESCE(r.name, s.name) as item_name,
            s.description as service_description
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        -- استخدام LEFT JOIN هنا لأن الحجوزات القائمة على المواعيد ليس لها أصل
        LEFT JOIN business_resources r ON b.resource_id = r.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $current_user_id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$details) {
        echo json_encode(['success' => false, 'message' => 'الحجز غير موجود أو لا تملك صلاحية لعرضه.']);
        exit;
    }

    // --- بناء كود HTML الذي سيتم عرضه في النافذة المنبثقة ---
    $start_datetime_obj = new DateTime($details['start_datetime']);
    $end_datetime_obj = new DateTime($details['end_datetime']);

    $html = '<div class="booking-details-list">';
    $html .= '<div class="detail-row"><span>النشاط التجاري:</span><strong>' . htmlspecialchars($details['business_name']) . '</strong></div>';
    
    // **التعديل هنا: استخدام item_name**
    $item_label = $details['resource_id'] ? 'الغرفة/الطاولة:' : 'الخدمة:';
    $html .= '<div class="detail-row"><span>' . $item_label . '</span><strong>' . htmlspecialchars($details['item_name']) . '</strong></div>';
    
    // **التعديل هنا: عرض التواريخ والأوقات بشكل كامل**
    $html .= '<div class="detail-row"><span>موعد الوصول:</span><strong>' . $start_datetime_obj->format('Y-m-d \ا\ل\س\ا\ع\ة h:i A') . '</strong></div>';
    
    // عرض موعد المغادرة فقط إذا كان مختلفًا عن موعد الوصول (للفنادق وليس للعيادات)
    if ($start_datetime_obj->format('Y-m-d') !== $end_datetime_obj->format('Y-m-d')) {
        $html .= '<div class="detail-row"><span>موعد المغادرة:</span><strong>' . $end_datetime_obj->format('Y-m-d \ا\ل\س\ا\ع\ة h:i A') . '</strong></div>';
    }

    $html .= '<div class="detail-row"><span>الإجمالي:</span><strong class="price">' . number_format($details['total_price']) . ' ل.س</strong></div>';
    if ($details['deposit_amount'] > 0) {
        $html .= '<div class="detail-row"><span>العربون المدفوع:</span><strong>' . number_format($details['deposit_amount']) . ' ل.س</strong></div>';
    }
    $html .= '<div class="detail-row"><span>حالة الدفع:</span><strong>' . htmlspecialchars($details['payment_status']) . '</strong></div>';
    if (!empty($details['cancellation_reason'])) {
         $html .= '<div class="detail-row"><span>ملاحظات:</span><strong>' . htmlspecialchars($details['cancellation_reason']) . '</strong></div>';
    }
    $html .= '</div>';

    // إرسال الرد بنجاح
    echo json_encode(['success' => true, 'details' => $details, 'html' => $html]);

} catch (PDOException $e) {
    error_log("Get Booking Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ فني أثناء جلب التفاصيل.']);
}
?>