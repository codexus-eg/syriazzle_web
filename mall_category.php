<?php
// ========================================================================
// Syriazzle Mall - Category View (Final Fixed)
// ========================================================================
require_once 'php/db_connect.php';

$category_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$category_id) { header('Location: mall.php'); exit; }

// الإعدادات المالية (الموحدة)
$exchange_rate = 15000;
$active_discount = 0;
try {
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    if ($r = $stmt_rate->fetchColumn()) $exchange_rate = (float)$r;

    $stmt_disc = $pdo->query("SELECT discount_percentage FROM mall_discounts WHERE is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY id DESC LIMIT 1");
    if ($d = $stmt_disc->fetchColumn()) $active_discount = (float)$d;
} catch (Exception $e) { }

// جلب بيانات الصنف
$current_cat = [];
$siblings = [];
try {
    $stmt = $pdo->prepare("SELECT c.id, c.name, d.id as dept_id, d.name as dept_name FROM mall_categories c JOIN mall_departments d ON c.department_id = d.id WHERE c.id = ?");
    $stmt->execute([$category_id]);
    $current_cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$current_cat) { header('Location: mall.php'); exit; }

    $stmt_sib = $pdo->prepare("SELECT id, name FROM mall_categories WHERE department_id = ? ORDER BY name");
    $stmt_sib->execute([$current_cat['dept_id']]);
    $siblings = $stmt_sib->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Error"); }

// جلب المنتجات
$products = [];
$limit = 12;
try {
    $sql_prod = "SELECT p.id, p.name AS item_name, p.image_path, p.description,
                        p.price_usd, p.fixed_price_syp,
                        b.name AS brand_name, b.logo_path AS brand_logo
                 FROM mall_products p
                 LEFT JOIN mall_brands b ON p.brand_id = b.id
                 WHERE p.category_id = ?
                 ORDER BY p.id DESC LIMIT ?";
    
    $stmt_prod = $pdo->prepare($sql_prod);
    $stmt_prod->bindValue(1, $category_id, PDO::PARAM_INT);
    $stmt_prod->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt_prod->execute();
    $raw_products = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

    foreach($raw_products as $prod) {
        $base = (!empty($prod['fixed_price_syp']) && $prod['fixed_price_syp'] > 0) ? (float)$prod['fixed_price_syp'] : (float)$prod['price_usd'] * $exchange_rate;
        $final = ($active_discount > 0) ? $base * (1 - $active_discount/100) : $base;
        $prod['price_final'] = ceil($final / 100) * 100;
        $prod['price_base'] = ceil($base / 100) * 100;
        $prod['is_disc'] = ($active_discount > 0);
        $products[] = $prod;
    }
} catch (Exception $e) { }

// جلب بيانات القائمة الجانبية
$menu_data = [];
try {
    $sql_menu = "SELECT c.id, c.name as cat_name, d.name as dept_name FROM mall_categories c LEFT JOIN mall_departments d ON c.department_id = d.id ORDER BY d.id ASC, c.id ASC";
    $stmt_menu = $pdo->query($sql_menu);
    while($row = $stmt_menu->fetch(PDO::FETCH_ASSOC)){ $menu_data[$row['dept_name']][] = ['id'=>$row['id'], 'name'=>$row['cat_name']]; }
} catch(Exception $e){}

