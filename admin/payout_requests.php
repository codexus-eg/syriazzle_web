<?php
// ========================================================================
// Syriazzle Admin - Driver Payout Requests (Robust Extraction V12.0)
// ========================================================================

$page_title = 'طلبات تسوية أرصدة الكباتن';
require_once 'header.php'; 

// 1. التحقق من الصلاحيات المالية
if (!hasPermission('view_financials')) {
    echo "<div class='status-message error'><p>عذراً، لا تملك صلاحية الوصول للبيانات المالية.</p></div>";
    exit;
}

try {
    // 2. جلب الإشعارات الخام الموجهة للأدمن الحالي
    // لا نقوم باستخراج البيانات داخل SQL لضمان عدم حدوث خطأ، سنفعل ذلك في PHP
    $sql = "
        SELECT id as notif_id, message, is_read, created_at 
        FROM site_notifications 
        WHERE user_id = ? AND title LIKE '%تسوية رصيد%'
        ORDER BY created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['admin_id']]);
    $raw_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Payout Request Page Error: " . $e->getMessage());
    $raw_notifications = [];
}

// دالة مساعدة لاستخراج البيانات من النص (Regex Extraction)
function parsePayoutMessage($message) {
    // استخراج المعرف من بين [ID: ]
    preg_match('/\[ID:(\d+)\]/', $message, $id_match);
    // استخراج المبلغ بعد كلمة 'مبلغ: '
    preg_match('/مبلغ: ([\d,]+)/', $message, $amount_match);
    
    return [
        'driver_id' => $id_match[1] ?? null,
        'amount' => $amount_match[1] ?? 'غير محدد'
    ];
}
?>

