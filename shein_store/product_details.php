<?php
require_once __DIR__ . '/../php/db_connect.php';

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header('Location: index.php');
    exit;
}

// 1. العثور على المعرف الأصلي (original_handle) للمنتج المطلوب
$stmt_handle = $pdo->prepare("SELECT original_handle FROM shn_products WHERE id = ?");
$stmt_handle->execute([$product_id]);
$original_handle = $stmt_handle->fetchColumn();

if (!$original_handle) {
    http_response_code(404); echo "<h1>404 - المنتج غير موجود</h1>"; exit;
}

// 2. جلب المنتج الرئيسي للعرض (سنستخدم المنتج ذو أقل ID كممثل)
$stmt_main = $pdo->prepare("SELECT * FROM shn_products WHERE original_handle = ? ORDER BY id ASC LIMIT 1");
$stmt_main->execute([$original_handle]);
$product = $stmt_main->fetch(PDO::FETCH_ASSOC);
// إزالة اسم اللون من العنوان الرئيسي
$product['name'] = preg_replace('/ - .*/', '', $product['name']);


// 3. جلب كل البيانات المجمعة من كل تنويعات الألوان
$all_images = [];
$all_sizes = [];
$all_colors = [];

$stmt_variants = $pdo->prepare("
    SELECT p.id, c.id as color_id, c.name as color_name, c.hex_code
    FROM shn_products p 
    LEFT JOIN shn_product_colors pc ON p.id = pc.product_id
    LEFT JOIN shn_colors c ON pc.color_id = c.id
    WHERE p.original_handle = ?
");
$stmt_variants->execute([$original_handle]);
$variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

$variant_ids = array_column($variants, 'id');
$placeholders = implode(',', array_fill(0, count($variant_ids), '?'));

// جلب الصور
$images_stmt = $pdo->prepare("SELECT product_id, image_url FROM shn_product_images WHERE product_id IN ($placeholders) ORDER BY is_main DESC, id ASC");
$images_stmt->execute($variant_ids);
$images_by_variant = $images_stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

// جلب المقاسات
$sizes_stmt = $pdo->prepare("SELECT s.id, s.name FROM shn_sizes s JOIN shn_product_sizes ps ON s.id = ps.size_id WHERE ps.product_id IN ($placeholders)");
$sizes_stmt->execute($variant_ids);
$all_sizes = $sizes_stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE); // UNIQUE لمنع التكرار


// تجميع الألوان والصور
foreach($variants as $variant) {
    $variant_images = $images_by_variant[$variant['id']] ?? [];
    $all_images = array_merge($all_images, $variant_images);
    
    $all_colors[] = [
        'id' => $variant['color_id'],
        'name' => $variant['color_name'],
        'hex_code' => $variant['hex_code'],
        'image_url' => $variant_images[0] ?? '' // الصورة الرئيسية لهذا اللون
    ];
}
$all_images = array_unique($all_images);

