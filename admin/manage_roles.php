<?php
$page_title = 'إدارة الأدوار والصلاحيات';
include 'header.php';

// حارس بوابة: تأكد من أن السوبر أدمن فقط يمكنه الوصول
if (!hasPermission('super_admin_access_all')) {
    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;
}

// --- **(جديد)** مصفوفة الترجمة الشاملة ---
$permissions_translation = [
    // فئة المتاجر
    'view_businesses' => 'عرض المتاجر',
    'edit_business' => 'تعديل بيانات متجر',
    'add_business' => 'إضافة متجر جديد',
    'delete_business' => 'حذف متجر',
    // فئة السائقين
    'view_drivers' => 'عرض السائقين',
    'approve_driver' => 'قبول/رفض/حظر سائق',
    // فئة الطلبات
    'view_all_orders' => 'عرض كل الطلبات',
    'edit_order' => 'تعديل حالة طلب',
    // فئة المالية
    'view_financials' => 'عرض التقارير المالية',
    'process_payouts' => 'تسجيل دفعات مالية',
    // فئة المستخدمين
    'view_users' => 'عرض المستخدمين',
    'edit_user' => 'تعديل مستخدم',
    'delete_user' => 'حذف مستخدم', // الصلاحية الجديدة
    // فئة الإعدادات (خاصة بالسوبر أدمن، لكن نضعها للتكامل)
    'manage_staff' => 'إدارة الموظفين',
    'manage_system_settings' => 'إدارة إعدادات النظام',
    'manage_zones' => 'إدارة مناطق الخدمة',
];

try {
    // جلب كل الأدوار
    $roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    // جلب كل الصلاحيات المتاحة وتجميعها حسب الفئة
    $permissions_stmt = $pdo->query("SELECT id, name, category FROM permissions ORDER BY category, id");
    $permissions_by_category = [];
    while ($p = $permissions_stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions_by_category[$p['category']][] = $p;
    }
    // جلب الصلاحيات المعينة حالياً لكل دور
    $role_permissions_stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
    $assigned_permissions = [];
    while ($rp = $role_permissions_stmt->fetch(PDO::FETCH_ASSOC)) {
        $assigned_permissions[$rp['role_id']][] = $rp['permission_id'];
    }

} catch (PDOException $e) { die("خطأ: " . $e->getMessage()); }

$roles_translation = ['super_admin' => 'سوبر أدمن', 'governorate_manager' => 'مدير محافظة', 'support_staff' => 'موظف دعم'];
?>
<style>
    .roles-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; }
    .role-card { background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid #e9ecef; }
    .role-header { padding: 15px 20px; border-bottom: 1px solid #e9ecef; }
    .role-header h3 { margin: 0; font-size: 18px; }
    .role-header p { margin: 5px 0 0; color: #6c757d; font-size: 14px; }
    .permissions-body { padding: 20px; max-height: 500px; overflow-y: auto; }
    .permission-category { margin-bottom: 20px; }
    .permission-category h5 { font-size: 15px; font-weight: 700; color: #0d6efd; margin-bottom: 10px; border-bottom: 2px solid #e9ecef; padding-bottom: 5px; }
    .permission-group { display: flex; flex-direction: column; gap: 8px; }
    .permission-item label { display: flex; align-items: center; gap: 8px; cursor: pointer; }
    .role-footer { padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: left; }
    .btn-save { background-color: #198754; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; }
    .btn-save:disabled { background-color: #ccc; }
</style>

<div class="page-header">
    <h1>إدارة الأدوار والصلاحيات</h1>
</div>

<form id="permissions-form" onsubmit="return false;">
    <div class="roles-grid">
        <?php foreach ($roles as $role): ?>
            <div class="role-card">
                <div class="role-header">
                    <h3><?php echo htmlspecialchars($roles_translation[$role['name']] ?? $role['name']); ?></h3>
                    <p><?php echo htmlspecialchars($role['description']); ?></p>
                </div>
                <div class="permissions-body">
                    <?php foreach ($permissions_by_category as $category => $permissions): ?>
                        <div class="permission-category">
                            <h5><?php echo htmlspecialchars($category); ?></h5>
                            <div class="permission-group">
                                <?php foreach ($permissions as $permission):
                                    // --- **المنطق الجديد والمحسّن هنا** ---
                                    // 1. هل الدور هو سوبر أدمن؟ إذا كان كذلك، فكل شيء محدد ومعطل.
                                    $is_super_admin_role = ($role['id'] == 1); 
                                    
                                    // 2. هل الصلاحية محددة حالياً في قاعدة البيانات؟
                                    $is_checked_in_db = in_array($permission['id'], $assigned_permissions[$role['id']] ?? []);
                                    
                                    // 3. القرار النهائي: تكون محددة إما لأنها محددة في قاعدة البيانات أو لأن الدور هو سوبر أدمن.
                                    $is_checked = $is_checked_in_db || $is_super_admin_role;
                                    
                                    // 4. القرار النهائي: تكون معطلة فقط إذا كان الدور هو سوبر أدمن.
                                    $is_disabled = $is_super_admin_role;

                                    // 5. **(جديد)** جلب الترجمة العربية من المصفوفة
                                    $display_name = $permissions_translation[$permission['name']] ?? $permission['name'];
                                ?>
                                <div class="permission-item">
                                    <label title="<?php echo htmlspecialchars($permission['name']); ?>">
                                        <input type="checkbox" 
                                               name="permissions[<?php echo $role['id']; ?>][]" 
                                               value="<?php echo $permission['id']; ?>"
                                               <?php if ($is_checked) echo 'checked'; ?>
                                               <?php if ($is_disabled) echo 'disabled'; ?>>
                                        <span><?php echo htmlspecialchars($display_name); ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($role['id'] != 1): // لا تظهر زر الحفظ للسوبر أدمن ?>
                <div class="role-footer">
                    <button type="button" class="btn-save" onclick="saveRolePermissions(<?php echo $role['id']; ?>)">
                        حفظ صلاحيات "<?php echo htmlspecialchars($roles_translation[$role['name']] ?? ''); ?>"
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<script>
async function saveRolePermissions(roleId) {
    const form = document.getElementById('permissions-form');
    // جلب فقط الـ checkboxes الخاصة بهذا الدور المحدد
    const checkboxes = form.querySelectorAll(`input[name='permissions[${roleId}][]']:checked`);
    const permissionIds = Array.from(checkboxes).map(cb => cb.value);
    
    const formData = new FormData();
    formData.append('role_id', roleId);
    permissionIds.forEach(id => {
        formData.append('permissions[]', id);
    });

    try {
        const response = await fetch('php/update_role_permissions.php', { method: 'POST', body: formData });
        const result = await response.json();
        alert(result.message);
        if (result.success) {
            // لا حاجة لإعادة تحميل الصفحة لأن التغيير تم في الخلفية
        }
    } catch (error) {
        alert('حدث خطأ في الشبكة.');
    }
}
</script>

<?php include 'footer.php'; ?>