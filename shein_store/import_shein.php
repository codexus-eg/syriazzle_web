<?php

// =================================================================
// 1. اعدادات الاتصال وقراءة ملف JSON
// =================================================================

// ## قم بتعديل هذه المتغيرات ##
$servername = "localhost";
$username = "syriazzle_user";
$password = "Drj$,iEVQ_Bg";
$dbname = "syriazzle_online";

// !! تغيير مهم: اسم ملف الإدخال هو الآن ملف JSON الذي ننتجه !!
$json_file = 'products_output_smart.json'; 

$target_category_id = 10; // رقم تصنيف المنتجات في قاعدة بياناتك

// إعدادات عرض الأخطاء للمساعدة في التصحيح
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =================================================================
// 2. دوال مساعدة (Helper Functions)
// =================================================================

/**
 * دالة تتحقق من وجود قيمة في جدول (مثل لون أو مقاس) وتعيد الـ ID الخاص بها،
 * أو تقوم بإنشائها إذا لم تكن موجودة.
 * (هذه الدالة من الكود القديم وهي ممتازة، لذلك سنحتفظ بها)
 */
function getOrCreateId($pdo, $table, $column, $value) {
    if (empty($value) || $value === 'غير محدد') return null;
    $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE `$column` = ? LIMIT 1");
    $stmt->execute([$value]);
    $result = $stmt->fetch();
    if ($result) {
        return $result['id'];
    } else {
        // إضافة خاصة لجدول الألوان لإدراج قيمة افتراضية للـ hex_code
        if ($table === 'shn_colors') {
            $stmt = $pdo->prepare("INSERT INTO `shn_colors` (`name`, `hex_code`) VALUES (?, '#000000')");
            $stmt->execute([$value]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `$table` (`$column`) VALUES (?)");
            $stmt->execute([$value]);
        }
        return $pdo->lastInsertId();
    }
}

/**
 * دالة جديدة: تقوم بتحويل مصفوفة المواصفات إلى وصف جميل بصيغة HTML.
 */
function formatSpecificationsAsHtml($specs) {
    $html = "<h4>تفاصيل المنتج:</h4><ul>";
    foreach ($specs as $key => $value) {
        if (!empty($value) && $value !== 'غير محدد') {
            $html .= "<li><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "</li>";
        }
    }
    $html .= "</ul>";
    return $html;
}

/**
 * دالة جديدة: تقوم بإنشاء "handle" فريد من اسم المنتج (مفيد للتوافقية).
 */
function createHandleFromName($name) {
    $handle = mb_strtolower($name, 'UTF-8');
    $handle = preg_replace('/\s+/', '-', $handle); // استبدال المسافات بـ -
    $handle = preg_replace('/[^a-z0-9\-]/', '', $handle); // إزالة أي شيء ليس حرف أو رقم أو -
    return trim($handle, '-');
}

// =================================================================
// 3. الدالة الرئيسية للتنفيذ
// =================================================================
function runJsonImport($servername, $username, $password, $dbname, $json_file, $target_category_id) {
    // --- الاتصال بقاعدة البيانات ---
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        echo "تم الاتصال بقاعدة البيانات بنجاح.<br>";
    } catch (PDOException $e) { die("خطأ في الاتصال: " . $e->getMessage()); }

    // --- قراءة وتحليل ملف JSON ---
    if (!file_exists($json_file) || !is_readable($json_file)) {
        die("خطأ: تعذر العثور على ملف JSON أو قراءته.");
    }
    $json_content = file_get_contents($json_file);
    $products = json_decode($json_content, true); // true لتحويله إلى مصفوفة

    if (json_last_error() !== JSON_ERROR_NONE) {
        die("خطأ في تحليل ملف JSON: " . json_last_error_msg());
    }
    
    if (empty($products)) {
        die("<b>ملف JSON فارغ أو لا يحتوي على منتجات.</b>");
    }

    echo "<b>تم العثور على " . count($products) . " منتج في ملف JSON. بدء عملية الإدراج...</b><br>--------------------<br>";

    // --- المرور على كل منتج وإدراجه في قاعدة البيانات ---
    foreach ($products as $product) {
        // استخراج البيانات من المنتج الحالي
        $name = $product['name'] ?? 'منتج بدون اسم';
        $price = $product['price'] ?? '0.00';
        $images = $product['images'] ?? [];
        $specifications = $product['specifications'] ?? [];

        // استخراج اللون من المواصفات
        $color_name = $specifications['اللون'] ?? 'غير محدد';
        
        // إنشاء وصف HTML من المواصفات
        $description = formatSpecificationsAsHtml($specifications);
        
        // إنشاء handle من الاسم
        $original_handle = createHandleFromName($name);
        
        // دمج اسم اللون مع اسم المنتج الرئيسي لعنوان فريد
        $final_title = $name . " - " . $color_name;
        
        echo "إدراج: " . htmlspecialchars($final_title) . "... ";
        
        $pdo->beginTransaction();
        try {
            // 1. إدراج المنتج الأساسي في جدول shn_products
            $stmt = $pdo->prepare("INSERT INTO shn_products (category_id, original_handle, name, description, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$target_category_id, $original_handle, $final_title, $description, $price]);
            $product_id = $pdo->lastInsertId();
            
            // 2. إدراج اللون وربطه بالمنتج
            $color_id = getOrCreateId($pdo, 'shn_colors', 'name', $color_name);
            if ($color_id) {
                $stmt = $pdo->prepare("INSERT INTO shn_product_colors (product_id, color_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $color_id]);
            }
            
            // 3. إدراج الصور وربطها بالمنتج
            foreach ($images as $index => $url) {
                $stmt = $pdo->prepare("INSERT INTO shn_product_images (product_id, image_url, is_main) VALUES (?, ?, ?)");
                // اعتبار أول صورة هي الصورة الرئيسية
                $stmt->execute([$product_id, $url, ($index == 0)]);
            }

            // ملاحظة: ملف JSON لا يحتوي على معلومات المقاسات، لذلك تم تجاهل جدول shn_product_sizes
            
            $pdo->commit();
            echo "<b style='color:green;'>نجح!</b><br>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<b style='color:red;'>فشل: " . $e->getMessage() . "</b><br>";
        }
    }
    echo "--------------------<br><b>اكتملت عملية الاستيراد بالكامل.</b>";
}

// --- تشغيل السكريبت ---
runJsonImport($servername, $username, $password, $dbname, $json_file, $target_category_id);
?>