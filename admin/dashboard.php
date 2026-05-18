<?php

$page_title = 'مركز التحكم بالأنشطة';

require_once 'header.php';



// --- حارس البوابة: التحقق من الصلاحيات ---

if (!hasPermission('view_businesses')) {

    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;

}



try {

    // --- بناء شروط WHERE الديناميكية والآمنة ---

    $where_conditions = ['b.deleted_at IS NULL'];

    $params = [];

    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {

        $where_conditions[] = 'b.governorate_id = ?';

        $params[] = $admin_governorate_id;

    }

    $where_clause = "WHERE " . implode(' AND ', $where_conditions);

    

    // --- حساب الإحصائيات مع الفلترة الجغرافية ---

    $total_businesses_stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses b $where_clause");

    $total_businesses_stmt->execute($params);

    $total_businesses = $total_businesses_stmt->fetchColumn();



    $pending_conditions = array_merge($where_conditions, ["b.status = 'pending'"]);

    $pending_where_sql = "WHERE " . implode(' AND ', $pending_conditions);

    $stmt_pending = $pdo->prepare("SELECT COUNT(*) FROM businesses b $pending_where_sql");

    $stmt_pending->execute($params); 

    $pending_businesses = $stmt_pending->fetchColumn();



    // إحصائيات عامة

    $total_classifieds = $pdo->query("SELECT COUNT(*) FROM form_submissions")->fetchColumn();

    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();

    $total_reviews = $pdo->query("SELECT COUNT(*) FROM business_reviews")->fetchColumn();



    // --- الاستعلام المطور: جلب العملة (currency) ---

    $sql = "

        SELECT 

            b.id, b.name, b.status, b.currency, /* <--- تمت الإضافة هنا */

            u.username,

            b.commission_balance, 

            b.commission_rate, 

            b.credit_limit,

            b.booking_commission_rate,

            b.booking_credit_limit,

            b.business_type

        FROM businesses b

        LEFT JOIN users u ON b.user_id = u.id

        $where_clause

        ORDER BY b.created_at DESC

    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);



} catch (PDOException $e) { die("خطأ في جلب البيانات: " . $e->getMessage()); }



// دالة تنسيق العملة

function formatMoney($amount, $currency) {

    if ($currency === 'USD') return '$' . number_format((float)$amount, 2);

    return number_format((float)$amount) . ' ل.س';

}

?>

<link rel="stylesheet" href="css/admin_dashboard.css">

