<?php
// ========================================================================
// Syriazzle Admin API - جلب تفاصيل مراجعة الحجز (النسخة النهائية 2.0)
// ========================================================================

// **الإصلاح الجوهري هنا:** استدعاء حارس البوابة من المجلد الأعلى
require_once '../auth_guard.php';

// --- إعدادات الأمان الأساسية ---
header('Content-Type: application/json; charset=UTF-8');

// --- حارس الصلاحيات: يجب أن يملك صلاحية عرض الحجوزات على الأقل ---
if (!hasPermission('view_bookings')) {
    echo json_encode(['success' => false, 'message' => 'وصول مرفوض.']);
    exit;
}

// --- استقبال البيانات ---
$request_data = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($request_data['booking_id']) ? (int)$request_data['booking_id'] : 0;
$action = $request_data['action'] ?? 'view'; // الافتراضي هو العرض

if (empty($booking_id)) {
    echo json_encode(['success' => false, 'message' => 'رقم الحجز غير صحيح.']);
    exit;
}

try {
    // --- الاستعلام النهائي والمُصحح لجلب كل التفاصيل اللازمة ---
    $stmt = $pdo->prepare("
        SELECT 
            b.deposit_amount, 
            b.cancellation_reason, 
            b.payment_method,
            b.start_datetime,
            b.payment_status,
            u.username as user_name, 
            u.phone as user_phone,
            biz.name as business_name,
            s.name as service_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN business_services s ON b.service_id = s.id
        LEFT JOIN businesses biz ON s.business_id = biz.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$details) {
        echo json_encode(['success' => false, 'message' => 'لم يتم العثور على الحجز.']);
        exit;
    }

    // --- بناء كود HTML الذي سيتم عرضه في النافذة المنبثقة ---
    $html = '';
    
    // بناء الأجزاء المشتركة
    $html .= "<style>
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .detail-item { background: #f9fafb; padding: 10px; border-radius: 6px; display: flex; flex-direction: column; }
        .detail-item span { color: #7f8c8d; font-size: 0.9rem; }
        .detail-item strong { font-size: 1.1rem; color: var(--dark-color); }
        .detail-item .price { color: var(--success-color); font-weight: bold; }
        .payment-proof-box { margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem; }
        .payment-proof-box h4 { margin-bottom: 0.5rem; }
        .proof-code { background: #e0e0e0; padding: 1rem; border-radius: 6px; font-size: 1.5rem; font-weight: bold; text-align: center; letter-spacing: 2px; }
    </style>";

    // بناء المحتوى بناءً على الإجراء المطلوب
    if ($action === 'review') {
        $payment_proof = str_replace("إثبات الدفع من المستخدم: ", "", $details['cancellation_reason']);

        $html .= "<div class='details-grid'>";
        $html .= "<div class='detail-item'><span>الزبون:</span><strong>" . htmlspecialchars($details['user_name'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>هاتف الزبون:</span><strong>" . htmlspecialchars($details['user_phone'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>النشاط التجاري:</span><strong>" . htmlspecialchars($details['business_name'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>الخدمة:</span><strong>" . htmlspecialchars($details['service_name'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>قيمة العربون:</span><strong class='price'>" . number_format($details['deposit_amount']) . " ل.س</strong></div>";
        $html .= "<div class='detail-item'><span>طريقة الدفع:</span><strong>" . htmlspecialchars($details['payment_method']) . "</strong></div>";
        $html .= "</div>";
        $html .= "<div class='payment-proof-box'>";
        $html .= "<h4><i class='fas fa-receipt'></i> إثبات الدفع المقدم من الزبون</h4>";
        $html .= "<div class='proof-code'>" . htmlspecialchars($payment_proof) . "</div>";
        $html .= "</div>";
    } else { // action === 'view'
        $html .= "<div class='details-grid'>";
        $html .= "<div class='detail-item'><span>الزبون:</span><strong>" . htmlspecialchars($details['user_name'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>هاتف الزبون:</span><strong>" . htmlspecialchars($details['user_phone'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>النشاط التجاري:</span><strong>" . htmlspecialchars($details['business_name'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>الخدمة:</span><strong>" . htmlspecialchars($details['service_name'] ?? 'غير متوفر') . "</strong></div>";
        $html .= "<div class='detail-item'><span>موعد الحجز:</span><strong>" . date('Y-m-d H:i', strtotime($details['start_datetime'])) . "</strong></div>";
        $html .= "<div class='detail-item'><span>حالة الدفع:</span><strong>" . htmlspecialchars($details['payment_status']) . "</strong></div>";
        $html .= "</div>";
    }

    // إرسال الرد بنجاح
    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    // تسجيل الخطأ للمطور وإرسال رسالة عامة للمستخدم
    error_log("AJAX Get Booking Details Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات.']);
}
?>