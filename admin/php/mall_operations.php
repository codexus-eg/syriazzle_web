<?php
// ========================================================================
// Syriazzle - Mall Operations Engine (Final Full Version)
// ========================================================================

header('Content-Type: application/json; charset=utf-8');

// 1. بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. الاتصال بقاعدة البيانات
require_once __DIR__ . '/../../php/db_connect.php';

// 3. التحقق من الصلاحيات
$is_logged_in = (
    (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
    isset($_SESSION['admin_id'])
);

// دالة الرد الموحد
function send_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$is_logged_in) {
    send_response(false, 'انتهت الجلسة، يرجى تسجيل الدخول مجدداً.');
}

$action = $_REQUEST['action'] ?? '';

// جلب سعر الصرف الحالي (للاستخدام في العرض)
try {
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    $current_exchange_rate = (float)($stmt_rate->fetchColumn() ?: 15000);
} catch (Exception $e) {
    $current_exchange_rate = 15000;
}

try {
    switch ($action) {

        // ============================================================
        // 1. الإعدادات (سعر الصرف)
        // ============================================================
        case 'get_exchange_rate':
            send_response(true, 'تم جلب السعر', ['rate' => $current_exchange_rate]);
            break;

        case 'save_settings':
        case 'update_settings':
            $new_rate = (float)($_POST['mall_usd_exchange_rate'] ?? 0);
            if ($new_rate <= 0) send_response(false, 'يرجى إدخال سعر صرف صحيح.');

            // التحقق مما إذا كان الإعداد موجوداً مسبقاً
            $check = $pdo->query("SELECT count(*) FROM system_settings WHERE setting_key = 'usd_to_syp_rate'")->fetchColumn();
            
            if ($check > 0) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'usd_to_syp_rate'");
                $stmt->execute([$new_rate]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('usd_to_syp_rate', ?)");
                $stmt->execute([$new_rate]);
            }
            send_response(true, 'تم تحديث سعر الصرف العام بنجاح!');
            break;

        // ============================================================
        // 2. إدارة المنتجات (مع معالجة الصور الدقيقة)
        // ============================================================
        case 'fetch_products':
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 5);
            $offset = ($page - 1) * $limit;

            $sql = "SELECT p.*, 
                           c.name as category_name, 
                           d.name as department_name, 
                           b.name as brand_name 
                    FROM mall_products p
                    LEFT JOIN mall_categories c ON p.category_id = c.id
                    LEFT JOIN mall_departments d ON c.department_id = d.id
                    LEFT JOIN mall_brands b ON p.brand_id = b.id
                    ORDER BY p.id DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->query($sql);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // تنسيق السعر للعرض
            foreach ($products as &$prod) {
                $syp_calc = $prod['price_usd'] * $current_exchange_rate;
                if (!empty($prod['fixed_price_syp']) && $prod['fixed_price_syp'] > 0) {
                    $prod['display_price'] = number_format($prod['fixed_price_syp']) . ' ل.س (ثابت)';
                } else {
                    $prod['display_price'] = number_format($syp_calc) . ' ل.س';
                }
            }

            send_response(true, '', ['items' => $products, 'has_more' => count($products) === $limit]);
            break;

        case 'save_product':
            $id = (int)$_POST['product_id'];
            $name = trim($_POST['name']);
            
            // --- التحقق من أخطاء رفع الملف (الحجم الكبير) ---
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $errCode = $_FILES['image']['error'];
                if ($errCode == UPLOAD_ERR_INI_SIZE || $errCode == UPLOAD_ERR_FORM_SIZE) {
                    send_response(false, 'خطأ: حجم الصورة أكبر من المسموح به في السيرفر (Max Upload Size). يرجى اختيار صورة أصغر.');
                } else {
                    send_response(false, 'حدث خطأ تقني أثناء رفع الصورة (Code: ' . $errCode . ')');
                }
            }

            // البيانات النصية
            $price_usd = (float)$_POST['price_usd'];
            $old_price = !empty($_POST['old_price_usd']) ? (float)$_POST['old_price_usd'] : null;
            $fixed = !empty($_POST['fixed_price_syp']) ? (float)$_POST['fixed_price_syp'] : null;
            $cat_id = (int)$_POST['category_id'];
            $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
            $desc = trim($_POST['description']);
            $existing_image = $_POST['existing_image_path'];

            if (empty($name) || empty($cat_id)) send_response(false, 'اسم المنتج والقسم حقول مطلوبة.');

            // معالجة الصورة
            $image_path = $existing_image;
            
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                $file = $_FILES['image'];
                
                // التحقق من الامتداد
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    send_response(false, 'نوع الصورة غير مدعوم (JPG, PNG, WEBP فقط).');
                }

                // التحقق من الحجم البرمجي (5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    send_response(false, 'حجم الصورة يجب ألا يتجاوز 5 ميغابايت.');
                }

                $uploadDir = '../../image/mall/products/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                // حذف الصورة القديمة
                if (!empty($existing_image)) {
                    $oldFile = __DIR__ . '/../../' . $existing_image;
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                // رفع الجديدة
                $filename = 'prod_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $image_path = 'image/mall/products/' . $filename;
                } else {
                    send_response(false, 'فشل نقل ملف الصورة إلى المجلد.');
                }
            }

            if ($id === 0) {
                // إضافة
                $stmt = $pdo->prepare("INSERT INTO mall_products (name, description, price_usd, old_price_usd, fixed_price_syp, category_id, brand_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $desc, $price_usd, $old_price, $fixed, $cat_id, $brand_id, $image_path]);
                
                // إنشاء مخزون افتراضي
                $new_id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO mall_product_inventory (product_id, stock_quantity) VALUES (?, 0)")->execute([$new_id]);
                
                $msg = 'تم إضافة المنتج بنجاح';
            } else {
                // تعديل
                $stmt = $pdo->prepare("UPDATE mall_products SET name=?, description=?, price_usd=?, old_price_usd=?, fixed_price_syp=?, category_id=?, brand_id=?, image_path=? WHERE id=?");
                $stmt->execute([$name, $desc, $price_usd, $old_price, $fixed, $cat_id, $brand_id, $image_path, $id]);
                $msg = 'تم تعديل بيانات المنتج بنجاح';
            }
            send_response(true, $msg);
            break;

        case 'delete_product':
            $id = (int)$_POST['id'];
            
            // حذف الملف
            $img = $pdo->query("SELECT image_path FROM mall_products WHERE id=$id")->fetchColumn();
            if ($img && file_exists(__DIR__ . '/../../' . $img)) {
                @unlink(__DIR__ . '/../../' . $img);
            }

            // حذف الارتباطات
            $pdo->prepare("DELETE FROM mall_product_inventory WHERE product_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM mall_order_items WHERE mall_product_id = ?")->execute([$id]); 
            $pdo->prepare("DELETE FROM mall_products WHERE id = ?")->execute([$id]);
            
            send_response(true, 'تم حذف المنتج نهائياً.');
            break;

        // ============================================================
        // 3. إدارة الأقسام (Departments)
        // ============================================================
        case 'fetch_departments':
            $data = $pdo->query("SELECT * FROM mall_departments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            send_response(true, '', ['items' => $data]);
            break;

        case 'save_department':
            $id = (int)$_POST['department_id'];
            $name = trim($_POST['name']);
            if (empty($name)) send_response(false, 'الاسم مطلوب');

            if ($id == 0) $pdo->prepare("INSERT INTO mall_departments (name) VALUES (?)")->execute([$name]);
            else $pdo->prepare("UPDATE mall_departments SET name=? WHERE id=?")->execute([$name, $id]);
            send_response(true, 'تم الحفظ');
            break;

        case 'delete_department':
            $id = (int)$_POST['id'];
            if ($pdo->query("SELECT COUNT(*) FROM mall_categories WHERE department_id=$id")->fetchColumn() > 0) {
                send_response(false, 'لا يمكن حذف القسم لأنه يحتوي على أصناف.');
            }
            $pdo->prepare("DELETE FROM mall_departments WHERE id=?")->execute([$id]);
            send_response(true, 'تم الحذف');
            break;

        // ============================================================
        // 4. إدارة الأصناف (Categories)
        // ============================================================
        case 'fetch_categories':
            $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
            $sql = "SELECT c.*, d.name as department_name FROM mall_categories c JOIN mall_departments d ON c.department_id = d.id";
            if ($dept_id > 0) $sql .= " WHERE c.department_id = $dept_id";
            $sql .= " ORDER BY c.id DESC";
            $data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            send_response(true, '', ['items' => $data]);
            break;

        case 'save_category':
            $id = (int)$_POST['category_id'];
            $name = trim($_POST['name']);
            $dept_id = (int)$_POST['department_id'];
            
            if (empty($name) || empty($dept_id)) send_response(false, 'البيانات ناقصة');
            if ($id == 0) $pdo->prepare("INSERT INTO mall_categories (name, department_id) VALUES (?, ?)")->execute([$name, $dept_id]);
            else $pdo->prepare("UPDATE mall_categories SET name=?, department_id=? WHERE id=?")->execute([$name, $dept_id, $id]);
            send_response(true, 'تم الحفظ');
            break;

        case 'delete_category':
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM mall_categories WHERE id=?")->execute([$id]);
            send_response(true, 'تم الحذف');
            break;

        // ============================================================
        // 5. إدارة الماركات (Brands)
        // ============================================================
        case 'fetch_brands':
            $data = $pdo->query("SELECT * FROM mall_brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            send_response(true, '', ['items' => $data]);
            break;

        case 'save_brand':
            $id = (int)$_POST['brand_id'];
            $name = trim($_POST['name']);
            $path = $_POST['existing_logo_path'];

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                if (!is_dir('../../image/mall/brands/')) mkdir('../../image/mall/brands/', 0777, true);
                if (!empty($path) && file_exists(__DIR__.'/../../'.$path)) @unlink(__DIR__.'/../../'.$path);
                
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $path = 'image/mall/brands/brand_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], '../../' . $path);
            }

            if ($id == 0) $pdo->prepare("INSERT INTO mall_brands (name, logo_path) VALUES (?, ?)")->execute([$name, $path]);
            else $pdo->prepare("UPDATE mall_brands SET name=?, logo_path=? WHERE id=?")->execute([$name, $path, $id]);
            send_response(true, 'تم الحفظ');
            break;

        case 'delete_brand':
            $id = (int)$_POST['id'];
            $p = $pdo->query("SELECT logo_path FROM mall_brands WHERE id=$id")->fetchColumn();
            if ($p && file_exists(__DIR__.'/../../'.$p)) @unlink(__DIR__.'/../../'.$p);
            $pdo->prepare("DELETE FROM mall_brands WHERE id=?")->execute([$id]);
            send_response(true, 'تم الحذف');
            break;

        // ============================================================
        // 6. إدارة الخصومات (Discounts)
        // ============================================================
        case 'fetch_discounts':
            $data = $pdo->query("SELECT * FROM mall_discounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            send_response(true, '', ['items' => $data]);
            break;

        case 'get_discount': // لجلب البيانات عند التعديل
            $id = (int)$_GET['id'];
            $data = $pdo->query("SELECT * FROM mall_discounts WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            send_response(true, '', ['discount' => $data]);
            break;

        case 'save_discount':
            $id = (int)$_POST['discount_id'];
            $name = $_POST['name'];
            $pct = (float)$_POST['discount_percentage'];
            
            // إصلاح التاريخ: استبدال T بمسافة ليقبله MySQL بشكل صحيح
            $start = !empty($_POST['start_date']) ? str_replace('T', ' ', $_POST['start_date']) : null;
            $end = !empty($_POST['end_date']) ? str_replace('T', ' ', $_POST['end_date']) : null;

            if ($id === 0) {
                $pdo->prepare("INSERT INTO mall_discounts (name, discount_percentage, start_date, end_date, is_active) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$name, $pct, $start, $end]);
            } else {
                $pdo->prepare("UPDATE mall_discounts SET name=?, discount_percentage=?, start_date=?, end_date=? WHERE id=?")
                    ->execute([$name, $pct, $start, $end, $id]);
            }
            send_response(true, 'تم حفظ الحملة وتصحيح التاريخ');
            break;

        case 'toggle_discount_status':
            $id = (int)$_POST['id'];
            // عكس الحالة الحالية
            $curr = $pdo->query("SELECT is_active FROM mall_discounts WHERE id=$id")->fetchColumn();
            $new = $curr ? 0 : 1;
            $pdo->prepare("UPDATE mall_discounts SET is_active = ? WHERE id = ?")->execute([$new, $id]);
            send_response(true, 'تم تغيير حالة الخصم');
            break;

        case 'delete_discount':
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM mall_discounts WHERE id=?")->execute([$id]);
            send_response(true, 'تم الحذف');
            break;

        default:
            send_response(false, 'Invalid Action');
    }

} catch (PDOException $e) {
    error_log("Mall Ops Error: " . $e->getMessage());
    send_response(false, 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
}
?>