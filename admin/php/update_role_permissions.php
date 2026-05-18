<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة الخاص بالصفحة ---
// تأكد من أن المستخدم لديه صلاحية "إدارة الموظفين" (التي تتضمن إدارة الأدوار)
if (!hasPermission('manage_staff')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل الأدوار.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.']);
    exit;
}

$role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
$permissions = $_POST['permissions'] ?? [];

// منع تعديل صلاحيات دور السوبر أدمن (role_id = 1)
if (!$role_id || $role_id == 1) { 
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'لا يمكن تعديل صلاحيات دور السوبر أدمن.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. حذف كل الصلاحيات القديمة لهذا الدور
    $stmt_delete = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt_delete->execute([$role_id]);

    // 2. إضافة الصلاحيات الجديدة المحددة
    if (!empty($permissions) && is_array($permissions)) {
        $stmt_insert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permissions as $permission_id) {
            if (filter_var($permission_id, FILTER_VALIDATE_INT)) {
                $stmt_insert->execute([$role_id, (int)$permission_id]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'تم تحديث الصلاحيات بنجاح.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Update Role Permissions Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'فشل تحديث الصلاحيات بسبب خطأ في الخادم.']);
}
?>