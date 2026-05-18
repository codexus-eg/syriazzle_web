<?php
require_once 'php/db_connect.php';

// --- 1. استقبال البيانات والتحقق الأمني ---
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($business_id === 0) {
    die("خطأ: لم يتم تحديد النشاط التجاري.");
}
$current_user_id = $_SESSION['user_id'] ?? null;

// --- 2. جلب البيانات الأساسية للنشاط التجاري ---
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ? AND status = 'approved' AND deleted_at IS NULL AND business_type IN ('booking', 'hybrid')");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$business) {
        die("هذا النشاط التجاري غير موجود أو لا يدعم نظام الحجوزات حاليًا.");
    }
    
    $booking_category = $business['booking_category'];
    $services = [];
    $existing_bookings = [];

    // **المنطق الديناميكي لجلب البيانات بناءً على نوع النشاط**
    if (in_array($booking_category, ['clinic', 'consulting', 'tourism'])) {
        // **المنطق القديم: للأنشطة القائمة على المواعيد**
        $services_stmt = $pdo->prepare("
            SELECT 
                s.*, 
                CONCAT('[', 
                    IFNULL(GROUP_CONCAT(DISTINCT
                        CONCAT('{\"day_of_week\":', sa.day_of_week, ',\"start_time\":\"', sa.start_time, '\",\"end_time\":\"', sa.end_time, '\"}')
                    ), '')
                , ']') as availability_schedule
            FROM business_services s
            LEFT JOIN service_availability sa ON s.id = sa.service_id
            WHERE s.business_id = ? AND s.is_active = 1
            GROUP BY s.id
            ORDER BY s.price ASC
        ");
        $services_stmt->execute([$business_id]);
        $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // جلب الحجوزات للخدمات (Service-based bookings)
        $bookings_stmt = $pdo->prepare("
            SELECT b.service_id, b.start_datetime, b.end_datetime 
            FROM bookings b
            JOIN business_services s ON b.service_id = s.id
            WHERE s.business_id = ? AND b.status IN ('confirmed', 'pending_confirmation') AND b.end_datetime > NOW()
        ");
        $bookings_stmt->execute([$business_id]);
        $existing_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif (in_array($booking_category, ['hotel', 'restaurant', 'event'])) {
        // **المنطق الجديد: للأنشطة القائمة على الأصول**
        $services_stmt = $pdo->prepare("
            SELECT 
                s.*,
                r.name as resource_name, 
                r.meta_data,
                r.id as resource_id_fk
            FROM business_services s
            JOIN business_resources r ON s.resource_id = r.id
            WHERE s.business_id = ? AND s.is_active = 1 AND r.status = 'available'
            ORDER BY s.price ASC
        ");
        $services_stmt->execute([$business_id]);
        $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

        // جلب الحجوزات للموارد (Resource-based bookings)
        $bookings_stmt = $pdo->prepare("
            SELECT b.resource_id, b.start_datetime, b.end_datetime 
            FROM bookings b
            WHERE b.resource_id IN (SELECT id FROM business_resources WHERE business_id = ?)
            AND b.status IN ('confirmed', 'pending_confirmation') AND b.end_datetime > NOW()
        ");
        $bookings_stmt->execute([$business_id]);
        $existing_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // --- جلب بقية البيانات ---
    $gallery_stmt = $pdo->prepare("SELECT id, image_path FROM business_gallery WHERE business_id = ? ORDER BY id ASC");
    $gallery_stmt->execute([$business_id]);
    $gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $reviews_stmt = $pdo->prepare("SELECT r.rating, r.review_text, r.created_at, u.username FROM business_reviews r JOIN users u ON r.user_id = u.id WHERE r.business_id = ? ORDER BY r.created_at DESC");
    $reviews_stmt->execute([$business_id]);
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // حساب متوسط التقييم
    $total_rating = array_sum(array_column($reviews, 'rating'));
    $avg_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 0;

} catch (PDOException $e) {
    error_log("Service Profile Page Error: " . $e->getMessage());
    die("حدث خطأ أثناء تحميل بيانات الصفحة. يرجى المحاولة مرة أخرى.");
}

$page_title = htmlspecialchars($business['name']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Syriazzle</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/service_profile.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <?php include 'header_store.php'; ?>

    <div class="service-profile-container">
        <header class="service-hero" style="background-image: url('<?php echo htmlspecialchars($business['cover_image'] ?? 'image/default_cover.jpg'); ?>')">
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <span class="business-category-badge"><?php echo htmlspecialchars(ucfirst($business['booking_category'])); ?></span>
                <h1><?php echo htmlspecialchars($business['name']); ?></h1>
                <div class="hero-meta">
                    <?php if ($avg_rating > 0): ?>
                        <div class="rating"><i class="fas fa-star"></i> <?php echo $avg_rating; ?> <span>(<?php echo count($reviews); ?> مراجعة)</span></div>
                    <?php endif; ?>
                    <div class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($business['city']); ?></div>
                </div>
            </div>
        </header>

        <div class="profile-layout">
            <main class="profile-main-content">
                <nav class="profile-tabs">
                    <a href="#overview" class="tab-link active" data-tab="overview"><i class="fas fa-info-circle"></i> نظرة عامة</a>
                    <a href="#services" class="tab-link" data-tab="services"><i class="fas fa-concierge-bell"></i> الخيارات المتاحة</a>
                    <a href="#gallery" class="tab-link" data-tab="gallery"><i class="fas fa-images"></i> معرض الصور</a>
                    <a href="#reviews" class="tab-link" data-tab="reviews"><i class="fas fa-star"></i> المراجعات</a>
                </nav>
                <div class="tab-content-container">
                    <div id="overview" class="tab-pane active">
                        <h2>عن المكان</h2>
                        <p class="description-text"><?php echo nl2br(htmlspecialchars($business['description'])); ?></p>
                        <h3><i class="fas fa-map-marked-alt"></i> الموقع على الخريطة</h3>
                        <div id="map" style="height: 300px; border-radius: 12px; margin-bottom: 2rem;"></div>
                        <h3><i class="fas fa-phone-alt"></i> معلومات التواصل</h3>
                        <ul class="contact-list">
                            <li><i class="fas fa-phone fa-fw"></i> <span><?php echo htmlspecialchars($business['phone'] ?? 'لم يحدد'); ?></span></li>
                            <li><i class="fab fa-whatsapp fa-fw"></i> <span><?php echo htmlspecialchars($business['whatsapp'] ?? 'لم يحدد'); ?></span></li>
                        </ul>
                    </div>
                    <div id="services" class="tab-pane">
                        <h2><?php echo (in_array($booking_category, ['hotel', 'restaurant', 'event'])) ? 'اختر الغرفة أو الطاولة التي تناسبك' : 'اختر الخدمة التي تناسبك'; ?></h2>
                        <div id="services-list-container">
                            <!-- المحتوى هنا يتم بناؤه بواسطة JavaScript -->
                        </div>
                    </div>
                    <div id="gallery" class="tab-pane">
                        <h2>معرض الصور</h2>
                        <?php if (empty($gallery_images)): ?>
                            <p>لا يوجد صور في المعرض حالياً.</p>
                        <?php else: ?>
                            <div class="gallery-grid">
                                <?php foreach ($gallery_images as $img): ?>
                                    <a href="<?php echo htmlspecialchars($img['image_path']); ?>" class="gallery-item" data-fancybox="gallery">
                                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="صورة من المعرض" loading="lazy">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="reviews" class="tab-pane">
                        <h2>آراء الزبائن</h2>
                        <div class="reviews-container">
                            <?php if (empty($reviews)): ?>
                                <p>لا توجد مراجعات لهذا النشاط التجاري بعد.</p>
                            <?php else: ?>
                                <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                        <div class="review-rating"><?php echo str_repeat('<i class="fas fa-star"></i>', (int)$review['rating']); ?></div>
                                    </div>
                                    <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                                    <small class="review-date"><?php echo date('Y-m-d', strtotime($review['created_at'])); ?></small>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            
            <aside class="profile-sidebar">
                <div id="booking-widget" class="profile-card">
                    <div id="booking-interface-container"></div>
                    <div id="booking-summary-container" style="display: none;"></div>
                    <button id="checkout-button" class="btn btn-primary btn-block" disabled>
                        أكمل اختياراتك للمتابعة
                    </button>
                    <form id="checkout-form" action="booking_checkout.php" method="POST" style="display: none;">
                        <input type="hidden" name="booking_data" id="booking-data-input">
                    </form>
                </div>
            </aside>
        </div>
    </div>

    <script>
        const BUSINESS_DATA = <?php echo json_encode($business, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const SERVICES_DATA = <?php echo json_encode($services, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>; 
        const EXISTING_BOOKINGS = <?php echo json_encode($existing_bookings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ar.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/service_profile.js"></script>
</body>
</html>