<style>

    .actions-cell { display: flex; align-items: center; gap: 5px; justify-content: center; }

    .action-btn { background: none; border: none; cursor: pointer; padding: 8px 10px; border-radius: 6px; transition: background-color 0.2s; font-size: 16px; color: #6c757d; }

    .action-btn:hover { background-color: #f8f9fa; }

    .action-btn.financial { color: #198754; }

    .action-btn.financial:hover { background-color: #d1e7dd; }

    .action-btn.edit { color: #0d6efd; }

    .action-btn.edit:hover { background-color: #e7f1ff; }

    .action-btn.settings { color: #6f42c1; }

    .action-btn.settings:hover { background-color: #e6dff6; }

    .action-btn.delete { color: #dc3545; }

    .action-btn.delete:hover { background-color: #f8d7da; }



    .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }

    .modal-content { background-color: #fff; margin: auto; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 500px; }

    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; }

    .modal-close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }

    #settings-form h5 { margin-top: 20px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e9ecef; }

    #settings-form h5:first-of-type { margin-top: 0; }

    input#live-search-input { padding: 15px; width: 600px; border: none; border-radius: 10px; box-shadow: 3px 3px 10px #0000ffad; margin-right: 20px; }

    

    /* شارة العملة */

    .currency-badge {

        font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold;

        display: inline-block; min-width: 35px; text-align: center;

    }

    .currency-usd { background-color: #e7f3ff; color: #0d6efd; border: 1px solid #b6d4fe; }

    .currency-syp { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }

    .data-table tbody tr {border-bottom:1px solid rgb(244, 116, 24)}

    .data-table tbody td{border-bottom:none}

</style>



<!-- بطاقات الإحصائيات -->

<div class="stats-grid">

    <div class="stat-card blue"><div class="info"><div class="number"><?php echo $total_businesses; ?></div><div class="label">إجمالي الأنشطة</div></div></div>

    <div class="stat-card green"><div class="info"><div class="number"><?php echo $total_classifieds; ?></div><div class="label">الإعلانات</div></div></div>

    <div class="stat-card purple"><div class="info"><div class="number"><?php echo $total_users; ?></div><div class="label">المستخدمون</div></div></div>

    <div class="stat-card yellow"><div class="info"><div class="number"><?php echo $pending_businesses; ?></div><div class="label">بانتظار المراجعة</div></div></div>

    <div class="stat-card red"><div class="info"><div class="number"><?php echo $total_reviews; ?></div><div class="label">المراجعات</div></div></div>

    <div class="stat-card cyan"><div class="info"><div class="number">0</div><div class="label">المتابعات</div></div></div>

</div>



<div class="dashboard-header">

    <h1>إدارة الأنشطة التجارية</h1>

    <?php if (hasPermission('add_business')): ?>

        <a href="add_business.php" class="add-new-btn"><i class="fas fa-plus"></i> إضافة نشاط</a>

    <?php endif; ?>

</div>



<!-- نموذج البحث اللحظي -->

<div class="filter-card" style="margin-bottom: 20px;">

    <div class="filter-group">

        <label for="live-search-input">البحث عن نشاط تجاري:</label>

        <input type="text" id="live-search-input" onkeyup="liveSearch()" placeholder="اكتب اسم النشاط أو نوعه للفلترة...">

    </div>

</div>



<div class="data-table">

    <table id="businesses-table">

        <thead>

            <tr>

                <th>اسم النشاط</th>

                <th>العملة</th> <!-- عمود جديد -->

                <th>صاحب الحساب</th>

                <th>رصيد العمولة</th>

                <th>الحد المتبقي</th>

                <th>العمولة (%)</th>

                <th>الحالة</th>

                <th style="text-align: center;">إجراءات</th>

            </tr>

        </thead>

        <tbody>

            <?php if (empty($businesses)): ?>

                <tr><td colspan="8" style="text-align: center; padding: 20px;">لا توجد أنشطة لعرضها.</td></tr>

            <?php endif; ?>

            <?php foreach ($businesses as $business): 

                $currency = $business['currency'] ?? 'SYP';

                $currencySymbol = ($currency === 'USD') ? '$' : 'ل.س';

                $badgeClass = ($currency === 'USD') ? 'currency-usd' : 'currency-syp';

            ?>

                <tr data-business-id="<?php echo $business['id']; ?>">

                    <td class="business-name">

                        <strong><?php echo htmlspecialchars($business['name']); ?></strong><br>

                        <small style="color:#6c757d; font-weight:bold;"><?php echo ucfirst($business['business_type']); ?></small>

                    </td>

                    <!-- عرض العملة -->

                    <td><span class="currency-badge <?php echo $badgeClass; ?>"><?php echo $currency; ?></span></td>

                    

                    <td><?php echo htmlspecialchars($business['username'] ?? '<em>إداري</em>'); ?></td>

                    

                    <!-- عرض رصيد العمولة مع العملة -->

                    <td class="financial-info commission-balance-cell" style="color: <?php echo ($business['commission_balance'] < 0) ? '#f58611' : '#198754'; ?>; direction: ltr; font-weight: bold;">

                        <?php echo formatMoney($business['commission_balance'], $currency); ?>

                    </td>

                    

                    <?php

                        $credit_limit_to_use = ($business['business_type'] === 'booking' || $business['business_type'] === 'hybrid') ? $business['booking_credit_limit'] : $business['credit_limit'];

                        $remaining_credit = (float)$credit_limit_to_use - abs((float)$business['commission_balance']);

                    ?>

                    

                    <!-- عرض الحد المتبقي مع العملة -->

                    <td class="financial-info remaining-credit-cell" style="direction: ltr;">

                        <?php echo formatMoney($remaining_credit, $currency); ?>

                    </td>

                    

                    <?php

                        $commission_to_display = ($business['business_type'] === 'booking' || $business['business_type'] === 'hybrid') ? $business['booking_commission_rate'] : $business['commission_rate'];

                    ?>

                    <td class="financial-info commission-rate-cell"><?php echo $commission_to_display; ?>%</td>

                    

                    <td>

                        <form action="change_status.php" method="POST" style="display:inline;">

                            <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">

                            <select name="status" class="status-select" onchange="this.form.submit()">

                                <option value="approved" <?php if($business['status'] === 'approved') echo 'selected'; ?>>موافق عليه</option>

                                <option value="pending" <?php if($business['status'] === 'pending') echo 'selected'; ?>>قيد المراجعة</option>

                                <option value="rejected" <?php if($business['status'] === 'rejected') echo 'selected'; ?>>مرفوض</option>

                            </select>

                        </form>

                    </td>

                    <td class="actions-cell">

                        <?php if (hasPermission('view_financials')): ?>

                            <a href="financial_profile.php?type=business&id=<?php echo $business['id']; ?>" class="action-btn financial" title="عرض الملف المالي"><i class="fas fa-chart-line"></i></a>

                        <?php endif; ?>

                        <?php

                        $can_edit_delivery = ($business['business_type'] === 'delivery' || $business['business_type'] === 'hybrid') && hasPermission('edit_business');

                        $can_edit_booking = ($business['business_type'] === 'booking' || $business['business_type'] === 'hybrid') && hasPermission('edit_booking_settings');

                        if ($can_edit_delivery || $can_edit_booking):

                        ?>

                            <button class="action-btn settings" title="تعديل إعدادات العمولة والائتمان" 

                                    onclick="openSettingsModal(<?php echo htmlspecialchars(json_encode($business, JSON_UNESCAPED_UNICODE)); ?>)">

                                <i class="fas fa-cog"></i>

                            </button>

                        <?php endif; ?>

                        <?php

                        if (hasPermission('edit_business')):

                            $edit_link = ($business['business_type'] === 'delivery') 

                                        ? "edit_business.php?id={$business['id']}" 

                                        : "../booking_dashboard.php?business_id={$business['id']}";

                            $edit_title = ($business['business_type'] === 'delivery')

                                        ? "تعديل بيانات المتجر"

                                        : "إدارة لوحة تحكم النشاط";

                        ?>

                            <a href="<?php echo $edit_link; ?>" class="action-btn edit" title="<?php echo $edit_title; ?>" target="_blank"><i class="fas fa-edit"></i></a>

                        <?php endif; ?>

                        <?php if (hasPermission('delete_business')): ?>

                            <button class="action-btn delete" title="حذف النشاط" 

                                    onclick="confirmDelete(<?php echo $business['id']; ?>, '<?php echo htmlspecialchars(addslashes($business['name'])); ?>')">

                                <i class="fas fa-trash-alt"></i>

                            </button>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>



<!-- النافذة المنبثقة لإعدادات العمولة -->

<div class="modal-overlay" id="settings-modal">

    <div class="modal-content">

        <div class="modal-header">

            <h4 id="settings-modal-title">تعديل الإعدادات</h4>

            <button class="modal-close-btn" onclick="closeModal('settings-modal')">&times;</button>

        </div>

        <form id="settings-form" onsubmit="submitSettingsForm(event)">

            <input type="hidden" id="settings-business-id" name="business_id">

            

            <p style="text-align: center; color: #666; font-size: 0.9em; margin-bottom: 15px;">

                العملة المعتمدة لهذا المتجر هي: <strong id="settings-currency-label" style="color: #0d6efd;"></strong>

            </p>



            <div id="delivery-settings-section" style="display: none;">

                <h5>إعدادات التوصيل</h5>

                <div class="form-group"><label for="settings-commission-rate">نسبة عمولة التوصيل (%)</label><input type="number" step="any" id="settings-commission-rate" name="commission_rate" class="form-control"></div>

                <!-- عرض رمز العملة بجانب الحد الائتماني -->

                <div class="form-group"><label for="settings-credit-limit">الحد الائتماني للتوصيل (<span class="currency-span"></span>)</label><input type="number" step="any" id="settings-credit-limit" name="credit_limit" class="form-control"></div>

            </div>

            <div id="booking-settings-section" style="display: none;">

                <h5>إعدادات الحجوزات</h5>

                <div class="form-group"><label for="settings-booking-commission-rate">نسبة عمولة الحجوزات (%)</label><input type="number" step="any" id="settings-booking-commission-rate" name="booking_commission_rate" class="form-control"></div>

                <div class="form-group"><label for="settings-booking-credit-limit">الحد الائتماني للحجوزات (<span class="currency-span"></span>)</label><input type="number" step="any" id="settings-booking-credit-limit" name="booking_credit_limit" class="form-control"></div>

            </div>

            <button type="submit" class="btn-submit">حفظ التغييرات</button>

        </form>

    </div>

</div>



<script>

    function closeModal(modalId) {

        document.getElementById(modalId).style.display = 'none';

    }



    function openSettingsModal(businessData) {

        const modal = document.getElementById('settings-modal');

        modal.querySelector('#settings-modal-title').textContent = `تعديل إعدادات: ${businessData.name}`;

        modal.querySelector('#settings-business-id').value = businessData.id;

        

        // عرض العملة في المودال

        const currency = businessData.currency || 'SYP';

        const symbol = currency === 'USD' ? '$' : 'ل.س';

        document.getElementById('settings-currency-label').textContent = currency;

        document.querySelectorAll('.currency-span').forEach(span => span.textContent = symbol);



        const deliverySection = modal.querySelector('#delivery-settings-section');

        const bookingSection = modal.querySelector('#booking-settings-section');

        

        const isDelivery = businessData.business_type === 'delivery' || businessData.business_type === 'hybrid';

        const isBooking = businessData.business_type === 'booking' || businessData.business_type === 'hybrid';

        

        deliverySection.style.display = isDelivery ? 'block' : 'none';

        bookingSection.style.display = isBooking ? 'block' : 'none';



        if (isDelivery) {

            modal.querySelector('#settings-commission-rate').value = Number(parseFloat(businessData.commission_rate).toFixed(2));

            modal.querySelector('#settings-credit-limit').value = parseInt(businessData.credit_limit);

        }

        if (isBooking) {

            modal.querySelector('#settings-booking-commission-rate').value = Number(parseFloat(businessData.booking_commission_rate || 0).toFixed(2));

            modal.querySelector('#settings-booking-credit-limit').value = parseInt(businessData.booking_credit_limit || 0);

        }



        modal.style.display = 'flex';

    }



    async function submitSettingsForm(event) {

        event.preventDefault();

        const form = document.getElementById('settings-form');

        const formData = new FormData(form);

        const businessId = formData.get('business_id');

        const submitButton = form.querySelector('.btn-submit');

        submitButton.disabled = true;

        submitButton.innerHTML = 'جاري الحفظ...';



        try {

            const response = await fetch('php/update_business_specific_settings.php', { method: 'POST', body: formData });

            const result = await response.json();



            if (result.success && result.newData) {

                // إعادة تحميل الصفحة لتحديث البيانات بشكل كامل ودقيق

                window.location.reload(); 

            } else {

                alert(result.message || 'فشل تحديث البيانات.');

                submitButton.disabled = false;

                submitButton.innerHTML = 'حفظ التغييرات';

            }

        } catch(error) {

            alert('حدث خطأ في الشبكة.');

            submitButton.disabled = false;

            submitButton.innerHTML = 'حفظ التغييرات';

        }

    }



    function confirmDelete(id, name) {

        const message = `تحذير! هل أنت متأكد من حذف النشاط '${name}'؟\nسيتم نقله إلى سلة المحذوفات.`;

        if (confirm(message)) {

            window.location.href = `php/safe_delete_business.php?id=${id}`;

        }

    }



    function liveSearch() {

        const input = document.getElementById("live-search-input");

        const filter = input.value.toUpperCase();

        const table = document.getElementById("businesses-table");

        const tr = table.getElementsByTagName("tr");



        for (let i = 1; i < tr.length; i++) { 

            const td = tr[i].getElementsByClassName("business-name")[0];

            if (td) {

                const txtValue = td.textContent || td.innerText;

                if (txtValue.toUpperCase().indexOf(filter) > -1) {

                    tr[i].style.display = "";

                } else {

                    tr[i].style.display = "none";

                }

            }

        }

    }

</script>



<?php include 'footer.php'; ?>