<?php
// ========================================================================
// Syriazzle - Mall Management API (النسخة 3.1 - مع دعم التحميل التدريجي)
// هذا الملف هو الواجهة الخلفية (Backend) التي تخدم لوحة تحكم المول وواجهة الزبون.
// ========================================================================

// 1. استدعاء الملفات الأساسية
// auth_guard.php سيتم استدعاؤه لاحقاً فقط للإجراءات التي تتطلب ذلك
require_once __DIR__ . '/db_connect.php';

// نضبط نوع المحتوى كـ JSON لجميع الاستجابات
header('Content-Type: application/json; charset=UTF-8');

// 2. الدوال المساعدة
function send_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleImageUpload($file, $uploadDir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return [null, null];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxFileSize) return [null, 'حجم الملف كبير جدًا (الحد الأقصى 5 ميجابايت).'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowedExtensions)) return [null, 'نوع الملف غير مسموح به.'];
    $newFileName = uniqid('img_', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;
    if (!is_dir($uploadDir)) { if (!mkdir($uploadDir, 0775, true)) return [null, 'فشل في إنشاء مجلد الرفع.']; }
    if (!move_uploaded_file($file['tmp_name'], $destination)) return [null, 'فشل نقل الملف المرفوع.'];
    return [str_replace('../', '', $uploadDir) . $newFileName, null];
}

// 3. تحديد الإجراء المطلوب
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 4. التحقق من الصلاحيات (فقط للإجراءات الخاصة بلوحة التحكم)
$public_actions = ['fetch_more_products']; // قائمة الإجراءات العامة التي لا تتطلب تسجيل دخول
if (!in_array($action, $public_actions)) {
    require_once __DIR__ . '/../admin/auth_guard.php';
    if (!hasPermission('manage_mall')) {
        send_response(false, 'وصول غير مصرح به.');
    }
}


