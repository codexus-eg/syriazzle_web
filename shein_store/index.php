<?php
require_once __DIR__ . '/../php/db_connect.php';
$categories = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM shn_categories ORDER BY name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

// -- NEW: تحضير مصفوفة للبحث السريع عن التصنيفات بالاسم --
$categoriesByName = [];
foreach ($categories as $cat) {
    $categoriesByName[$cat['name']] = $cat;
}

// -- NEW: دالة مساعدة لطباعة بيانات التصنيف كرابط --
function get_category_attributes($name, $map) {
    if (isset($map[$name])) {
        $id = htmlspecialchars($map[$name]['id']);
        $name_attr = htmlspecialchars($map[$name]['name']);
        return "data-category-id=\"{$id}\" data-category-name=\"{$name_attr}\"";
    }
    return ""; // في حال لم يتطابق الاسم مع قاعدة البيانات
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Syriazzle - المتجر</title>
    <link rel="icon" href="../image/favicon.png" type="image/png" />
    <link rel="stylesheet" href="../css/normalize.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../css/main_header.css" />
    <link rel="stylesheet" href="styles.css">
    <style>
        .hidden { display: none !important; }
        .shop-layout { display: flex; flex-direction: row; gap: 20px; margin-top: 20px; }
        .filter-sidebar { flex: 0 0 250px; background-color: #f9f9f9; padding: 15px; border-radius: 8px; align-self: flex-start; }
        .products-area { flex: 1; }
        .filter-group { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .filter-group:last-child { border-bottom: none; }
        .back-btn { display: inline-block; margin-bottom: 15px; background-color: #f0f0f0; border: 1px solid #ddd; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .categories-container { text-align: center; padding: 20px 0; }
        
        /* -- NEW: أنماط جديدة للتبديل بين الصورتين -- */

        /* حاوية الصورة (سواء للكمبيوتر أو الموبايل) */
        .image-map-wrapper {
            position: relative;
            width: 100%;
            margin: 20px auto;
            direction: ltr; /* مهم لتحديد الأماكن بدقة */
        }
        .image-map-wrapper img {
            display: block;
            width: 100%;
            height: auto;
        }
        
        .map-link {
            position: absolute;
            display: block;
            border-radius: 50%;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .map-link:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        /* التحكم بالإظهار والإخفاء */
        .image-map-desktop { display: block; } /* نسخة الكمبيوتر ظاهرة افتراضياً */
        .image-map-mobile { display: none; }  /* نسخة الموبايل مخفية افتراضياً */

        /* Media Query للتبديل عند عرض شاشات الموبايل */
        @media (max-width: 768px) {
            .image-map-desktop { display: none; } /* إخفاء نسخة الكمبيوتر */
            .image-map-mobile { display: block; }  /* إظهار نسخة الموبايل */
            
            .shop-layout { flex-direction: column; }
            .filter-sidebar { flex: 0 0 auto; width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
   <main class="container">
        <div id="categories-container" class="categories-container">
            <h2>تصفح الأصناف</h2>

            <!-- NEW: نسخة الكمبيوتر (الصورة الأفقية) -->
            <div class="image-map-wrapper image-map-desktop" style="max-width: 1200px;">
                <img src="sheinar.webp" alt="تصفح الأصناف" />
                <!-- الروابط الشفافة -->
                <a href="#" class="map-link" title="نساء" style="top: 5.5%; left: 3.3%; width: 8%; height: 20%;" <?php echo get_category_attributes('نساء', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="أطفال" style="top: 5.5%; left: 17.3%; width: 8%; height: 20%;" <?php echo get_category_attributes('أطفال', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="مقاسات كبيرة" style="top: 5.5%; left: 31.8%; width: 8%; height: 20%;" <?php echo get_category_attributes('مقاسات كبيرة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="رجال" style="top: 5.5%; left: 46%; width: 8%; height: 20%;" <?php echo get_category_attributes('رجال', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="الأمومة والرضع" style="top: 5.5%; left: 60.25%; width: 8%; height: 20%;" <?php echo get_category_attributes('الأمومة والرضع', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="المنزل والمعيشة" style="top: 5.5%; left: 74.4%; width: 8%; height: 20%;" <?php echo get_category_attributes('المنزل والمعيشة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="الجمال والصحة" style="top: 5.5%; left: 88.6%; width: 8%; height: 20%;" <?php echo get_category_attributes('الجمال والصحة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس النوم وملابس داخلية" style="top: 35%; left: 3.3%; width: 8%; height: 20%;" <?php echo get_category_attributes('ملابس النوم وملابس داخلية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="مجوهرات واكسسوارات" style="top: 35%; left: 17.3%; width: 8%; height: 20%;" <?php echo get_category_attributes('مجوهرات واكسسوارات', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="أحذية" style="top: 35%; left: 31.8%; width: 8%; height: 20%;" <?php echo get_category_attributes('أحذية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="حقائب وأمتعة" style="top: 35%; left: 46%; width: 8%; height: 20%;" <?php echo get_category_attributes('حقائب وأمتعة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="إلكترونيات والهواتف" style="top: 35%; left: 60.25%; width: 8%; height: 20%;" <?php echo get_category_attributes('إلكترونيات والهواتف', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="منسوجات منزلية" style="top: 35%; left: 74.4%; width: 8%; height: 20%;" <?php echo get_category_attributes('منسوجات منزلية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="لوازم مكتبية ومدرسية" style="top: 35%; left: 88.6%; width: 8%; height: 20%;" <?php echo get_category_attributes('لوازم مكتبية ومدرسية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="فساتين" style="top: 67%; left: 3.3%; width: 8%; height: 20%;" <?php echo get_category_attributes('فساتين', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس علوية" style="top: 67%; left: 17.3%; width: 8%; height: 20%;" <?php echo get_category_attributes('ملابس علوية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس سفلية" style="top: 67%; left: 31.8%; width: 8%; height: 20%;" <?php echo get_category_attributes('ملابس سفلية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="أطقم منسقة" style="top: 67%; left: 46%; width: 8%; height: 20%;" <?php echo get_category_attributes('أطقم منسقة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="دينيم" style="top: 67%; left: 60.25%; width: 8%; height: 20%;" <?php echo get_category_attributes('دينيم', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس عربية" style="top: 67%; left: 74.4%; width: 8%; height: 20%;" <?php echo get_category_attributes('ملابس عربية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="الرياضة والأنشطة الخارجية" style="top: 67%; left: 88.6%; width: 8%; height: 20%;" <?php echo get_category_attributes('الرياضة والأنشطة الخارجية', $categoriesByName); ?>></a>
            </div>
            
            
             <!-- NEW: نسخة الموبايل (الصورة العمودية) -->
            <div class="image-map-wrapper image-map-mobile" style="max-width: 500px;">
                <img src="sheinar.png" alt="تصفح الأصناف" />
                <!-- روابط الصورة العمودية (بإحداثيات جديدة) -->
                <!-- الصف 1 -->
                <a href="#" class="map-link" title="نساء" style="top: 2%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('نساء', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="أطفال" style="top: 2%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('أطفال', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="مقاسات كبيرة" style="top: 2%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('مقاسات كبيرة', $categoriesByName); ?>></a>
                <!-- الصف 2 -->
                <a href="#" class="map-link" title="ملابس النوم وملابس داخلية" style="top: 16%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('ملابس النوم وملابس داخلية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="مجوهرات واكسسوارات" style="top: 16%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('مجوهرات واكسسوارات', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="أحذية" style="top: 16%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('أحذية', $categoriesByName); ?>></a>
                <!-- الصف 3 -->
                <a href="#" class="map-link" title="فساتين" style="top: 30%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('فساتين', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس علوية" style="top: 30%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('ملابس علوية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس سفلية" style="top: 30%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('ملابس سفلية', $categoriesByName); ?>></a>
                <!-- الصف 4 -->
                <a href="#" class="map-link" title="رجال" style="top: 44.5%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('رجال', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="الأمومة والرضع" style="top: 44.5%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('الأمومة والرضع', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="المنزل والمعيشة" style="top: 44.5%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('المنزل والمعيشة', $categoriesByName); ?>></a>
                <!-- الصف 5 -->
                <a href="#" class="map-link" title="حقائب وأمتعة" style="top: 58.5%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('حقائب وأمتعة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="إلكترونيات والهواتف" style="top: 58.5%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('إلكترونيات والهواتف', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="منسوجات منزلية" style="top: 58.5%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('منسوجات منزلية', $categoriesByName); ?>></a>
                <!-- الصف 6 -->
                <a href="#" class="map-link" title="أطقم منسقة" style="top: 72.5%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('أطقم منسقة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="دينيم" style="top: 72.5%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('دينيم', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="ملابس عربية" style="top: 72.5%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('ملابس عربية', $categoriesByName); ?>></a>
                <!-- الصف 7 -->
                <a href="#" class="map-link" title="الرياضة والأنشطة الخارجية" style="top: 87%; left: 4%; width: 28%; height: 10%;" <?php echo get_category_attributes('الرياضة والأنشطة الخارجية', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="الجمال والصحة" style="top: 87%; left: 36%; width: 28%; height: 10%;" <?php echo get_category_attributes('الجمال والصحة', $categoriesByName); ?>></a>
                <a href="#" class="map-link" title="لوازم مكتبية ومدرسية" style="top: 87%; left: 68%; width: 28%; height: 10%;" <?php echo get_category_attributes('لوازم مكتبية ومدرسية', $categoriesByName); ?>></a>
            </div>
        </div>
        
        <!-- باقي الكود يبقى كما هو -->
        <div id="products-view" class="hidden">
            <button id="back-to-categories" class="back-btn">&larr; العودة للأصناف</button>
            <h2 id="category-title"></h2>
            <div class="shop-layout">
                <aside id="filter-sidebar" class="filter-sidebar"></aside>
                <div class="products-area">
                    <div id="products-container" class="products-grid"></div>
                    <p id="no-results" class="hidden">لا توجد نتائج تطابق بحثك.</p>
                </div>
            </div>
        </div>
    </main>
    
    <div id="cart-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2>سلة التسوق</h2>
                <button id="close-modal-btn" class="close-btn">&times;</button>
            </div>
            <div id="cart-items" class="modal-body"></div>
            <div class="modal-footer">
                <div class="price-summary">
                    <strong>الإجمالي:</strong> <strong id="cart-total">$0.00</strong>
                </div>
                <button id="checkout-btn" class="checkout-btn">إتمام الشراء</button>
            </div>
        </div>
    </div>

    <script src="cart.js"></script>
    <script src="script.js"></script>
</body>
</html>