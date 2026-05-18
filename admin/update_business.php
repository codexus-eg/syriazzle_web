<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die("Access Denied.");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    die("Invalid CSRF token.");
}
unset($_SESSION['csrf_token']);

require_once '../php/db_connect.php';

function handleImageUpload($file, $uploadDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $maxFileSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxFileSize) throw new Exception('حجم الملف كبير جدًا.');
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array(mime_content_type($file['tmp_name']), $allowedMimeTypes)) {
        throw new Exception('نوع الملف غير مسموح به.');
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFileName = uniqid('business_', true) . '.' . $extension;
    $destination = $uploadDir . $newFileName;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception('فشل نقل الملف المرفوع.');
    return 'uploads/businesses/' . $newFileName;
}

$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
if ($business_id === 0) {
    $_SESSION['message'] = "خطأ: معرف النشاط التجاري غير صالح.";
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}

try {
    $name = trim($_POST['name'] ?? '');

    $pdo->beginTransaction();
    $uploadDirectory = '../uploads/businesses/';

    $stmt_current = $pdo->prepare("SELECT logo_image, cover_image FROM businesses WHERE id = ?");
    $stmt_current->execute([$business_id]);
    $current_images = $stmt_current->fetch(PDO::FETCH_ASSOC);

    $logo_path = $current_images['logo_image'];
    if (!empty($_POST['delete_logo'])) {
        if ($logo_path && file_exists('../' . $logo_path)) unlink('../' . $logo_path);
        $logo_path = null;
    }
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $logo_path = handleImageUpload($_FILES['logo_image'], $uploadDirectory);
    }

    $cover_path = $current_images['cover_image'];
    if (!empty($_POST['delete_cover'])) {
        if ($cover_path && file_exists('../' . $cover_path)) unlink('../' . $cover_path);
        $cover_path = null;
    }
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $cover_path = handleImageUpload($_FILES['cover_image'], $uploadDirectory);
    }
    
    $update_fields = [
        'name' => $name, 
        'category' => trim($_POST['category'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'address' => trim($_POST['address'] ?? null),
        'phone' => trim($_POST['phone'] ?? null),
        'whatsapp' => trim($_POST['whatsapp'] ?? null),
        'description' => trim($_POST['description'] ?? null),
        'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
        'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
        'website_url' => trim($_POST['website_url'] ?? null),
        'facebook_url' => trim($_POST['facebook_url'] ?? null),
        'instagram_url' => trim($_POST['instagram_url'] ?? null),
        'opening_hours' => !empty(array_filter($_POST['opening_hours'] ?? [])) ? json_encode($_POST['opening_hours'], JSON_UNESCAPED_UNICODE) : null,
        'logo_image' => $logo_path,
        'cover_image' => $cover_path,
        'id' => $business_id
    ];
    $sql_parts = [];
    foreach ($update_fields as $key => $value) {
        if ($key !== 'id') $sql_parts[] = "`$key` = :$key";
    }
    $sql = "UPDATE businesses SET " . implode(', ', $sql_parts) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_fields);

    $stmt_delete_details = $pdo->prepare("DELETE FROM business_details WHERE business_id = ?");
    $stmt_delete_details->execute([$business_id]);
    if (isset($_POST['details']) && is_array($_POST['details'])) {
        $stmt_insert_details = $pdo->prepare("INSERT INTO business_details (business_id, detail_key, detail_value) VALUES (?, ?, ?)");
        foreach ($_POST['details'] as $key => $value) {
            if (trim($value) !== '') {
                $stmt_insert_details->execute([$business_id, $key, trim($value)]);
            }
        }
    }
    
    if (!empty($_POST['delete_gallery_ids']) && is_array($_POST['delete_gallery_ids'])) {
        $ids_to_delete = $_POST['delete_gallery_ids'];
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $stmt_get_paths = $pdo->prepare("SELECT image_path FROM business_gallery WHERE id IN ($placeholders) AND business_id = ?");
        $stmt_get_paths->execute(array_merge($ids_to_delete, [$business_id]));
        $paths_to_delete = $stmt_get_paths->fetchAll(PDO::FETCH_COLUMN);
        foreach ($paths_to_delete as $path) {
            if ($path && file_exists('../' . $path)) unlink('../' . $path);
        }
        $stmt_delete_gallery = $pdo->prepare("DELETE FROM business_gallery WHERE id IN ($placeholders)");
        $stmt_delete_gallery->execute($ids_to_delete);
    }
    if (isset($_FILES['gallery_images_new'])) {
        $stmt_gallery = $pdo->prepare("INSERT INTO business_gallery (business_id, image_path) VALUES (?, ?)");
        $gallery_files = $_FILES['gallery_images_new'];
        foreach ($gallery_files['name'] as $key => $file_name) { 
            if ($gallery_files['error'][$key] === UPLOAD_ERR_OK) {
                $file_tmp = ['name' => $file_name, 'type' => $gallery_files['type'][$key], 'tmp_name' => $gallery_files['tmp_name'][$key], 'error' => 0, 'size' => $gallery_files['size'][$key]];
                $path = handleImageUpload($file_tmp, $uploadDirectory);
                if ($path) $stmt_gallery->execute([$business_id, $path]);
            }
        }
    }

    if (!empty($_POST['delete_menu_items'])) {
        $ids_to_delete = $_POST['delete_menu_items'];
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $stmt_delete_menu = $pdo->prepare("DELETE FROM business_menu_items WHERE id IN ($placeholders) AND business_id = ?");
        $stmt_delete_menu->execute(array_merge($ids_to_delete, [$business_id]));
    }
    if (isset($_POST['menu_items']) && is_array($_POST['menu_items'])) {
        $stmt_update_menu = $pdo->prepare("UPDATE business_menu_items SET item_name = ?, description = ?, price = ? WHERE id = ? AND business_id = ?");
        $stmt_update_menu_image = $pdo->prepare("UPDATE business_menu_items SET image_path = ? WHERE id = ? AND business_id = ?");
        foreach ($_POST['menu_items'] as $id => $itemData) {
            $stmt_update_menu->execute([trim($itemData['name']), trim($itemData['desc']), trim($itemData['price']), $id, $business_id]);
            if (isset($_FILES['menu_items_images']['error'][$id]) && $_FILES['menu_items_images']['error'][$id] === UPLOAD_ERR_OK) {
                $file = ['name' => $_FILES['menu_items_images']['name'][$id], 'type' => $_FILES['menu_items_images']['type'][$id], 'tmp_name' => $_FILES['menu_items_images']['tmp_name'][$id], 'error' => 0, 'size' => $_FILES['menu_items_images']['size'][$id]];
                $path = handleImageUpload($file, $uploadDirectory);
                if ($path) $stmt_update_menu_image->execute([$path, $id, $business_id]);
            }
        }
    }
    if (isset($_POST['menu_items_new']) && is_array($_POST['menu_items_new'])) {
        $stmt_insert_menu = $pdo->prepare("INSERT INTO business_menu_items (business_id, item_name, description, price, image_path) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['menu_items_new'] as $index => $itemData) {
            $item_name = trim($itemData['name'] ?? '');
            if (empty($item_name)) continue;
            $path = null;
            if (isset($_FILES['menu_items_new_images']['error'][$index]['image']) && $_FILES['menu_items_new_images']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                $file = ['name' => $_FILES['menu_items_new_images']['name'][$index]['image'], 'type' => $_FILES['menu_items_new_images']['type'][$index]['image'], 'tmp_name' => $_FILES['menu_items_new_images']['tmp_name'][$index]['image'], 'error' => 0, 'size' => $_FILES['menu_items_new_images']['size'][$index]['image']];
                $path = handleImageUpload($file, $uploadDirectory);
            }
            $stmt_insert_menu->execute([$business_id, $item_name, trim($itemData['desc'] ?? ''), trim($itemData['price'] ?? ''), $path]);
        }
    }

    $pdo->commit();
    $_SESSION['message'] = "تم تحديث النشاط التجاري '<strong>" . htmlspecialchars($name) . "</strong>' بنجاح!";
    $_SESSION['message_type'] = 'success';
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['message'] = "فشل التحديث: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: edit_business.php?id=' . $business_id);
    exit;
}
?>