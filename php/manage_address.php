<?php
// ========================================================================
// Syriazzle - Address Manager Backend
// ========================================================================
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// حماية الدخول
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;

// دالة مساعدة لتعيين الرسالة وإعادة التوجيه
function redirectWithMsg($msg, $type = 'success') {
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
    header('Location: ../my_addresses.php');
    exit;
}

try {
    if ($action === 'add' || $action === 'edit') {
        // تنظيف البيانات
        $name = trim($_POST['address_name']);
        $details = trim($_POST['address_details']);
        $lat = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $lng = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

        if (empty($name) || empty($details) || !$lat || !$lng) {
            redirectWithMsg("يرجى ملء كافة الحقول وتحديد الموقع على الخريطة.", "error");
        }

        if ($action === 'add') {
            // التحقق هل هذا أول عنوان؟ لنجعله افتراضياً
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $stmt_check->execute([$current_user_id]);
            $is_default = ($stmt_check->fetchColumn() == 0) ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_name, latitude, longitude, address_details, is_default) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$current_user_id, $name, $lat, $lng, $details, $is_default]);
            redirectWithMsg("تم إضافة العنوان الجديد بنجاح.");
        } 
        else { // edit
            // التأكد من الملكية قبل التعديل
            $stmt_check = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt_check->execute([$address_id, $current_user_id]);
            if(!$stmt_check->fetch()) redirectWithMsg("لا تملك صلاحية تعديل هذا العنوان.", "error");

            $stmt = $pdo->prepare("UPDATE user_addresses SET address_name = ?, latitude = ?, longitude = ?, address_details = ? WHERE id = ?");
            $stmt->execute([$name, $lat, $lng, $details, $address_id]);
            redirectWithMsg("تم تحديث العنوان بنجاح.");
        }

    } elseif ($action === 'delete') {
        $pdo->beginTransaction();
        // التأكد من الملكية
        $stmt_check = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$address_id, $current_user_id]);
        $addr = $stmt_check->fetch();

        if($addr) {
            $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ?");
            $stmt->execute([$address_id]);
            
            // إذا حذفنا الافتراضي، نعين واحداً آخر إن وجد
            if ($addr['is_default']) {
                $stmt_new_def = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                $stmt_new_def->execute([$current_user_id]);
            }
            $pdo->commit();
            redirectWithMsg("تم حذف العنوان.");
        } else {
            redirectWithMsg("العنوان غير موجود.", "error");
        }

    } elseif ($action === 'set_default') {
        $pdo->beginTransaction();
        // تصفير الكل
        $stmt_reset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt_reset->execute([$current_user_id]);
        // تعيين الجديد
        $stmt_set = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        $stmt_set->execute([$address_id, $current_user_id]);
        $pdo->commit();
        
        redirectWithMsg("تم تغيير العنوان الافتراضي.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirectWithMsg("حدث خطأ في النظام: " . $e->getMessage(), "error");
}
?>