<?php
// ========================================================================
// Syriazzle Bookings - Settings Management API (النسخة النهائية الكاملة والمصححة)
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

// دالة موحدة لإرسال الرد
function send_json_response($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit;
}

// --- Layer 1: Security & Auth ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'طريقة الطلب غير مسموح بها.');
}
if (!isset($_SESSION['user_id'])) {
    send_json_response(false, 'جلسة المستخدم غير صالحة.');
}

$current_user_id = (int)$_SESSION['user_id'];
// بما أننا نتعامل مع FormData، نستخدم $_POST
$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;

// --- Layer 2: Ownership Verification ---
try {
    // نجلب الصور الحالية هنا لاستخدامها لاحقاً في عملية الحذف
    $stmt_check = $pdo->prepare("SELECT user_id, logo_image, cover_image FROM businesses WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$business_id]);
    $current_business = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$current_business || $current_business['user_id'] !== $current_user_id) {
        send_json_response(false, 'وصول غير مصرح به.');
    }
} catch (PDOException $e) {
    error_log("Booking settings auth error: " . $e->getMessage());
    send_json_response(false, 'خطأ في التحقق من الصلاحيات.');
}

// --- Layer 3: Data Sanitization & Validation ---
$booking_category = $_POST['booking_category'] ?? null;
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
$whatsapp = preg_replace('/[^0-9+]/', '', $_POST['whatsapp'] ?? '');
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$payment_details_raw = $_POST['payment_details'] ?? [];
$delete_gallery_ids = $_POST['delete_gallery_ids'] ?? [];

if (empty($name) || empty($booking_category)) {
    send_json_response(false, 'اسم النشاط التجاري وفئة الحجز هي حقول إلزامية.');
}
$allowed_categories = ['hotel', 'restaurant', 'clinic', 'consulting', 'tourism', 'event'];
if (!in_array($booking_category, $allowed_categories)) {
    send_json_response(false, 'فئة النشاط المحددة غير صالحة.');
}

$payment_details = [
    'syriatel_cash' => preg_replace('/[^0-9]/', '', $payment_details_raw['syriatel_cash'] ?? ''),
    'mtn_cash' => preg_replace('/[^0-9]/', '', $payment_details_raw['mtn_cash'] ?? ''),
    'sham_cash' => preg_replace('/[^0-9]/', '', $payment_details_raw['sham_cash'] ?? '')
];
$payment_details_json = json_encode($payment_details);

// --- Layer 4: Image Handling Logic ---
$upload_dir = '../uploads/businesses/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function handle_image_upload($file_key, $current_image, $delete_flag_key, $upload_dir) {
    // حالة الحذف من الواجهة
    if (!empty($_POST[$delete_flag_key]) && $_POST[$delete_flag_key] == '1') {
        if ($current_image && file_exists('../' . $current_image)) {
            @unlink('../' . $current_image); // @ لإخفاء الأخطاء إذا كان الملف غير موجود
        }
        return ''; // إرجاع مسار فارغ ليتم حفظه
    }
    // حالة وجود ملف جديد مرفوع
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        // حذف الصورة القديمة قبل رفع الجديدة
        if ($current_image && file_exists('../' . $current_image)) {
            @unlink('../' . $current_image);
        }
        $file_tmp_path = $_FILES[$file_key]['tmp_name'];
        $file_name = $_FILES[$file_key]['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = 'business_' . uniqid() . rand(1000,9999) . '.' . $file_extension;
            $dest_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                return 'uploads/businesses/' . $new_file_name;
            }
        }
    }
    // إذا لم يحدث أي تغيير، أعد المسار الحالي
    return $current_image;
}

$new_logo_path = handle_image_upload('logo_image', $current_business['logo_image'], 'delete_logo_image', $upload_dir);
$new_cover_path = handle_image_upload('cover_image', $current_business['cover_image'], 'delete_cover_image', $upload_dir);

