<?php
// ========================================================================
// Syriazzle Admin - Financial Dashboard (Corrected Logic V9.0)
// المنطق الجديد: الرصيد الموجب (> 0) في commission_balance يعني دين على الشريك
// ========================================================================
$page_title = 'مركز القيادة المالية';
require_once 'header.php'; 

if (!hasPermission('view_financials')) {
    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;
}

try {
    // --- 1. بناء شروط WHERE (للفلترة الجغرافية) ---
    $governorate_condition = '';
    $params = [];
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $governorate_condition = 'WHERE governorate_id = ?';
        $params[] = $admin_governorate_id;
    }

    // --- 2. الاستعلامات المالية (معدلة للمنطق الجديد) ---
    
    // أ. الديون (Commission Debt)
    // الآن نبحث عن القيم الموجبة (> 0) لأن الدين يزداد بالجمع
    // ------------------------------------------------
    // 1. ديون السائقين (دائماً بالليرة)
    $sql_debt_drivers = "SELECT SUM(commission_balance) FROM drivers " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "commission_balance > 0";
    $stmt = $pdo->prepare($sql_debt_drivers); $stmt->execute($params);
    $debt_drivers_syp = $stmt->fetchColumn() ?? 0; // القيمة تأتي موجبة أصلاً

    // 2. ديون المتاجر (SYP)
    $sql_debt_biz_syp = "SELECT SUM(commission_balance) FROM businesses " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "currency = 'SYP' AND commission_balance > 0";
    $stmt = $pdo->prepare($sql_debt_biz_syp); $stmt->execute($params);
    $debt_biz_syp = $stmt->fetchColumn() ?? 0;

    // 3. ديون المتاجر (USD)
    $sql_debt_biz_usd = "SELECT SUM(commission_balance) FROM businesses " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "currency = 'USD' AND commission_balance > 0";
    $stmt = $pdo->prepare($sql_debt_biz_usd); $stmt->execute($params);
    $debt_biz_usd = $stmt->fetchColumn() ?? 0;

    // المجاميع للعرض
    $total_debt_syp = $debt_drivers_syp + $debt_biz_syp;
    $total_debt_usd = $debt_biz_usd;


    // ب. المستحقات (Payouts Due)
    // هذه تبقى كما هي (قيم موجبة تعني أن للمتجر أموال عند المنصة)
    // ------------------------------------------------
    // 1. مستحقات المتاجر (SYP)
    $sql_payout_syp = "SELECT SUM(payouts_balance) FROM businesses " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "currency = 'SYP'";
    $stmt = $pdo->prepare($sql_payout_syp); $stmt->execute($params);
    $total_payout_syp = $stmt->fetchColumn() ?? 0;

    // 2. مستحقات المتاجر (USD)
    $sql_payout_usd = "SELECT SUM(payouts_balance) FROM businesses " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "currency = 'USD'";
    $stmt = $pdo->prepare($sql_payout_usd); $stmt->execute($params);
    $total_payout_usd = $stmt->fetchColumn() ?? 0;


    // ج. صافي المركز المالي (Net Position)
    // -------------------------------------------------------
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    $usd_rate = (float)($stmt_rate->fetchColumn() ?: 15000);

    // معادلة الصافي: (ما للمتاجر - ما على الشركاء)
    $net_syp = $total_payout_syp - $total_debt_syp;
    $net_usd = $total_payout_usd - $total_debt_usd;
    
    // القيمة التقديرية بالليرة
    $net_position_estimated = $net_syp + ($net_usd * $usd_rate);


    // د. إجمالي أرباح العمولات (Total Earnings)
    // -------------------------------------------------------
    // نجمع القيم المطلقة للمعاملات من نوع commission لأنها تخزن بالسالب
    
    // 1. أرباح الليرة
    $sql_earn_syp = "
        SELECT SUM(ABS(t.amount))
        FROM transactions t
        LEFT JOIN businesses b ON t.user_id = b.id AND t.user_type = 'business'
        LEFT JOIN drivers d ON t.user_id = d.id AND t.user_type = 'driver'
        WHERE t.transaction_type LIKE '%commission%'
        AND (
            t.user_type = 'driver' 
            OR (t.user_type = 'business' AND (b.currency = 'SYP' OR b.currency IS NULL))
        )
    ";
    
    $p_earn = [];
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $sql_earn_syp .= " AND (
            (t.user_type = 'business' AND b.governorate_id = ?) 
            OR 
            (t.user_type = 'driver' AND d.governorate_id = ?)
        )";
        $p_earn = [$admin_governorate_id, $admin_governorate_id];
    }
    
    $stmt = $pdo->prepare($sql_earn_syp); $stmt->execute($p_earn);
    $earnings_syp = $stmt->fetchColumn() ?? 0;

    // 2. أرباح الدولار
    $sql_earn_usd = "
        SELECT SUM(ABS(t.amount))
        FROM transactions t
        JOIN businesses b ON t.user_id = b.id AND t.user_type = 'business'
        WHERE t.transaction_type LIKE '%commission%' AND b.currency = 'USD'
    ";
    
    $p_earn_usd = [];
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $sql_earn_usd .= " AND b.governorate_id = ?";
        $p_earn_usd = [$admin_governorate_id];
    }

    $stmt = $pdo->prepare($sql_earn_usd); $stmt->execute($p_earn_usd);
    $earnings_usd = $stmt->fetchColumn() ?? 0;


    // --- 3. جلب بيانات الجداول (للقوائم التفصيلية) ---
    
    // السائقون (ديون) -> الشرط: الرصيد أكبر من 0
    $sql_drivers_list = "SELECT id, full_name, commission_balance, credit_limit FROM drivers " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "commission_balance > 0 ORDER BY commission_balance DESC";
    $stmt = $pdo->prepare($sql_drivers_list); $stmt->execute($params);
    $drivers_with_debt = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // المتاجر (ديون) -> الشرط: الرصيد أكبر من 0
    $sql_biz_debt = "SELECT id, name, commission_balance, credit_limit, business_type, currency FROM businesses " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "commission_balance > 0 ORDER BY commission_balance DESC";
    $stmt = $pdo->prepare($sql_biz_debt); $stmt->execute($params);
    $businesses_with_debt = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // المتاجر (مستحقات) -> الشرط: الرصيد أكبر من 0
    $sql_biz_credit = "SELECT id, name, commission_balance, payouts_balance, currency FROM businesses " . ($governorate_condition ? $governorate_condition . " AND " : "WHERE ") . "payouts_balance > 0 ORDER BY payouts_balance DESC";
    $stmt = $pdo->prepare($sql_biz_credit); $stmt->execute($params);
    $businesses_with_credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("خطأ: " . $e->getMessage()); }

