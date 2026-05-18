<?php
require_once 'php/db_connect.php';

$page_title = 'لوحة التحكم الرئيسية - Syriazzle';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

// جلب سعر الصرف الحالي من الإعدادات للتحويلات التقديرية
$stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
$usd_rate = (float)($stmt_rate->fetchColumn() ?: 15000);

try {
    // 1. جلب المتاجر
    $sql_businesses = "
        SELECT 
            b.id, b.name, b.status, b.logo_image, b.balance, b.currency, b.created_at,
            (SELECT COUNT(*) FROM business_followers WHERE business_id = b.id) as follower_count,
            (SELECT COUNT(*) FROM business_reviews WHERE business_id = b.id) as review_count,
            (SELECT COUNT(*) FROM orders WHERE business_id = b.id AND status = 'pending_approval') as new_orders_count
        FROM businesses b
        WHERE b.user_id = ? 
        AND b.business_type IN ('delivery', 'hybrid') 
        AND b.deleted_at IS NULL
        ORDER BY b.created_at DESC
    ";
    $stmt_businesses = $pdo->prepare($sql_businesses);
    $stmt_businesses->execute([$current_user_id]);
    $user_businesses = $stmt_businesses->fetchAll(PDO::FETCH_ASSOC);
    $business_ids = array_column($user_businesses, 'id');

    // 2. حساب المجاميع المنفصلة (دولار وليرة)
    $total_balance_syp = 0;
    $total_balance_usd = 0;
    $total_new_orders = 0;
    $total_followers = 0;
    
    foreach ($user_businesses as $business) {
        if ($business['currency'] === 'USD') {
            $total_balance_usd += $business['balance'];
        } else {
            $total_balance_syp += $business['balance'];
        }
        $total_new_orders += $business['new_orders_count'];
        $total_followers += $business['follower_count'];
    }
    $total_stores = count($user_businesses);

    // 3. بيانات المخطط البياني
    $all_chart_data = [];
    if (!empty($business_ids)) {
        $placeholders = implode(',', array_fill(0, count($business_ids), '?'));
        $stmt_chart = $pdo->prepare("
            SELECT b.id as business_id, b.currency, DATE(t.created_at) as day, SUM(t.amount) as daily_revenue
            FROM transactions t
            JOIN businesses b ON t.user_id = b.id
            WHERE t.user_type = 'business' AND t.transaction_type = 'order_revenue'
              AND t.user_id IN ($placeholders) AND t.created_at >= CURDATE() - INTERVAL 3 MONTH
            GROUP BY b.id, b.currency, DATE(t.created_at)
            ORDER BY DATE(t.created_at) ASC
        ");
        $stmt_chart->execute($business_ids);
        $all_chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { die("خطأ في جلب البيانات: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #e60000; --secondary-color: #0d6efd; --bg-main: #f0f2f5; 
            --card-bg: rgba(255, 255, 255, 0.7); --card-border: rgba(255, 255, 255, 0.5);
            --text-dark: #212529; --text-light: #5a6472; --border-color: #e9ecef;
            --success-color: #198754; --warning-color: #fd7e14; --purple-color: #6f42c1;
        }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            background-attachment: fixed;
        }
        .dashboard-nav {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 10px;
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .dashboard-nav a {
            flex-grow: 1;
            text-align: center;
            text-decoration: none;
            color: var(--text-light);
            padding: 12px 0px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .dashboard-nav a:hover:not(.active1) {
            background-color: #f0f2f5;
            color: var(--text-dark);
        }
        
        .dashboard-nav a.active1 {
            background-color: var(--secondary-color);
            color: #fff;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
            transform: translateY(-2px);
        }
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 10px; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .dashboard-header h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; }
        .currency-switcher { display: flex; background: var(--card-bg); border-radius: 25px; padding: 5px; border: 1px solid var(--card-border); }
        .currency-switcher button { background: none; border: none; padding: 8px 15px; border-radius: 20px; font-family: inherit; font-weight: 700; cursor: pointer; transition: all 0.3s; }
        .currency-switcher button.active { background-color: var(--secondary-color); color: #fff; box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); }
        
        .main-tabs { display: flex; gap: 10px; margin-bottom: 25px; background: var(--card-bg); backdrop-filter: blur(10px); padding: 10px; border-radius: 12px; border: 1px solid var(--card-border); overflow-x: auto; scrollbar-width: none;}
        .main-tabs::-webkit-scrollbar { display: none; }
        .main-tab-btn { flex-shrink: 0; padding: 10px 20px; font-size: 16px; font-weight: 700; background: none; border: none; cursor: pointer; color: var(--text-light); border-radius: 8px; transition: all 0.3s; }
        .main-tab-btn.active { color: #fff; background-color: var(--secondary-color); box-shadow: 0 4px 15px rgba(13,110,253,0.3); }
        .tab-pane { display: none; } .tab-pane.active { display: block; animation: fadeIn 0.5s; } @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: var(--card-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid var(--card-border); padding: 18px; border-radius: 15px; display: flex; flex-direction: column; align-items: center; gap: 20px; transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .stat-icon { width: 55px; height: 55px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; flex-shrink: 0; }
        .stat-info {display: flex; flex-direction: column; align-items: center; justify-content: center;}
        .stat-info h4 { margin: 0 0 5px; font-size: 15px; color: var(--text-light); font-weight: 600; }
        .stat-info span { font-size: 26px; font-weight: 800; color: var(--text-dark); }
        
        .chart-container { background-color: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--card-border); padding: 25px; border-radius: 15px; }
        .chart-header { display: flex; flex-direction: column; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;}
        .chart-header h3 { margin: 0; font-size: 20px; }
        .chart-header {display: flex; flex-wrap: wrap; gap: 15px;}
        .chart-filters{display: flex; flex-wrap: wrap; gap: 15px;}
        .chart-filters button { background: #e9ecef; border: 1px solid #dee2e6; padding: 6px 14px; border-radius: 8px; font-family: inherit; cursor: pointer; font-weight: 600; font-size: 14px; }
        .chart-filters button.active { background-color: var(--secondary-color); color: #fff; border-color: var(--secondary-color); }
        .chart-wrapper { height: 350px; position: relative; }
        
        .stores-list-container { padding: 0; }
        .stores-list-header { font-size: 22px; margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .business-card-op { background: var(--card-bg); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid var(--card-border); border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; flex-direction: column; }
        .business-card-op-main { display: flex; align-items: center; gap: 20px; padding: 20px; }
        .business-card-op .logo { width: 70px; height: 70px; border-radius: 12px; object-fit: cover; }
        .business-card-op .info { flex-grow: 1; }
        .business-card-op .info h4 { margin: 0 0 8px; font-size: 18px; font-weight: 800; }
        .status-badge { font-size: 11px; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #fff; }
        .status-approved { background-color: var(--success-color); } .status-pending { background-color: #ffc107; color: #212121; } .status-blocked { background-color: var(--primary-color); }
        .business-card-op-body { padding: 0 20px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; border-bottom: 1px solid var(--border-color); }
        .highlight-stat { text-align: center; }
        .highlight-stat .stat-value { font-size: 22px; font-weight: 800; color: var(--text-dark); }
        .highlight-stat .stat-label { font-size: 13px; color: var(--text-light); }
        .business-card-op-footer { padding: 15px 10px; display: flex; justify-content: space-around; }
        .footer-action { text-decoration: none; color: var(--text-light); display: flex; flex-direction: column; align-items: center; gap: 5px; font-weight: 600; font-size: 12px; transition: color 0.2s; width: 70px; background: none; border: none; cursor: pointer; font-family: inherit; }
        .footer-action:hover { color: var(--secondary-color); }
        .footer-action .icon { font-size: 20px; }
        .footer-action.has-badge { position: relative; }
        .badge { position: absolute; top: -5px; right: 15px; background-color: var(--primary-color); color: #fff; font-size: 11px; width: 20px; height: 20px; border-radius: 50%; display: flex; justify-content: center; align-items: center; }
        .empty-state { text-align: center; padding: 40px; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1020; display: none; justify-content: center; align-items: center; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
        .modal-overlay.visible { display: flex; }
        .modal-content { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 15px; max-width: 900px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-header { padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { margin: 0; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .modal-header h2 img { width: 40px; height: 40px; border-radius: 8px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-light); }
        .modal-body { padding: 25px; overflow-y: auto; }
        
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px;} .stat-info span { font-size: 20px; } .dashboard-header { flex-direction: column; gap: 20px; } }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="dashboard-container">
        <div class="dashboard-nav">
            <a href="business_dashboard.php" class="active1"><i class="fas fa-chart-line"></i> لوحة القيادة</a>
            <a href="manage_orders.php"><i class="fas fa-receipt"></i> إدارة الطلبات</a>
            <a href="manage_reviews_user.php"><i class="fas fa-star"></i> إدارة المراجعات</a>
        </div>
        <div class="dashboard-header">
            <h1>لوحة التحكم</h1>
            <!-- تم إزالة مبدل العملة لأنه غير منطقي هنا، سنعرض العملتين معاً -->
        </div>

        <nav class="main-tabs" id="main-tabs">
            <button class="main-tab-btn active" data-tab="dashboard-pane">لوحة القيادة</button>
            <button class="main-tab-btn" data-tab="stores-pane">إدارة المتاجر</button>
        </nav>

        <div id="dashboard-pane" class="tab-pane active">
            <div class="stats-grid">
                <!-- بطاقة الرصيد المزدوجة -->
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e7f3ff; color: #0d6efd;"><i class="fas fa-wallet"></i></div>
                    <div class="stat-info">
                        <h4>إجمالي الرصيد</h4>
                        <span id="stat-balance" style="font-size: 1.1em;">
                            <?php 
                                echo number_format($total_balance_usd, 2) . ' $ <span style="font-size:0.8em; color:#888;">+</span> ' . number_format($total_balance_syp) . ' ل.س';
                            ?>
                        </span>
                    </div>
                </div>
                <div class="stat-card"><div class="stat-icon" style="background-color: #fff4e8; color: #fd7e14;"><i class="fas fa-bell"></i></div><div class="stat-info"><h4>الطلبات الجديدة</h4><span id="stat-orders"><?php echo $total_new_orders; ?></span></div></div>
                <div class="stat-card"><div class="stat-icon" style="background-color: #e6f8f2; color: #198754;"><i class="fas fa-users"></i></div><div class="stat-info"><h4>إجمالي المتابعين</h4><span id="stat-followers"><?php echo $total_followers; ?></span></div></div>
                <div class="stat-card"><div class="stat-icon" style="background-color: #f8e7f8; color: #6f42c1;"><i class="fas fa-store"></i></div><div class="stat-info"><h4>عدد المتاجر</h4><span id="stat-stores"><?php echo $total_stores; ?></span></div></div>
            </div>
            
            <!-- حاوية المخطط البياني مع خيار التوحيد -->
            <div class="chart-container" id="overall-chart-container">
                <div class="chart-header">
                    <h3>تحليل الإيرادات (موحد بالليرة)</h3>
                    <div class="chart-filters" id="overall-chart-filters">
                        <button class="active" data-range="7">7 أيام</button>
                        <button data-range="30">30 يوماً</button>
                    </div>
                </div>
                <div class="chart-wrapper"><canvas id="overallRevenueChart"></canvas></div>
                <p style="text-align: center; font-size: 0.85em; color: #888; margin-top: 10px;">
                    * يتم تحويل الإيرادات الدولارية إلى ليرة سورية لأغراض العرض في الرسم البياني فقط (سعر الصرف التقديري: <?php echo number_format($usd_rate); ?> ل.س).
                </p>
            </div>
        </div>
        
        <div id="stores-pane" class="tab-pane">
            <h2 class="stores-list-header">قائمة متاجركم</h2>
            <div class="stores-grid">
                <?php if (empty($user_businesses)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;"><h3>ليس لديك متاجر لعرضها.</h3></div>
                <?php else: ?>
                    <?php foreach ($user_businesses as $business): ?>
                        <div class="business-card-op">
                            <div class="business-card-op-main">
                                <img src="<?php echo htmlspecialchars($business['logo_image'] ?? 'image/default_logo.png'); ?>" class="logo">
                                <div class="info">
                                    <h4><?php echo htmlspecialchars($business['name']); ?></h4>
                                    <span class="status-badge status-<?php echo strtolower($business['status']); ?>"><?php if ($business['status'] === 'pending') echo 'قيد المراجعة'; elseif ($business['status'] === 'approved') echo 'نشط'; else echo 'محظور'; ?></span>
                                </div>
                            </div>
                            <div class="business-card-op-body">
                                <div class="highlight-stat">
                                    <!-- عرض الرصيد بعملة المتجر الأصلية -->
                                    <span class="stat-value">
                                        <?php 
                                            if ($business['currency'] === 'USD') {
                                                echo '$' . number_format($business['balance'], 2);
                                            } else {
                                                echo number_format($business['balance']) . ' ل.س';
                                            }
                                        ?>
                                    </span>
                                    <span class="stat-label">الرصيد الحالي</span>
                                </div>
                                <div class="highlight-stat">
                                    <span class="stat-value"><?php echo $business['new_orders_count']; ?></span>
                                    <span class="stat-label">الطلبات الجديدة</span>
                                </div>
                            </div>
                            <div class="business-card-op-footer">
                                <button type="button" class="footer-action details-btn" data-business-id="<?php echo $business['id']; ?>"><i class="fas fa-chart-line icon"></i><span>التحليلات</span></button>
                                <a href="manage_orders.php" class="footer-action has-badge">
                                    <i class="fas fa-receipt icon"></i><span>الطلبات</span>
                                    <?php if ($business['new_orders_count'] > 0): ?><span class="badge"><?php echo $business['new_orders_count']; ?></span><?php endif; ?>
                                </a>
                                <a href="edit_business_user.php?id=<?php echo $business['id']; ?>" class="footer-action"><i class="fas fa-edit icon"></i><span>تعديل</span></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="business-details-modal"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const allBusinessesData = <?php echo json_encode($user_businesses, JSON_NUMERIC_CHECK); ?>;
            const allChartData = <?php echo json_encode($all_chart_data, JSON_NUMERIC_CHECK); ?>;
            const USD_RATE = <?php echo $usd_rate; ?>; // استخدام القيمة من PHP
            
            const mainTabsContainer = document.getElementById('main-tabs');
            const tabPanes = document.querySelectorAll('.tab-pane');
            const modal = document.getElementById('business-details-modal');
            
            let overallChart = null;
            let modalChart = null;

            // دالة تنسيق العملة
            function formatCurrency(amount, currency) {
                if (currency === 'USD') {
                    return `$${parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                }
                return `${parseInt(amount).toLocaleString('ar-SY')} ل.س`;
            }

            function prepareChartData(range, businessId) {
                const dataForBusiness = businessId === 'all' ? allChartData : allChartData.filter(d => d.business_id === businessId);
                const revenuesByDate = {};
                
                // تجميع البيانات وتحويلها لعملة موحدة (للرسم البياني فقط)
                dataForBusiness.forEach(curr => {
                    let amount = parseFloat(curr.daily_revenue);
                    // إذا كنا نعرض الإجمالي العام (all)، نوحد العملة إلى ليرة
                    if (businessId === 'all' && curr.currency === 'USD') {
                        amount *= USD_RATE;
                    }
                    // إذا كنا نعرض متجر محدد (modal)، نبقي العملة كما هي
                    
                    revenuesByDate[curr.day] = (revenuesByDate[curr.day] || 0) + amount;
                });

                let startDate, endDate = dayjs();
                if (range === '7') startDate = dayjs().subtract(6, 'day');
                else if (range === '30') startDate = dayjs().subtract(29, 'day');
                
                const labels = []; const data = [];
                for (let d = startDate; d.isBefore(endDate) || d.isSame(endDate, 'day'); d = d.add(1, 'day')) {
                    const dateString = d.format('YYYY-MM-DD');
                    labels.push(d.format('MM/DD'));
                    data.push(revenuesByDate[dateString] || 0);
                }
                return { labels, data };
            }

            function renderChart(canvas, range, businessId, existingChart) {
                if (existingChart) existingChart.destroy();
                const { labels, data } = prepareChartData(range, businessId);
                
                // تحديد تسمية العملة في الرسم البياني
                let chartCurrencyLabel = 'ل.س';
                if (businessId !== 'all') {
                    const biz = allBusinessesData.find(b => b.id === businessId);
                    if (biz && biz.currency === 'USD') chartCurrencyLabel = '$';
                }

                return new Chart(canvas.getContext('2d'), {
                    type: 'line', 
                    data: {
                        labels: labels,
                        datasets: [{
                            label: `الإيرادات (${chartCurrencyLabel})`, 
                            data: data,
                            borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 2, tension: 0.3, fill: true
                        }]
                    },
                    options: { 
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    } 
                });
            }
            
            // فتح نافذة تفاصيل المتجر
            function openBusinessModal(businessId) {
                const business = allBusinessesData.find(b => b.id === businessId);
                if (!business) return;
                
                const modalContentHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>${business.name} <small>(${business.currency})</small></h2>
                            <button class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
                                <div class="stat-card"><h4>الرصيد</h4><span>${formatCurrency(business.balance, business.currency)}</span></div>
                                <div class="stat-card"><h4>الطلبات الجديدة</h4><span>${business.new_orders_count}</span></div>
                            </div>
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3>الأداء المالي</h3>
                                    <div class="chart-filters" id="modal-chart-filters"><button class="active" data-range="7">7 أيام</button><button data-range="30">30 يوماً</button></div>
                                </div>
                                <div class="chart-wrapper"><canvas id="modalRevenueChart"></canvas></div>
                            </div>
                        </div>
                    </div>`;
                
                modal.innerHTML = modalContentHTML;
                modal.dataset.currentBusinessId = businessId;
                modal.classList.add('visible');
                
                modalChart = renderChart(document.getElementById('modalRevenueChart'), '7', business.id, modalChart);
                
                document.getElementById('modal-chart-filters').addEventListener('click', (e) => { 
                    if (e.target.tagName === 'BUTTON') { 
                        e.target.parentElement.querySelector('.active').classList.remove('active'); 
                        e.target.classList.add('active'); 
                        modalChart = renderChart(document.getElementById('modalRevenueChart'), e.target.dataset.range, business.id, modalChart); 
                    } 
                });
            }

            // تفعيل التبويبات
            mainTabsContainer.addEventListener('click', e => { 
                if(e.target.matches('.main-tab-btn')) { 
                    mainTabsContainer.querySelector('.active').classList.remove('active'); 
                    tabPanes.forEach(p => p.classList.remove('active')); 
                    e.target.classList.add('active'); 
                    document.getElementById(e.target.dataset.tab).classList.add('active'); 
                } 
            });

            // أزرار التفاصيل
            document.querySelectorAll('.details-btn').forEach(button => { 
                button.addEventListener('click', () => openBusinessModal(parseInt(button.dataset.businessId))); 
            });

            // إغلاق المودال
            modal.addEventListener('click', e => { 
                if (e.target === modal || e.target.matches('.modal-close')) modal.classList.remove('visible'); 
            });

            // رسم المخطط العام عند التحميل
            document.getElementById('overall-chart-filters').addEventListener('click', (e) => {
                if (e.target.tagName === 'BUTTON') {
                    e.target.parentElement.querySelector('.active').classList.remove('active');
                    e.target.classList.add('active');
                    renderChart(document.getElementById('overallRevenueChart'), e.target.dataset.range, 'all', overallChart);
                }
            });
            
            overallChart = renderChart(document.getElementById('overallRevenueChart'), '7', 'all', overallChart);
        });
    </script>
</body>
</html>