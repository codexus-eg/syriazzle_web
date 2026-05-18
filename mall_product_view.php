<?php
// ========================================================================
// Syriazzle Mall - Product View (Final Fixed)
// ========================================================================
require_once 'php/db_connect.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id === 0) { header('Location: mall.php'); exit; }

$exchange_rate = 15000; $active_discount = 0;
try {
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    if ($r = $stmt_rate->fetchColumn()) $exchange_rate = (float)$r;
    $stmt_disc = $pdo->query("SELECT discount_percentage FROM mall_discounts WHERE is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) LIMIT 1");
    if ($d = $stmt_disc->fetchColumn()) $active_discount = (float)$d;

    $stmt_prod = $pdo->prepare("SELECT p.*, c.name as category_name, d.name as department_name, b.name as brand_name, b.logo_path as brand_logo FROM mall_products p LEFT JOIN mall_categories c ON p.category_id = c.id LEFT JOIN mall_departments d ON c.department_id = d.id LEFT JOIN mall_brands b ON p.brand_id = b.id WHERE p.id = ?");
    $stmt_prod->execute([$product_id]);
    $product = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    if (!$product) { header('Location: mall.php'); exit; }
} catch (PDOException $e) { die("Error"); }

// الحساب المالي
$base_price = (!empty($product['fixed_price_syp']) && $product['fixed_price_syp'] > 0) ? $product['fixed_price_syp'] : $product['price_usd'] * $exchange_rate;
$final_price = ($active_discount > 0) ? $base_price * (1 - $active_discount/100) : $base_price;
$base_price = ceil($base_price / 100) * 100;
$final_price = ceil($final_price / 100) * 100;
$is_discounted = ($active_discount > 0);

// جلب بيانات القائمة الجانبية
$menu_data = [];
try {
    $sql_menu = "SELECT c.id, c.name as cat_name, d.name as dept_name FROM mall_categories c LEFT JOIN mall_departments d ON c.department_id = d.id ORDER BY d.id ASC, c.id ASC";
    $stmt_menu = $pdo->query($sql_menu);
    while($row = $stmt_menu->fetch(PDO::FETCH_ASSOC)){ $menu_data[$row['dept_name']][] = ['id'=>$row['id'], 'name'=>$row['cat_name']]; }
} catch(Exception $e){}