// دوال التنسيق
function format_dual_currency($amount_usd, $amount_syp) {
    $parts = [];
    if ($amount_usd > 0) $parts[] = '$' . number_format($amount_usd, 2);
    if ($amount_syp > 0 || empty($parts)) $parts[] = number_format($amount_syp) . ' ل.س';
    return implode(' + ', $parts);
}
function format_money($amount, $currency) {
    if ($currency === 'USD') return '$' . number_format($amount, 2);
    return number_format($amount) . ' ل.س';
}
?>
<link rel="stylesheet" href="css/financials.css">
<div class="dashboard-header"><h1>مركز القيادة المالية</h1></div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="icon" style="background-color: #dc3545;"><i class="fas fa-arrow-down"></i></div>
        <div class="info">
            <div class="value" style="font-size: 1.4rem; direction: ltr;">
                <?php echo format_dual_currency($total_debt_usd, $total_debt_syp); ?>
            </div>
            <div class="label">إجمالي الديون (للمنصة)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background-color: #198754;"><i class="fas fa-arrow-up"></i></div>
        <div class="info">
            <div class="value" style="font-size: 1.4rem; direction: ltr;">
                <?php echo format_dual_currency($total_payout_usd, $total_payout_syp); ?>
            </div>
            <div class="label">إجمالي المستحقات (للشركاء)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background-color: <?php echo ($net_position_estimated >= 0) ? '#198754' : '#dc3545'; ?>;"><i class="fas fa-balance-scale"></i></div>
        <div class="info">
            <div class="value" style="direction: ltr;">
                <?php echo number_format($net_position_estimated); ?> ل.س
            </div>
            <div class="label">صافي المركز (تقديري بالليرة)</div>
        </div>
    </div>
    
    <!-- البطاقة الرابعة: أرباح العمولات -->
    <div class="stat-card">
        <div class="icon" style="background-color: #0d6efd;"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="info">
            <div class="value" style="font-size: 1.4rem; direction: ltr;">
                <?php echo format_dual_currency($earnings_usd, $earnings_syp); ?>
            </div>
            <div class="label">إجمالي أرباح العمولات</div>
        </div>
    </div>
</div>

<div class="chart-container">
    <div class="chart-card">
        <h3>مقارنة أرباح العمولات (تقديري بالليرة)</h3>
        <canvas id="earningsChart"></canvas>
    </div>
</div>

