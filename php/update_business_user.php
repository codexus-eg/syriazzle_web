<?php
require_once 'db_connect.php';

// =========================================================================
// 1. الإعدادات والتحقق من الصلاحيات
// =========================================================================

$is_admin_action = isset($_POST['is_admin_action']) && $_POST['is_admin_action'] == '1';
$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
$user_id_to_check = null;

if ($business_id === 0) {
    die("خطأ: معرف النشاط التجاري غير صالح.");
}

// التحقق الأمني
if ($is_admin_action) {
    require_once '../admin/auth_guard.php';
    if (!hasPermission('edit_business')) {
        die("خطأ: لا تملك صلاحية تعديل المتاجر.");
    }
} else {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
    $user_id_to_check = (int)$_SESSION['user_id'];
}

// التحقق من CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token.");
}
unset($_SESSION['csrf_token']);


// =========================================================================
// 2. دوال مساعدة (رفع وحذف الملفات)
// =========================================================================

// دالة رفع الصور (الحد الأقصى 10 ميجابايت) - [محدثة بدون finfo لتجنب خطأ 500]
function handleImageUpload($file, $uploadDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $maxFileSize = 10 * 1024 * 1024; // 10 MB
    if ($file['size'] > $maxFileSize) {
        throw new Exception('حجم الملف كبير جدًا (الحد الأقصى 10 ميجابايت).');
    }

    // --- التحقق من نوع الملف بطريقة بديلة وآمنة بما يكفي (بدون fileinfo) ---
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('نوع الملف غير مسموح به (فقط JPG, PNG, WEBP, GIF).');
    }
    // --- نهاية التحقق البديل ---

    $newFileName = uniqid('business_', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('فشل نقل الملف المرفوع. تحقق من صلاحيات المجلد.');
    }

    return 'uploads/businesses/' . $newFileName;
}

// دالة حذف الملف من السيرفر
function deleteFileFromServer($path) {
    if ($path && file_exists('../' . $path)) {
        unlink('../' . $path);
    }
}


// =========================================================================
// 3. التنفيذ الرئيسي (Try-Catch Block)
// =========================================================================

