<?php
// =======================================================
// 1. إعدادات المسار وتحميل البيانات
// =======================================================
// تأكد من المسار الصحيح لملف JSON. (استخدمنا هنا المسار 'json/json-all.json' بناءً على المحادثات السابقة)

  require_once 'php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_url=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$jsonFilePath = 'json/json-all.json'; 
$mainCategoriesData = [];
$errorMessage = '';

if (file_exists($jsonFilePath)) {
    // قراءة محتوى الملف
    $jsonString = file_get_contents($jsonFilePath);
    
    // تحليل JSON
    $data = json_decode($jsonString, true);
    
    if ($data !== null && is_array($data)) {
        // يتم عرض المفاتيح الرئيسية للتصنيفات
        $mainCategoriesData = $data;
    } else {
        $errorMessage = 'خطأ في تحليل محتوى ملف الـ JSON. تأكد من أن الهيكل صحيح.';
    }
} else {
    $errorMessage = "ملف الـ JSON غير موجود في المسار المحدد: <strong>{$jsonFilePath}</strong>";
}

// =======================================================
// 2. دالة مساعدة لربط تصنيف الـ JSON بمسارات الصور
// =======================================================
function getCategoryDetails(string $categoryKey): array {
    // هذه المصفوفة تربط مفاتيح JSON بمسارات الصور الموجودة في ملف post.html
    $map = [
        'مركبات' => 'image/car.svg',
        'عقارات' => 'image/real.svg',
        'هواتف وإكسسوارات' => 'image/phone.svg', 
        'أثاث والديكور' => 'image/Furniture.svg', 
        'ملابس' => 'image/fashion.svg', 
        'مستلزمات_الأطفال' => 'image/children.svg',
        'أجهزة إلكترونية' => 'image/device.svg', 
        'معدات_أعمال_وورش' => 'image/industry.svg', 
        'مستلزمات_الرياضة' => 'image/sports.svg',
        'حيوانات أليفة' => 'image/animal.svg',
        'هوايات' => 'image/hobbies.svg', 
        'التوظيف' => 'image/Jobs.svg',
        'خدمات' => 'image/serveces.svg',
        'سياحة وسفر' => 'image/syklvg3jklvg3jklv.svg', // الصورة الطويلة
   
        // 'الموضة والجمال' => 'image/fashion.svg', 
        // 'المستلزمات_الطبية' => 'image/device.svg', 
    ];

    $image = $map[$categoryKey] ?? 'image/default.svg'; // صورة افتراضية
    $displayName = str_replace('_', ' ', $categoryKey);
    
    return [
        // ✅ التعديل هنا: يتم توجيه كل رابط إلى subcategories.php مع تمرير المفتاح كـ path
        'link' => "subcategories.php?path=" . urlencode($categoryKey),
        'image' => $image,
        'display' => $displayName
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>إضافة إعلان - اختر الفئة الرئيسية</title>
    <link rel="icon" href="image/favicon.png" type="image/png">
    <link rel="stylesheet" href="css/framework.css" />
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/dubizzle-inspired.css" />
        <link rel="stylesheet" href="css/main_header.css">

    <link rel="stylesheet" href="css/normalize.css" />
    <link rel="stylesheet" href="css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;800&family=Open+Sans:wght@400;700&family=Work+Sans:wght@200;300;400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <div class="post">
      <?php include 'header_store.php'; ?>
      <div class="container">
        <span>مرحبًا، ما الذي تريد نشره اليوم؟</span>
        <span>إختر الفئة</span>
        <div class="post-content">
          
            <?php if (!empty($errorMessage)): ?>
                <div class="error" style="color: red; padding: 20px; font-weight: bold;"><?= $errorMessage ?></div>
            <?php else: ?>
                
                <?php foreach (array_keys($mainCategoriesData) as $categoryKey): 
                    $details = getCategoryDetails($categoryKey);
                ?>
                    <a href="<?= htmlspecialchars($details['link']) ?>" class="col">
                        <div class="box">
                            <div class="image">
                                <img src="<?= htmlspecialchars($details['image']) ?>" alt="<?= htmlspecialchars($details['display']) ?>" />
                            </div>
                            <div class="text"><?= htmlspecialchars($details['display']) ?></div>
                            <i class="fa-solid fa-chevron-left"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
                
            <?php endif; ?>

        </div>
      </div>
    </div>
    
    <footer class="mobile-footer-nav">
      <a href="ads.php" class="nav-item">
        <i class="fas fa-home"></i>
        <span>الرئيسية</span>
        <div class="nav-loader"></div>
      </a>
      <a href="my-ads.php" class="nav-item protected-link">
        <i class="fas fa-layer-group"></i>
        <span>إعلاناتي</span>
        <div class="nav-loader"></div>
      </a>
      <a href="ads_new.php" class="nav-item add-ad-button protected-link">
        <i class="fas fa-plus-circle"></i>
        <span>أضف إعلان</span>
        <div class="nav-loader"></div>
      </a>
      <a href="php/favorite.php" class="nav-item protected-link">
        <i class="fas fa-heart"></i>
        <span>المفضلة</span>
        <div class="nav-loader"></div>
      </a>
      <a href="account.php" class="nav-item" id="account-link-mobile">
        <div class="nav-loader"></div>
      </a>
    </footer>
      <script src="js/main.js"></script>
  </body>
</html>