<div class="tabs-container">
    <div class="tabs">
        <button class="tab-link active" onclick="openTab(event, 'debts')">مراقبة الديون</button>
        <button class="tab-link" onclick="openTab(event, 'credits')">مراقبة المستحقات</button>
    </div>
    
    <div id="debts" class="tab-content active">
        <h3 class="table-title">الشركاء الذين عليهم ديون للمنصة</h3>
        
        <h4><i class="fas fa-motorcycle"></i> السائقون (ل.س فقط)</h4>
        <table class="data-table">
            <thead><tr><th>اسم السائق</th><th>العمولة المستحقة</th><th>الحد المتبقي</th><th>استخدام الحد</th><th>إجراء</th></tr></thead>
            <tbody>
                <?php if(empty($drivers_with_debt)): ?>
                    <tr><td colspan="5" style="text-align:center;">لا يوجد سائقون عليهم ديون حالياً.</td></tr>
                <?php endif; ?>
                <?php foreach ($drivers_with_debt as $driver): 
                    // الدين هو الرصيد الموجب
                    $debt = $driver['commission_balance'];
                    $limit = $driver['credit_limit'];
                    $usage_percentage = ($limit > 0) ? ($debt / $limit) * 100 : 0;
                    $remaining = $limit - $debt;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($driver['full_name']); ?></strong></td>
                    <td class="balance-negative"><?php echo number_format($debt); ?> ل.س</td>
                    <td><?php echo number_format($remaining); ?> ل.س</td>
                    <td>
                        <div class="progress-bar-container"><div class="progress-bar"><div class="progress-bar-inner" style="width: <?php echo min(100, $usage_percentage); ?>%;"></div></div><span class="progress-text"><?php echo round($usage_percentage); ?>%</span></div>
                    </td>
                    <td><a href="financial_profile.php?type=driver&id=<?php echo $driver['id']; ?>" class="payout-btn">تسوية</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h4 style="margin-top: 2rem;"><i class="fas fa-store"></i> الأنشطة التجارية</h4>
        <table class="data-table">
            <thead><tr><th>اسم النشاط</th><th>العملة</th><th>العمولة المستحقة</th><th>الحد المتبقي</th><th>استخدام الحد</th><th>إجراء</th></tr></thead>
            <tbody>
                <?php if(empty($businesses_with_debt)): ?>
                    <tr><td colspan="6" style="text-align:center;">لا توجد أنشطة عليها ديون حالياً.</td></tr>
                <?php endif; ?>
                <?php foreach ($businesses_with_debt as $biz): 
                    $currency = $biz['currency'];
                    // الدين هو الرصيد الموجب
                    $debt = $biz['commission_balance'];
                    $limit = $biz['credit_limit'];
                    $usage_percentage = ($limit > 0) ? ($debt / $limit) * 100 : 0;
                    $remaining = $limit - $debt;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($biz['name']); ?></strong> <small>(<?php echo ucfirst($biz['business_type']); ?>)</small></td>
                    <td><span class="badge"><?php echo $currency; ?></span></td>
                    <td class="balance-negative"><?php echo format_money($debt, $currency); ?></td>
                    <td><?php echo format_money($remaining, $currency); ?></td>
                    <td>
                        <div class="progress-bar-container"><div class="progress-bar"><div class="progress-bar-inner" style="width: <?php echo min(100, $usage_percentage); ?>%;"></div></div><span class="progress-text"><?php echo round($usage_percentage); ?>%</span></div>
                    </td>
                    <td><a href="financial_profile.php?type=business&id=<?php echo $biz['id']; ?>" class="payout-btn">تسوية</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div id="credits" class="tab-content">
        <h3 class="table-title">الأنشطة التي لها مستحقات</h3>
        <table class="data-table">
            <thead><tr><th>اسم النشاط</th><th>العملة</th><th>مستحقات الحجوزات</th><th>ديون العمولات</th><th>صافي المستحق للدفع</th><th>إجراء</th></tr></thead>
            <tbody>
                <?php if(empty($businesses_with_credits)): ?>
                    <tr><td colspan="6" style="text-align:center;">لا توجد مستحقات حالياً.</td></tr>
                <?php endif; ?>
                <?php foreach ($businesses_with_credits as $biz): 
                    $currency = $biz['currency'];
                    $payout = $biz['payouts_balance'];
                    // الدين موجب
                    $debt = $biz['commission_balance'];
                    $net = $payout - $debt;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($biz['name']); ?></strong></td>
                    <td><span class="badge"><?php echo $currency; ?></span></td>
                    <td class="balance-positive"><?php echo format_money($payout, $currency); ?></td>
                    <td class="balance-negative"><?php echo format_money($debt, $currency); ?></td>
                    <td class="balance-net"><strong><?php echo format_money($net, $currency); ?></strong></td>
                    <td><a href="financial_profile.php?type=business&id=<?php echo $biz['id']; ?>" class="payout-btn">تسوية</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
        tablinks = document.getElementsByClassName("tab-link");
        for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    async function renderChart() {
        try {
            const response = await fetch('php/get_chart_data.php');
            const chartData = await response.json();
            const ctx = document.getElementById('earningsChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        { label: 'عمولات المتاجر (محولة لليرة)', data: chartData.business_data, backgroundColor: 'rgba(13, 110, 253, 0.7)' },
                        { label: 'عمولات السائقين', data: chartData.driver_data, backgroundColor: 'rgba(220, 53, 69, 0.7)' }
                    ]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false,
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } 
                }
            });
        } catch (error) { console.error('فشل في تحميل بيانات الرسم البياني:', error); }
    }

    document.addEventListener('DOMContentLoaded', () => { 
        renderChart(); 
        document.querySelector('.tab-link.active').click(); 
    });
</script>

<?php include 'footer.php'; ?>