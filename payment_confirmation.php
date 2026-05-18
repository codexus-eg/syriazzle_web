<?php
// ========================================================================
// Syriazzle Bookings - صفحة تأكيد الدفع (النسخة 3.0 - الوسيط المالي)
// ========================================================================
require_once 'php/db_connect.php';
require_once 'php/config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$current_user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

try {
    // جلب بيانات الحجز الذي ينتظر الدفع
    $stmt = $pdo->prepare("SELECT id, deposit_amount, created_at FROM bookings WHERE id = ? AND user_id = ? AND status = 'pending_payment'");
    $stmt->execute([$booking_id, $current_user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // إذا لم يعد الحجز في حالة "انتظار الدفع"، وجهه إلى صفحة حجوزاته
    if (!$booking) { header('Location: my_bookings.php?status=expired_or_processed'); exit; }
    
    // جلب حسابات الدفع الخاصة بالمنصة
    $platform_payment_details = get_platform_payment_details();
    
    // استخدام فترة السماح من ملف الإعدادات
    $expiry_time = strtotime($booking['created_at']) + (BOOKING_GRACE_PERIOD_MINUTES * 60);
    $time_remaining = $expiry_time - time();

} catch (PDOException $e) { die("حدث خطأ أثناء تحميل معلومات الدفع."); }

$page_title = 'تأكيد دفع العربون';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <style>
        :root { --primary-blue: #0d6efd; --warning-color: #ffc107; --bg-light: #f8f9fa; --card-border: #dee2e6; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg-light); text-align: center; }
        .payment-container { max-width: 700px; margin: 3rem auto; padding: 2rem; background: #fff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .payment-icon { font-size: 4rem; color: var(--primary-blue); margin-bottom: 1.5rem; }
        h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .lead { font-size: 1.1rem; color: #6c757d; margin-bottom: 1.5rem; }
        .timer { background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); color: #856404; padding: 1rem; border-radius: 8px; font-weight: 700; font-size: 1.1rem; margin-bottom: 2rem; }
        .steps { text-align: right; list-style-type: none; padding: 0; counter-reset: step-counter; }
        .step { position: relative; padding-right: 40px; margin-bottom: 2rem; }
        .step::before {
            counter-increment: step-counter; content: counter(step-counter);
            position: absolute; right: 0; top: 0; width: 30px; height: 30px; line-height: 30px; text-align: center;
            background-color: var(--primary-blue); color: #fff; border-radius: 50%; font-weight: bold;
        }
        .step h3 { font-size: 1.2rem; margin: 0 0 0.5rem 0; }
        .payment-number { font-size: 1.5rem; font-weight: bold; user-select: all; background: var(--bg-light); padding: 10px; border-radius: 8px; display: block; margin: 0.5rem 0; text-align: center; letter-spacing: 2px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 8px; font-size: 1rem; text-align: left; direction: ltr; }
        .btn-primary { background: var(--primary-blue); color: #fff; width: 100%; padding: 14px; border-radius: 8px; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="payment-container">
        <div class="payment-icon"><i class="fas fa-shield-alt"></i></div>
        <h1>خطوة أخيرة لتأمين حجزك</h1>
        <p class="lead">حجزك معلّق. لتأكيده، يرجى دفع عربون بقيمة <strong><?php echo number_format($booking['deposit_amount']); ?> ل.س</strong> إلى حسابات <strong>Syriazzle</strong> الرسمية.</p>

        <?php if ($time_remaining > 0): ?>
            <div class="timer"><i class="fas fa-hourglass-half"></i> الوقت المتبقي للدفع: <span id="countdown"></span></div>
        <?php else: ?>
             <div class="timer" style="background-color: #f8d7da; color: #721c24;">انتهى الوقت! سيتم إلغاء حجزك تلقائياً.</div>
        <?php endif; ?>

        <form id="proof-form" action="php/submit_payment_proof.php" method="POST">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            <ol class="steps">
                <li class="step">
                    <h3>قم بتحويل المبلغ إلى أحد حساباتنا:</h3>
                    <?php foreach($platform_payment_details as $key => $number): if(!empty($number)): ?>
                        <p>عبر <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?></strong> على الرقم:</p>
                        <div class="payment-number"><?php echo htmlspecialchars($number); ?></div>
                    <?php endif; endforeach; ?>
                </li>
                <li class="step">
                    <h3>أدخل إثبات الدفع</h3>
                    <p>أدخل رقم العملية الذي وصلك برسالة، أو آخر 4 أرقام من رقم هاتفك الذي قمت بالتحويل منه.</p>
                    <div class="form-group">
                        <input type="text" id="transaction_id" name="transaction_id" required placeholder="مثال: 123456789 أو 6789">
                    </div>
                </li>
            </ol>
            <button type="submit" id="submit-proof-btn" class="btn-primary">لقد قمت بالدفع، تأكيد الحجز</button>
        </form>
    </div>

    <script>
        const countdownElement = document.getElementById('countdown');
        if (countdownElement) {
            let timeLeft = <?php echo $time_remaining; ?>;
            const interval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    countdownElement.closest('.timer').innerHTML = "انتهى الوقت!";
                    document.getElementById('submit-proof-btn').disabled = true;
                    return;
                }
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                timeLeft--;
            }, 1000);
        }
        
        // **استراتيجية حماية زر الإرسال**
        const proofForm = document.getElementById('proof-form');
        if (proofForm) {
            proofForm.addEventListener('submit', function() {
                const submitBtn = document.getElementById('submit-proof-btn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جار التحقق...';
            });
        }
    </script>
</body>
</html>