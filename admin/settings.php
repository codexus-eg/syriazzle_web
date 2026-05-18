<?php
$page_title = 'إعدادات النظام';
require_once 'header.php';

if (!hasPermission('manage_system_settings')) {
    echo "<div style='text-align:center; padding: 40px;'>
            <h2 style='color: #dc3545;'>خطأ: وصول غير مصرح به</h2>
            <p>ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة.</p>
          </div>";
    include 'footer.php';
    exit;
}

try {
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    die("خطأ في جلب الإعدادات: " . $e->getMessage());
}
?>
<style>
    :root {
        --blue: #0d6efd; --green: #198754; --yellow: #ffc107; --dark: #212529;
        --purple: #6f42c1; --gray-100: #f8f9fa; --gray-200: #e9ecef; 
        --gray-700: #495057; --card-bg: #ffffff;
    }
    .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 30px; }
    .settings-card {
        background-color: var(--card-bg); border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        border: 1px solid var(--gray-200); overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .settings-card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(0,0,0,0.1); }
    .card-header { padding: 20px 25px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid var(--gray-200); }
    .card-icon { font-size: 20px; width: 48px; height: 48px; border-radius: 10px; display: flex; justify-content: center; align-items: center; color: #fff; flex-shrink: 0; }
    .card-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--dark); }
    .card-body { padding: 25px; }
    .form-group { margin-bottom: 25px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { font-weight: 600; margin-bottom: 8px; display: block; font-size: 15px; color: var(--gray-700); }
    .form-control {
        width: 100%; padding: 14px 18px; border-radius: 8px; border: 1px solid #ced4da;
        font-family: 'Segoe UI', 'Roboto', sans-serif; font-size: 18px; box-sizing: border-box; 
        direction: ltr; text-align: left; transition: border-color 0.2s, box-shadow 0.2s;
        -moz-appearance: textfield;
    }
    .form-control::-webkit-outer-spin-button, .form-control::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .form-control:focus { border-color: var(--blue); outline: 0; box-shadow: 0 0 0 4px rgba(13,110,253,.2); }
    .input-group { display: flex; }
    .input-group-text { padding: 14px; background-color: var(--gray-100); border: 1px solid #ced4da; border-right: 0; border-radius: 0 8px 8px 0; font-weight: 600; }
    .input-group .form-control { border-radius: 8px 0 0 8px; }
    .card-delivery .card-icon { background: linear-gradient(45deg, #0d6efd, #3b8aff); }
    .card-commission .card-icon { background: linear-gradient(45deg, #198754, #28a745); }
    .card-booking .card-icon { background: linear-gradient(45deg, #6f42c1, #8a63d2); } /* لون جديد للحجوزات */
    .card-financial .card-icon { background: linear-gradient(45deg, #ffc107, #ffd24d); }
    /* لون جديد لبطاقة المول */
    .card-mall .card-icon { background: linear-gradient(45deg, #fd7e14, #ff9a4a); }
    .form-footer { text-align: center; padding-top: 20px; }
    .btn-save {
        background: linear-gradient(45deg, #0d6efd, #0a58ca); color: #fff; border: none; padding: 15px 40px;
        border-radius: 8px; font-weight: 700; font-size: 18px; cursor: pointer;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4); transition: all 0.3s ease;
    }
    .btn-save:hover { transform: translateY(-3px); box-shadow: 0 7px 25px rgba(13, 110, 253, 0.5); }
</style>

<div id="main-content">
    <div class="dashboard-header"><h1>إعدادات النظام الديناميكية</h1></div>
    <form id="settings-form" onsubmit="saveSettings(); return false;">
        <div class="settings-grid">
            <!-- ====== بطاقة إعدادات المول (جديدة بالكامل) ====== -->
            <div class="settings-card card-mall">
                <div class="card-header">
                    <div class="card-icon"><i class="fas fa-shopping-basket"></i></div>
                    <h3>إعدادات مول Syriazzle</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>الرسم الأساسي لتوصيل المول (ل.س)</label>
                        <input type="text" inputmode="decimal" name="mall_base_delivery_fee" class="form-control" value="<?php echo htmlspecialchars($settings['mall_base_delivery_fee'] ?? '3000'); ?>">
                    </div>
                    <div class="form-group">
                        <label>السعر لكل كيلومتر (ل.س)</label>
                        <input type="text" inputmode="decimal" name="mall_price_per_km" class="form-control" value="<?php echo htmlspecialchars($settings['mall_price_per_km'] ?? '1000'); ?>">
                    </div>
                    <hr style="border: 1px dashed #eee; margin: 25px 0;">
                    <div class="form-group">
                        <label>موقع المول (خط العرض - Latitude)</label>
                        <input type="text" inputmode="decimal" name="mall_latitude" class="form-control" value="<?php echo htmlspecialchars($settings['mall_latitude'] ?? '33.5138'); ?>" placeholder="مثال: 33.5138">
                    </div>
                    <div class="form-group">
                        <label>موقع المول (خط الطول - Longitude)</label>
                        <input type="text" inputmode="decimal" name="mall_longitude" class="form-control" value="<?php echo htmlspecialchars($settings['mall_longitude'] ?? '36.2765'); ?>" placeholder="مثال: 36.2765">
                    </div>
                </div>
            </div>
            <!-- بطاقة نظام التوصيل -->
            <div class="settings-card card-delivery">
                <div class="card-header"><div class="card-icon"><i class="fas fa-motorcycle"></i></div><h3>إعدادات نظام التوصيل</h3></div>
                <div class="card-body">
                    <div class="form-group"><label>الرسم الأساسي للتوصيل (ل.س)</label><input type="text" inputmode="decimal" name="delivery_base_fare" class="form-control" value="<?php echo htmlspecialchars($settings['delivery_base_fare'] ?? '0'); ?>"></div>
                    <div class="form-group"><label>السعر لكل كيلومتر (ل.س)</label><input type="text" inputmode="decimal" name="delivery_per_km_rate" class="form-control" value="<?php echo htmlspecialchars($settings['delivery_per_km_rate'] ?? '0'); ?>"></div>
                    <div class="form-group"><label>عمولة المنصة من السائق (%)</label><div class="input-group"><span class="input-group-text">%</span><input type="text" inputmode="decimal" name="driver_commission_rate" class="form-control" value="<?php echo htmlspecialchars($settings['driver_commission_rate'] ?? '0'); ?>"></div></div>
                    <div class="form-group"><label>عمولة المنصة من المتجر (%)</label><div class="input-group"><span class="input-group-text">%</span><input type="text" inputmode="decimal" name="business_commission_rate" class="form-control" value="<?php echo htmlspecialchars($settings['business_commission_rate'] ?? '0'); ?>"></div></div>
                    <div class="form-group"><label>الحد الائتماني للسائق (ل.س)</label><input type="text" inputmode="decimal" name="driver_credit_limit" class="form-control" value="<?php echo htmlspecialchars($settings['driver_credit_limit'] ?? '0'); ?>"></div>
                    <div class="form-group"><label>الحد الائتماني للمتجر (ل.س)</label><input type="text" inputmode="decimal" name="business_credit_limit" class="form-control" value="<?php echo htmlspecialchars($settings['business_credit_limit'] ?? '0'); ?>"></div>
                </div>
            </div>

            <!-- بطاقة نظام الحجوزات -->
            <div class="settings-card card-booking">
                <div class="card-header"><div class="card-icon"><i class="fas fa-calendar-check"></i></div><h3>إعدادات نظام الحجوزات</h3></div>
                <div class="card-body">
                    <div class="form-group"><label>عمولة المنصة من الحجوزات (%)</label><div class="input-group"><span class="input-group-text">%</span><input type="text" inputmode="decimal" name="booking_commission_rate" class="form-control" value="<?php echo htmlspecialchars($settings['booking_commission_rate'] ?? '0'); ?>"></div></div>
                    <div class="form-group"><label>الحد الائتماني لأنشطة الحجوزات (ل.س)</label><input type="text" inputmode="decimal" name="booking_credit_limit" class="form-control" value="<?php echo htmlspecialchars($settings['booking_credit_limit'] ?? '0'); ?>"></div>
                    <!-- **الحقل الجديد هنا** -->
                    <div class="form-group"><label>فترة السماح لدفع العربون (بالدقائق)</label><div class="input-group"><span class="input-group-text"><i class="fas fa-hourglass-half"></i></span><input type="text" inputmode="numeric" name="booking_grace_period_minutes" class="form-control" value="<?php echo htmlspecialchars($settings['booking_grace_period_minutes'] ?? '30'); ?>"></div></div>
                </div>
            </div>

             <!-- بطاقة الإعدادات المالية العامة -->
             <div class="settings-card card-financial">
                <div class="card-header"><div class="card-icon"><i class="fas fa-coins"></i></div><h3>إعدادات عامة</h3></div>
                <div class="card-body">
                    <div class="form-group"><label>سعر صرف الدولار (مقابل 1 دولار)</label><input type="text" inputmode="decimal" name="usd_to_syp_rate" class="form-control" value="<?php echo htmlspecialchars($settings['usd_to_syp_rate'] ?? '0'); ?>"></div>
                </div>
            </div>
        </div>
        <div class="form-footer"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ كل التغييرات</button></div>
    </form>
</div>

<script>
    // **لا يوجد أي تغيير على JavaScript، سيعمل كما هو**
    async function saveSettings() {
        const form = document.getElementById('settings-form');
        const formData = new FormData(form);
        const saveButton = form.querySelector('.btn-save');
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
        try {
            const response = await fetch('php/update_settings.php', { method: 'POST', body: formData });
            const result = await response.json();
            alert(result.message);
            if (result.success) { 
                window.location.reload(); 
            }
        } catch (error) {
            alert('حدث خطأ في الشبكة.');
            console.error('Save Settings Error:', error);
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i class="fas fa-save"></i> حفظ كل التغييرات';
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const settingsForm = document.getElementById('settings-form');
        if (settingsForm) {
            const inputs = settingsForm.querySelectorAll('.form-control');
            inputs.forEach(input => {
                const numericValue = parseFloat(input.value);
                if (!isNaN(numericValue)) {
                    if (numericValue % 1 === 0) {
                        input.value = Number(numericValue);
                    } else {
                        input.value = Number(numericValue.toFixed(2));
                    }
                }
                input.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/[^0-9.]/g, '');
                });
            });
        }
    });
</script>

<?php include 'footer.php'; ?>