// 5. معالجة الإجراء المطلوب
try {
    switch ($action) {

        // ======================= إجراء عام (واجهة الزبون) =======================
        case 'fetch_more_products':
            // هذا الإجراء الجديد مخصص للتحميل التدريجي في صفحة الصنف
            $category_id = (int)($_GET['id'] ?? 0);
            $page = (int)($_GET['page'] ?? 1);
            $products_per_page = 12; // يجب أن يطابق الرقم في mall_category.php
            $offset = ($page > 0) ? ($page - 1) * $products_per_page : 0;

            if ($category_id <= 0) {
                // نستخدم echo هنا بدلاً من send_response لتجنب التعارض
                echo json_encode(['success' => false, 'message' => 'معرف الصنف غير صالح.', 'data' => []]);
                exit;
            }

            $sql = "SELECT p.id, p.name AS item_name, p.description, p.image_path, 
                           p.price_usd, p.old_price_usd, p.fixed_price_syp, 
                           b.name AS brand_name, b.logo_path AS brand_logo_path
                    FROM mall_products AS p 
                    LEFT JOIN mall_brands AS b ON p.brand_id = b.id 
                    WHERE p.category_id = :category_id 
                    ORDER BY p.name 
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $products_per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // نستخدم echo هنا أيضاً
            echo json_encode(['success' => true, 'data' => $products]);
            exit;
            // لا يوجد break هنا لأن exit() يوقف التنفيذ


        // ======================= إدارة الأقسام (لوحة التحكم) =======================
        case 'fetch_departments':
            $stmt = $pdo->query("SELECT * FROM mall_departments ORDER BY name");
            send_response(true, 'تم جلب الأقسام بنجاح.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'save_department':
            $id = (int)($_POST['department_id'] ?? 0);
            $name = trim($_POST['name']);
            if (empty($name)) send_response(false, 'اسم القسم مطلوب.');
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE mall_departments SET name=? WHERE id=?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO mall_departments (name) VALUES (?)");
                $stmt->execute([$name]);
            }
            send_response(true, 'تم حفظ القسم بنجاح.');
            break;
        case 'delete_department':
            $id = (int)($_POST['department_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM mall_departments WHERE id=?");
            $stmt->execute([$id]);
            send_response(true, 'تم حذف القسم بنجاح.');
            break;

        // ======================= إدارة الأصناف (لوحة التحكم) =======================
        case 'fetch_categories':
            $sql = "SELECT c.id, c.name, d.name as department_name FROM mall_categories c LEFT JOIN mall_departments d ON c.department_id = d.id ORDER BY c.name";
            $stmt = $pdo->query($sql);
            send_response(true, 'تم جلب الأصناف بنجاح.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'get_category_details':
            $id = (int)($_POST['category_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM mall_categories WHERE id = ?");
            $stmt->execute([$id]);
            send_response(true, 'تم جلب التفاصيل', $stmt->fetch(PDO::FETCH_ASSOC));
            break;
        case 'save_category':
            $id = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name']);
            $department_id = (int)($_POST['department_id'] ?? 0);
            if (empty($name) || $department_id === 0) send_response(false, 'اسم الصنف واختيار القسم مطلوبان.');
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE mall_categories SET name=?, department_id=? WHERE id=?");
                $stmt->execute([$name, $department_id, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO mall_categories (name, department_id) VALUES (?, ?)");
                $stmt->execute([$name, $department_id]);
            }
            send_response(true, 'تم حفظ الصنف بنجاح.');
            break;
        case 'delete_category':
            $id = (int)($_POST['category_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM mall_categories WHERE id=?");
            $stmt->execute([$id]);
            send_response(true, 'تم حذف الصنف بنجاح.');
            break;

        // ======================= إدارة الماركات (لوحة التحكم) =======================
        case 'fetch_brands':
            $stmt = $pdo->query("SELECT * FROM mall_brands ORDER BY name");
            send_response(true, 'تم جلب الماركات بنجاح.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'get_brand_details':
            $id = (int)($_POST['brand_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM mall_brands WHERE id = ?");
            $stmt->execute([$id]);
            send_response(true, 'تم جلب التفاصيل', $stmt->fetch(PDO::FETCH_ASSOC));
            break;
        case 'save_brand':
            $pdo->beginTransaction();
            $id = (int)($_POST['brand_id'] ?? 0);
            $name = trim($_POST['name']);
            if (empty($name)) send_response(false, 'اسم الماركة مطلوب.');
            list($logo_path, $error) = handleImageUpload($_FILES['logo'] ?? null, '../uploads/brands/');
            if ($error) throw new Exception($error);
            if ($logo_path === null && $id > 0) $logo_path = $_POST['existing_logo_path'] ?? null;
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE mall_brands SET name=?, logo_path=? WHERE id=?");
                $stmt->execute([$name, $logo_path, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO mall_brands (name, logo_path) VALUES (?, ?)");
                $stmt->execute([$name, $logo_path]);
            }
            $pdo->commit();
            send_response(true, 'تم حفظ الماركة بنجاح.');
            break;
        case 'delete_brand':
            $id = (int)($_POST['brand_id'] ?? 0);
            $pdo->beginTransaction();
            $stmt_img = $pdo->prepare("SELECT logo_path FROM mall_brands WHERE id = ?"); $stmt_img->execute([$id]);
            $logo_to_delete = $stmt_img->fetchColumn();
            if ($logo_to_delete && file_exists('../' . $logo_to_delete)) @unlink('../' . $logo_to_delete);
            $stmt = $pdo->prepare("DELETE FROM mall_brands WHERE id=?"); $stmt->execute([$id]);
            $pdo->commit();
            send_response(true, 'تم حذف الماركة بنجاح.');
            break;

        // ======================= إدارة المنتجات (لوحة التحكم) =======================
        case 'fetch_products':
            $sql = "SELECT p.id, p.name, p.price_usd, p.image_path, c.name AS category_name, d.name AS department_name, b.name AS brand_name 
                    FROM mall_products p
                    LEFT JOIN mall_categories c ON p.category_id = c.id
                    LEFT JOIN mall_departments d ON c.department_id = d.id
                    LEFT JOIN mall_brands b ON p.brand_id = b.id
                    ORDER BY p.id DESC";
            $stmt = $pdo->query($sql);
            send_response(true, 'تم جلب المنتجات بنجاح.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'get_product_details':
            $id = (int)($_POST['product_id'] ?? 0);
            $sql = "SELECT p.*, c.department_id FROM mall_products p LEFT JOIN mall_categories c ON p.category_id = c.id WHERE p.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            send_response(true, 'تم جلب التفاصيل', $stmt->fetch(PDO::FETCH_ASSOC));
            break;
        case 'save_product':
            $pdo->beginTransaction();
            $id = (int)($_POST['product_id'] ?? 0);
            $name = trim($_POST['name']);
            $category_id = (int)($_POST['category_id'] ?? 0);
            if (empty($name) || $category_id === 0) send_response(false, 'اسم المنتج واختيار الصنف مطلوبان.');
            list($image_path, $error) = handleImageUpload($_FILES['image'] ?? null, '../uploads/menu_items/');
            if ($error) throw new Exception($error);
            if ($image_path === null && $id > 0) $image_path = $_POST['existing_image_path'] ?? null;

            $params = [
                'name' => $name,
                'description' => trim($_POST['description'] ?? null),
                'image_path' => $image_path,
                'price_usd' => (float)$_POST['price_usd'],
                'old_price_usd' => !empty($_POST['old_price_usd']) ? (float)$_POST['old_price_usd'] : null,
                'fixed_price_syp' => !empty($_POST['fixed_price_syp']) ? (float)$_POST['fixed_price_syp'] : null,
                'category_id' => $category_id,
                'brand_id' => !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null,
            ];

            if ($id > 0) {
                $params['id'] = $id;
                $sql = "UPDATE mall_products SET name=:name, description=:description, image_path=:image_path, price_usd=:price_usd, old_price_usd=:old_price_usd, fixed_price_syp=:fixed_price_syp, category_id=:category_id, brand_id=:brand_id WHERE id=:id";
            } else {
                $sql = "INSERT INTO mall_products (name, description, image_path, price_usd, old_price_usd, fixed_price_syp, category_id, brand_id) VALUES (:name, :description, :image_path, :price_usd, :old_price_usd, :fixed_price_syp, :category_id, :brand_id)";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();
            send_response(true, 'تم حفظ المنتج بنجاح.');
            break;
        case 'delete_product':
            $id = (int)($_POST['product_id'] ?? 0);
            $pdo->beginTransaction();
            $stmt_img = $pdo->prepare("SELECT image_path FROM mall_products WHERE id = ?"); $stmt_img->execute([$id]);
            $image_to_delete = $stmt_img->fetchColumn();
            if ($image_to_delete && file_exists('../' . $image_to_delete)) @unlink('../' . $image_to_delete);
            $stmt = $pdo->prepare("DELETE FROM mall_products WHERE id=?"); $stmt->execute([$id]);
            $pdo->commit();
            send_response(true, 'تم حذف المنتج بنجاح.');
            break;

        // ======================= طلبات مساعدة للقوائم المنسدلة (لوحة التحكم) =======================
        case 'get_form_dependencies':
            $stmt_departments = $pdo->query("SELECT id, name FROM mall_departments ORDER BY name");
            $departments = $stmt_departments->fetchAll(PDO::FETCH_ASSOC);
            $stmt_brands = $pdo->query("SELECT id, name FROM mall_brands ORDER BY name");
            $brands = $stmt_brands->fetchAll(PDO::FETCH_ASSOC);
            send_response(true, "تم جلب البيانات", ['departments' => $departments, 'brands' => $brands]);
            break;
        case 'fetch_categories_by_department':
            $department_id = (int)($_POST['department_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name FROM mall_categories WHERE department_id = ? ORDER BY name");
            $stmt->execute([$department_id]);
            send_response(true, 'تم جلب الأصناف.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        default:
            send_response(false, 'الإجراء المطلوب غير معروف أو غير محدد.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // إرسال رسالة خطأ أكثر تفصيلاً للمطور (يمكنك تغيير هذا في بيئة الإنتاج)
    error_log("Mall API Error: " . $e->getMessage());
    send_response(false, 'حدث خطأ فني في الخادم.');
}
?>