$page_title = $current_cat['name'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/cart.css">
    <link rel="stylesheet" href="css/mall.css">
    <style>
        /* تنسيقات إضافية خاصة بالصفحة */
        .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px; }
        @media (min-width: 992px) { .category-grid { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 25px; } }
        .breadcrumb-nav { background: #fff; padding: 15px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 20px; font-size: 0.9rem; color: #666; display: flex; align-items: center; gap: 8px; }
        .breadcrumb-nav a { color: #007bff; font-weight: 600; }
        .sub-cats-wrapper { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 30px; scrollbar-width: none; }
        .sub-cat-chip { white-space: nowrap; padding: 8px 20px; border-radius: 30px; background: #fff; border: 1px solid #eee; color: #555; font-weight: 600; transition: 0.2s; }
        .sub-cat-chip.active { background: #e60000; color: #fff; border-color: #e60000; }
        .load-btn-wrapper { text-align: center; margin: 40px 0; }
        .load-btn { padding: 12px 40px; background: #fff; color: #333; border: 1px solid #ddd; border-radius: 50px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>

    <!-- القائمة الجانبية (مضافة هنا لتظهر عند الضغط على الزر) -->
    <div class="off-canvas-menu" id="off-canvas-menu">
        <div class="off-canvas-header"><h3>تصفح الأقسام</h3><button id="close-canvas-btn">&times;</button></div>
        <div class="off-canvas-body">
            <a href="mall.php" class="canvas-category-link active">الرئيسية</a>
            <?php foreach ($menu_data as $dept => $cats): ?>
                <div class="canvas-category-group">
                    <div class="canvas-category-link main-cat"><span><?php echo htmlspecialchars($dept); ?></span><i class="fas fa-chevron-down"></i></div>
                    <div class="canvas-subcategory-list" style="display: none;">
                        <?php foreach ($cats as $cat): ?>
                            <a href="mall_category.php?id=<?php echo $cat['id']; ?>" class="canvas-category-link sub-cat"><?php echo htmlspecialchars($cat['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="off-canvas-overlay" id="off-canvas-overlay"></div>

    <div class="mall-container">
        <!-- زر الأقسام العائم (تمت إضافته) -->
        <button id="open-canvas-btn" class="floating-menu-btn"><i class="fas fa-th"></i> الأقسام</button>

        <div class="breadcrumb-nav">
            <a href="mall.php">الرئيسية</a> <span>/</span>
            <a href="#"><?php echo htmlspecialchars($current_cat['dept_name']); ?></a> <span>/</span>
            <strong><?php echo htmlspecialchars($current_cat['name']); ?></strong>
        </div>

        <div class="search-wrapper">
            <input type="text" id="cat-search" placeholder="ابحث داخل <?php echo htmlspecialchars($current_cat['name']); ?>...">
            <i class="fas fa-search"></i>
        </div>

        <?php if(count($siblings) > 1): ?>
        <div class="sub-cats-wrapper">
            <?php foreach($siblings as $sib): ?>
                <a href="mall_category.php?id=<?php echo $sib['id']; ?>" class="sub-cat-chip <?php echo ($sib['id'] == $category_id) ? 'active' : ''; ?>"><?php echo htmlspecialchars($sib['name']); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <main id="products-grid" class="category-grid">
            <?php if(empty($products)): ?>
                <div class="empty-state" style="grid-column: 1/-1;">
                    <i class="fas fa-box-open" style="font-size:3rem; color:#ddd;"></i><p>لا توجد منتجات.</p>
                </div>
            <?php else: ?>
                <?php foreach($products as $prod): ?>
                    <a href="mall_product_view.php?id=<?php echo $prod['id']; ?>" class="product-card-new">
                        <div class="img-wrap">
                            <img src="<?php echo htmlspecialchars($prod['image_path'] ?? 'image/default.png'); ?>" loading="lazy">
                            <?php if(!empty($prod['brand_logo'])): ?><div class="brand-overlay"><img src="<?php echo htmlspecialchars($prod['brand_logo']); ?>"></div><?php endif; ?>
                            <?php if($prod['is_disc']): ?><div class="discount-tag">خصم %<?php echo $active_discount; ?></div><?php endif; ?>
                        </div>
                        <div class="info-wrap">
                            <h4 class="title"><?php echo htmlspecialchars($prod['item_name']); ?></h4>
                            <span class="short-desc"><?php echo mb_strimwidth(strip_tags($prod['description'] ?? ''), 0, 40, '...'); ?></span>
                            <div class="price-row">
                                <span class="curr"><?php echo number_format($prod['price_final']); ?> ل.س</span>
                                <?php if($prod['is_disc']): ?><span class="old"><?php echo number_format($prod['price_base']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <?php if(count($products) >= $limit): ?>
        <div class="load-btn-wrapper">
            <button id="load-more-products" class="load-btn">عرض المزيد</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- زر السلة (تم تعديل السلوك في JS) -->
    <div id="cart-fab" class="cart-fab" style="display:none;">
        <i class="fas fa-shopping-bag"></i><span id="cart-count">0</span>
    </div>

    <script>const CATEGORY_ID = <?php echo $category_id; ?>; const INITIAL_OFFSET = <?php echo $limit; ?>;</script>
    <script src="js/mall_category.js"></script>
    <script src="js/mall.js"></script> 
</body>
</html>