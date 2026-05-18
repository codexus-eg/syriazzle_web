<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة الخاص بالصفحة ---
// تأكد من أن المستخدم لديه صلاحية "إدارة الموظفين"
if (!hasPermission('manage_staff')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتنفيذ هذا الإجراء.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.']);
    exit;
}

// --- استقبال وتنقية البيانات ---
$admin_id = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT); // **الإصلاح هنا**
$governorate_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT);
$is_active = isset($_POST['is_active']) ? 1 : 0;

// --- التحقق من صحة البيانات ---
if (empty($full_name) || empty($username) || empty($role_id)) {
    echo json_encode(['success' => false, 'message' => 'الرجاء ملء كل الحقول المطلوبة.']);
    exit;
}

// للتحقق إذا كان الدور هو "مدير محافظة"، نحتاج لجلب اسم الدور من قاعدة البيانات
$role_name_stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
$role_name_stmt->execute([$role_id]);
$role_name = $role_name_stmt->fetchColumn();

if ($role_name === 'governorate_manager' && empty($governorate_id)) {
    echo json_encode(['success' => false, 'message' => 'يجب تحديد محافظة لمدير المحافظة.']);
    exit;
}
if ($role_name !== 'governorate_manager') {
    $governorate_id = null; // اجعل المحافظة NULL لأي دور آخر
}

try {
    if ($admin_id) { // --- حالة التعديل ---
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET full_name=?, username=?, password=?, role_id=?, governorate_id=?, is_active=? WHERE id=?");
            $stmt->execute([$full_name, $username, $hashed_password, $role_id, $governorate_id, $is_active, $admin_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET full_name=?, username=?, role_id=?, governorate_id=?, is_active=? WHERE id=?");
            $stmt->execute([$full_name, $username, $role_id, $governorate_id, $is_active, $admin_id]);
        }
        $message = "تم تحديث بيانات الموظف بنجاح.";

    } else { // --- حالة الإضافة ---
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'كلمة المرور مطلوبة عند إضافة موظف جديد.']);
            exit;
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (full_name, username, password, role_id, governorate_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $username, $hashed_password, $role_id, $governorate_id, $is_active]);
        $message = "تم إضافة الموظف بنجاح.";
    }
    echo json_encode(['success' => true, 'message' => $message]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // خطأ تكرار اسم المستخدم
        echo json_encode(['success' => false, 'message' => 'اسم المستخدم هذا موجود بالفعل. الرجاء اختيار اسم آخر.']);
    } else {
        error_log("Update Staff Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'فشل الإجراء بسبب خطأ في قاعدة البيانات.']);
    }
}
?>