$product['all_images'] = array_values($all_images);
$product['available_sizes'] = array_values($all_sizes);
$product['available_colors'] = $all_colors;

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Syriazzle</title>
    <link rel="stylesheet" href="../css/normalize.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="../css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../css/main_header.css" />
    <link rel="stylesheet" href="styles.css">

    <style>
        .product-page-container { max-width: 1200px; margin: 30px auto; padding: 20px; display: flex; flex-direction: row-reverse; gap: 30px; }
        .product-details-info { flex: 0 0 400px; }
        .details-main-image { flex: 1; }
        .details-main-image img { width: 100%; max-height: 70vh; object-fit: contain; }
        .details-gallery-vertical { flex: 0 0 80px; display: flex; flex-direction: column; gap: 10px; max-height: 70vh; overflow-y: auto; }
        .details-gallery-vertical .thumb { width: 100%; height: 90px; object-fit: cover; border-radius: 4px; border: 2px solid #eee; cursor: pointer; transition: 0.2s; }
        .details-gallery-vertical .thumb.active, .details-gallery-vertical .thumb:hover { border-color: #000; }
        .details-price { font-size: 1.8em; font-weight: bold; margin: 10px 0; }
        .options-group { margin-bottom: 20px; }
        .options-label { font-weight: bold; margin-bottom: 10px; }
        .details-colors { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; }
        .color-option { border: 2px solid #eee; padding: 4px; border-radius: 5px; cursor: pointer; text-align: center; }
        .color-option.selected { border-color: #000; }
        .color-thumb-img { width: 100%; height: 70px; object-fit: cover; margin-bottom: 5px; }
        .color-option span { font-size: 0.8rem; }
        .details-sizes { display: flex; flex-wrap: wrap; gap: 10px; }
        .size-btn { padding: 8px 16px; background-color: #f2f2f2; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; }
        .size-btn.selected { background-color: #000; color: #fff; }
        .details-add-to-cart { width: 100%; padding: 15px; font-size: 1.1em; font-weight: bold; background-color: #27ae60; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; }
        .product-full-description { margin-top: 25px; }
        
        /* --- Media Queries for Responsiveness --- */

/* For Tablets and smaller devices (e.g., screen width less than 992px) */
@media (max-width: 992px) {
    .product-page-container {
        /* تغيير اتجاه العرض إلى عمودي */
        flex-direction: column;
        gap: 20px; /* تقليل المسافة بين العناصر */
    }

    .product-details-info {
        /* إلغاء العرض الثابت والسماح له بأخذ العرض الكامل */
        flex: 1 1 auto;
        order: 2; /* تغيير ترتيبه ليظهر بعد الصور */
    }

    .details-main-image {
        order: 1; /* عرض الصورة الرئيسية أولاً */
    }

    .details-gallery-vertical {
        order: 0; /* عرض معرض الصور المصغرة في الأعلى */
        /* تحويل المعرض العمودي إلى أفقي */
        flex-direction: row;
        overflow-x: auto; /* تمكين التمرير الأفقي */
        overflow-y: hidden;
        max-height: none; /* إزالة الارتفاع الأقصى */
        flex: 1 1 auto;
        padding-bottom: 10px; /* إضافة مسافة سفلية */
    }

    .details-gallery-vertical .thumb {
        width: 80px;  /* عرض ثابت للصور المصغرة */
        height: 90px; /* ارتفاع ثابت */
        flex-shrink: 0; /* منع الصور من الانكماش */
    }

    .details-main-image img {
        max-height: 60vh; /* تعديل ارتفاع الصورة الرئيسية */
    }
}

/* For Mobile phones (e.g., screen width less than 768px) */
@media (max-width: 768px) {
    .product-page-container {
        padding: 10px; /* تقليل الهوامش الداخلية للحاوية */
        margin: 15px auto;
    }

    h3 {
        font-size: 1.5em; /* تصغير حجم العنوان */
    }

    .details-price {
        font-size: 1.6em; /* تعديل حجم الخط للسعر */
    }

    .details-colors {
        /* تعديل شبكة الألوان لتناسب الشاشات الأصغر */
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
    }

    .details-add-to-cart {
        padding: 12px; /* تقليل الحشو لزر إضافة للسلة */
        font-size: 1em;
    }
}
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <main class="container">
        <div class="product-page-container" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>" data-product-price="<?php echo $product['price_usd']; ?>" data-product-image="<?php echo htmlspecialchars($product['all_images'][0] ?? ''); ?>">
            <div class="product-details-info">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p class="details-price"><?php echo number_format($product['price_usd'], 2); ?> $</p>
                <div class="divider"></div>
                <div class="options-group">
                    <p class="options-label">اللون: <span id="selected-color-name"><?php echo htmlspecialchars($product['available_colors'][0]['name'] ?? ''); ?></span></p>
                    <div class="details-colors">
                        <?php foreach ($product['available_colors'] as $index => $color): ?>
                            <div class="color-option <?php echo $index === 0 ? 'selected' : ''; ?>" data-color-name="<?php echo htmlspecialchars($color['name']); ?>" data-image-url="<?php echo htmlspecialchars($color['image_url']); ?>">
                                <img src="<?php echo htmlspecialchars($color['image_url']); ?>" class="color-thumb-img">
                                <span><?php echo htmlspecialchars($color['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="options-group">
                    <p class="options-label">المقاس:</p>
                    <div class="details-sizes">
                        <?php foreach ($product['available_sizes'] as $size): ?>
                            <button class="size-btn" data-size-name="<?php echo htmlspecialchars($size['name']); ?>"><?php echo htmlspecialchars($size['name']); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button id="add-to-cart-page-btn" class="details-add-to-cart">أضف إلى السلة</button>
                
            </div>
            <div class="details-main-image">
                <img src="<?php echo htmlspecialchars($product['all_images'][0] ?? ''); ?>" id="details-main-img">
            </div>
            <div class="details-gallery-vertical">
                <?php foreach ($product['all_images'] as $index => $img): ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" class="thumb <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    
    <!-- نافذة السلة، مطلوبة هنا أيضاً -->
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
    <script src="product_details.js"></script>
</body>
</html>