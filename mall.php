<?php
// ========================================================================
// Syriazzle Mall - Main Page (مع فلترة الأصناف الذكية)
// ========================================================================
require_once 'php/db_connect.php';

// 1. إعدادات المال
$exchange_rate = 15000; 
$active_discount = 0;

try {
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    $rate_db = $stmt_rate->fetchColumn();
    if ($rate_db) $exchange_rate = (float)$rate_db;

    $stmt_disc = $pdo->query("
        SELECT discount_percentage 
        FROM mall_discounts 
        WHERE is_active = 1 
        AND (start_date IS NULL OR start_date <= NOW()) 
        AND (end_date IS NULL OR end_date >= NOW()) 
        ORDER BY id DESC LIMIT 1
    ");
    $disc_db = $stmt_disc->fetchColumn();
    if ($disc_db) $active_discount = (float)$disc_db;
} catch (Exception $e) { }

// 2. جلب الأصناف (5 فقط)
$structured_data = [];
$limit_initial = 5; 

try {
    $sql_cats = "SELECT c.id, c.name, c.department_id, d.name as dept_name 
                 FROM mall_categories c
                 LEFT JOIN mall_departments d ON c.department_id = d.id
                 ORDER BY d.id ASC, c.id ASC 
                 LIMIT ?";
    
    $stmt_limit = $pdo->prepare($sql_cats);
    $stmt_limit->bindValue(1, $limit_initial, PDO::PARAM_INT);
    $stmt_limit->execute();
    $categories = $stmt_limit->fetchAll(PDO::FETCH_ASSOC);
    
    $cat_ids = array_column($categories, 'id');

    if (!empty($cat_ids)) {
        $placeholders = implode(',', array_fill(0, count($cat_ids), '?'));
        
        $sql = "SELECT 
                    p.id, p.name AS item_name, p.image_path, p.description,
                    p.price_usd, p.fixed_price_syp,
                    c.id AS category_id, c.name AS category_name, 
                    d.id AS department_id, d.name AS department_name,
                    b.name AS brand_name, b.logo_path AS brand_logo
                FROM mall_products AS p
                LEFT JOIN mall_categories AS c ON p.category_id = c.id
                LEFT JOIN mall_departments AS d ON c.department_id = d.id
                LEFT JOIN mall_brands AS b ON p.brand_id = b.id
                WHERE c.id IN ($placeholders)
                ORDER BY d.id ASC, c.id ASC, p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($cat_ids);
        
        $counters = [];

        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dept = $item['department_name'] ?? 'عام';
            $cat = $item['category_name'];
            $cat_id = $item['category_id'];

            if (!isset($counters[$cat_id])) $counters[$cat_id] = 0;
            if ($counters[$cat_id] >= 5) continue;

            if (!empty($item['fixed_price_syp']) && $item['fixed_price_syp'] > 0) {
                $base_price = (float)$item['fixed_price_syp'];
            } else {
                $base_price = (float)$item['price_usd'] * $exchange_rate;
            }
            
            $final_price = $base_price;
            $is_disc = false;
            if ($active_discount > 0) {
                $final_price = $base_price * (1 - ($active_discount / 100));
                $is_disc = true;
            }

            $item['price_final'] = ceil($final_price / 100) * 100;
            $item['price_base'] = ceil($base_price / 100) * 100;
            $item['is_disc'] = $is_disc;

            $structured_data[$dept]['id'] = $item['department_id'];
            $structured_data[$dept]['categories'][$cat]['id'] = $item['category_id'];
            $structured_data[$dept]['categories'][$cat]['items'][] = $item;
            
            $counters[$cat_id]++;
        }
    }
} catch (Exception $e) { }

// القائمة الجانبية
$menu_data = [];
try {
    $sql_menu = "SELECT c.id, c.name as cat_name, d.name as dept_name 
                 FROM mall_categories c 
                 LEFT JOIN mall_departments d ON c.department_id = d.id 
                 ORDER BY d.id ASC, c.id ASC";
    $stmt_menu = $pdo->query($sql_menu);
    while($row = $stmt_menu->fetch(PDO::FETCH_ASSOC)){
        $menu_data[$row['dept_name']][] = ['id'=>$row['id'], 'name'=>$row['cat_name']];
    }
} catch(Exception $e){}

$page_title = 'مول Syriazzle';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/cart.css">
    <link rel="stylesheet" href="css/mall.css">
