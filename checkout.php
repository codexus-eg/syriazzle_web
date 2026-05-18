<?php
// ========================================================================
// Syriazzle - Checkout Page (نسخة دعم العملات المختلطة 1.0)
// ========================================================================
session_start();
require_once 'php/db_connect.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = "checkout.php?" . http_build_query($_GET);
    header('Location: login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$business_id = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;

if ($business_id === 0) {
    die("خطأ: معرف المتجر غير محدد. يرجى العودة واختيار متجر.");
}

try {
    // 1. جلب بيانات المتجر بما فيها العملة (currency)
    // تمت إضافة currency للاستعلام
    $stmt_business = $pdo->prepare("SELECT name, latitude, longitude, currency FROM businesses WHERE id = ? AND status = 'approved'");
    $stmt_business->execute([$business_id]);
    $business = $stmt_business->fetch(PDO::FETCH_ASSOC);

    if (!$business) {
        die("خطأ: المتجر غير موجود أو غير متاح حالياً.");
    }

    // تحديد العملة والرمز
    $currency_code = $business['currency'] ?? 'SYP';
    $currency_symbol = ($currency_code === 'USD') ? '$' : 'ل.س';

    // 2. جلب عناوين المستخدم
    $stmt_addresses = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt_addresses->execute([$current_user_id]);
    $user_addresses = $stmt_addresses->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("حدث خطأ أثناء الاتصال بقاعدة البيانات. يرجى المحاولة مرة أخرى لاحقاً.");
}

$page_title = 'إتمام الطلب - Syriazzle';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/lib/leaflet.css"/>
    <link rel="stylesheet" href="css/lib/geosearch.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/checkout_styles.css">
</head>
<body>
    <?php include 'header_store.php'; ?>

    <div class="checkout-page">
        <header class="checkout-header">إتمام الطلب من <?php echo htmlspecialchars($business['name']); ?></header>
        
        <div class="checkout-container">
            <!-- وقت التوصيل -->
            <div class="checkout-section">
                <h3 class="section-title"><i class="fas fa-clock"></i> وقت التوصيل</h3>
                <div class="delivery-time-options">
                    <button type="button" class="time-option-btn selected" data-time-pref="asap">بأسرع وقت</button>
                    <button type="button" class="time-option-btn" data-time-pref="scheduled">تحديد وقت لاحق</button>
                </div>
                <div id="scheduled-time-display"></div>
            </div>

            <!-- العنوان -->
            <div class="checkout-section">
                <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> عنوان التوصيل</h3>
                <div id="address-display">
                    <div class="skeleton" style="width: 100%; height: 80px; border-radius: 8px;"></div>
                </div>
            </div>

            <!-- الدفع -->
            <div class="checkout-section">
                <h3 class="section-title"><i class="fas fa-credit-card"></i> طريقة الدفع</h3>
                <div class="payment-options options-grid">
                    <div class="option-box selected" data-method="cash"><i class="fas fa-money-bill-wave icon"></i><span>الدفع نقداً</span></div>
                    <div class="option-box" data-method="syriatel_cash" disabled><i class="fas fa-mobile-alt icon"></i><span>سيريتل كاش</span></div>
                    <div class="option-box" data-method="bank_card" disabled><i class="fas fa-credit-card icon"></i><span>بطاقة بنكية</span></div>
                </div>
            </div>

            <!-- الإكرامية (دائماً بالليرة السورية لأنها للسائق) -->
            <div class="checkout-section">
                <h3 class="section-title"><i class="fas fa-hand-holding-heart"></i> إكرامية للسائق (اختياري)</h3>
                <div id="tip-options" class="tip-options">
                    <button type="button" class="tip-btn selected" data-tip="0">لا يوجد</button>
                    <button type="button" class="tip-btn" data-tip="2000">2,000</button>
                    <button type="button" class="tip-btn" data-tip="3000">3,000</button>
                    <button type="button" class="tip-btn" data-tip="5000">5,000</button>
                </div>
            </div>

            <!-- كود الحسم -->
            <div class="checkout-section">
                <h3 class="section-title"><i class="fas fa-tags"></i> كود الحسم</h3>
                <div class="promo-container">
                    <input type="text" id="promo-input" class="promo-input" placeholder="هل لديك كود حسم؟">
                    <button type="button" id="apply-promo-btn" class="promo-btn">تطبيق</button>
                </div>
                <div id="promo-feedback" class="promo-feedback"></div>
            </div>
            
            <!-- ملخص الفاتورة -->
            <div class="checkout-section">
                 <h3 class="section-title"><i class="fas fa-receipt"></i> ملخص الفاتورة (العملة: <?php echo $currency_symbol; ?>)</h3>
                <div id="invoice-summary">
                    <div class="summary-line">
                        <span>المجموع الفرعي</span>
                        <!-- سيتم تعبئته بواسطة JS حسب عملة المتجر -->
                        <span id="summary-items-price"><div class="skeleton skeleton-text"></div></span>
                    </div>
                    
                    <div class="summary-line" id="promo-line" style="display:none;">
                        <span>الخصم</span>
                        <span id="summary-promo-discount"></span>
                    </div>
                    
                    <div class="summary-line">
                        <span>رسوم التوصيل</span>
                        <!-- دائماً بالليرة السورية -->
                        <span id="summary-delivery-fee"><div class="skeleton skeleton-text"></div></span>
                    </div>
                    
                    <div class="summary-line">
                        <span>إكرامية السائق</span>
                        <!-- دائماً بالليرة السورية -->
                        <span id="summary-tip-amount"><div class="skeleton skeleton-text"></div></span>
                    </div>
                    
                    <div class="summary-line total">
                        <strong>المجموع النهائي</strong>
                        <!-- هذا العنصر سيعالج العرض المزدوج (دولار + ليرة) إذا لزم الأمر -->
                        <strong id="summary-total-price" style="direction: ltr;"><div class="skeleton skeleton-text" style="width:120px;"></div></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="checkout-footer">
            <div class="footer-total">
                <span>المجموع</span>
                <strong id="footer-total-price" style="direction: ltr;"><div class="skeleton skeleton-text" style="width:120px;"></div></strong>
            </div>
            <button id="submit-order-btn" class="submit-btn" disabled>إرسال الطلب</button>
        </footer>
    </div>

    <!-- Map Modal -->
    <div class="map-modal-overlay" id="map-modal" role="dialog" aria-modal="true">
        <div class="map-modal-content">
            <header class="map-modal-header">
                <h4>حدد موقع التوصيل بدقة</h4>
                <button id="close-map-btn" class="close-modal-btn" aria-label="إغلاق">&times;</button>
            </header>
            <main class="map-modal-body">
                <div id="map-container"></div>
                <div class="map-center-marker"><i class="fas fa-map-marker-alt"></i></div>
                <button id="my-location-btn" class="map-my-location-btn" aria-label="تحديد موقعي الحالي"><i class="fas fa-crosshairs"></i></button>
            </main>
            <footer class="map-modal-footer">
                <div class="map-modal-address" id="modal-address-text"><div class="skeleton skeleton-text" style="width: 80%;"></div></div>
                <div class="map-modal-buttons">
                    <button class="map-modal-btn btn-confirm-address" id="confirm-address-btn" disabled>تأكيد هذا الموقع</button>
                </div>
            </footer>
        </div>
    </div>

    <!-- JS Libraries -->
    <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/geosearch.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>

    <!-- تمرير البيانات إلى JS -->
    <script>
        const CHECKOUT_DATA = {
            businessId: <?php echo json_encode($business_id); ?>,
            userAddresses: <?php echo json_encode($user_addresses, JSON_NUMERIC_CHECK); ?>,
            businessLocation: {
                lat: <?php echo json_encode(floatval($business['latitude'] ?? 33.5138)); ?>,
                lon: <?php echo json_encode(floatval($business['longitude'] ?? 36.2765)); ?>
            },
            // البيانات الجديدة للعملة
            currencyCode: <?php echo json_encode($currency_code); ?>, // 'USD' or 'SYP'
            currencySymbol: <?php echo json_encode($currency_symbol); ?> // '$' or 'ل.س'
        };
    </script>
    
    <script src="js/checkout.js"></script>
</body>
</html>