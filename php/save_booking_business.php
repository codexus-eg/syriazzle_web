<?php
// ========================================================================
// Syriazzle Bookings - محرك حفظ نشاط الحجوزات (النسخة 2.0 - مع تجميد الإعدادات والموافقة الإدارية)
// ========================================================================
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

// --- تنقية واستقبال البيانات ---
$booking_category = $_POST['booking_category'] ?? null;
$name = trim($_POST['name'] ?? '');
$governorate_id = (int)($_POST['governorate_id'] ?? 0);
$city = trim($_POST['city'] ?? '');
$description = trim($_POST['description'] ?? '');
$business_type = $_POST['business_type'] ?? 'booking'; // Default to booking
$is_booking_enabled = isset($_POST['is_booking_enabled']) ? 1 : 0;

// --- التحقق من صحة البيانات ---
if (empty($booking_category) || empty($name) || empty($governorate_id) || empty($city)) {
    die("خطأ: الرجاء ملء جميع الحقول الإلزامية.");
}

// --- معالجة رفع الصور ---
$upload_dir = '../uploads/businesses/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function upload_image($file_key, $upload_dir) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_key]['tmp_name'];
        $file_name = $_FILES[$file_key]['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = 'business_' . uniqid() . rand(1000, 9999) . '.' . $file_extension;
            $dest_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                return 'uploads/businesses/' . $new_file_name;
            }
        }
    }
    return null;
}

$logo_path = upload_image('logo_image', $upload_dir);
$cover_path = upload_image('cover_image', $upload_dir);

// --- حفظ البيانات في قاعدة البيانات ---
try {
    // **الخطوة الجديدة: جلب الإعدادات المالية الافتراضية من قاعدة البيانات**
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $default_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $booking_comm_rate = (float)($default_settings['booking_commission_rate'] ?? 0.0);
    $booking_cred_limit = (float)($default_settings['booking_credit_limit'] ?? 0.0);

    // نستخدم فئة الحجز كـ category أيضًا للتبسيط والتوافق
    $category_for_db = ucfirst($booking_category);

    // **الاستعلام المطور: الحالة الافتراضية هي "pending" وتمرير الإعدادات المجمدة**
    $sql = "INSERT INTO businesses 
                (user_id, name, category, governorate_id, city, description, logo_image, cover_image, 
                 status, business_type, is_booking_enabled, booking_category,
                 booking_commission_rate, booking_credit_limit, created_at) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $current_user_id,
        $name,
        $category_for_db,
        $governorate_id,
        $city,
        $description,
        $logo_path,
        $cover_path,
        $business_type,
        $is_booking_enabled,
        $booking_category,
        $booking_comm_rate,    // تجميد عمولة الحجز
        $booking_cred_limit    // تجميد الحد الائتماني للحجز
    ]);

    $new_business_id = $pdo->lastInsertId();
    
    // ملاحظة: يمكنك هنا إضافة كود لإنشاء إشعار للأدمن في جدول admin_notifications إذا أردت.
    // مثال:
    // $notify_stmt = $pdo->prepare("INSERT INTO admin_notifications (message, link) VALUES (?, ?)");
    // $notify_stmt->execute(["نشاط حجز جديد '{$name}' بانتظار المراجعة.", "dashboard.php"]);


    // --- إعادة التوجيه إلى صفحة نجاح تخبر المستخدم بأن طلبه قيد المراجعة ---
    // هذا أفضل من توجيهه مباشرة إلى لوحة التحكم التي قد لا تعمل بالكامل قبل الموافقة
    header("Location: ../booking_pending_approval.php"); // اسم مقترح لصفحة جديدة
    exit;

} catch (PDOException $e) {
    error_log("Save Booking Business Error: " . $e->getMessage());
    die("حدث خطأ فني أثناء إنشاء نشاطك التجاري. الخطأ: " . $e->getMessage());
}
?>