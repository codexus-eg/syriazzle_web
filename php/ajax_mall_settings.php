<?php
// 1. استدعاء الملفات الأساسية وإعدادات الأمان
require_once __DIR__ . '/../admin/auth_guard.php';
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

// التحقق من صلاحيات المستخدم أولاً
if (!hasPermission('manage_mall')) {
    send_response(false, 'وصول غير مصرح به.');
}

// 2. الدوال المساعدة
function send_response($success, $message, $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. تحديد الإجراء المطلوب ومعالجته
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // --- قسم الإعدادات العامة ---

        case 'get_settings':
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key = 'mall_usd_exchange_rate'");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            send_response(true, 'تم جلب الإعدادات.', $settings);
            break;

        case 'save_settings':
            $exchange_rate = (float)($_POST['mall_usd_exchange_rate'] ?? 0);
            if ($exchange_rate <= 0) {
                send_response(false, 'الرجاء إدخال سعر صرف صالح.');
            }
            // UPSERT logic: UPDATE if key exists, INSERT if not.
            $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value)
                    ON DUPLICATE KEY UPDATE setting_value = :value";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['key' => 'mall_usd_exchange_rate', 'value' => $exchange_rate]);
            
            send_response(true, 'تم حفظ الإعدادات بنجاح!');
            break;

        // --- قسم إدارة الخصومات ---

        case 'get_discounts':
            $stmt = $pdo->query("SELECT * FROM mall_discounts ORDER BY id DESC");
            send_response(true, 'تم جلب حملات الخصم.', $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get_discount_details':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM mall_discounts WHERE id = ?");
            $stmt->execute([$id]);
            send_response(true, 'تم جلب تفاصيل الخصم.', $stmt->fetch(PDO::FETCH_ASSOC));
            break;
            
        case 'save_discount':
            $id = (int)($_POST['discount_id'] ?? 0);
            $name = trim($_POST['name']);
            $percentage = (float)$_POST['discount_percentage'];
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

            if (empty($name) || $percentage <= 0 || $percentage > 100) {
                send_response(false, 'الرجاء إدخال اسم ونسبة خصم صالحة (بين 1 و 100).');
            }

            if ($id > 0) { // تحديث
                $stmt = $pdo->prepare("UPDATE mall_discounts SET name=?, discount_percentage=?, start_date=?, end_date=? WHERE id=?");
                $stmt->execute([$name, $percentage, $start_date, $end_date, $id]);
            } else { // إضافة
                $stmt = $pdo->prepare("INSERT INTO mall_discounts (name, discount_percentage, start_date, end_date, is_active) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$name, $percentage, $start_date, $end_date]);
            }
            send_response(true, 'تم حفظ حملة الخصم بنجاح.');
            break;

        case 'toggle_discount_status':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE mall_discounts SET is_active = !is_active WHERE id = ?");
            $stmt->execute([$id]);
            send_response(true, 'تم تغيير حالة حملة الخصم.');
            break;

        case 'delete_discount':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM mall_discounts WHERE id = ?");
            $stmt->execute([$id]);
            send_response(true, 'تم حذف حملة الخصم.');
            break;

        default:
            send_response(false, 'الإجراء المطلوب غير معروف أو غير محدد.');
    }
} catch (Exception $e) {
    send_response(false, 'حدث خطأ فني: ' . $e->getMessage());
}
?>