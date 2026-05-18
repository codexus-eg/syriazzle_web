<?php
$page_title = 'تعديل بيانات المستخدم';
include 'header.php';

// --- 1. حارس البوابة (Security Check) ---
// نتحقق إذا كان المستخدم يملك صلاحية تعديل المستخدمين أو على الأقل عرضهم
if (!hasPermission('edit_user') && !hasPermission('view_users')) {
    echo "<div class='access-denied-container'>
            <i class='fas fa-lock'></i>
            <h2>عذراً، ليس لديك صلاحية للوصول لهذه الصفحة.</h2>
          </div>";
    include 'footer.php';
    exit;
}

// --- 2. جلب بيانات المستخدم ---
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    echo "<div class='alert-box error'>رقم المستخدم غير صحيح.</div>";
    include 'footer.php';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<div class='alert-box error'>المستخدم غير موجود أو تم حذفه.</div>";
        include 'footer.php';
        exit;
    }

} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}
?>

<!-- تنسيقات بسيطة خاصة بالصفحة لتتوافق مع التصميم العام -->
<style>
    .edit-container {
        max-width: 600px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border: 1px solid #e0e0e0;
    }
    .edit-header {
        border-bottom: 1px solid #eee;
        padding-bottom: 20px;
        margin-bottom: 25px;
    }
    .edit-header h2 { margin: 0; color: #333; font-size: 24px; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #555; }
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 16px;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.3s;
    }
    .form-control:focus { border-color: #007bff; outline: none; }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    .btn-save {
        background-color: #007bff; color: #fff; border: none; padding: 12px 30px;
        border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px;
    }
    .btn-cancel {
        background-color: #f8f9fa; color: #333; border: 1px solid #ccc; padding: 12px 30px;
        border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 16px;
    }
    .note { font-size: 13px; color: #888; margin-top: 5px; }
</style>

<div class="edit-container">
    <div class="edit-header">
        <h2>تعديل بيانات: <?php echo htmlspecialchars($user['username']); ?></h2>
    </div>

    <form action="update_user.php" method="POST">
        <!-- حقل مخفي لمعرف المستخدم -->
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

        <!-- اسم المستخدم -->
        <div class="form-group">
            <label>اسم المستخدم الكامل</label>
            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <!-- رقم الهاتف -->
        <div class="form-group">
            <label>رقم الهاتف</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
        </div>

        <!-- البريد الإلكتروني -->
        <div class="form-group">
            <label>البريد الإلكتروني</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>

        <!-- حالة الحساب -->
        <div class="form-group">
            <label>حالة الحساب</label>
            <select name="is_verified" class="form-control">
                <option value="1" <?php echo $user['is_verified'] == 1 ? 'selected' : ''; ?>>مفعل (Verified)</option>
                <option value="0" <?php echo $user['is_verified'] == 0 ? 'selected' : ''; ?>>غير مفعل / محظور</option>
            </select>
        </div>

        <!-- تغيير كلمة المرور -->
        <div class="form-group">
            <label>تغيير كلمة المرور</label>
            <input type="password" name="new_password" class="form-control" placeholder="اتركها فارغة إذا لم ترد التغيير" autocomplete="new-password">
            <div class="note">أدخل كلمة مرور جديدة فقط إذا أردت تغيير الحالية.</div>
        </div>

        <!-- الأزرار -->
        <div class="form-actions">
            <button type="submit" class="btn-save">حفظ التغييرات</button>
            <a href="manage_users.php" class="btn-cancel">إلغاء</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>