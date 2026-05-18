<?php
$page_title = 'مركز التحكم بالسائقين';
include 'header.php'; // يتضمن auth_guard.php ويعرف المتغيرات $is_super_admin, $admin_governorate_id

// --- حارس البوابة: التحقق من صلاحية "عرض السائقين" ---
if (!hasPermission('view_drivers')) {
    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;
}

try {
    // **الإصلاح الحاسم هنا: استخدام المتغير الصحيح $is_super_admin**
    $governorate_where_clause = 'WHERE d.deleted_at IS NULL'; 
    $params = [];
    if (!$is_super_admin && $admin_governorate_id) {
        $governorate_where_clause .= ' AND d.governorate_id = ?';
        $params[] = $admin_governorate_id;
    }

    // جلب كل بيانات السائق اللازمة في استعلام واحد
    $sql = "
        SELECT d.id, d.full_name, d.phone, d.status, d.commission_rate, d.credit_limit, d.vehicle_type, d.governorate_id, g.name as governorate_name
        FROM drivers d
        LEFT JOIN governorates g ON d.governorate_id = g.id
        $governorate_where_clause 
        ORDER BY d.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    $governorates = [];
    if ($is_super_admin) {
        $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $governorates = $pdo->query("SELECT id, name FROM governorates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) { die("خطأ في جلب البيانات: " . $e->getMessage()); }

$status_map = [
    'pending' => ['text' => 'قيد المراجعة', 'color' => '#ffc107', 'text_color' => '#212121'],
    'approved' => ['text' => 'مقبول', 'color' => '#198754', 'text_color' => '#fff'],
    'blocked' => ['text' => 'محظور', 'color' => '#dc3545', 'text_color' => '#fff'],
];
$vehicle_types_map = [
    'Motorcycle' => 'دراجة نارية',
    'Car' => 'سيارة',
    'Van' => 'دراجة هوائية'
];
?>
<link rel="stylesheet" href="css/admin_drivers.css">
<style>
    .actions-cell { display: flex; align-items: center; gap: 5px; justify-content: center; }
    .action-btn { background: none; border: none; cursor: pointer; padding: 8px 10px; border-radius: 6px; transition: background-color 0.2s; font-size: 16px; color: #6c757d; }
    .action-btn:hover { background-color: #f8f9fa; }
    .action-btn.settings { color: #6f42c1; } .action-btn.settings:hover { background-color: #e6dff6; }
    .action-btn.edit { color: #0d6efd; } .action-btn.edit:hover { background-color: #e7f1ff; }
    .action-btn.delete { color: #dc3545; } .action-btn.delete:hover { background-color: #f8d7da; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-grid .full-width { grid-column: 1 / -1; }
</style>

<div class="page-header">
    <?php if ($is_super_admin): ?>
        <button class="bulk-action-btn" onclick="openModal('bulk-update-modal')"><i class="fas fa-users-cog"></i> تطبيق إعدادات عامة جديدة</button>
    <?php endif; ?>
</div>

<div class="data-table">
    <table>
        <thead>
            <tr>
                <th>الاسم الكامل</th>
                <th>الهاتف</th>
                <th>العمولة (%)</th>
                <th>الحد الائتماني (ل.س)</th>
                <th>الحالة</th>
                <th style="text-align: center;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($drivers)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 20px;">لا يوجد سائقون لعرضهم.</td></tr>
            <?php endif; ?>
            <?php foreach ($drivers as $driver): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($driver['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                    <td><?php echo htmlspecialchars($driver['commission_rate']); ?>%</td>
                    <td><?php echo number_format($driver['credit_limit']); ?></td>
                    <td>
                        <?php if (hasPermission('approve_driver')): ?>
                            <form action="php/update_driver_status.php" method="POST" style="display: contents;">
                                <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                <select name="new_status" class="status-select" onchange="this.form.submit()">
                                    <option value="approved" <?php if($driver['status'] == 'approved') echo 'selected'; ?>>قبول</option>
                                    <option value="pending" <?php if($driver['status'] == 'pending') echo 'selected'; ?>>مراجعة</option>
                                    <option value="blocked" <?php if($driver['status'] == 'blocked') echo 'selected'; ?>>حظر</option>
                                </select>
                            </form>
                        <?php else: 
                            $status_info = $status_map[$driver['status']]; ?>
                            <span class="status-badge" style="background-color: <?php echo $status_info['color']; ?>; color: <?php echo $status_info['text_color']; ?>"><?php echo $status_info['text']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <?php if (hasPermission('edit_driver_financials')): ?>
                            <button class="action-btn settings" title="تعديل الإعدادات المالية" onclick='openFinancialsModal(<?php echo json_encode($driver, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>)'>
                                <i class="fas fa-cog"></i>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('edit_driver_info')): ?>
                            <button class="action-btn edit" title="تعديل معلومات السائق" onclick='openInfoModal(<?php echo json_encode($driver, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>)'>
                                <i class="fas fa-user-edit"></i>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('delete_driver')): ?>
                            <form action="delete_driver.php" method="POST" style="display: contents;" onsubmit="return confirm('تحذير! هل أنت متأكد من حذف السائق \'<?php echo htmlspecialchars(addslashes($driver['full_name'])); ?>\'؟')">
                                <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                <button type="submit" class="action-btn delete" title="حذف السائق"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- **النافذة الأولى: تعديل الإعدادات المالية** -->
<div class="modal-overlay" id="financials-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="financials-modal-title">تعديل الإعدادات المالية</h4>
            <button class="modal-close-btn" onclick="closeModal('financials-modal')">&times;</button>
        </div>
        <form id="financials-form" onsubmit="submitFinancialsForm(event)">
            <input type="hidden" id="financials-driver-id" name="driver_id">
            <div class="form-group"><label for="financials-commission-rate">نسبة العمولة (%)</label><input type="number" step="any" id="financials-commission-rate" name="commission_rate" class="form-control" required></div>
            <div class="form-group"><label for="financials-credit-limit">الحد الائتماني (ل.س)</label><input type="number" step="any" id="financials-credit-limit" name="credit_limit" class="form-control" required></div>
            <button type="submit" class="btn-submit">حفظ التغييرات</button>
        </form>
    </div>
</div>

<!-- **النافذة الثانية (الجديدة): تعديل المعلومات الأساسية** -->
<div class="modal-overlay" id="info-modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h4 id="info-modal-title">تعديل معلومات السائق</h4>
            <button class="modal-close-btn" onclick="closeModal('info-modal')">&times;</button>
        </div>
        <form id="info-form" onsubmit="submitInfoForm(event)">
            <input type="hidden" id="info-driver-id" name="driver_id">
            <div class="form-grid">
                <div class="form-group full-width"><label for="info-full-name">الاسم الكامل</label><input type="text" id="info-full-name" name="full_name" class="form-control" required></div>
                <div class="form-group"><label for="info-phone">رقم الهاتف</label><input type="text" id="info-phone" name="phone" class="form-control" required></div>
                <div class="form-group">
                    <label for="info-vehicle-type">نوع المركبة</label>
                    <select id="info-vehicle-type" name="vehicle_type" class="form-control">
                        <!-- سيتم تعبئة الخيارات هنا عبر JavaScript -->
                    </select>
                </div>
                <?php if ($is_super_admin): ?>
                <div class="form-group"><label for="info-governorate-id">المحافظة</label><select id="info-governorate-id" name="governorate_id" class="form-control" required></select></div>
                <?php endif; ?>
                <div class="form-group full-width"><label for="info-password">كلمة المرور الجديدة (اتركه فارغاً لعدم التغيير)</label><input type="password" id="info-password" name="password" class="form-control"></div>
            </div>
            <button type="submit" class="btn-submit">حفظ التعديلات</button>
        </form>
    </div>
</div>

<!-- **النافذة الثالثة: التحديث الجماعي** -->
<?php if ($is_super_admin): ?>
<div class="modal-overlay" id="bulk-update-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4>تطبيق إعدادات عامة جديدة</h4>
            <button class="modal-close-btn" onclick="closeModal('bulk-update-modal')">&times;</button>
        </div>
        <form id="bulk-update-form" onsubmit="submitBulkUpdate(event)">
            <p>سيتم تطبيق هذه الإعدادات على <strong>جميع</strong> السائقين الحاليين (في جميع المحافظات). هذا الإجراء لا يمكن التراجع عنه.</p>
            <div class="form-group">
                <label for="bulk-commission-rate">نسبة العمولة الجديدة (%)</label>
                <input type="number" step="any" id="bulk-commission-rate" name="commission_rate" class="form-control" value="<?php echo htmlspecialchars($settings['driver_commission_rate'] ?? '20'); ?>" required>
            </div>
            <div class="form-group">
                <label for="bulk-credit-limit">الحد الائتماني الجديد (ل.س)</label>
                <input type="number" step="any" id="bulk-credit-limit" name="credit_limit" class="form-control" value="<?php echo htmlspecialchars($settings['driver_credit_limit'] ?? '50000'); ?>" required>
            </div>
            <button type="submit" class="btn-submit" style="background-color: #dc3545;">تطبيق على الجميع</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- تهيئة عامة ---
    const governoratesData = <?php echo json_encode($governorates, JSON_UNESCAPED_UNICODE); ?>;
    
    // جعل الدوال عامة لتكون قابلة للاستدعاء من onclick
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'flex';
    }
    
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    }

    // --- منطق نافذة الإعدادات المالية ---
    window.openFinancialsModal = function(driverData) {
        document.getElementById('financials-modal-title').textContent = `تعديل الإعدادات المالية لـ: ${driverData.full_name}`;
        document.getElementById('financials-driver-id').value = driverData.id;
        document.getElementById('financials-commission-rate').value = Number(driverData.commission_rate);
        document.getElementById('financials-credit-limit').value = Number(driverData.credit_limit);
        openModal('financials-modal');
    }

    window.submitFinancialsForm = async function(event) {
        event.preventDefault();
        const form = document.getElementById('financials-form');
        const formData = new FormData(form);
        try {
            const response = await fetch('php/update_driver_settings.php', { method: 'POST', body: formData });
            const result = await response.json();
            alert(result.message);
            if (result.success) window.location.reload();
        } catch(error) { alert('حدث خطأ في الشبكة.'); console.error(error); }
    }

    // --- منطق نافذة المعلومات الأساسية ---
    window.openInfoModal = function(driverData) {
        document.getElementById('info-modal-title').textContent = `تعديل معلومات: ${driverData.full_name}`;
        document.getElementById('info-driver-id').value = driverData.id;
        document.getElementById('info-full-name').value = driverData.full_name;
        document.getElementById('info-phone').value = driverData.phone;
        // --- تعديل حقل نوع المركبة ---
        const vehicleSelect = document.getElementById('info-vehicle-type');
        vehicleSelect.innerHTML = ''; // تفريغ الخيارات القديمة

        // جلب مصفوفة المركبات من PHP
        const vehicleTypes = <?php echo json_encode($vehicle_types_map, JSON_UNESCAPED_UNICODE); ?>;

        // إنشاء الخيارات وإضافتها إلى القائمة المنسدلة
        for (const key in vehicleTypes) {
            const option = document.createElement('option');
            option.value = key; // القيمة بالإنجليزية (Motorcycle)
            option.textContent = vehicleTypes[key]; // النص بالعربية (دراجة نارية)
            
            // تحديد الخيار الحالي للسائق
            if (key === driverData.vehicle_type) {
                option.selected = true;
            }
            vehicleSelect.appendChild(option);
        }
        document.getElementById('info-password').value = ''; 
        
        <?php if ($is_super_admin): ?>
        const govSelect = document.getElementById('info-governorate-id');
        govSelect.innerHTML = '';
        governoratesData.forEach(gov => {
            const option = document.createElement('option');
            option.value = gov.id;
            option.textContent = gov.name;
            if (gov.id == driverData.governorate_id) {
                option.selected = true;
            }
            govSelect.appendChild(option);
        });
        <?php endif; ?>
        openModal('info-modal');
    }

    window.submitInfoForm = async function(event) {
        event.preventDefault();
        const form = document.getElementById('info-form');
        const formData = new FormData(form);
        try {
            const response = await fetch('php/update_driver.php', { method: 'POST', body: formData });
            const result = await response.json();
            alert(result.message);
            if (result.success) window.location.reload();
        } catch(error) { alert('حدث خطأ في الشبكة.'); console.error(error); }
    }

    // --- منطق التحديث الجماعي ---
    <?php if ($is_super_admin): ?>
    window.submitBulkUpdate = async function(event) {
        event.preventDefault();
        if (!confirm('تحذير! هل أنت متأكد من أنك تريد تطبيق هذه الإعدادات على جميع السائقين؟ هذا الإجراء لا يمكن التراجع عنه.')) return;
        const form = document.getElementById('bulk-update-form');
        const formData = new FormData(form);
        formData.append('action', 'bulk_update');
        
        try {
            // ملاحظة: هذا يفترض أن ملف update_driver_settings.php قادر على التعامل مع action=bulk_update
            // إذا لم يكن كذلك، ستحتاج لإنشاء ملف PHP خاص بالمعالجة الجماعية.
            const response = await fetch('php/update_driver_settings.php', { method: 'POST', body: formData });
            const result = await response.json();
            alert(result.message);
            if (result.success) window.location.reload();
        } catch(error) { alert('حدث خطأ في الشبكة.'); console.error(error); }
    }
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>