<?php
// ========================================================================
// Syriazzle Admin - Financial Profile (Final Multi-Currency Version 5.0)
// ========================================================================
$page_title = 'الملف المالي';
require_once 'header.php';

// --- حارس البوابة 1: التحقق من صلاحية "عرض المالية" ---
if (!hasPermission('view_financials')) {
    echo "<div style='text-align:center; padding:50px; color:red;'><h2>وصول غير مصرح به.</h2></div>"; 
    include 'footer.php'; exit;
}

// --- 1. جلب البيانات الأساسية ---
$user_type = $_GET['type'] ?? '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (($user_type !== 'business' && $user_type !== 'driver') || $user_id === 0) {
    die("<div class='alert alert-danger'>خطأ: بيانات الرابط غير صالحة.</div>");
}

try {
    // جلب سعر الصرف الحالي (للتحويل التقديري في العرض فقط)
    $usd_rate_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    $usd_rate = $usd_rate_stmt ? (float)$usd_rate_stmt->fetchColumn() : 15000;

    // جلب بيانات المستخدم والإحصائيات بناءً على النوع
    if ($user_type === 'business') {
        // للمتاجر: نجلب العملة (currency) والأرصدة (commission & payouts)
        $stmt = $pdo->prepare("
            SELECT b.id, b.name, b.commission_balance, b.payouts_balance, b.currency, b.balance as total_sales, b.governorate_id, 
            (SELECT COUNT(*) FROM orders WHERE business_id = b.id AND status = 'delivered') as total_orders
            FROM businesses b
            WHERE b.id = ?
        ");
        $user_label = 'النشاط التجاري';
        // استعلام لجلب سجل الطلبات (مع العملة)
        $orders_sql = "SELECT id, created_at, status, total_price, currency FROM orders WHERE business_id = ? ORDER BY created_at DESC LIMIT 100";
    } else { 
        // للسائقين: دائماً ليرة سورية، لا يوجد payouts_balance عادةً (نعتبره 0)
        $stmt = $pdo->prepare("
            SELECT d.id, d.full_name as name, d.commission_balance, 0 as payouts_balance, 'SYP' as currency, d.governorate_id,
            (SELECT SUM(delivery_fee + tip_amount) FROM orders WHERE driver_id = d.id AND status = 'delivered') as total_sales,
            (SELECT COUNT(*) FROM orders WHERE driver_id = d.id AND status = 'delivered') as total_orders
            FROM drivers d
            WHERE d.id = ?
        ");
        $user_label = 'السائق';
        // استعلام طلبات السائق (نظهر ربحه الخاص delivery_fee + tip)
        $orders_sql = "SELECT id, created_at, status, (delivery_fee + tip_amount) as total_price, 'SYP' as currency FROM orders WHERE driver_id = ? ORDER BY created_at DESC LIMIT 100";
    }
    
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) { die("<div class='alert alert-danger'>خطأ: الشريك غير موجود.</div>"); }

    // --- حارس البوابة 2: التحقق من صلاحية المحافظة ---
    if (!hasPermission('super_admin_access_all') && isset($admin_governorate_id)) {
        if ($admin_governorate_id !== (int)$user['governorate_id']) {
            echo "<div class='alert alert-danger'>هذا الملف المالي لا يتبع للمحافظة الخاصة بك.</div>"; 
            include 'footer.php'; exit;
        }
    }

    // تحديد عملة الشريك الحالية
    $partner_currency = $user['currency'] ?? 'SYP';
    $currency_symbol = ($partner_currency === 'USD') ? '$' : 'ل.س';

    // حساب الأرصدة (كما هي في قاعدة البيانات)
    $commission_debt = abs($user['commission_balance']); // الدين دائماً يظهر كموجب في العرض (هو سالب في الداتابيز)
    $payouts_due = $user['payouts_balance'] ?? 0;
    
    // صافي الرصيد: (المستحقات - الديون)
    // إذا كان الناتج موجباً: المنصة تدفع للشريك.
    // إذا كان سالباً: الشريك يدفع للمنصة.
    $net_balance = $payouts_due - $commission_debt; 
    
    // جلب سجل المعاملات
    $transactions = $pdo->prepare("SELECT * FROM transactions WHERE user_type = ? AND user_id = ? ORDER BY created_at DESC LIMIT 50");
    $transactions->execute([$user_type, $user_id]);
    $transactions_history = $transactions->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب سجل الطلبات
    $orders = $pdo->prepare($orders_sql);
    $orders->execute([$user_id]);
    $orders_history = $orders->fetchAll(PDO::FETCH_ASSOC);

    $transaction_type_translations = [
        'order_revenue' => 'إيراد طلب', 'delivery_fee' => 'أجرة توصيل', 
        'commission' => 'عمولة مستحقة', 'payout' => 'تسوية (دفع)', 'payment' => 'تسوية (قبض)', 
        'adjustment' => 'تسوية يدوية', 'payout_due' => 'استحقاق حجز'
    ];
    $status_translations = [
        'pending_approval' => 'بانتظار الموافقة', 'preparing' => 'قيد التحضير', 
        'ready_for_pickup' => 'جاهز للاستلام', 'accepted' => 'مقبول', 
        'picked_up' => 'مع السائق', 'delivered' => 'تم التوصيل', 
        'canceled' => 'ملغي', 'confirmed' => 'مؤكد'
    ];

} catch (PDOException $e) { die("خطأ في جلب البيانات: " . $e->getMessage()); }

// دالة تنسيق العرض (PHP)
function format_money_display($amount, $currency_symbol) {
    if ($currency_symbol === '$') return '$' . number_format((float)$amount, 2);
    return number_format((float)$amount) . ' ل.س';
}
?>

<!-- تنسيقات خاصة للصفحة -->
<link rel="stylesheet" href="css/financial_profile.css">

<div class="page-header">
    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
        <h1>
            الملف المالي: <?php echo htmlspecialchars($user['name']); ?> 
            <span class="badge" style="font-size:0.5em; vertical-align:middle; background:#6c757d;"><?php echo $user_label; ?></span>
            <span class="badge" style="font-size:0.5em; vertical-align:middle; background:<?php echo $partner_currency == 'USD' ? '#198754' : '#0d6efd'; ?>;"><?php echo $partner_currency; ?></span>
        </h1>
        
        <!-- أدوات التحويل (للعرض فقط) -->
        <div class="currency-controls" style="display:flex; align-items:center; gap:10px;">
            <label style="font-size:0.9rem; color:#555;">عرض تقديري:</label>
            <div class="currency-toggle">
                <span style="font-weight: bold; font-size:0.9rem;">أصلي</span>
                <label class="currency-switch">
                  <input type="checkbox" id="currency-toggle-checkbox" onchange="toggleDisplayCurrency()">
                  <span class="slider"></span>
                </label>
                <span style="font-weight: bold; font-size:0.9rem;">تحويل</span>
            </div>
            <!-- حقل مخفي لسعر الصرف لاستخدامه في JS -->
            <input type="hidden" id="usd-rate-input" value="<?php echo $usd_rate; ?>">
        </div>
    </div>
</div>

<div class="profile-grid-top">
    <!-- 1. بطاقة الميزان المالي (الأهم) -->
    <div class="card balance-card-unified">
        <div class="card-header"><h3><i class="fas fa-balance-scale"></i> الميزان المالي (<?php echo $currency_symbol; ?>)</h3></div>
        <div class="card-body">
            <!-- الديون -->
            <div class="balance-row debt">
                <div class="label">ديون العمولات (على الشريك)</div>
                <!-- data-original يخزن القيمة الأصلية للتحويل لاحقاً -->
                <div class="amount dynamic-amount" data-original="<?php echo $commission_debt; ?>">
                    <?php echo format_money_display($commission_debt, $currency_symbol); ?>
                </div>
            </div>
            
            <!-- المستحقات (للمتاجر فقط) -->
            <?php if ($user_type === 'business'): ?>
            <div class="balance-row credit">
                <div class="label">مستحقات الحجوزات (للشريك)</div>
                <div class="amount dynamic-amount" data-original="<?php echo $payouts_due; ?>">
                    <?php echo format_money_display($payouts_due, $currency_symbol); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <hr>
            
            <!-- الصافي -->
            <div class="balance-row net-balance <?php echo ($net_balance >= 0) ? 'positive' : 'negative'; ?>">
                <div class="label">
                    <strong>
                        <?php 
                            if ($net_balance > 0) echo "المطلوب دفعه للشريك";
                            elseif ($net_balance < 0) echo "المطلوب تحصيله من الشريك";
                            else echo "الرصيد متوازن (صفر)";
                        ?>
                    </strong>
                </div>
                <div class="amount dynamic-amount" data-original="<?php echo abs($net_balance); ?>">
                    <strong><?php echo format_money_display(abs($net_balance), $currency_symbol); ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 2. بطاقة مؤشرات الأداء -->
    <div class="card">
         <div class="card-header"><h3><i class="fas fa-chart-bar"></i> الأداء العام</h3></div>
         <div class="card-body">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="value"><?php echo number_format($user['total_orders'] ?? 0); ?></div>
                    <div class="label">إجمالي الطلبات</div>
                </div>
                <div class="kpi-card">
                    <div class="value amount dynamic-amount" data-original="<?php echo $user['total_sales'] ?? 0; ?>">
                        <?php echo format_money_display($user['total_sales'] ?? 0, $currency_symbol); ?>
                    </div>
                    <div class="label">إجمالي المبيعات/الإيراد</div>
                </div>
            </div>
         </div>
    </div>

    <!-- 3. بطاقة تنفيذ التسوية (Action) -->
    <div class="card actions-card">
        <div class="card-header"><h3><i class="fas fa-hand-holding-usd"></i> تنفيذ تسوية</h3></div>
        <div class="card-body">
            <?php if(hasPermission('process_payouts')): ?>
            <form id="settlement-form" onsubmit="submitSettlement(); return false;">
                <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <!-- نمرر العملة للملف الخلفي لضمان المعالجة الصحيحة -->
                <input type="hidden" name="currency" value="<?php echo $partner_currency; ?>">

                <p class="form-hint" style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
                    العملة المعتمدة: <strong><?php echo $partner_currency; ?></strong>.
                    <?php 
                        if ($net_balance > 0) echo " <span style='color:green'>أنت تدفع للشريك</span> لتصفير رصيده.";
                        elseif ($net_balance < 0) echo " <span style='color:red'>أنت تقبض من الشريك</span> لتسوية ديونه.";
                        else echo " الرصيد صفر، لا يلزم إجراء.";
                    ?>
                </p>

                <div class="form-group">
                    <label for="settlement-amount">المبلغ (<?php echo $currency_symbol; ?>)</label>
                    <input type="number" id="settlement-amount" name="amount" class="form-control" step="any" placeholder="0.00" required <?php if($net_balance == 0) echo 'disabled'; ?>>
                </div>
                 <div class="form-group">
                    <label for="settlement-desc">ملاحظات (رقم إيصال، طريقة الدفع)</label>
                    <input type="text" id="settlement-desc" name="description" class="form-control" placeholder="مثال: تحويل بنكي رقم..." required <?php if($net_balance == 0) echo 'disabled'; ?>>
                </div>
                <button type="submit" class="btn-submit btn-payout" <?php if($net_balance == 0) echo 'disabled'; ?>>تنفيذ العملية</button>
            </form>
            <?php else: ?>
                <div class="alert alert-warning">أنت لا تملك صلاحية تنفيذ التسويات المالية.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- بطاقة السجلات -->
<div class="card records-card" style="margin-top: 20px;">
    <div class="tabs">
        <button class="tab-link active" onclick="openTab(event, 'transactions')">سجل المعاملات المالية</button>
        <button class="tab-link" onclick="openTab(event, 'orders')">سجل الطلبات</button>
    </div>
    
    <div id="transactions" class="tab-content active">
        <div style="max-height: 500px; overflow-y: auto;">
            <table class="data-table" id="transactions-table">
                <thead><tr><th>التاريخ</th><th>النوع</th><th>الوصف</th><th>المبلغ (<?php echo $currency_symbol; ?>)</th></tr></thead>
                <tbody>
                    <?php if (empty($transactions_history)): ?>
                        <tr><td colspan="4" style="text-align:center;">لا توجد معاملات سابقة.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions_history as $tx): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?></td>
                                <td><?php echo $transaction_type_translations[$tx['transaction_type']] ?? $tx['transaction_type']; ?></td>
                                <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                <td style="direction:ltr; font-weight:bold; color: <?php echo $tx['amount'] >= 0 ? 'green' : 'red'; ?>;">
                                    <?php echo ($tx['amount'] > 0 ? '+' : '') . format_money_display($tx['amount'], $currency_symbol); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="orders" class="tab-content">
        <div style="max-height: 500px; overflow-y: auto;">
            <table class="data-table" id="orders-table">
                <thead><tr><th>#</th><th>التاريخ</th><th>الحالة</th><th>القيمة (<?php echo $currency_symbol; ?>)</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($orders_history)): ?>
                        <tr><td colspan="5" style="text-align:center;">لا توجد طلبات.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders_history as $order): ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                <td><?php echo $status_translations[$order['status']] ?? $order['status']; ?></td>
                                <td dir="ltr"><?php echo format_money_display($order['total_price'], ($order['currency'] ?? 'SYP') == 'USD' ? '$' : 'ل.س'); ?></td>
                                <td><a href="order_details.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn-sm">تفاصيل</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // المتغيرات القادمة من PHP
    const partnerCurrency = "<?php echo $partner_currency; ?>"; // 'USD' or 'SYP'
    const usdRateInput = document.getElementById('usd-rate-input');

    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
        tablinks = document.getElementsByClassName("tab-link");
        for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    // دالة التحويل للعرض فقط (Visual only)
    function toggleDisplayCurrency() {
        const checkbox = document.getElementById('currency-toggle-checkbox');
        const showConverted = checkbox.checked; // true = اعرض العملة المقابلة
        const rate = parseFloat(usdRateInput.value) || 15000;

        document.querySelectorAll('.dynamic-amount').forEach(el => {
            const originalVal = parseFloat(el.dataset.original);
            if (isNaN(originalVal)) return;

            let displayVal = 0;
            let symbol = '';
            let isApprox = false;

            if (partnerCurrency === 'USD') {
                // العملة الأصلية دولار
                if (showConverted) {
                    displayVal = originalVal * rate; // تحويل لليرة
                    symbol = ' ل.س';
                    isApprox = true;
                } else {
                    displayVal = originalVal; // بقاء ع الدولار
                    symbol = ' $';
                }
            } else {
                // العملة الأصلية ليرة
                if (showConverted) {
                    displayVal = originalVal / rate; // تحويل للدولار
                    symbol = ' $';
                    isApprox = true;
                } else {
                    displayVal = originalVal; // بقاء ع الليرة
                    symbol = ' ل.س';
                }
            }

            // التنسيق
            let formatted = '';
            if (symbol.includes('$')) {
                formatted = symbol + Number(displayVal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                formatted = Number(displayVal).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + symbol;
            }
            
            if (isApprox) {
                formatted += ' <small style="color:#999; font-size:0.6em;">(تقريبي)</small>';
                el.style.color = '#555';
            } else {
                el.style.color = ''; // استعادة اللون الأصلي
            }
            
            el.innerHTML = formatted;
        });
    }

    async function submitSettlement() {
        const form = document.getElementById('settlement-form');
        const formData = new FormData(form);
        const amount = parseFloat(formData.get('amount'));

        if (isNaN(amount) || amount <= 0) { alert('الرجاء إدخال مبلغ صحيح.'); return; }
        if (formData.get('description').trim() === '') { alert('الرجاء كتابة ملاحظات.'); return; }

        if (confirm(`تأكيد عملية التسوية بقيمة ${amount} ${partnerCurrency === 'USD' ? 'دولار' : 'ليرة'}؟\nهذا الإجراء لا يمكن التراجع عنه.`)) {
            const btn = form.querySelector('.btn-submit');
            btn.disabled = true;
            btn.textContent = 'جاري المعالجة...';
            
            try {
                // تأكد من وجود ملف process_settlement.php في مجلد php
                const response = await fetch('php/process_settlement.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    alert('خطأ: ' + result.message);
                    btn.disabled = false;
                    btn.textContent = 'تنفيذ العملية';
                }
            } catch (error) { 
                alert('فشل الإجراء (خطأ في الاتصال).'); 
                btn.disabled = false;
                btn.textContent = 'تنفيذ العملية';
            }
        }
    }
    
    // تفعيل التبويب الأول عند التحميل
    document.addEventListener('DOMContentLoaded', () => {
        if(document.querySelector('.tab-link.active')) {
            document.querySelector('.tab-link.active').click();
        }
    });
</script>

<?php include 'footer.php'; ?>