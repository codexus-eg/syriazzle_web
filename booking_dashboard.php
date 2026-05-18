<?php
require_once 'php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_url=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT id, name, logo_image, booking_category, payment_details, description, phone, whatsapp, latitude, longitude, status,
         checkin_time, checkout_time 
         FROM businesses 
         WHERE user_id = ? 
         AND business_type IN ('booking', 'hybrid') 
         AND deleted_at IS NULL"
    );
    $stmt->execute([$current_user_id]);
    $user_businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $current_business_id = null;
    $current_business = null;

    if (!empty($user_businesses)) {
        $current_business_id = isset($_GET['business_id']) ? (int)$_GET['business_id'] : $user_businesses[0]['id'];
        foreach($user_businesses as $business) {
            if ($business['id'] == $current_business_id) {
                $current_business = $business;
                break;
            }
        }
    }
    
    if ($current_business_id && !$current_business) {
        header('Location: status_page.php?status=unauthorized');
        exit;
    }

    if ($current_business && $current_business['status'] !== 'approved') {
        $status_param = $current_business['status'];
        header('Location: status_page.php?status=' . urlencode($status_param));
        exit;
    }

} catch (PDOException $e) {
    error_log("Error fetching business data for booking dashboard: " . $e->getMessage());
    header('Location: status_page.php?status=generic_error');
    exit;
}

