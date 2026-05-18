<?php
session_start();

require_once 'php/db_connect.php';
$jsonFilePath = 'json/json-all.json';
$subcategories = [];
$data = [];
$errorMessage = '';

$path = $_GET['path'] ?? null; 
$pathSegments = $path ? explode('/', $path) : [];

$currentCategoryKey = end($pathSegments);
$categoryName = str_replace('_', ' ', $currentCategoryKey ?: 'الرئيسية');

function getCurrentLevelData(array $fullData, array $segments): ?array {
    $current = $fullData;
    
    $mainCategoryKey = $segments[0] ?? null; 
    if (!$mainCategoryKey || !isset($current[$mainCategoryKey])) {
        return null;
    }
    $current = $current[$mainCategoryKey]; 
    
    for ($i = 1; $i < count($segments); $i++) {
        $segment = $segments[$i];
        
        if (isset($current['subcategories'][$segment])) {
            $current = $current['subcategories'][$segment];
            
        } elseif (isset($current['subsubcategories'][$segment])) { 
            $current = $current['subsubcategories'][$segment];
            
        } else {
            return null; 
        }
    }
    return $current; 
}

function getNextDestination(array $fullData, string $currentPath, string $nextSegment): array {
    $newPath = $currentPath ? ($currentPath . '/' . $nextSegment) : $nextSegment;
    $newPathSegments = explode('/', $newPath);
    
    $newNode = getCurrentLevelData($fullData, $newPathSegments);
    
    $link = 'index.php';
    
    if ($newNode) {
        if (isset($newNode['fields'])) {
            $link = "form.php?path=" . urlencode($newPath);
        } 
        elseif (isset($newNode['subcategories']) || isset($newNode['subsubcategories'])) {
            $link = "subcategories.php?path=" . urlencode($newPath);
        } else {
            $link = "form.php?path=" . urlencode($newPath);
        }
    }

    return [
        'link' => $link,
        'image' => 'image/car.svg',
        'display' => str_replace('_', ' ', $nextSegment)
    ];
}

if (file_exists($jsonFilePath)) {
    $jsonString = file_get_contents($jsonFilePath);
    $data = json_decode($jsonString, true);
    
    if ($data === null || !is_array($data)) {
        $errorMessage = "خطأ في تحليل محتوى ملف الـ JSON.";
    } elseif (empty($pathSegments)) {
        $errorMessage = "المسار غير صالح. يرجى البدء من التصنيفات الرئيسية.";
    } else {
        $currentNode = getCurrentLevelData($data, $pathSegments);

        if ($currentNode === null) {
            $errorMessage = "المسار غير صالح أو التصنيف غير موجود.";
        } 
        elseif (isset($currentNode['subcategories'])) {
            $subcategories = $currentNode['subcategories'];
        } elseif (isset($currentNode['subsubcategories'])) { 
            $subcategories = $currentNode['subsubcategories'];
        } elseif (isset($currentNode['fields'])) {
            $errorMessage = "لقد وصلت إلى المستوى الأخير للإعلان.";
 
        } else {
            $errorMessage = "لم يتم العثور على تصنيفات فرعية لهذا المسار: " . htmlspecialchars($categoryName);
        }
    }
} else {
    $errorMessage = "ملف الـ JSON غير موجود في المسار المحدد: <strong>{$jsonFilePath}</strong>";
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>اختر تصنيف فرعي لـ: <?= htmlspecialchars($categoryName) ?></title>
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
        </br>
        
        <span>مرحبًا، ما الذي تريد نشره اليوم
        ؟</span>
        </br>
        <span>اختر تصنيف فرعي لـ: <strong><?= htmlspecialchars($categoryName) ?></strong></span>
         </br>
        
        <div class="post-content">
          
            <?php if (!empty($errorMessage)): ?>
                <div class="error" style="color: red; padding: 20px; font-weight: bold;"><?= $errorMessage ?></div>
            <?php elseif (empty($subcategories)): ?>
                <div class="error">لم يتم العثور على تصنيفات فرعية لعرضها.</div>
            <?php else: // توليد أزرار التصنيفات الفرعية ?>
                
                <?php foreach (array_keys($subcategories) as $subCategoryKey): 
                    $details = getNextDestination($data, $path, $subCategoryKey);
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
      </footer>
      <script src="js/main.js"></script>
  </body>
</html>