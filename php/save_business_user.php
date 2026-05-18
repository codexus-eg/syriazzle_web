<?php
require_once 'db_connect.php';

// --- 1. تحديد نوع الإجراء والصلاحيات ---
$is_admin_action = isset($_POST['is_admin_action']) && $_POST['is_admin_action'] == '1';
$user_id = null;
$status = 'pending';

if ($is_admin_action) {
    require_once '../admin/auth_guard.php'; 
    if (!hasPermission('add_business')) {
        die("خطأ: ليس لديك صلاحية إضافة المتاجر.");
    }
    
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
    } else {
        die("خطأ: يجب تحديد صاحب المتجر.");
    }

    $status = 'approved'; 
} else {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $status = 'pending';
}

// --- 2. التحقق من CSRF Token ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}
unset($_SESSION['csrf_token']);

// --- 3. دالة معالجة رفع الصور (النسخة الآمنة التي تعمل بدون fileinfo) ---
function handleImageUpload($file, $uploadDir) {
    // التحقق الأساسي من وجود خطأ
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxFileSize = 10 * 1024 * 1024; // 10 MB
    if ($file['size'] > $maxFileSize) {
        throw new Exception('حجم الملف كبير جدًا (الحد الأقصى 10 ميجابايت).');
    }

    // --- التعديل هنا: الفحص عبر الامتداد فقط لتجنب خطأ السيرفر ---
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('نوع الملف غير مسموح به (فقط صور).');
    }
    // -------------------------------------------------------------

    $newFileName = uniqid('business_', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('فشل نقل الملف المرفوع. تأكد من صلاحيات المجلد.');
    }

    return 'uploads/businesses/' . $newFileName;
}