$page_title = $current_business ? 'لوحة التحكم - ' . htmlspecialchars($current_business['name']) : 'لوحة التحكم';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/booking_dashboard.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
</head>
<body>
    <?php if (empty($user_businesses)): ?>
        <div class="no-booking-business-found">
            <div class="message-box">
                <i class="fas fa-store-slash"></i>
                <h1>لم يتم العثور على نشاط تجاري يدعم الحجوزات</h1>
                <p>يبدو أنك لم تقم بتفعيل نظام الحجوزات لأي من أنشطتك التجارية بعد. يمكنك إضافة نشاط جديد مخصص للحجوزات أو تفعيل الميزة لنشاط حالي.</p>
                <a href="add_booking_business.php" class="btn btn-primary"><i class="fas fa-plus"></i> ابدأ الآن، وأضف نشاطك للحجوزات</a>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-container">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <a href="index.html" class="brand-logo">Syriazzle</a>
                    <button class="close-sidebar-btn" id="close-sidebar-btn">&times;</button>
                </div>
                <div class="business-selector">
                    <img src="<?php echo htmlspecialchars($current_business['logo_image'] ?? 'image/default_logo.png'); ?>" alt="Logo">
                    <div class="business-info">
                        <strong><?php echo htmlspecialchars($current_business['name']); ?></strong>
                        <span>لوحة تحكم الحجوزات</span>
                    </div>
                </div>

                <!-- ====== القائمة الجانبية المطورة والكاملة ====== -->
                <nav class="sidebar-nav">
                    <a href="#" class="nav-link active" data-view="overview"><i class="fas fa-tachometer-alt fa-fw"></i><span>نظرة عامة</span></a>
                    <a href="#" class="nav-link" data-view="calendar"><i class="fas fa-calendar-alt fa-fw"></i><span>تقويم الحجوزات</span></a>
                    
                    <a href="#" class="nav-link" data-view="manage_resources"><i class="fas fa-cubes fa-fw"></i><span id="dynamic-manage-link-text">إدارة الأصول</span></a>
                    
                    <!-- **التبويب الجديد 1: إدارة الخدمات والأسعار (يظهر دائمًا)** -->
                    <a href="#" class="nav-link" data-view="manage_services"><i class="fas fa-tags fa-fw"></i><span>الخدمات والأسعار</span></a>

                    <!-- **التبويب الجديد 2: إدارة التوافر (يظهر بشكل مشروط)** -->
                    <?php if (in_array($current_business['booking_category'], ['clinic', 'consulting'])): ?>
                        <a href="#" class="nav-link" data-view="manage_availability"><i class="fas fa-clock fa-fw"></i><span>إدارة التوافر</span></a>
                    <?php endif; ?>

                    <a href="#" class="nav-link" data-view="customers"><i class="fas fa-users fa-fw"></i><span>العملاء</span></a>
                    <a href="#" class="nav-link" data-view="settings"><i class="fas fa-cog fa-fw"></i><span>الإعدادات</span></a>
                </nav>
                <!-- ======================================================== -->

                <div class="sidebar-footer">
                    <a href="index.html"><i class="fas fa-home"></i> العودة للرئيسية</a>
                    <a href="php/logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
                </div>
            </aside>

            <main class="main-content">
                <header class="main-header">
                    <button class="open-sidebar-btn" id="open-sidebar-btn"><i class="fas fa-bars"></i></button>
                    <h1 id="view-title">نظرة عامة</h1>
                </header>

                <div class="view" id="module-content-area">
                    <!-- سيتم تحميل محتوى الوحدات النمطية هنا بواسطة JavaScript -->
                </div>
                
                <div class="view" id="settings-view" style="display: none;">
                    <div class="settings-container">
                        <div class="settings-nav">
                            <a href="#category-settings" class="settings-nav-link active"><i class="fas fa-briefcase"></i> نوع النشاط</a>
                            <a href="#profile-settings" class="settings-nav-link"><i class="fas fa-id-card"></i> الملف التعريفي</a>
                            <a href="#location-settings" class="settings-nav-link"><i class="fas fa-map-marker-alt"></i> الموقع</a>
                            <a href="#payment-settings" class="settings-nav-link"><i class="fas fa-credit-card"></i> إعدادات الدفع</a>
                        </div>
                        <div class="settings-content">
                            <form id="settings-form" enctype="multipart/form-data">
                                <div class="settings-pane active" id="category-settings">
                                    <h2>حدد نوع نشاطك التجاري</h2>
                                    <p>هذا هو أهم إعداد، سيقوم بتخصيص كل لوحة التحكم لتناسب احتياجاتك.</p>
                                    <div class="category-selector-grid"></div>
                                    <input type="hidden" name="booking_category" id="booking_category_input">
                                </div>

                                <div class="settings-pane" id="profile-settings">
                                    <h2>الملف التعريفي والصور</h2>
                                    <p>هذه هي المعلومات التي سيراها الزبائن عند زيارة صفحتك.</p>
                                    <div class="form-group"><label for="business_name">اسم النشاط التجاري</label><input type="text" id="business_name" name="name"></div>
                                    <div class="form-group"><label for="business_description">الوصف</label><textarea id="business_description" name="description" rows="5"></textarea></div>
                                    <div class="form-row"><div class="form-group"><label for="business_phone">رقم الهاتف</label><input type="tel" id="business_phone" name="phone"></div><div class="form-group"><label for="business_whatsapp">رقم الواتساب</label><input type="tel" id="business_whatsapp" name="whatsapp"></div></div>
                                    <div class="image-upload-section">
                                        <div class="form-group"><label>الشعار (Logo)</label><div class="image-uploader" id="logo-uploader"></div></div>
                                        <div class="form-group"><label>صورة الغلاف</label><div class="image-uploader" id="cover-uploader"></div></div>
                                    </div>
                                    <div class="form-group"><label>معرض الصور (حتى 10 صور)</label><div class="gallery-uploader" id="gallery-uploader"></div></div>
                                </div>

                                <div class="settings-pane" id="location-settings">
                                    <h2>الموقع على الخريطة</h2>
                                    <p>حدد موقع نشاطك بدقة ليسهل على الزبائن الوصول إليك.</p>
                                    <div id="map-container">
                                        <div id="settings-map"></div>
                                        <div class="map-pin"><i class="fas fa-map-marker-alt"></i></div>
                                    </div>
                                    <input type="hidden" name="latitude" id="latitude_input">
                                    <input type="hidden" name="longitude" id="longitude_input">
                                </div>
                                
                                <div class="settings-pane" id="payment-settings">
                                    <h2>إعدادات الدفع واستقبال العربون</h2>
                                    <p>أدخل أرقام حساباتك التي ترغب باستقبال دفعات العربون عليها.</p>
                                    <div class="payment-options-container">
                                        <div class="payment-option"><img src="image/payment/syriatel_cash.png" alt="Syriatel Cash"><div class="form-group"><label for="syriatel_cash_number">رقم سيريتل كاش</label><input type="tel" dir="ltr" id="syriatel_cash_number" name="payment_details[syriatel_cash]" placeholder="09xxxxxxxx"></div></div>
                                        <div class="payment-option"><img src="image/payment/mtn_cash.svg" alt="MTN Cash"><div class="form-group"><label for="mtn_cash_number">رقم MTN كاش</label><input type="tel" dir="ltr" id="mtn_cash_number" name="payment_details[mtn_cash]" placeholder="09xxxxxxxx"></div></div>
                                        <div class="payment-option"><img src="image/payment/sham_cash.svg" alt="Sham Cash"><div class="form-group"><label for="sham_cash_number">رقم شام كاش</label><input type="tel" dir="ltr" id="sham_cash_number" name="payment_details[sham_cash]" placeholder="09xxxxxxxx"></div></div>
                                    </div>
                                </div>

                                <div class="settings-footer">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ الإعدادات</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- النوافذ المنبثقة تبقى كما هي -->
        <div class="modal-overlay" id="service-modal">
            <div class="modal-content"><div class="modal-header"><h3 id="service-modal-title"></h3><button class="modal-close-btn" id="service-modal-close-btn">&times;</button></div><form id="service-form"><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" id="service-modal-cancel-btn">إلغاء</button><button type="submit" class="btn btn-primary">حفظ</button></div></form></div>
        </div>
        <div class="modal-overlay" id="booking-details-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="booking-modal-title">تفاصيل الحجز</h3>
                    <button class="modal-close-btn" id="booking-modal-close-btn">&times;</button>
                </div>
                <div class="modal-body" id="booking-modal-body">
                    <!-- JavaScript will fill booking details here -->
                </div>
                <div class="modal-footer" id="booking-modal-footer">
                    <!-- JavaScript will add action buttons here -->
                </div>
            </div>
        </div>
        <script>
            const CURRENT_BUSINESS_ID = <?php echo json_encode((int)$current_business_id); ?>;
            const BUSINESS_DATA = <?php echo json_encode($current_business); ?>;
        </script>
        <script src="js/booking_dashboard.js"></script> 
    <?php endif; ?>
</body>
</html>