$pdo->beginTransaction();
try {
    // --- Layer 5.1: Gallery Image Management ---
    if (!empty($delete_gallery_ids)) {
        // نستخدم IN() لحذف كل المعرفات دفعة واحدة بشكل آمن
        $placeholders = implode(',', array_fill(0, count($delete_gallery_ids), '?'));
        // جلب مسارات الصور المراد حذفها أولاً
        $stmt_get_paths = $pdo->prepare("SELECT image_path FROM business_gallery WHERE id IN ($placeholders) AND business_id = ?");
        $stmt_get_paths->execute(array_merge($delete_gallery_ids, [$business_id]));
        $paths_to_delete = $stmt_get_paths->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($paths_to_delete as $path) {
            if ($path && file_exists('../' . $path)) {
                @unlink('../' . $path);
            }
        }
        
        // الآن حذف السجلات من قاعدة البيانات
        $stmt_delete_img = $pdo->prepare("DELETE FROM business_gallery WHERE id IN ($placeholders) AND business_id = ?");
        $stmt_delete_img->execute(array_merge($delete_gallery_ids, [$business_id]));
    }
    
    // معالجة الصور الجديدة المرفوعة للمعرض
    if (isset($_FILES['gallery_images'])) {
        $gallery_files = $_FILES['gallery_images'];
        $stmt_insert_gallery = $pdo->prepare("INSERT INTO business_gallery (business_id, image_path) VALUES (?, ?)");
        foreach ($gallery_files['tmp_name'] as $key => $tmp_name) {
            if ($gallery_files['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $gallery_files['name'][$key];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_file_name = 'gallery_' . uniqid() . rand(1000, 9999) . '.' . $file_extension;
                $dest_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($tmp_name, $dest_path)) {
                    $stmt_insert_gallery->execute([$business_id, 'uploads/businesses/' . $new_file_name]);
                }
            }
        }
    }
    
    // --- Layer 5.2: Main Business Data Update ---
    $sql = "UPDATE businesses SET 
                name = ?, description = ?, phone = ?, whatsapp = ?, 
                latitude = ?, longitude = ?, booking_category = ?, 
                payment_details = ?, logo_image = ?, cover_image = ?
            WHERE id = ?";
    $params = [ $name, $description, $phone, $whatsapp, $latitude, $longitude,
                $booking_category, $payment_details_json, $new_logo_path, $new_cover_path, $business_id ];
    $stmt_update_main = $pdo->prepare($sql);
    $stmt_update_main->execute($params);

    $pdo->commit();

    // ================== التصحيح الثاني (مهم جداً) ==================
    // تم تغيير طريقة جلب البيانات بعد الحفظ لتكون آمنة ومستقرة.
    // بدلاً من بناء JSON يدوياً، نقوم بجلب البيانات بشكل نظيف من قاعدة البيانات.
    
    // 1. جلب البيانات الأساسية المحدثة للنشاط
    $stmt_new_data = $pdo->prepare("
        SELECT id, user_id, name, description, phone, whatsapp, logo_image, cover_image, 
               latitude, longitude, payment_details, booking_category
        FROM businesses 
        WHERE id = ?
    ");
    $stmt_new_data->execute([$business_id]);
    $updated_business_details = $stmt_new_data->fetch(PDO::FETCH_ASSOC);

    // 2. جلب صور المعرض المحدثة
    $stmt_gallery = $pdo->prepare("SELECT id, image_path FROM business_gallery WHERE business_id = ? ORDER BY id ASC");
    $stmt_gallery->execute([$business_id]);
    $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);

    // 3. دمج صور المعرض مع بيانات النشاط
    $updated_business_details['gallery_images'] = $gallery_images;

    send_json_response(true, 'تم حفظ الإعدادات بنجاح!', ['businessDetails' => $updated_business_details]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // إضافة تفاصيل الخطأ إلى سجل الخادم للمساعدة في تصحيح الأخطاء المستقبلية
    error_log("Booking settings save error on line " . $e->getLine() . ": " . $e->getMessage());
    send_json_response(false, 'حدث خطأ فني أثناء حفظ الإعدادات.');
}
?>