try {
    // أ) جلب البيانات الحالية للتحقق والحذف لاحقاً
    $stmt_check = $pdo->prepare("SELECT user_id, governorate_id, logo_image, cover_image FROM businesses WHERE id = ?");
    $stmt_check->execute([$business_id]);
    $current_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_data) {
        throw new Exception("النشاط التجاري غير موجود.");
    }

    // التحقق من ملكية المستخدم العادي
    if (!$is_admin_action && $current_data['user_id'] != $user_id_to_check) {
        throw new Exception("لا تملك صلاحية التعديل على هذا النشاط.");
    }

    // بدء المعاملة (Transaction) لضمان سلامة البيانات
    $pdo->beginTransaction();

    $uploadDir = '../uploads/businesses/';

    // ب) تجهيز البيانات الأساسية
    $name = trim($_POST['name'] ?? ''); 
    $category = trim($_POST['category'] ?? '');
    $governorate_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
    $city = trim($_POST['city'] ?? '');
    
    // استقبال العملة والتحقق منها (التعديل الجديد)
    $currency = $_POST['currency'] ?? 'SYP';
    if (!in_array($currency, ['SYP', 'USD'])) { 
        $currency = 'SYP'; 
    }
    
    if (empty($name) || empty($category) || empty($city) || empty($governorate_id)) {
        throw new Exception("البيانات الأساسية (الاسم، الفئة، المحافظة، المدينة) مطلوبة.");
    }

    // ج) معالجة صورة الشعار (Logo)
    $logo_path = $current_data['logo_image']; // الافتراضي هو القديم
    
    // 1. هل تم رفع صورة جديدة؟
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $new_logo = handleImageUpload($_FILES['logo_image'], $uploadDir);
        if ($new_logo) {
            deleteFileFromServer($logo_path); // حذف القديم
            $logo_path = $new_logo; // اعتماد الجديد
        }
    } 
    // 2. هل طلب المستخدم حذف الصورة فقط؟
    elseif (!empty($_POST['delete_logo']) && $_POST['delete_logo'] == '1') {
        deleteFileFromServer($logo_path);
        $logo_path = null;
    }

    // د) معالجة صورة الغلاف (Cover)
    $cover_path = $current_data['cover_image'];
    
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $new_cover = handleImageUpload($_FILES['cover_image'], $uploadDir);
        if ($new_cover) {
            deleteFileFromServer($cover_path);
            $cover_path = $new_cover;
        }
    } elseif (!empty($_POST['delete_cover']) && $_POST['delete_cover'] == '1') {
        deleteFileFromServer($cover_path);
        $cover_path = null;
    }

    // هـ) منطق تغيير المالك (خاص بالأدمن)
    $owner_id = $current_data['user_id']; // الافتراضي: المالك الحالي
    if ($is_admin_action && !empty($_POST['new_owner_id'])) {
        $owner_id = (int)$_POST['new_owner_id'];
    }

    // و) تحديث سجل النشاط التجاري (Main Update) - تمت إضافة currency
    $sql = "UPDATE businesses SET 
            name=?, category=?, governorate_id=?, city=?, address=?, phone=?, whatsapp=?, 
            description=?, latitude=?, longitude=?, website_url=?, facebook_url=?, instagram_url=?, video_url=?, 
            opening_hours=?, logo_image=?, cover_image=?, status=?, user_id=?, currency=? 
            WHERE id=?";
            
    $params = [
        $name, 
        $category, 
        $governorate_id, 
        $city, 
        $_POST['address'] ?? null, 
        $_POST['phone'] ?? null, 
        $_POST['whatsapp'] ?? null,
        $_POST['description'] ?? null, 
        !empty($_POST['latitude']) ? $_POST['latitude'] : null, 
        !empty($_POST['longitude']) ? $_POST['longitude'] : null, 
        $_POST['website_url'] ?? null, 
        $_POST['facebook_url'] ?? null, 
        $_POST['instagram_url'] ?? null, 
        $_POST['video_url'] ?? null,
        !empty($_POST['opening_hours']) ? json_encode($_POST['opening_hours'], JSON_UNESCAPED_UNICODE) : null,
        $logo_path, 
        $cover_path, 
        ($is_admin_action ? 'approved' : 'pending'), // الأدمن يوافق فوراً، المستخدم يعود للمراجعة
        $owner_id,
        $currency, // المتغير الجديد
        $business_id
    ];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // ز) تحديث التفاصيل الإضافية (Details)
    $pdo->prepare("DELETE FROM business_details WHERE business_id = ?")->execute([$business_id]);
    if (!empty($_POST['details']) && is_array($_POST['details'])) {
        $stmt_det = $pdo->prepare("INSERT INTO business_details (business_id, detail_key, detail_value) VALUES (?, ?, ?)");
        foreach ($_POST['details'] as $k => $v) { 
            if (trim($v) !== '') { 
                $stmt_det->execute([$business_id, $k, trim($v)]); 
            } 
        }
    }

    // =========================================================================
    // 4. معالجة الأقسام الفرعية (معرض، سلايدر، منيو، عروض)
    // =========================================================================

    // --- 1. معرض الصور (Gallery) ---
    // الحذف
    if (!empty($_POST['delete_gallery_ids'])) {
        $ids = array_filter($_POST['delete_gallery_ids'], 'is_numeric');
        if (!empty($ids)) {
            $in = str_repeat('?,', count($ids) - 1) . '?';
            // جلب المسارات للحذف من السيرفر
            $stmt_paths = $pdo->prepare("SELECT image_path FROM business_gallery WHERE id IN ($in) AND business_id = ?");
            $stmt_paths->execute(array_merge($ids, [$business_id]));
            foreach ($stmt_paths->fetchAll(PDO::FETCH_COLUMN) as $p) deleteFileFromServer($p);
            
            // الحذف من القاعدة
            $pdo->prepare("DELETE FROM business_gallery WHERE id IN ($in) AND business_id = ?")->execute(array_merge($ids, [$business_id]));
        }
    }
    // الإضافة
    if (!empty($_FILES['gallery_images_new']['name'][0])) {
        $stmt_gal = $pdo->prepare("INSERT INTO business_gallery (business_id, image_path) VALUES (?, ?)");
        $files = $_FILES['gallery_images_new'];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                // تجميع ملف مفرد
                $file = [
                    'name' => $files['name'][$i], 'type' => $files['type'][$i], 
                    'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 
                    'size' => $files['size'][$i]
                ];
                $path = handleImageUpload($file, $uploadDir);
                if ($path) $stmt_gal->execute([$business_id, $path]);
            }
        }
    }

    // --- 2. سلايدر العروض (Offers Slider) ---
    // الحذف
    if (!empty($_POST['delete_offer_ids'])) {
        $ids = array_filter($_POST['delete_offer_ids'], 'is_numeric');
        if (!empty($ids)) {
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $stmt_paths = $pdo->prepare("SELECT image_path FROM business_offers WHERE id IN ($in) AND business_id = ?");
            $stmt_paths->execute(array_merge($ids, [$business_id]));
            foreach ($stmt_paths->fetchAll(PDO::FETCH_COLUMN) as $p) deleteFileFromServer($p);
            
            $pdo->prepare("DELETE FROM business_offers WHERE id IN ($in) AND business_id = ?")->execute(array_merge($ids, [$business_id]));
        }
    }
    // الإضافة
    if (!empty($_FILES['offer_images_new']['name'][0])) {
        $stmt_off = $pdo->prepare("INSERT INTO business_offers (business_id, image_path) VALUES (?, ?)");
        $files = $_FILES['offer_images_new'];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i], 'type' => $files['type'][$i], 
                    'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 
                    'size' => $files['size'][$i]
                ];
                $path = handleImageUpload($file, $uploadDir);
                if ($path) $stmt_off->execute([$business_id, $path]);
            }
        }
    }

    // --- 3. القائمة (Menu) ---
    // أ) تحديث العناصر الموجودة
    if (isset($_POST['menu_items']) && is_array($_POST['menu_items'])) {
        $stmt_upd_menu = $pdo->prepare("UPDATE business_menu_items SET category_name=?, item_name=?, description=?, price=? WHERE id=? AND business_id=?");
        $stmt_img_path = $pdo->prepare("SELECT image_path FROM business_menu_items WHERE id=?");
        $stmt_upd_img = $pdo->prepare("UPDATE business_menu_items SET image_path=? WHERE id=?");
        
        foreach ($_POST['menu_items'] as $id => $data) {
            $stmt_upd_menu->execute([
                trim($data['category'] ?? 'عام'), trim($data['name']), trim($data['desc']), trim($data['price']), 
                $id, $business_id
            ]);
            
            // إذا تم رفع صورة جديدة لهذا العنصر
            if (isset($_FILES['menu_items_images']['name'][$id]) && $_FILES['menu_items_images']['error'][$id] === UPLOAD_ERR_OK) {
                // حذف القديمة
                $stmt_img_path->execute([$id]);
                deleteFileFromServer($stmt_img_path->fetchColumn());
                
                // رفع الجديدة
                $file = [
                    'name' => $_FILES['menu_items_images']['name'][$id],
                    'type' => $_FILES['menu_items_images']['type'][$id],
                    'tmp_name' => $_FILES['menu_items_images']['tmp_name'][$id],
                    'error' => $_FILES['menu_items_images']['error'][$id],
                    'size' => $_FILES['menu_items_images']['size'][$id]
                ];
                $path = handleImageUpload($file, $uploadDir);
                if ($path) $stmt_upd_img->execute([$path, $id]);
            }
        }
    }
    // ب) حذف العناصر
    if (!empty($_POST['delete_menu_items'])) {
        $ids = array_filter($_POST['delete_menu_items'], 'is_numeric');
        if (!empty($ids)) {
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $stmt_paths = $pdo->prepare("SELECT image_path FROM business_menu_items WHERE id IN ($in) AND business_id = ?");
            $stmt_paths->execute(array_merge($ids, [$business_id]));
            foreach ($stmt_paths->fetchAll(PDO::FETCH_COLUMN) as $p) deleteFileFromServer($p);
            
            $pdo->prepare("DELETE FROM business_menu_items WHERE id IN ($in) AND business_id = ?")->execute(array_merge($ids, [$business_id]));
        }
    }
    // ج) إضافة عناصر جديدة
    if (isset($_POST['menu_items_new']) && is_array($_POST['menu_items_new'])) {
        $stmt_insert_menu = $pdo->prepare("INSERT INTO business_menu_items (business_id, category_name, item_name, description, price, image_path) VALUES (?,?,?,?,?,?)");
        
        foreach ($_POST['menu_items_new'] as $key => $data) {
            if (empty($data['name'])) continue;
            
            $path = null;
            if (isset($_FILES['menu_items_new_images']['name'][$key]) && $_FILES['menu_items_new_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['menu_items_new_images']['name'][$key],
                    'type' => $_FILES['menu_items_new_images']['type'][$key],
                    'tmp_name' => $_FILES['menu_items_new_images']['tmp_name'][$key],
                    'error' => $_FILES['menu_items_new_images']['error'][$key],
                    'size' => $_FILES['menu_items_new_images']['size'][$key]
                ];
                $path = handleImageUpload($file, $uploadDir);
            }
            $stmt_insert_menu->execute([
                $business_id, trim($data['category'] ?? 'عام'), $data['name'], trim($data['desc'] ?? ''), trim($data['price'] ?? ''), $path
            ]);
        }
    }

    // --- 4. العروض (Deals) ---
    // أ) تحديث العروض الحالية
    if (isset($_POST['deals']) && is_array($_POST['deals'])) {
        $stmt_upd_deal = $pdo->prepare("UPDATE business_deals SET category_name=?, deal_name=?, description=?, old_price=?, new_price=? WHERE id=? AND business_id=?");
        $stmt_deal_img = $pdo->prepare("SELECT image_path FROM business_deals WHERE id=?");
        $stmt_upd_deal_img = $pdo->prepare("UPDATE business_deals SET image_path=? WHERE id=?");
        
        foreach ($_POST['deals'] as $id => $data) {
             $stmt_upd_deal->execute([ 
                 trim($data['category_name'] ?? 'عروض عامة'), trim($data['deal_name']), trim($data['description']), 
                 (float)$data['old_price'] ?: null, (float)$data['new_price'], $id, $business_id 
             ]);
             
             // صورة جديدة؟
             if (isset($_FILES['deals_images']['name'][$id]) && $_FILES['deals_images']['error'][$id] === UPLOAD_ERR_OK) {
                $stmt_deal_img->execute([$id]);
                deleteFileFromServer($stmt_deal_img->fetchColumn());
                
                $file = [
                    'name' => $_FILES['deals_images']['name'][$id],
                    'type' => $_FILES['deals_images']['type'][$id],
                    'tmp_name' => $_FILES['deals_images']['tmp_name'][$id],
                    'error' => $_FILES['deals_images']['error'][$id],
                    'size' => $_FILES['deals_images']['size'][$id]
                ];
                $path = handleImageUpload($file, $uploadDir);
                if ($path) $stmt_upd_deal_img->execute([$path, $id]);
            }
        }
    }
    // ب) حذف العروض
    if (!empty($_POST['delete_deals'])) {
        $ids = array_filter($_POST['delete_deals'], 'is_numeric');
        if (!empty($ids)) {
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $stmt_paths = $pdo->prepare("SELECT image_path FROM business_deals WHERE id IN ($in) AND business_id = ?");
            $stmt_paths->execute(array_merge($ids, [$business_id]));
            foreach ($stmt_paths->fetchAll(PDO::FETCH_COLUMN) as $p) deleteFileFromServer($p);
            
            $pdo->prepare("DELETE FROM business_deals WHERE id IN ($in) AND business_id = ?")->execute(array_merge($ids, [$business_id]));
        }
    }
    // ج) إضافة عروض جديدة
    if (isset($_POST['deals_new']) && is_array($_POST['deals_new'])) {
        $stmt_insert = $pdo->prepare("INSERT INTO business_deals (business_id, category_name, deal_name, description, old_price, new_price, image_path) VALUES (?,?,?,?,?,?,?)");
        
        foreach ($_POST['deals_new'] as $key => $data) {
            if (empty($data['deal_name'])) continue;
            
            $path = null;
            if (isset($_FILES['deals_new_images']['name'][$key]) && $_FILES['deals_new_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['deals_new_images']['name'][$key],
                    'type' => $_FILES['deals_new_images']['type'][$key],
                    'tmp_name' => $_FILES['deals_new_images']['tmp_name'][$key],
                    'error' => $_FILES['deals_new_images']['error'][$key],
                    'size' => $_FILES['deals_new_images']['size'][$key]
                ];
                $path = handleImageUpload($file, $uploadDir);
            }
            $stmt_insert->execute([
                $business_id, trim($data['category_name'] ?? 'عروض عامة'), $data['deal_name'], trim($data['description']), 
                (float)$data['old_price'] ?: null, (float)$data['new_price'], $path
            ]);
        }
    }
    
    // =========================================================================
    // 5. الانتهاء والتوجيه
    // =========================================================================
    
    $pdo->commit();

    $_SESSION['message'] = "تم حفظ التعديلات بنجاح!";
    $_SESSION['message_type'] = 'success';
    
    // التوجيه الصحيح بناءً على نوع المستخدم
    if ($is_admin_action) {
        $_SESSION['admin_message'] = "تم تحديث النشاط '{$name}' بنجاح.";
        $_SESSION['admin_message_type'] = "success";
        header('Location: ../admin/dashboard.php');
    } else {
        header("Location: ../edit_business_user.php?id=$business_id");
    }
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    $_SESSION['message'] = "فشل التحديث: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    
    $redirect_url = $is_admin_action ? "../admin/edit_business.php?id=$business_id" : "../edit_business_user.php?id=$business_id";
    header("Location: $redirect_url");
    exit;
}
?>