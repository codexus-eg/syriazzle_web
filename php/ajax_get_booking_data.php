<?php
// ========================================================================
// Syriazzle - Data API (النسخة 4.1 - النهائية والمحصنة)
// هذا الملف هو المصدر الوحيد والآمن لجلب كل بيانات لوحة التحكم
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

// دالة موحدة لإرسال الرد بشكل آمن
function send_json_response($success, $message, $data = null) {
    // التأكد من عدم وجود أخطاء في الـ output buffer قبل إرسال JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // ضبط الهيدر مرة أخرى قبل الإرسال مباشرة
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Layer 1: Security & Request Setup ---
$request_data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'طريقة الطلب غير مسموح بها.');
}
if (!isset($_SESSION['user_id'])) {
    send_json_response(false, 'جلسة المستخدم غير صالحة. يرجى تسجيل الدخول مرة أخرى.');
}

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($request_data['business_id']) ? (int)$request_data['business_id'] : 0;

if (empty($business_id)) {
    send_json_response(false, 'لم يتم تحديد النشاط التجاري.');
}

// --- Layer 2: Ownership Verification ---
try {
    $stmt_check = $pdo->prepare("SELECT * FROM businesses WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$business_id, $current_user_id]);
    $business_details = $stmt_check->fetch(PDO::FETCH_ASSOC);
    if (!$business_details) {
        send_json_response(false, 'وصول غير مصرح به أو النشاط التجاري غير موجود.');
    }
} catch (PDOException $e) {
    error_log("Booking data auth error: " . $e->getMessage());
    send_json_response(false, 'خطأ في التحقق من الصلاحيات.');
}

// --- Layer 3: Data Fetching Logic ---
$request_type = isset($request_data['type']) ? trim($request_data['type']) : '';
try {
    switch ($request_type) {
        
        case 'initial':
            // 1. جلب صور المعرض
            $stmt_gallery = $pdo->prepare("SELECT id, image_path FROM business_gallery WHERE business_id = ? ORDER BY id ASC");
            $stmt_gallery->execute([$business_id]);
            $business_details['gallery_images'] = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);

            // 2. جلب الخدمات
            $stmt_services = $pdo->prepare("SELECT * FROM business_services WHERE business_id = ? ORDER BY created_at DESC");
            $stmt_services->execute([$business_id]);
            $services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. جلب الحجوزات للتقويم (استعلام مصحح)
            $stmt_bookings = $pdo->prepare("
                SELECT 
                    b.id,
                    bs.name as `title`,
                    b.start_datetime as `start`,
                    b.end_datetime as `end`,
                    b.status
                FROM bookings b
                JOIN business_services bs ON b.service_id = bs.id
                WHERE bs.business_id = ?
            ");
            $stmt_bookings->execute([$business_id]);
            $bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

            $initial_data = [
                'businessDetails' => $business_details,
                'services' => $services,
                'bookings' => $bookings
            ];
            
            send_json_response(true, 'تم جلب البيانات الأولية بنجاح.', $initial_data);
            break;

        case 'overview_stats':
            // الاستعلام عن الحجوزات المؤكدة هذا الشهر (استعلام مصحح)
            $stmt1 = $pdo->prepare("
                SELECT COUNT(b.id) 
                FROM bookings b
                JOIN business_services bs ON b.service_id = bs.id
                WHERE bs.business_id = ? 
                AND b.status = 'confirmed' 
                AND MONTH(b.start_datetime) = MONTH(CURDATE()) 
                AND YEAR(b.start_datetime) = YEAR(CURDATE())
            ");
            $stmt1->execute([$business_id]);
            $confirmed_this_month = $stmt1->fetchColumn();

            // الاستعلام عن إيرادات هذا الشهر (استعلام مصحح)
            $stmt2 = $pdo->prepare("
                SELECT SUM(b.total_price) 
                FROM bookings b
                JOIN business_services bs ON b.service_id = bs.id
                WHERE bs.business_id = ? 
                AND b.status = 'confirmed' 
                AND MONTH(b.start_datetime) = MONTH(CURDATE()) 
                AND YEAR(b.start_datetime) = YEAR(CURDATE())
            ");
            $stmt2->execute([$business_id]);
            $revenue_this_month = $stmt2->fetchColumn() ?: 0;
            
            // الاستعلام عن العملاء الجدد هذا الشهر (صحيح)
            $stmt3 = $pdo->prepare("SELECT COUNT(id) FROM business_customers WHERE business_id = ? AND MONTH(first_booking_date) = MONTH(CURDATE()) AND YEAR(first_booking_date) = YEAR(CURDATE())");
            $stmt3->execute([$business_id]);
            $new_customers_this_month = $stmt3->fetchColumn();
            
            // الاستعلام عن أحدث الحجوزات (صحيح)
            $stmt4 = $pdo->prepare("SELECT b.id, u.username, bs.name as service_name, b.start_datetime, b.status FROM bookings b JOIN users u ON b.user_id = u.id JOIN business_services bs ON b.service_id = bs.id WHERE bs.business_id = ? ORDER BY b.created_at DESC LIMIT 5");
            $stmt4->execute([$business_id]);
            $recent_bookings = $stmt4->fetchAll(PDO::FETCH_ASSOC);

            $stats_data = [
                'confirmed_this_month' => $confirmed_this_month,
                'revenue_this_month' => number_format($revenue_this_month, 0),
                'new_customers_this_month' => $new_customers_this_month,
                'recent_bookings' => $recent_bookings
            ];
            send_json_response(true, 'تم جلب الإحصائيات بنجاح.', $stats_data);
            break;

        case 'resources':
            $stmt = $pdo->prepare("SELECT * FROM business_resources WHERE business_id = ? ORDER BY name ASC");
            $stmt->execute([$business_id]);
            $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_json_response(true, 'تم جلب الموارد بنجاح.', $resources);
            break;

        case 'customers':
            $stmt = $pdo->prepare("SELECT c.*, u.username, u.phone FROM business_customers c JOIN users u ON c.user_id = u.id WHERE c.business_id = ? ORDER BY c.total_bookings_count DESC");
            $stmt->execute([$business_id]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_json_response(true, 'تم جلب العملاء بنجاح.', $customers);
            break;

        case 'services':
            $stmt = $pdo->prepare("SELECT * FROM business_services WHERE business_id = ? ORDER BY created_at DESC");
            $stmt->execute([$business_id]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_json_response(true, 'تم جلب الخدمات بنجاح.', $services);
            break;
        
        default:
            send_json_response(false, 'نوع الطلب غير معروف: ' . htmlspecialchars($request_type));
            break;
    }
} catch (PDOException $e) {
    // تسجيل الخطأ بشكل مفصل في سجلات الخادم
    error_log("Data fetching error (type: {$request_type}, business: {$business_id}): " . $e->getMessage());
    send_json_response(false, 'حدث خطأ فني أثناء جلب البيانات.');
}
?>