<style>
    .payout-audit-card { background: #fff; border-radius: 25px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); padding: 30px; border: 1px solid #f0f0f0; margin-top: 10px; }
    .sy-table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: right; }
    .sy-table th { background: #f8f9fa; padding: 15px; font-size: 0.85rem; color: #888; font-weight: 800; border-radius: 10px; }
    .sy-table td { padding: 20px 15px; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
    
    .reported-val { color: #28a745; font-weight: 900; font-size: 1.1rem; }
    .debt-val { color: #e60000; font-weight: 800; }
    
    .btn-action { padding: 10px 18px; border-radius: 12px; text-decoration: none; font-size: 0.8rem; font-weight: bold; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
    .btn-audit { background: #007bff; color: #fff; }
    .btn-delete { background: #fff1f1; color: #e60000; margin-right: 10px; }

    /* Modal الحماية */
    .p-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 100000; backdrop-filter: blur(5px); }
    .p-modal-content { background: #fff; border-radius: 30px; padding: 35px; width: 100%; max-width: 380px; text-align: center; }
    .p-input { width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 15px; margin: 20px 0; text-align: center; font-size: 1.5rem; font-weight: 900; color: #2d3436; outline: none; }
    .p-input:focus { border-color: #e60000; }
</style>

<div class="payout-audit-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <div>
            <h2 style="margin:0; font-weight:900; color:var(--sy-red-dark);">مراجعة طلبات التسوية</h2>
            <p style="margin:5px 0 0; color:#999; font-size:0.85rem;">تدقيق الحوالات المالية وتصفير ديون الكباتن</p>
        </div>
        <span style="background:var(--sy-red-main); color:#fff; padding:8px 18px; border-radius:15px; font-weight:900; font-size:0.85rem; box-shadow:0 4px 10px rgba(230,0,0,0.2);">
            إجمالي البلاغات: <?php echo count($raw_notifications); ?>
        </span>
    </div>

    <?php if (empty($raw_notifications)): ?>
        <div style="text-align:center; padding:80px 20px; color:#ccc;">
            <i class="fas fa-wallet" style="font-size:4.5rem; margin-bottom:20px; opacity:0.2;"></i>
            <h3>لا توجد طلبات معلقة</h3>
        </div>
    <?php else: ?>
        <table class="sy-table">
            <thead>
                <tr>
                    <th>الكابتن</th>
                    <th>المبلغ المُرسل</th>
                    <th>الدين في السيستم</th>
                    <th>توقيت البلاغ</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($raw_notifications as $n): 
                    $parsed = parsePayoutMessage($n['message']);
                    $driver_id = $parsed['driver_id'];
                    $reported_amount = $parsed['amount'];

                    // جلب بيانات السائق من القاعدة بناءً على الـ ID المستخرج
                    $stmt_d = $pdo->prepare("SELECT full_name, commission_balance FROM drivers WHERE id = ?");
                    $stmt_d->execute([$driver_id]);
                    $drv = $stmt_d->fetch();
                    
                    // إذا لم نجد سائق بهذا الـ ID، نظهر رسالة خطأ في السطر
                    if (!$drv) {
                        echo "<tr style='opacity:0.5;'>
                                <td colspan='4' style='color:red; font-size:0.8rem; padding:15px;'>خطأ: تعذر ربط هذا الإشعار بسائق موجود (ID: $driver_id)</td>
                                <td><button onclick='askForPassword({$n['notif_id']})' class='btn-action btn-delete'>حذف الإشعار</button></td>
                              </tr>";
                        continue;
                    }
                ?>
                <tr>
                    <td>
                        <div style="font-weight:900; color:#333;"><?php echo htmlspecialchars($drv['full_name']); ?></div>
                        <small style="color:var(--sy-blue); font-weight:700;">ID: #<?php echo $driver_id; ?></small>
                    </td>
                    <td class="reported-val"><?php echo $reported_amount; ?> <small style="font-size:0.6rem;">ل.س</small></td>
                    <td class="debt-val"><?php echo number_format(abs((float)$drv['commission_balance'])); ?> <small style="font-size:0.6rem;">ل.س</small></td>
                    <td style="color:#888; font-size:0.8rem;"><?php echo date('Y/m/d H:i', strtotime($n['created_at'])); ?></td>
                    <td>
                        <a href="financial_profile.php?type=driver&id=<?php echo $driver_id; ?>" class="btn-action btn-audit">
                            <i class="fas fa-check-circle"></i> تصفير الرصيد
                        </a>
                        <button onclick="askForPassword(<?php echo $n['notif_id']; ?>)" class="btn-action btn-delete">
                            <i class="fas fa-trash-alt"></i> حذف
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- نافذة الحماية بكلمة المرور -->
<div id="password-modal" class="p-modal-overlay">
    <div class="p-modal-content">
        <div style="width:70px; height:70px; background:#fff1f1; color:#e60000; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:2rem;">
            <i class="fas fa-lock"></i>
        </div>
        <h3 style="margin:0; color:#333;">رمز الأمان</h3>
        <p style="font-size:0.85rem; color:#777; margin-top:10px;">أدخل كلمة المرور لتتمكن من حذف الطلب</p>
        <input type="password" id="admin-pass-input" class="p-input" autofocus>
        <div style="display:flex; gap:12px;">
            <button onclick="verifyAndDelete()" class="btn-action btn-audit" style="flex:2; background:#e60000; justify-content:center;">تأكيد الحذف</button>
            <button onclick="closePassModal()" class="btn-action" style="flex:1; background:#f1f2f6; color:#333; justify-content:center;">إلغاء</button>
        </div>
    </div>
</div>

<script>
let currentDeleteId = null;

function askForPassword(notifId) {
    currentDeleteId = notifId;
    document.getElementById('password-modal').style.display = 'flex';
}

function closePassModal() {
    document.getElementById('password-modal').style.display = 'none';
    document.getElementById('admin-pass-input').value = '';
}

async function verifyAndDelete() {
    const pass = document.getElementById('admin-pass-input').value;
    if (pass !== "24ufi7$") {
        alert("⚠️ كلمة المرور خاطئة!");
        return;
    }

    try {
        const fd = new FormData();
        fd.append('notif_id', currentDeleteId);
        const res = await fetch('php/delete_settlement_request.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            location.reload();
        } else {
            alert("خطأ: " + data.message);
        }
    } catch (e) {
        alert("فشل الاتصال بالسيرفر");
    }
}
</script>

<?php include 'footer.php'; ?>