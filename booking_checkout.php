<?php
require_once 'php/db_connect.php';
require_once 'php/config.php'; 


if (!isset($_SESSION['user_id'])) {
    $_SESSION['post_login_booking_data'] = $_POST['booking_data'] ?? null;
    header('Location: login.php?redirect_url=' . urlencode('booking_checkout.php'));
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$booking_data_json = $_POST['booking_data'] ?? ($_SESSION['post_login_booking_data'] ?? null);
unset($_SESSION['post_login_booking_data']); 

if (empty($booking_data_json)) {
    header('Location: index.html');
    exit;
}

$booking_data = json_decode($booking_data_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($booking_data['service_id'])) {
    die("خطأ: بيانات الحجز غير صالحة أو تالفة.");
}


try {
    $stmt = $pdo->prepare("SELECT s.*, b.name as business_name FROM business_services s JOIN businesses b ON s.business_id = b.id WHERE s.id = ? AND s.is_active = 1");
    $stmt->execute([(int)$booking_data['service_id']]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) { die("خطأ: الخدمة المطلوبة غير متاحة حاليًا."); }
    
    $platform_payment_details = get_platform_payment_details();

} catch (PDOException $e) { die("حدث خطأ أثناء تحميل بيانات الحجز."); }

$total_price = (float)$booking_data['total_price'];
$deposit_percentage = (int)$service['deposit_required_percentage'];
$deposit_amount = 0;
$is_deposit_required = $deposit_percentage > 0 && $total_price > 0;

if ($is_deposit_required) {
    $deposit_amount = ceil(($total_price * $deposit_percentage) / 100);
}

$page_title = 'إتمام الحجز - ' . htmlspecialchars($service['business_name']);
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
    <link rel="stylesheet" href="css/service_profile.css">
    <style>
        .checkout-container { max-width: 850px; margin: 2rem auto; display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: flex-start; }
        .checkout-main, .checkout-sidebar .checkout-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); }
        .checkout-sidebar { position: sticky; top: 2rem; }
        .checkout-card { padding: 1.5rem; }
        .card-header { padding-bottom: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .card-header h2 { font-size: 1.5rem; margin: 0; }
        .payment-method { border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; cursor: pointer; transition: all 0.2s; position: relative; }
        .payment-method:hover { border-color: var(--primary-blue); }
        .payment-method.selected { border-color: var(--primary-blue); box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25); background-color: #f7faff; }
        .payment-method-header { display: flex; align-items: center; gap: 1rem; }
        .payment-method-header img { height: 24px; }
        .payment-method input[type=radio] { position: absolute; opacity: 0; width: 100%; height: 100%; top: 0; left: 0; cursor: pointer; }
        
        @media (max-width: 992px) { .checkout-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="checkout-container">
        <main class="checkout-main">
            <form id="booking-form" action="php/process_booking.php" method="POST">
                <input type="hidden" name="booking_data_json" value="<?php echo htmlspecialchars($booking_data_json); ?>">

                <div class="card-header" style="padding: 1.5rem;">
                    <h2>تأكيد الحجز والدفع</h2>
                </div>

                <div style="padding: 0 1.5rem 1.5rem 1.5rem;">
                    <?php if ($is_deposit_required): ?>
                        <h3>اختر طريقة دفع العربون</h3>
                        <p class="text-secondary">أنت تدفع الآن لمنصة <strong>Syriazzle</strong> كوسيط مالي موثوق. سيتم تأكيد حجزك فور مراجعة الدفعة.</p>
                        
                        <div id="payment-methods-container">
                            <!-- js -->
                        </div>
                        <input type="hidden" name="payment_method" id="selected_payment_method">
                    <?php else: ?>
                        <h3>تأكيد الحجز</h3>
                        <p class="text-secondary">هذه الخدمة لا تتطلب دفع عربون. سيتم تأكيد حجزك مباشرة عند الضغط على الزر أدناه.</p>
                        <input type="hidden" name="payment_method" value="no_deposit_required">
                    <?php endif; ?>
                </div>
            </form>
        </main>

        <aside class="checkout-sidebar">
            <div class="checkout-card">
                <div class="card-header"><h3>ملخص الحجز</h3></div>
                <div class="summary-item"><span>الخدمة:</span> <strong><?php echo htmlspecialchars($booking_data['details']['service_name']); ?></strong></div>
                
                <?php if (isset($booking_data['details']['nights'])): 
                    $start_datetime_obj = new DateTime($booking_data['start_datetime']);
                    $end_datetime_obj = new DateTime($booking_data['end_datetime']);
                ?>
                    <div class="summary-item">
                        <span>الوصول:</span> 
                        <strong><?php echo $start_datetime_obj->format('Y-m-d \ا\ل\س\ا\ع\ة h:i A'); ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>المغادرة:</span> 
                        <strong><?php echo $end_datetime_obj->format('Y-m-d \ا\ل\س\ا\ع\ة h:i A'); ?></strong>
                    </div>
                    <div class="summary-item"><span>عدد الليالي:</span> <strong><?php echo htmlspecialchars($booking_data['details']['nights']); ?></strong></div>
                <?php else: ?>
                    <div class="summary-item"><span>تاريخ الموعد:</span> <strong><?php echo htmlspecialchars($booking_data['details']['date']); ?></strong></div>
                    <div class="summary-item"><span>الوقت:</span> <strong><?php echo htmlspecialchars($booking_data['details']['time']); ?></strong></div>
                <?php endif; ?>

                <div class="summary-item summary-total"><span>الإجمالي:</span> <span><?php echo number_format($total_price); ?> ل.س</span></div>
                <?php if ($is_deposit_required): ?>
                     <div class="summary-item" style="color: var(--primary-dark);"><span>العربون المطلوب:</span> <strong><?php echo number_format($deposit_amount); ?> ل.س</strong></div>
                <?php endif; ?>

                <button type="submit" form="booking-form" id="final-checkout-btn" class="btn-primary" style="margin-top: 1rem; width: 100%;">
                    <?php echo $is_deposit_required ? 'الانتقال إلى تأكيد الدفع' : 'تأكيد الحجز الآن'; ?>
                </button>
            </div>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethodsContainer = document.getElementById('payment-methods-container');
            const selectedPaymentMethodInput = document.getElementById('selected_payment_method');
            const checkoutBtn = document.getElementById('final-checkout-btn');
            const paymentDetails = <?php echo json_encode($platform_payment_details); ?>;

            const availableMethods = [];
            // الآن نستخدم متغيرات المنصة
            if (paymentDetails && paymentDetails.syriatel_cash) availableMethods.push({ key: 'syriatel_cash', name: 'سيريتل كاش', icon: 'image/payment/syriatel_cash.png' });
            if (paymentDetails && paymentDetails.mtn_cash) availableMethods.push({ key: 'mtn_cash', name: 'MTN كاش', icon: 'image/payment/mtn_cash.svg' });
            if (paymentDetails && paymentDetails.sham_cash) availableMethods.push({ key: 'sham_cash', name: 'شام كاش', icon: 'image/payment/sham_cash.svg' });
            
            if (paymentMethodsContainer && availableMethods.length > 0) {
                availableMethods.forEach(method => {
                    const methodDiv = document.createElement('div');
                    methodDiv.className = 'payment-method';
                    methodDiv.innerHTML = `
                        <input type="radio" name="payment_option" value="${method.key}" id="radio_${method.key}">
                        <label for="radio_${method.key}" class="payment-method-header">
                            <img src="${method.icon}" alt="${method.name}">
                            <strong>${method.name}</strong>
                        </label>
                    `;
                    paymentMethodsContainer.appendChild(methodDiv);
                });

                const radios = paymentMethodsContainer.querySelectorAll('input[type="radio"]');
                radios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        paymentMethodsContainer.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
                        this.closest('.payment-method').classList.add('selected');
                        selectedPaymentMethodInput.value = this.value;
                    });
                });

                if (radios.length > 0) {
                    radios[0].checked = true;
                    radios[0].dispatchEvent(new Event('change'));
                }
            } else if (paymentMethodsContainer) {
                 paymentMethodsContainer.innerHTML = '<p class="placeholder-text" style="color:var(--danger-color);">عذرًا، خدمة الدفع غير متاحة حاليًا. لا يمكنك إكمال الحجز.</p>';
                 if(checkoutBtn) checkoutBtn.disabled = true;
            }
        });
    </script>
</body>
</html>