try {
    // --- 4. استقبال وتنقية كل البيانات ---
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $governorate_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? null);
    $phone = trim($_POST['phone'] ?? null);
    $whatsapp = trim($_POST['whatsapp'] ?? null);
    $description = trim($_POST['description'] ?? null);
    
    // استقبال العملة
    $currency = $_POST['currency'] ?? 'SYP';
    if (!in_array($currency, ['SYP', 'USD'])) { 
        $currency = 'SYP'; 
    }

    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $website_url = filter_var(trim($_POST['website_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;
    $facebook_url = filter_var(trim($_POST['facebook_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;
    $instagram_url = filter_var(trim($_POST['instagram_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;
    $video_url = filter_var(trim($_POST['video_url'] ?? ''), FILTER_VALIDATE_URL) ?: null;
    $opening_hours = !empty(array_filter($_POST['opening_hours'] ?? [])) ? json_encode($_POST['opening_hours'], JSON_UNESCAPED_UNICODE) : null;

    if (empty($name) || empty($category) || empty($city) || empty($governorate_id)) {
        throw new Exception("الرجاء ملء الحقول الأساسية: الاسم، الفئة، المحافظة، المدينة.");
    }
    
    // --- 5. جلب الإعدادات الافتراضية ---
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $default_commission = (float)($settings['business_commission_rate'] ?? 5.0);
    $default_credit_limit = (float)($settings['business_credit_limit'] ?? 10000000.0);
    
    $uploadDirectory = '../uploads/businesses/'; 
    
    // معالجة الصور الرئيسية
    $logo_path = null;
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $logo_path = handleImageUpload($_FILES['logo_image'], $uploadDirectory);
    }
    
    $cover_path = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $cover_path = handleImageUpload($_FILES['cover_image'], $uploadDirectory);
    }

    $pdo->beginTransaction();

    // --- 6. الإدخال في قاعدة البيانات ---
    $stmt = $pdo->prepare(
        "INSERT INTO businesses (
            user_id, name, category, governorate_id, city, address, phone, whatsapp, 
            latitude, longitude, description, logo_image, cover_image, opening_hours, 
            status, website_url, facebook_url, instagram_url, video_url, 
            commission_rate, credit_limit, currency
        ) 
        VALUES (
            :user_id, :name, :category, :governorate_id, :city, :address, :phone, :whatsapp, 
            :latitude, :longitude, :description, :logo_image, :cover_image, :opening_hours, 
            :status, :website_url, :facebook_url, :instagram_url, :video_url, 
            :commission_rate, :credit_limit, :currency
        )"
    );
    
    $stmt->execute([
        ':user_id' => $user_id, 
        ':name' => $name, 
        ':category' => $category, 
        ':governorate_id' => $governorate_id,
        ':city' => $city, 
        ':address' => $address,
        ':phone' => $phone, 
        ':whatsapp' => $whatsapp, 
        ':latitude' => $latitude, 
        ':longitude' => $longitude, 
        ':description' => $description, 
        ':logo_image' => $logo_path, 
        ':cover_image' => $cover_path, 
        ':opening_hours' => $opening_hours, 
        ':status' => $status,
        ':website_url' => $website_url, 
        ':facebook_url' => $facebook_url, 
        ':instagram_url' => $instagram_url,
        ':video_url' => $video_url,
        ':commission_rate' => $default_commission,
        ':credit_limit' => $default_credit_limit,
        ':currency' => $currency 
    ]);
    $business_id = $pdo->lastInsertId();

    // --- 7. معالجة البيانات الإضافية ---
    
    // التفاصيل
    if (isset($_POST['details']) && is_array($_POST['details'])) {
        $stmt_details = $pdo->prepare("INSERT INTO business_details (business_id, detail_key, detail_value) VALUES (?, ?, ?)");
        foreach ($_POST['details'] as $key => $value) {
            if (trim($value) !== '') { $stmt_details->execute([$business_id, $key, trim($value)]); }
        }
    }

    // معرض الصور
    if (isset($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
        $stmt_gallery = $pdo->prepare("INSERT INTO business_gallery (business_id, image_path) VALUES (?, ?)");
        $file_count = count($_FILES['gallery_images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_data = [
                    'name' => $_FILES['gallery_images']['name'][$i],
                    'type' => $_FILES['gallery_images']['type'][$i],
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                    'error' => $_FILES['gallery_images']['error'][$i],
                    'size' => $_FILES['gallery_images']['size'][$i]
                ];
                
                $path = handleImageUpload($file_data, $uploadDirectory);
                if ($path) {
                    $stmt_gallery->execute([$business_id, $path]);
                }
            }
        }
    }

    // سلايدر العروض
    if (isset($_FILES['offer_images']['name']) && is_array($_FILES['offer_images']['name'])) {
        $stmt_offers = $pdo->prepare("INSERT INTO business_offers (business_id, image_path) VALUES (?, ?)");
        $file_count = count($_FILES['offer_images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['offer_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_data = [
                    'name' => $_FILES['offer_images']['name'][$i],
                    'type' => $_FILES['offer_images']['type'][$i],
                    'tmp_name' => $_FILES['offer_images']['tmp_name'][$i],
                    'error' => $_FILES['offer_images']['error'][$i],
                    'size' => $_FILES['offer_images']['size'][$i]
                ];
                
                $path = handleImageUpload($file_data, $uploadDirectory);
                if ($path) {
                    $stmt_offers->execute([$business_id, $path]);
                }
            }
        }
    }

    // قائمة الأسعار
    if (isset($_POST['menu_items']) && is_array($_POST['menu_items'])) {
        $stmt_menu = $pdo->prepare( "INSERT INTO business_menu_items (business_id, category_name, item_name, description, price, image_path) VALUES (?, ?, ?, ?, ?, ?)" );
        
        foreach ($_POST['menu_items'] as $key => $item) {
            $item_name = trim($item['name'] ?? ''); 
            $item_price = trim($item['price'] ?? '');
            
            if (empty($item_name) || empty($item_price)) continue;
            
            $item_category = trim($item['category'] ?? 'عام');
            $item_desc = trim($item['desc'] ?? null);
            $item_image_path = null;

            if (isset($_FILES['menu_items']['error'][$key]['image']) && $_FILES['menu_items']['error'][$key]['image'] === UPLOAD_ERR_OK) {
                $file_info = [
                    'name' => $_FILES['menu_items']['name'][$key]['image'],
                    'type' => $_FILES['menu_items']['type'][$key]['image'],
                    'tmp_name' => $_FILES['menu_items']['tmp_name'][$key]['image'],
                    'error' => $_FILES['menu_items']['error'][$key]['image'],
                    'size' => $_FILES['menu_items']['size'][$key]['image']
                ];
                $item_image_path = handleImageUpload($file_info, $uploadDirectory);
            }
            
            $stmt_menu->execute([ $business_id, $item_category, $item_name, $item_desc, $item_price, $item_image_path ]);
        }
    }

    // العروض والصفقات
    if (isset($_POST['deals']) && is_array($_POST['deals'])) {
        $stmt_deals = $pdo->prepare(
            "INSERT INTO business_deals (business_id, category_name, deal_name, description, old_price, new_price, image_path) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        foreach ($_POST['deals'] as $key => $deal) {
            $deal_name = trim($deal['deal_name'] ?? '');
            $new_price = trim($deal['new_price'] ?? '');
            
            if (empty($deal_name) || empty($new_price)) continue;
            
            $category_name = trim($deal['category_name'] ?? 'عروض عامة');
            $description = trim($deal['description'] ?? null);
            $old_price = !empty($deal['old_price']) ? (float)$deal['old_price'] : null;
            $image_path = null;
            
            if (isset($_FILES['deals_images']['error'][$key]) && $_FILES['deals_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_info = [
                    'name' => $_FILES['deals_images']['name'][$key],
                    'type' => $_FILES['deals_images']['type'][$key],
                    'tmp_name' => $_FILES['deals_images']['tmp_name'][$key],
                    'error' => $_FILES['deals_images']['error'][$key],
                    'size' => $_FILES['deals_images']['size'][$key]
                ];
                $image_path = handleImageUpload($file_info, $uploadDirectory);
            }
            $stmt_deals->execute([$business_id, $category_name, $deal_name, $description, $old_price, (float)$new_price, $image_path]);
        }
    }
    
    $pdo->commit();

    // --- 8. التوجيه ---
    if ($is_admin_action) {
        $_SESSION['admin_message'] = "تم إنشاء النشاط '{$name}' بنجاح.";
        $_SESSION['admin_message_type'] = "success";
        header('Location: ../admin/dashboard.php');
    } else {
        $_SESSION['message'] = "تم إرسال نشاطك التجاري '{$name}' للمراجعة بنجاح!";
        $_SESSION['message_type'] = 'success';
        header('Location: ../business_dashboard.php');
    }
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    $redirect_url = $is_admin_action ? '../admin/add_business.php' : '../add_business_user.php';
    $message_key = $is_admin_action ? 'admin_message' : 'message';
    $message_type_key = $is_admin_action ? 'admin_message_type' : 'message_type';

    $_SESSION[$message_key] = "فشل إضافة النشاط: " . $e->getMessage();
    $_SESSION[$message_type_key] = 'error';
    header('Location: ' . $redirect_url);
    exit;
}
?>