$page_title = $product['name'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/cart.css">
    <link rel="stylesheet" href="css/mall.css">
    <style>
        /* تنسيقات خاصة بصفحة المنتج */
        .product-view-container { max-width: 1200px; margin: 2rem auto; background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-wrap: wrap; }
        .product-image-section { flex: 1 1 500px; background: #f8f9fa; padding: 2rem; display: flex; align-items: center; justify-content: center; position: relative; }
        .product-image-section img { max-width: 100%; max-height: 500px; object-fit: contain; }
        .product-details-section { flex: 1 1 500px; padding: 3rem; display: flex; flex-direction: column; }
        .brand-tag { display: inline-flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #eee; padding: 5px 15px; border-radius: 30px; margin-bottom: 1rem; color: #666; font-weight: 600; }
        .brand-tag img { width: 25px; height: 25px; object-fit: contain; }
        h1.prod-title { font-size: 2rem; font-weight: 800; margin: 0 0 1.5rem 0; }
        .price-block { margin-bottom: 2rem; padding: 15px; background: #f8f9fa; border-radius: 12px; border-right: 4px solid #e60000; }
        .current-price-large { font-size: 2.2rem; font-weight: 800; color: #e60000; }
        .old-price-large { text-decoration: line-through; color: #999; margin-right: 10px; }
        .desc-text { font-size: 1.05rem; line-height: 1.8; color: #555; margin-bottom: 2rem; }
        .actions-row { display: flex; gap: 15px; margin-top: auto; }
        .qty-control { display: flex; border: 2px solid #eee; border-radius: 12px; overflow: hidden; }
        .qty-btn { width: 50px; background: #fff; border: none; font-size: 1.2rem; cursor: pointer; }
        .qty-input { width: 60px; border: none; text-align: center; font-weight: bold; font-size: 1.1rem; }
        .add-cart-btn-large { flex: 1; background: #e60000; color: #fff; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .silver-badge { position: absolute; top: 20px; left: 20px; background: linear-gradient(135deg, #e0e0e0, #fff); color: #333; padding: 8px 15px; border-radius: 8px; font-weight: 800; }
        @media (max-width: 768px) { .product-view-container { margin: 0; border-radius: 0; } .product-image-section { height: 40vh; padding: 20px; } .product-details-section { padding: 20px; padding-bottom: 100px; } .actions-row { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 15px; box-shadow: 0 -5px 20px rgba(0,0,0,0.1); z-index: 100; margin: 0; } }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>

    <!-- القائمة الجانبية (لتعمل أيقونة الأقسام) -->
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
        <!-- زر الأقسام العائم -->
        <button id="open-canvas-btn" class="floating-menu-btn"><i class="fas fa-th"></i> الأقسام</button>

        <div class="product-view-container">
            <div class="product-image-section">
                <?php if ($is_discounted): ?><div class="silver-badge">خصم <?php echo $active_discount; ?>%</div><?php endif; ?>
                <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'image/default.png'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>

            <div class="product-details-section">
                <nav style="font-size:0.9rem; color:#888; margin-bottom:1rem;">
                    <a href="mall.php">الرئيسية</a> / <?php echo htmlspecialchars($product['department_name']); ?> / <?php echo htmlspecialchars($product['category_name']); ?>
                </nav>

                <?php if (!empty($product['brand_name'])): ?>
                <div class="brand-tag">
                    <?php if ($product['brand_logo']): ?><img src="<?php echo htmlspecialchars($product['brand_logo']); ?>"><?php endif; ?>
                    <?php echo htmlspecialchars($product['brand_name']); ?>
                </div>
                <?php endif; ?>

                <h1 class="prod-title"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="price-block">
                    <span class="current-price-large"><?php echo number_format($final_price); ?> ل.س</span>
                    <?php if ($is_discounted): ?><span class="old-price-large"><?php echo number_format($base_price); ?> ل.س</span><?php endif; ?>
                </div>

                <div class="desc-text"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>

                <div class="actions-row">
                    <div class="qty-control">
                        <button class="qty-btn" onclick="document.getElementById('p-qty').value++">+</button>
                        <input type="number" id="p-qty" class="qty-input" value="1" min="1">
                        <button class="qty-btn" onclick="if(document.getElementById('p-qty').value>1) document.getElementById('p-qty').value--">-</button>
                    </div>
                    <button class="add-cart-btn-large" id="add-btn-single"><i class="fas fa-cart-plus"></i> إضافة للسلة</button>
                </div>
            </div>
        </div>
    </div>

    <!-- زر السلة -->
    <div id="cart-fab" class="cart-fab" style="display:none;">
        <i class="fas fa-shopping-bag"></i><span id="cart-count">0</span>
    </div>

    <script>
        const PRODUCT_DATA = {
            id: <?php echo $product['id']; ?>,
            name: <?php echo json_encode($product['name']); ?>,
            price: <?php echo $final_price; ?>,
            image: <?php echo json_encode($product['image_path'] ?? 'image/default.png'); ?>
        };
    </script>
    
    <script src="js/mall.js"></script> 
    <script>
        document.getElementById('add-btn-single').addEventListener('click', function() {
            const qty = parseInt(document.getElementById('p-qty').value);
            addToCart(PRODUCT_DATA, qty);
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> تمت الإضافة';
            this.style.background = '#28a745';
            setTimeout(() => {
                this.innerHTML = originalText;
                this.style.background = '';
            }, 1500);
        });
        
        // تفعيل زر السلة (في الصفحة الداخلية) ليفتح القائمة الجانبية بدلاً من التوجيه
        document.getElementById('cart-fab').onclick = function() {
            toggleCart(true);
        };
    </script>
</body>
</html>