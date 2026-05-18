<?php
$page_title = 'إدارة الموظفين';
// --- header.php هو المسؤول عن بدء الجلسة وتعريف الصلاحيات ---
include 'header.php';

// --- **حارس البوابة الأمني** ---
// نستخدم دالة الصلاحيات الجديدة التي عرفناها في الهيدر
if (!hasPermission('manage_staff')) {
    echo "<div style='text-align:center; padding: 40px;'>
            <h2 style='color: #dc3545;'>خطأ: وصول غير مصرح به</h2>
            <p>ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة.</p>
          </div>";
    include 'footer.php';
    exit;
}

try {
    // جلب كل الموظفين مع أسماء أدوارهم ومحافظاتهم
    $sql = "
        SELECT 
            a.id, a.full_name, a.username, a.is_active, a.governorate_id,
            r.id as role_id, r.name as role_name,
            g.name as governorate_name 
        FROM admins a 
        JOIN roles r ON a.role_id = r.id
        LEFT JOIN governorates g ON a.governorate_id = g.id
        ORDER BY r.id, a.full_name
    ";
    $staff = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب قائمة الأدوار والمحافظات للنموذج
    $roles = $pdo->query("SELECT id, name, description FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $governorates = $pdo->query("SELECT id, name FROM governorates ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) { die("خطأ في جلب البيانات: " . $e->getMessage()); }

// مصفوفة ترجمة بسيطة للأدوار
$roles_translation = ['super_admin' => 'سوبر أدمن', 'governorate_manager' => 'مدير محافظة', 'support_staff' => 'موظف دعم'];
?>
<link rel="stylesheet" href="css/admin_dashboard.css"> <!-- إعادة استخدام نفس التنسيق -->
<style>
    .actions-cell .edit-btn { background: none; border: none; color: #0d6efd; cursor: pointer; font-size: 16px; padding: 5px 10px; }
    .actions-cell .edit-btn:hover { background-color: #e9ecef; border-radius: 6px; }
</style>

<div class="page-header">
    <h1>إدارة الموظفين</h1>
    <button class="add-new-btn" onclick="openStaffModal()"><i class="fas fa-user-plus"></i> إضافة موظف جديد</button>
</div>

<div class="data-table">
    <table>
        <thead><tr><th>الاسم الكامل</th><th>اسم المستخدم</th><th>الدور</th><th>المحافظة</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
            <?php foreach ($staff as $member): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($member['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($member['username']); ?></td>
                    <td><?php echo $roles_translation[$member['role_name']] ?? htmlspecialchars($member['role_name']); ?></td>
                    <td><?php echo htmlspecialchars($member['governorate_name'] ?? '<em>كل المحافظات</em>'); ?></td>
                    <td>
                        <span style="color: <?php echo $member['is_active'] ? '#198754' : '#dc3545'; ?>; font-weight: bold;">
                            <?php echo $member['is_active'] ? 'فعال' : 'معطل'; ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <button class="edit-btn" onclick='openStaffModal(<?php echo htmlspecialchars(json_encode($member), ENT_QUOTES, 'UTF-8'); ?>)'>
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal for Add/Edit Staff -->
<div class="modal-overlay" id="staff-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="staff-modal-title">إضافة موظف جديد</h4>
            <button class="modal-close-btn" onclick="closeModal('staff-modal')">&times;</button>
        </div>
        <form id="staff-form" onsubmit="submitStaffForm(); return false;">
            <input type="hidden" id="admin-id" name="admin_id">
            <div class="form-group"><label for="full-name">الاسم الكامل</label><input type="text" id="full-name" name="full_name" class="form-control" required></div>
            <div class="form-group"><label for="username">اسم المستخدم (للدخول)</label><input type="text" id="username" name="username" class="form-control" required></div>
            <div class="form-group"><label for="password">كلمة المرور</label><input type="password" id="password" name="password" class="form-control" placeholder="اتركه فارغاً لعدم التغيير"><small>يجب أن تكون قوية</small></div>
            <div class="form-group"><label for="role-id">الدور</label>
                <select id="role-id" name="role_id" class="form-control" onchange="toggleGovernorateSelect()">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" title="<?php echo htmlspecialchars($role['description']); ?>"><?php echo $roles_translation[$role['name']] ?? htmlspecialchars($role['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="governorate-group" style="display: none;"><label for="governorate-id">المحافظة</label>
                <select id="governorate-id" name="governorate_id" class="form-control">
                    <option value="">-- اختر محافظة --</option>
                    <?php foreach ($governorates as $gov): ?>
                    <option value="<?php echo $gov['id']; ?>"><?php echo htmlspecialchars($gov['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><input type="checkbox" id="is-active" name="is_active" value="1"> الحساب فعال</label></div>
            <button type="submit" class="btn-submit">حفظ</button>
        </form>
    </div>
</div>

<script>
    const staffModal = document.getElementById('staff-modal');
    const staffForm = document.getElementById('staff-form');
    const modalTitle = document.getElementById('staff-modal-title');
    const adminIdInput = document.getElementById('admin-id');
    const fullNameInput = document.getElementById('full-name');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const roleSelect = document.getElementById('role-id'); // تم تصحيح الـ ID
    const governorateSelect = document.getElementById('governorate-id');
    const isActiveCheckbox = document.getElementById('is-active');

    function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }

    function openStaffModal(data = null) {
        staffForm.reset();
        adminIdInput.value = '';
        passwordInput.placeholder = "اتركه فارغاً لعدم التغيير";
        passwordInput.required = false;

        if (data) { // حالة التعديل
            modalTitle.textContent = 'تعديل بيانات الموظف';
            adminIdInput.value = data.id;
            fullNameInput.value = data.full_name;
            usernameInput.value = data.username;
            roleSelect.value = data.role_id; // **الإصلاح هنا**
            governorateSelect.value = data.governorate_id || '';
            isActiveCheckbox.checked = (data.is_active == 1);
        } else { // حالة الإضافة
            modalTitle.textContent = 'إضافة موظف جديد';
            isActiveCheckbox.checked = true;
            passwordInput.placeholder = "كلمة مرور قوية";
            passwordInput.required = true;
        }
        
        toggleGovernorateSelect();
        openModal('staff-modal');
    }

    function toggleGovernorateSelect() {
        const governorateGroup = document.getElementById('governorate-group');
        // جلب اسم الدور النصي من الخيار المحدد
        const selectedRoleText = roleSelect.options[roleSelect.selectedIndex].text;
        // نعتمد على النص العربي الآن
        governorateGroup.style.display = (selectedRoleText === 'مدير محافظة') ? 'block' : 'none';
    }

    async function submitStaffForm() {
        const formData = new FormData(staffForm);
        const submitButton = staffForm.querySelector('.btn-submit');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch('php/update_staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                window.location.reload();
            }
        } catch (error) {
            alert('حدث خطأ في الشبكة.');
            console.error('Staff Form Error:', error);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'حفظ';
        }
    }
</script>

<?php include 'footer.php'; ?>