<?php
// ========================================================================
// Syriazzle Bookings - محرك معالجة الحجز (النسخة 5.0 - تدعم الأصول)
// ========================================================================
require_once 'db_connect.php';
require_once 'config.php';

// --- حارس الأمان: التحقق من تسجيل الدخول والطلب ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

// --- فك تشفير وتنقية البيانات ---
$booking_data = json_decode($_POST['booking_data_json'] ?? '', true);
$payment_method = $_POST['payment_method'] ?? '';

if (json_last_error() !== JSON_ERROR_NONE || !isset($booking_data['service_id'], $booking_data['start_datetime'], $booking_data['total_price'])) {
    $_SESSION['booking_error_message'] = "بيانات الحجز المرسلة تالفة أو غير مكتملة.";
    header("Location: ../booking_error.php");
    exit;
}

$pdo->beginTransaction();
try {
    // --- الخطوة 1: جلب البيانات النهائية من قاعدة البيانات ---
    // تم تعديل الاستعلام ليشمل booking_category
    $stmt = $pdo->prepare("
        SELECT s.*, b.commission_rate, b.id as owner_id, b.booking_category 
        FROM business_services s 
        JOIN businesses b ON s.business_id = b.id 
        WHERE s.id = ? AND s.is_active = 1 FOR UPDATE
    ");
    $stmt->execute([(int)$booking_data['service_id']]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) { throw new Exception("عذرًا، هذه الخدمة لم تعد متاحة للحجز."); }

    // --- الخطوة 2: إعادة حساب السعر في الخلفية (Anti-Tampering) ---
    $server_calculated_price = 0;
    if (isset($booking_data['details']['nights'])) {
        // التحقق من صحة التواريخ
        $start_date = new DateTime($booking_data['start_datetime']);
        $end_date = new DateTime($booking_data['end_datetime']);
        if ($end_date <= $start_date) {
            throw new Exception("تاريخ المغادرة يجب أن يكون بعد تاريخ الوصول.");
        }
        $nights = $start_date->diff($end_date)->days;
        if ($nights <= 0) $nights = 1; // على الأقل ليلة واحدة
        $server_calculated_price = (float)$service['price'] * $nights;
    } else {
        $server_calculated_price = (float)$service['price'];
    }
    
    if (abs($server_calculated_price - (float)$booking_data['total_price']) > 0.01) {
        throw new Exception("خطأ أمني: تم اكتشاف محاولة تلاعب في السعر.");
    }

    // --- الخطوة 3: التحقق النهائي من التوافر (المنطق المطور) ---
    $start_datetime = $booking_data['start_datetime'];
    $end_datetime = $booking_data['end_datetime'];
    
    // **المنطق الجديد هنا**
    if (in_array($service['booking_category'], ['hotel', 'restaurant', 'event'])) {
        // التحقق بناءً على الأصل (resource_id)
        $resource_id = $booking_data['resource_id'] ?? null;
        if (empty($resource_id)) { throw new Exception("خطأ: لم يتم تحديد الأصل (الغرفة/الطاولة) للحجز."); }

        $check_availability_stmt = $pdo->prepare(
            "SELECT COUNT(id) FROM bookings 
             WHERE resource_id = ? 
             AND status IN ('confirmed', 'pending_confirmation', 'pending_payment') 
             AND (start_datetime < ? AND end_datetime > ?)"
        );
        $check_availability_stmt->execute([$resource_id, $end_datetime, $start_datetime]);
        $conflicting_bookings_count = $check_availability_stmt->fetchColumn();

        if ($conflicting_bookings_count > 0) {
            throw new Exception("عذرًا، هذا الخيار (الغرفة/الطاولة) تم حجزه للتو. يرجى اختيار خيار آخر.");
        }
    } else {
        // التحقق بناءً على الخدمة (service_id) - المنطق القديم للعيادات
        $check_availability_stmt = $pdo->prepare(
            "SELECT COUNT(id) FROM bookings 
             WHERE service_id = ? 
             AND status IN ('confirmed', 'pending_confirmation', 'pending_payment') 
             AND (start_datetime < ? AND end_datetime > ?)"
        );
        $check_availability_stmt->execute([$service['id'], $end_datetime, $start_datetime]);
        $conflicting_bookings_count = $check_availability_stmt->fetchColumn();
        
        // نفترض أن كمية الخدمات المتزامنة هي 1
        if ($conflicting_bookings_count >= 1) {
            throw new Exception("عذرًا، هذا الموعد تم حجزه للتو. يرجى اختيار موعد آخر.");
        }
    }

    // --- الخطوة 4: حساب تفاصيل الدفع والعمولة ---
    $deposit_percentage = (int)$service['deposit_required_percentage'];
    $is_deposit_required = $deposit_percentage > 0 && $server_calculated_price > 0;
    $deposit_amount = $is_deposit_required ? ceil(($server_calculated_price * $deposit_percentage) / 100) : 0;
    
    $booking_category = $service['booking_category'];

    $commission_rate_to_use = (in_array($booking_category, ['hotel', 'restaurant', 'event', 'clinic', 'consulting', 'tourism']))
                               ? $service['booking_commission_rate'] 
                               : $service['commission_rate'];

    $platform_commission_rate = (float)$commission_rate_to_use;
    $platform_commission_amount = round(($server_calculated_price * $platform_commission_rate) / 100);

    $initial_status = $is_deposit_required ? 'pending_payment' : 'confirmed';
    $initial_payment_status = $is_deposit_required ? 'pending' : 'not_required';

    $resource_id_to_save = $booking_data['resource_id'] ?? null;
    $stmt_insert = $pdo->prepare(
        "INSERT INTO bookings (service_id, user_id, resource_id, start_datetime, end_datetime, status, total_price, deposit_amount, payment_status, payment_method, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt_insert->execute([
        $service['id'], $current_user_id, $resource_id_to_save, $start_datetime, $end_datetime, 
        $initial_status, $server_calculated_price, $deposit_amount, $initial_payment_status, $payment_method
    ]);
    $booking_id = $pdo->lastInsertId();

    // --- الخطوة 6: التكامل المالي الفوري (للنموذج الجديد) ---
    if (!$is_deposit_required) {
        $trans_stmt = $pdo->prepare("INSERT INTO transactions (order_id, user_id, user_type, transaction_type, amount, description) VALUES (?, ?, 'business', 'commission', ?, ?)");
        $trans_stmt->execute([$booking_id, $service['owner_id'], -$platform_commission_amount, "عمولة المنصة على الحجز المؤكد رقم #{$booking_id}"]);
        
        $update_balance_stmt = $pdo->prepare("UPDATE businesses SET commission_balance = commission_balance - ? WHERE id = ?");
        $update_balance_stmt->execute([$platform_commission_amount, $service['owner_id']]);
    }
    
    $pdo->commit();
    
    // --- الخطوة 7: إعادة التوجيه إلى الصفحة المناسبة ---
    if ($is_deposit_required) {
        header("Location: ../payment_confirmation.php?booking_id=$booking_id");
    } else {
        header("Location: ../booking_success.php?booking_id=$booking_id&status=confirmed");
    }
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Process Booking Error: " . $e->getMessage());
    $_SESSION['booking_error_message'] = $e->getMessage();
    header("Location: ../booking_error.php");
    exit;
}
?>