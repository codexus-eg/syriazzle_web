<?php
// ========================================================================
// Syriazzle Admin - Update User Logic (النسخة النهائية)
// ========================================================================

// استدعاء حارس المنطق الموحد
require_once 'auth_guard.php';

// --- حارس البوابة: التحقق من الصلاحية ---
if (!hasPermission('edit_user') && !hasPermission('view_users')) {
    $_SESSION['msg'] = "ليس لديك الصلاحية لتعديل المستخدمين.";
    $_SESSION['msg_type'] = 'error';
    header('Location: manage_users.php');
    exit;
}

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استقبال البيانات وتنظيفها
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $is_verified = isset($_POST['is_verified']) ? (int)$_POST['is_verified'] : 0;
    $new_password = $_POST['new_password'] ?? '';

    // التحقق من الحقول الأساسية
    if ($id === 0 || empty($username) || empty($phone)) {
        $_SESSION['msg'] = "بيانات غير مكتملة. الرجاء التأكد من الاسم ورقم الهاتف.";
        $_SESSION['msg_type'] = 'error';
        header("Location: edit_user.php?id=$id");
        exit;
    }

    try {
        // 1. التحقق من عدم تكرار الهاتف أو الإيميل (مع استثناء المستخدم الحالي)
        // هذا الاستعلام يتأكد أننا لا نحاول استخدام رقم هاتف موجود لمستخدم *آخر*
        $check_sql = "SELECT id FROM users WHERE (phone = ? OR (email = ? AND email != '')) AND id != ? AND deleted_at IS NULL";
        $stmt_check = $pdo->prepare($check_sql);
        $stmt_check->execute([$phone, $email, $id]);

        if ($stmt_check->rowCount() > 0) {
            $_SESSION['msg'] = "رقم الهاتف أو البريد الإلكتروني مستخدم بالفعل لمستخدم آخر.";
            $_SESSION['msg_type'] = 'error';
            header("Location: edit_user.php?id=$id");
            exit;
        }

        // 2. بناء استعلام التحديث
        // نستخدم مصفوفة للمعاملات لتسهيل إضافة كلمة المرور شرطياً
        $sql = "UPDATE users SET username = ?, phone = ?, email = ?, is_verified = ?";
        $params = [$username, $phone, empty($email) ? null : $email, $is_verified];

        // 3. إذا تم إدخال كلمة مرور جديدة، نقوم بتحديثها
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        // 4. تنفيذ التحديث
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['msg'] = "تم تحديث بيانات المستخدم '{$username}' بنجاح.";
        $_SESSION['msg_type'] = 'success';

    } catch (PDOException $e) {
        // تسجيل الخطأ وعرض رسالة للمستخدم
        error_log("Update User Error (ID: $id): " . $e->getMessage());
        $_SESSION['msg'] = "حدث خطأ في قاعدة البيانات أثناء التحديث.";
        $_SESSION['msg_type'] = 'error';
        header("Location: edit_user.php?id=$id");
        exit;
    }

} else {
    // محاولة وصول مباشر للملف
    $_SESSION['msg'] = "طريقة الوصول غير صحيحة.";
    $_SESSION['msg_type'] = 'error';
}

// العودة للصفحة الرئيسية
header('Location: manage_users.php');
exit;
?>