</head>
<body>
    <?php include 'header_store.php'; ?>

    <div class="off-canvas-menu" id="off-canvas-menu">
        <div class="off-canvas-header">
            <h3>تصفح الأقسام</h3>
            <button id="close-canvas-btn" class="close-canvas-btn">&times;</button>
        </div>
        <div class="off-canvas-body">
            <a href="mall.php" class="canvas-category-link active">الرئيسية</a>
            <?php foreach ($menu_data as $dept => $cats): ?>
                <div class="canvas-category-group">
                    <div class="canvas-category-link main-cat">
                        <span><?php echo htmlspecialchars($dept); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="canvas-subcategory-list" style="display: none;">
                        <?php foreach ($cats as $cat): ?>
                            <a href="mall_category.php?id=<?php echo $cat['id']; ?>" class="canvas-category-link sub-cat">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="off-canvas-overlay" id="off-canvas-overlay"></div>

    <div class="mall-container">
        <button id="open-canvas-btn" class="floating-menu-btn"><i class="fas fa-th"></i> الأقسام</button>

        <div class="hero-slider-container">
            <div class="swiper-container" id="hero-slider">
                <div class="swiper-wrapper">
                    <div class="swiper-slide"><img src="image/offers/slidermall1.jpg" alt="عرض 1"></div>
                    <div class="swiper-slide"><img src="image/offers/slidermall2.png" alt="عرض 2"></div>
                    <div class="swiper-slide"><img src="image/offers/slidermall3.webp" alt="عرض 3"></div>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>

        <div class="search-wrapper">
            <input type="text" id="search-input" placeholder="ابحث عن منتج أو ماركة...">
            <i class="fas fa-search"></i>
        </div>

        <main id="mall-content">
            <?php if (empty($structured_data)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open" style="font-size: 3rem; color: #eee; margin-bottom: 15px;"></i>
                    <p>جاري إضافة المنتجات...</p>
                </div>
            <?php else: ?>
                
                <?php foreach ($structured_data as $dept_name => $dept_data): ?>
                    <section class="dept-section" id="dept-<?php echo $dept_data['id']; ?>">
                        <h2 class="dept-title"><?php echo htmlspecialchars($dept_name); ?></h2>
                        
                        <!-- === شريط الفلترة (الجديد) === -->
                        <?php if (count($dept_data['categories']) > 1): ?>
                        <div class="filters-scroll-wrapper">
                            <button class="filter-chip active" data-filter="all">الكل</button>
                            <?php foreach ($dept_data['categories'] as $cat_name => $cat_data): ?>
                                <button class="filter-chip" data-filter="<?php echo $cat_data['id']; ?>">
                                    <?php echo htmlspecialchars($cat_name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <!-- ============================= -->

                        <?php foreach ($dept_data['categories'] as $cat_name => $cat_data): ?>
                            <?php if (empty($cat_data['items'])) continue; ?>

                            <!-- إضافة data-cat-id للتحكم بالظهور -->
                            <div class="cat-container" data-cat-id="<?php echo $cat_data['id']; ?>">
                                <div class="cat-header">
                                    <h3><?php echo htmlspecialchars($cat_name); ?></h3>
                                    <a href="mall_category.php?id=<?php echo $cat_data['id']; ?>" class="see-all">
                                        المزيد <i class="fas fa-angle-left"></i>
                                    </a>
                                </div>
                                
                                <div class="products-scroll">
                                    <?php 
                                    // --- تعديل هام للسرعة والدقة ---
                                    // نأخذ أول 5 عناصر فقط من المصفوفة، لضمان عدم عرض أكثر من ذلك في الشريط الأفقي
                                    $display_items = array_slice($cat_data['items'], 0, 5);
                                    
                                    foreach ($display_items as $prod): 
                                    ?>
                                        <a href="mall_product_view.php?id=<?php echo $prod['id']; ?>" class="product-card-new">
                                            
                                            <div class="img-wrap">
                                                <!-- صورة المنتج -->
                                                <img src="<?php echo htmlspecialchars($prod['image_path'] ?? 'image/default.png'); ?>" alt="<?php echo htmlspecialchars($prod['item_name']); ?>" loading="lazy">
                                                
                                                <!-- شعار الماركة (الدائري المتداخل) -->
                                                <?php if (!empty($prod['brand_logo'])): ?>
                                                    <div class="brand-overlay">
                                                        <img src="<?php echo htmlspecialchars($prod['brand_logo']); ?>" alt="Brand">
                                                    </div>
                                                <?php endif; ?>
                                
                                                <!-- شارة الخصم -->
                                                <?php if ($prod['is_disc']): ?>
                                                    <div class="discount-tag">خصم %<?php echo $active_discount; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="info-wrap">
                                                <!-- العنوان -->
                                                <h4 class="title"><?php echo htmlspecialchars($prod['item_name']); ?></h4>
                                                
                                                <!-- الوصف المختصر (40 حرف) -->
                                                <span class="short-desc">
                                                    <?php echo mb_strimwidth(strip_tags($prod['description'] ?? ''), 0, 40, '...'); ?>
                                                </span>
                                                
                                                <!-- السعر -->
                                                <div class="price-row">
                                                    <span class="curr"><?php echo number_format($prod['price_final']); ?> ل.س</span>
                                                    <?php if ($prod['is_disc']): ?>
                                                        <span class="old"><?php echo number_format($prod['price_base']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <div id="load-more-container" style="text-align: center; margin: 40px 0;">
            <button id="load-more-btn" style="padding: 12px 35px; border-radius: 30px; background: #fff; color:#333; border:1px solid #ddd; font-weight:bold; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                عرض المزيد
            </button>
        </div>
    </div>

    <div id="cart-fab" class="cart-fab" onclick="window.location.href='mall_checkout.php'" style="display:none;">
        <i class="fas fa-shopping-cart"></i><span id="cart-count">0</span>
    </div>

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        const INITIAL_OFFSET = <?php echo $limit_initial; ?>;
    </script>
    <script src="js/mall.js"></script>
</body>
</html>