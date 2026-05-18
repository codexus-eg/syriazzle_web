<?php
// ========================================================================
// Syriazzle Mall - Checkout Page (النسخة النهائية المضمونة)
// ========================================================================
require_once 'php/db_connect.php';

// 1. التحقق من الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = "mall_checkout.php";
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    // 2. جلب بيانات المستخدم
    $stmt_user = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
    $stmt_user->execute([$current_user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 3. جلب العناوين المحفوظة
    $stmt_addresses = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt_addresses->execute([$current_user_id]);
    $user_addresses = $stmt_addresses->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) { 
    error_log("Checkout DB Error: " . $e->getMessage());
    die("خطأ في تحميل البيانات. حاول مرة أخرى."); 
}

$page_title = 'إتمام الطلب - مول Syriazzle';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- مكتبات الخرائط (يجب أن تكون في الهيدر) -->
    <link rel="stylesheet" href="css/lib/leaflet.css"/>
    <link rel="stylesheet" href="css/lib/geosearch.css"/>
    
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/checkout_styles.css">
</head>
<body>
    <?php include 'header_store.php'; ?>

    <div class="checkout-page">
        <header class="checkout-header">إتمام الطلب من مول Syriazzle</header>
        
        <form id="mall-checkout-form">
            <div class="checkout-container">
                <!-- 1. معلومات المستلم -->
                <div class="checkout-section">
                    <h3 class="section-title"><i class="fas fa-user-circle"></i> معلومات المستلم</h3>
                    <div class="form-group">
                        <label for="customer_name">الاسم الكامل</label>
                        <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_phone">رقم الهاتف</label>
                        <input type="tel" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- 2. عنوان التوصيل (المشكلة كانت هنا) -->
                <div class="checkout-section">
                    <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> عنوان التوصيل</h3>
                    <div id="address-display">
                        <!-- سيتم استبداله فوراً بواسطة JS -->
                        <div class="skeleton" style="height: 80px;"></div>
                    </div>
                </div>

                <!-- 3. ملخص الطلب -->
                <div class="checkout-section">
                    <h3 class="section-title"><i class="fas fa-receipt"></i> ملخص الطلب</h3>
                    <div id="order-summary-container">
                        <div class="skeleton" style="height: 50px;"></div>
                    </div>
                </div>

                <!-- 4. كود الحسم -->
                <div class="checkout-section">
                    <h3 class="section-title"><i class="fas fa-tags"></i> كود الحسم</h3>
                    <div class="promo-container">
                        <input type="text" id="promo-input" class="promo-input" placeholder="أدخل كود الحسم">
                        <button type="button" id="apply-promo-btn" class="promo-btn">تطبيق</button>
                    </div>
                    <div id="promo-feedback" class="promo-feedback"></div>
                </div>
                
                <!-- 5. الفاتورة -->
                <div class="checkout-section">
                    <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> الفاتورة النهائية</h3>
                    <div id="invoice-summary">
                        <div class="summary-line"><span>مجموع المنتجات</span><span id="summary-items-price">0 ل.س</span></div>
                        <div class="summary-line" id="promo-line" style="display:none;"><span>الخصم</span><span id="summary-promo-discount">0</span></div>
                        <div class="summary-line"><span>رسوم التوصيل</span><span id="summary-delivery-fee">---</span></div>
                        <div class="summary-line total"><strong>المجموع النهائي</strong><strong id="summary-total-price">---</strong></div>
                    </div>
                </div>
            </div>
            
            <footer class="checkout-footer">
                <button id="submit-order-btn" type="submit" class="submit-btn" disabled>الرجاء تحديد الموقع أولاً</button>
            </footer>
        </form>
    </div>

    <!-- نافذة الخريطة -->
    <div class="map-modal-overlay" id="map-modal">
        <div class="map-modal-content">
            <header class="map-modal-header">
                <h4>تحديد الموقع بدقة</h4>
                <button id="close-map-btn" type="button" class="close-modal-btn">&times;</button>
            </header>
            <main class="map-modal-body">
                <div id="map-container"></div>
                <div class="map-center-marker"><i class="fas fa-map-marker-alt"></i></div>
                <button id="my-location-btn" type="button" class="map-my-location-btn"><i class="fas fa-crosshairs"></i></button>
            </main>
            <footer class="map-modal-footer">
                <div class="map-modal-address" id="modal-address-text">حرك الدبوس لتحديد موقعك</div>
                <button class="map-modal-btn btn-confirm-address" id="confirm-address-btn" type="button" disabled>تأكيد الموقع</button>
            </footer>
        </div>
    </div>

    <!-- تحميل السكربتات بالترتيب الصحيح -->
    <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/geosearch.js"></script>
    
    <script>
        // تمرير البيانات كمتغير عام قبل تحميل ملف JS
        const CHECKOUT_DATA = {
            userAddresses: <?php echo json_encode($user_addresses, JSON_NUMERIC_CHECK); ?>
        };
    </script>
    
    <!-- ملف المنطق الرئيسي -->
    <script src="js/mall_checkout.js"></script>
</body>
</html>