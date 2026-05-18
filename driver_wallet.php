<?php
// ========================================================================
// Syriazzle - Professional Driver Wallet (Final Precision Build)
// ========================================================================
require_once 'php/db_connect.php';

// 1. التحقق الصارم من الجلسة
if (!isset($_SESSION['driver_id'])) {
    header('Location: driver_login.php');
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$page_title = 'محفظتي المالية - Syriazzle';

try {
    // 2. جلب بيانات السائق المالية الحالية
    $stmt_d = $pdo->prepare("SELECT full_name, commission_balance, credit_limit FROM drivers WHERE id = ?");
    $stmt_d->execute([$driver_id]);
    $driver = $stmt_d->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        session_unset(); session_destroy(); header('Location: driver_login.php'); exit;
    }

    // 3. حسابات أداء اليوم (من الساعة 00:00:00)
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // كاش الليرة (تحصيل)
    $syp_cash_stmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE driver_id=? AND status='delivered' AND updated_at BETWEEN ? AND ? AND (currency='SYP' OR currency IS NULL)");
    $syp_cash_stmt->execute([$driver_id, $today_start, $today_end]);
    $today_cash_syp = (float)($syp_cash_stmt->fetchColumn() ?: 0);

    // كاش الدولار (تحصيل)
    $usd_cash_stmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE driver_id=? AND status='delivered' AND updated_at BETWEEN ? AND ? AND currency='USD'");
    $usd_cash_stmt->execute([$driver_id, $today_start, $today_end]);
    $today_cash_usd = (float)($usd_cash_stmt->fetchColumn() ?: 0);

    // صافي ربح السائق (أجرة التوصيل + الإكرامية)
    $profit_stmt = $pdo->prepare("SELECT SUM(delivery_fee + tip_amount) FROM orders WHERE driver_id=? AND status='delivered' AND updated_at BETWEEN ? AND ?");
    $profit_stmt->execute([$driver_id, $today_start, $today_end]);
    $today_net_profit = (float)($profit_stmt->fetchColumn() ?: 0);

    // 4. قائمة طلبات اليوم المنجزة
    $stmt_orders = $pdo->prepare("
        SELECT id, total_price, currency, delivery_fee, tip_amount, updated_at 
        FROM orders 
        WHERE driver_id = ? AND status = 'delivered' AND updated_at >= ? 
        ORDER BY updated_at DESC
    ");
    $stmt_orders->execute([$driver_id, $today_start]);
    $today_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // 5. سجل العمليات المالية (آخر 20 حركة)
    $stmt_tx = $pdo->prepare("
        SELECT amount, transaction_type, description, created_at 
        FROM transactions 
        WHERE user_type='driver' AND user_id=? 
        ORDER BY created_at DESC LIMIT 20
    ");
    $stmt_tx->execute([$driver_id]);
    $transactions = $stmt_tx->fetchAll(PDO::FETCH_ASSOC);

    // خريطة التسميات المالية
    $tx_labels = [
        'commission'   => 'عمولة المنصة',
        'payout'       => 'تسديد مبالغ للإدارة',
        'delivery_fee' => 'ربح توصيل طلب',
        'adjustment'   => 'تعديل يدوي'
    ];

} catch (PDOException $e) {
    error_log("Wallet Page Error: " . $e->getMessage());
    die("خطأ فني في جلب بيانات المحفظة.");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    
    <link rel="stylesheet" href="css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root { --sy-red: #e60000; --sy-blue: #007bff; --sy-green: #28a745; --sy-dark: #212529; --sy-bg: #f4f7f6; }
        
        body { font-family: 'Cairo', sans-serif; background: var(--sy-bg); margin: 0; padding-bottom: 60px; }

        .wallet-wrapper { max-width: 500px; margin: 0 auto; padding: 15px; }

        /* البطاقة العلوية (Hero) */
        .debt-card {
            background: linear-gradient(135deg, #2d3436 0%, #000 100%);
            color: #fff; border-radius: 30px; padding: 35px 20px;
            text-align: center; margin-bottom: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            position: relative; overflow: hidden;
        }
        .debt-card h2 { margin: 0; font-size: 0.8rem; opacity: 0.7; font-weight: 400; }
        .debt-card .debt-val { font-size: 2.5rem; font-weight: 900; margin: 10px 0; display: block; }
        .debt-card .limit-tag { font-size: 0.75rem; background: rgba(255,255,255,0.15); padding: 5px 15px; border-radius: 20px; }

        /* شبكة الإحصائيات */
        .stats-grid-h { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 25px; }
        .s-item { background: #fff; padding: 15px; border-radius: 22px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); border: 1px solid #fff; }
        .s-item i { color: var(--sy-blue); font-size: 1.2rem; margin-bottom: 8px; display: block; }
        .s-item small { font-size: 0.7rem; color: #888; font-weight: 700; display: block; }
        .s-item strong { font-size: 0.95rem; font-weight: 800; color: var(--sy-dark); }

        /* نظام التبويبات */
        .wallet-tabs { display: flex; background: #eee; padding: 5px; border-radius: 18px; margin-bottom: 20px; }
        .tab-trigger { flex: 1; padding: 12px; border: none; border-radius: 14px; font-family: 'Cairo'; font-weight: 800; cursor: pointer; color: #777; transition: 0.3s; }
        .tab-trigger.active { background: #fff; color: var(--sy-blue); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

        .tab-content-panel { display: none; animation: fadeIn 0.4s ease; }
        .tab-content-panel.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* العناصر في القائمة */
        .list-row { background: #fff; padding: 16px; border-radius: 20px; margin-bottom: 10px; display: flex; align-items: center; gap: 15px; border: 1px solid #f0f0f0; }
        .row-icon { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .row-data { flex-grow: 1; }
        .row-data h5 { margin: 0; font-size: 0.9rem; color: #333; font-weight: 800; }
        .row-data small { color: #aaa; font-size: 0.7rem; }
        .row-val { font-weight: 900; font-size: 1rem; direction: ltr; }

        /* زر التسديد والنافذة المنبثقة */
        .payout-action-area { background: #e7f3ff; border-radius: 25px; padding: 25px; text-align: center; border: 2px dashed #bde0fe; margin-top: 30px; }
        .btn-open-modal { background: var(--sy-blue); color: #fff; border: none; padding: 16px; border-radius: 16px; font-weight: 800; cursor: pointer; width: 100%; font-size: 1.1rem; margin-top: 15px; font-family: 'Cairo'; box-shadow: 0 5px 15px rgba(0,123,255,0.3); }

        /* Modal Overlay */
        .modal-overlay-sy { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 200000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-content-sy { background: #fff; border-radius: 30px; width: 100%; max-width: 380px; padding: 30px; position: relative; text-align: right; }
        .modal-input { width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 15px; text-align: center; font-size: 1.8rem; font-weight: 900; color: #2d3436; margin: 20px 0; outline: none; }
        .modal-input:focus { border-color: var(--sy-blue); }
        .modal-btn-group { display: flex; gap: 10px; }
        .modal-btn-sy { flex: 1; padding: 15px; border: none; border-radius: 14px; font-weight: 800; cursor: pointer; font-family: 'Cairo'; }
    </style>
</head>
<body>

    <?php include 'header_store.php'; ?>

    <div class="wallet-wrapper">
        
        <!-- بطاقة المديونية -->
        <div class="debt-card">
            <h2>العمولة المستحقة للمنصة</h2>
            <span class="debt-val"><?php echo number_format(abs($driver['commission_balance'])); ?> ل.س</span>
            <span class="limit-tag">الحد الائتماني: <?php echo number_format($driver['credit_limit']); ?> ل.س</span>
        </div>

        <!-- إحصائيات سريعة -->
        <div class="stats-grid-h">
            <div class="s-item">
                <i class="fas fa-hand-holding-usd"></i>
                <small>كاش اليوم (في عهدتك)</small>
                <strong>
                    <?php 
                        $c_parts = [];
                        if($today_cash_usd > 0) $c_parts[] = "$".number_format($today_cash_usd, 2);
                        if($today_cash_syp > 0 || empty($c_parts)) $c_parts[] = number_format($today_cash_syp)." ل.س";
                        echo implode(' + ', $c_parts);
                    ?>
                </strong>
            </div>
            <div class="s-item">
                <i class="fas fa-wallet" style="color:var(--sy-green);"></i>
                <small>أرباحك الصافية اليوم</small>
                <strong style="color:var(--sy-green);"><?php echo number_format($today_net_profit); ?> ل.س</strong>
            </div>
        </div>

        <!-- التبويبات -->
        <div class="wallet-tabs">
            <button class="tab-trigger active" onclick="changeTab(this, 'tab-orders')">طلبات اليوم</button>
            <button class="tab-trigger" onclick="changeTab(this, 'tab-history')">السجل المالي</button>
        </div>

        <!-- محتوى التبويبات -->
        <div id="tab-orders" class="tab-content-panel active">
            <?php if(empty($today_orders)): ?>
                <div style="text-align:center; padding:40px; color:#999;">لم تنجز أي طلبات اليوم.</div>
            <?php else: foreach($today_orders as $o): ?>
                <div class="list-row">
                    <div class="row-icon" style="background:#e8f5e9; color:var(--sy-green);"><i class="fas fa-check-circle"></i></div>
                    <div class="row-data">
                        <h5>طلب #<?php echo $o['id']; ?></h5>
                        <small>تحصيل: <?php echo ($o['currency']=='USD'?'$':'').number_format($o['total_price']).($o['currency']=='SYP'?' ل.س':''); ?></small>
                    </div>
                    <div class="row-val" style="color:var(--sy-green);">+<?php echo number_format($o['delivery_fee'] + $o['tip_amount']); ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div id="tab-history" class="tab-content-panel">
            <?php if(empty($transactions)): ?>
                <div style="text-align:center; padding:40px; color:#999;">سجل المعاملات فارغ.</div>
            <?php else: foreach($transactions as $tx): 
                $is_pos = $tx['amount'] >= 0;
            ?>
                <div class="list-row">
                    <div class="row-icon" style="background:<?php echo $is_pos ? '#e8f5e9' : '#fff0f0'; ?>; color:<?php echo $is_pos ? 'var(--sy-green)' : 'var(--sy-red)'; ?>;">
                        <i class="fas <?php echo $is_pos ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                    </div>
                    <div class="row-data">
                        <h5><?php echo $tx_labels[$tx['transaction_type']] ?? $tx['transaction_type']; ?></h5>
                        <small><?php echo date('d/m | H:i', strtotime($tx['created_at'])); ?></small>
                    </div>
                    <div class="row-val" style="color:<?php echo $is_pos ? 'var(--sy-green)' : 'var(--sy-red)'; ?>;">
                        <?php echo ($is_pos ? '+' : '') . number_format($tx['amount']); ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- منطقة البلاغ عن تسديد -->
        <div class="payout-action-area">
            <h4 style="margin:0; color:var(--sy-blue);">هل قمت بتسديد مبالغ للمكتب؟</h4>
            <p style="font-size:0.8rem; color:#666; margin:10px 0;">اضغط أدناه لإعلام المحاسب بمراجعة حوالتك وتصفير رصيدك.</p>
            <button class="btn-open-modal" id="open-payout-modal">إرسال بلاغ تسديد</button>
        </div>

    </div>

    <!-- النافذة المنبثقة لإدخال المبلغ -->
    <div class="modal-overlay-sy" id="sy-payout-modal">
        <div class="modal-content-sy">
            <h3 style="margin:0; color:var(--sy-blue);">تسجيل مبلغ مسدد</h3>
            <p style="font-size:0.8rem; color:#888;">أدخل المبلغ الذي قمت بتحويله بدقة:</p>
            <input type="number" id="payout-amount-input" class="modal-input" placeholder="0">
            <div class="modal-btn-group">
                <button id="confirm-payout-btn" class="modal-btn-sy" style="background:var(--sy-blue); color:#fff;">إرسال للإدارة</button>
                <button id="close-payout-modal" class="modal-btn-sy" style="background:#eee; color:#333;">إلغاء</button>
            </div>
        </div>
    </div>

    <script>
        // 1. تبديل التبويبات
        function changeTab(btn, tabId) {
            document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-trigger').forEach(t => t.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        // 2. إدارة الـ Modal
        const modal = document.getElementById('sy-payout-modal');
        document.getElementById('open-payout-modal').onclick = () => modal.style.display = 'flex';
        document.getElementById('close-payout-modal').onclick = () => modal.style.display = 'none';

        // 3. إرسال البلاغ
        document.getElementById('confirm-payout-btn').onclick = async function() {
            const amount = document.getElementById('payout-amount-input').value;
            if(!amount || amount <= 0) { alert("يرجى إدخال مبلغ صحيح."); return; }

            this.disabled = true;
            this.textContent = "جاري الإرسال...";
            
            try {
                const fd = new FormData();
                fd.append('amount', amount);
                const res = await fetch('php/notify_admin_payout.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                alert(data.message);
                if(data.success) {
                    location.reload();
                } else {
                    this.disabled = false;
                    this.textContent = "إرسال للإدارة";
                }
            } catch(e) { 
                alert("خطأ في الاتصال بالخادم."); 
                this.disabled = false; 
                this.textContent = "إرسال للإدارة";
            }
        };
    </script>
</body>
</html>