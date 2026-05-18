<?php
require_once 'php/db_connect.php';

// --- 1. تحديد نوع الصفحة والتحقق منه ---
$page_type = $_GET['type'] ?? 'delivery'; // الافتراضي هو التوصيل
if (!in_array($page_type, ['delivery', 'booking'])) {
    $page_type = 'delivery'; // قيمة احتياطية آمنة
}

// --- 2. تحديد ما إذا كنا في وضع عرض الفئات أو عرض النتائج ---
$category_filter = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : null;
$is_category_view = ($category_filter === null);

// --- 3. إعداد العناوين والمصفوفات بناءً على نوع الصفحة ---
$page_title = '';
$hero_title = '';
$hero_subtitle = '';
$categories_array = [];

if ($page_type === 'delivery') {
    $page_title = 'متاجر التوصيل';
    $hero_title = 'اكتشف أفضل المتاجر في سوريا';
    $hero_subtitle = 'طعام، حلويات، مشروبات، ملابس،أحذية وحقائب وأكثر... تصلك لبيتك بضغطة زر.';
    // **مصفوفة الفئات الثابتة للتوصيل**
    $categories_array = [
        // ['name' => 'مطاعم', 'db_category' => 'مطعم', 'icon' => 'fas fa-utensils', 'image' => 'image/categories/delivery/restaurants.webp'],
        ['name' => 'عصائر وكوكتيلات', 'db_category' => 'عصائر وكوكتيلات', 'icon' => 'fas fa-glass-water', 'image' => 'image/categories/delivery/coffee.webp'],
        ['name' => 'محلات أكل', 'db_category' => 'محلات أكل', 'icon' => 'fas fa-hamburger', 'image' => 'image/categories/delivery/fast-food.webp'],
        ['name' => 'ملابس', 'db_category' => 'متجر ملابس', 'icon' => 'fas fa-tshirt', 'image' => 'image/categories/delivery/clothing.webp'],
        ['name' => 'أحذية وحقائب', 'db_category' => 'متجر أحذية وحقائب', 'icon' => 'fas fa-shoe-prints', 'image' => 'image/categories/delivery/shoes.webp'],
        ['name' => 'حلويات', 'db_category' => 'حلويات', 'icon' => 'fas fa-ice-cream', 'image' => 'image/categories/delivery/sweets.webp'],
        ['name' => 'معجنات', 'db_category' => 'معجنات', 'icon' => 'fas fa-bread-slice', 'image' => 'image/categories/delivery/pastries.webp'],
        ['name' => 'أجهزة كشف معادن', 'db_category' => 'أجهزة كشف معادن', 'icon' => 'fas fas fa-search', 'image' => 'image/categories/delivery/detectors.webp'],
        ['name' => 'خدمات طبية', 'db_category' => 'خدمات طبية', 'icon' => 'fas fa-user-doctor', 'image' => 'image/categories/delivery/Medical Services.webp'],
        ['name' => 'سياحة', 'db_category' => 'سياحة', 'icon' => 'fas fa-plane-departure', 'image' => 'image/categories/delivery/Tourism.webp'],
        ['name' => 'سيارات', 'db_category' => 'سيارات', 'icon' => 'fas fa-car-side', 'image' => 'image/categories/delivery/Cars.webp'],
        ['name' => 'بقالة', 'db_category' => 'بقالة', 'icon' => 'fas fa-basket-shopping', 'image' => 'image/categories/delivery/Grocery.webp'],
        ['name' => 'إلكترونيات', 'db_category' => 'إلكترونيات', 'icon' => 'fas fa-plug', 'image' => 'image/categories/delivery/Electronics.webp'],
        ['name' => 'هدايا وإكسسوارات', 'db_category' => 'هدايا وإكسسوارات', 'icon' => 'fas fa-gift', 'image' => 'image/categories/delivery/Gifts & Accessories.webp'],
        ['name' => 'مكياجات وعطور', 'db_category' => 'مكياجات وعطور', 'icon' => 'fas fa-spray-can', 'image' => 'image/categories/delivery/Makeup & Perfumes.webp'],
        ['name' => 'هواتف وإكسسوارات', 'db_category' => 'هواتف وإكسسوارات', 'icon' => 'fas fa-mobile-screen-button', 'image' => 'image/categories/delivery/Mobile & Accessories.webp'],
        ['name' => 'بصريات ونظارات', 'db_category' => 'نظارات', 'icon' => 'fas fa-glasses', 'image' => 'image/categories/delivery/Optics.webp'],
        ['name' => 'مكتبة وقرطاسية', 'db_category' => 'مكتبة وقرطاسية', 'icon' => 'fas fa-book-open', 'image' => 'image/categories/delivery/Stationery.webp'],
        ['name' => 'زهور وتباتات', 'db_category' => 'زهور ونباتات', 'icon' => 'fas fa-seedling', 'image' => 'image/categories/delivery/Flowers & Plants.webp'],
        ['name' => 'مفروشات وديكور', 'db_category' => 'مفروشات وديكور', 'icon' => 'fas fa-couch', 'image' => 'image/categories/delivery/Furniture & Decor.webp'],
        ['name' => 'أراجيل ودخان', 'db_category' => 'أراجيل ودخان', 'icon' => 'fas ', 'image' => 'image/categories/delivery/hookahs&cigarettes.webp'],
    ];
} else { // booking
    $page_title = 'خدمات الحجز';
    $hero_title = 'احجز تجربتك القادمة بسهولة';
    $hero_subtitle = 'طعام، حلويات، مشروبات، ملابس،أحذية وحقائب وأكثر... تصلك لبيتك بضغطة زر.';
    // **مصفوفة الفئات الثابتة للحجوزات**
    $categories_array = [
        ['name' => 'فنادق', 'db_category' => 'hotel', 'icon' => 'fas fa-hotel', 'image' => 'image/categories/booking/hotels.webp'],
        ['name' => 'مطاعم', 'db_category' => 'restaurant', 'icon' => 'fas fa-utensils', 'image' => 'image/categories/booking/restaurants.webp'],
        ['name' => 'عيادات', 'db_category' => 'clinic', 'icon' => 'fas fa-user-doctor', 'image' => 'image/categories/booking/clinics.webp'],
        ['name' => 'صالات أفراح', 'db_category' => 'event', 'icon' => 'fas fa-ring', 'image' => 'image/categories/booking/events.webp'],
        ['name' => 'استشارات', 'db_category' => 'consulting', 'icon' => 'fas fa-handshake', 'image' => 'image/categories/booking/consulting.webp'],
        ['name' => 'سياحة وسفر', 'db_category' => 'tourism', 'icon' => 'fas fa-plane', 'image' => 'image/categories/booking/tourism.webp'],
    ];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Syriazzle</title>
    <link rel="icon" href="image/favicon.png" type="image/png">
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/listings_page.css"> <!-- ملف التصميم الجديد -->
</head>
<body>

    <?php include 'header_store.php'; ?>

    <!-- ======================= نافذة الفلاتر المتقدمة (مخفية) ======================= -->
    <div class="filter-modal" id="filter-modal">
        <div class="filter-modal-header">
            <h3><i class="fas fa-filter"></i> خيارات البحث المتقدم</h3>
            <button class="close-filter-btn" id="close-filter-btn">&times;</button>
        </div>
        <div class="filter-modal-body">
            <div class="filter-group">
                <label for="search-text">البحث بالاسم:</label>
                <input type="text" id="search-text" placeholder="اكتب اسم المتجر أو النشاط...">
            </div>
            <div class="filter-group">
                <label for="governorate-select">البحث حسب المحافظة:</label>
                <select id="governorate-select">
                    <option value="">كل المحافظات</option>
                    <!-- JavaScript -->
                </select>
            </div>
            <div class="filter-group nearby-group">
                <label for="distance-slider">البحث قربي (حتى <?php echo '<span id="distance-value">10</span>'; ?> كم)</label>
                <input type="range" id="distance-slider" min="1" max="50" value="10" class="slider">
                <button class="btn-primary" id="nearby-search-btn">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>استخدم موقعي الحالي</span>
                </button>
            </div>
        </div>
    </div>


    <main class="listings-wrapper">
        <!-- ======================= قسم البطل والبحث الرئيسي ======================= -->
        <header class="hero-slider-section">
            <div class="carousel-3d">
                
                <!-- الشريحة الأولى -->
                <div class="carousel-item">
                    <img src="image/offers/1.png" alt="عرض 1">
                    <div class="hero-overlay-gradient"></div>
                    <div class="hero-content-wrapper">
                        <h1 class="main-title"><?php echo $hero_title; ?></h1>
                    </div>
                </div>

                <!-- الشريحة الثانية -->
                <div class="carousel-item">
                    <img src="image/offers/2.png" alt="عرض 2">
                    <div class="hero-overlay-gradient"></div>
                    <div class="hero-content-wrapper">
                        <h1 class="main-title">العرض المميز الثاني</h1>
                    </div>
                </div>

                <!-- الشريحة الثالثة -->
                <div class="carousel-item">
                    <img src="image/offers/4.png" alt="عرض 3">
                    <div class="hero-overlay-gradient"></div>
                    <div class="hero-content-wrapper">
                        <h1 class="main-title">العرض الثالث</h1>
                    </div>
                </div>

            </div>
        </header>

        <div class="container page-content">
            <!-- ======================= قسم عرض الفئات (يظهر افتراضيًا) ======================= -->
            <section id="categories-section" class="<?php if (!$is_category_view) echo 'hidden'; ?>">
                <h2 class="section-title">تصفح الأقسام</h2>
                <div class="categories-grid">
                    <?php foreach ($categories_array as $category):
                        $category_db_name = $category['db_category'] ?? $category['name'];
                        $link = "listings.php?type={$page_type}&category=" . urlencode($category_db_name);
                    ?>
                        <a href="<?php echo $link; ?>" class="category-card">
                            <img src="<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" loading="lazy">
                            <div class="card-overlay"></div>
                            <h3><span><i class="<?php echo $category['icon']; ?>"></i> <?php echo htmlspecialchars($category['name']); ?></span></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ======================= قسم عرض النتائج (يظهر بعد اختيار فئة) ======================= -->
            <section id="results-section" class="<?php if ($is_category_view) echo 'hidden'; ?>">
                <div class="results-header">
                    <h2 class="section-title" id="results-title">
                        <?php echo "عرض: " . $category_filter; ?>
                    </h2>
                    <button class="filter-toggle-btn" id="filter-toggle-btn">
                        <i class="fas fa-sliders-h"></i>
                        <span>فلترة</span>
                    </button>
                </div>
                <div id="results-grid" class="results-grid">
                    <!-- سيتم ملء النتائج هنا بواسطة JavaScript -->
                    <div class="placeholder-card">جاري تحميل البيانات...</div>
                </div>
                 <div id="no-results" class="no-results-message hidden">
                    <i class="fas fa-store-slash"></i>
                    <h3>لا توجد نتائج</h3>
                    <p>عذرًا، لم نتمكن من العثور على أي نتائج تطابق معايير بحثك الحالية.</p>
                </div>
            </section>
        </div>
    </main>
<script src="js/listings.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const items = document.querySelectorAll('.carousel-item');
        let currentIndex = 0; 
        const totalItems = items.length;
        const intervalTime = 4000; 
        function updateCarousel() {
            items.forEach((item, index) => {
                item.className = 'carousel-item';
                if (index === currentIndex) {
                    item.classList.add('active'); 
                } else if (index === (currentIndex - 1 + totalItems) % totalItems) {
                    item.classList.add('prev'); 
                } else if (index === (currentIndex + 1) % totalItems) {
                    item.classList.add('next');
                }
            });
        }
        function nextSlide() {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }
        updateCarousel();
        setInterval(nextSlide, intervalTime);
    });
</script>
</body>
</html>