<?php
// ========================================================================
// Syriazzle - Promo Code Validator (Multi-Currency Support - Final)
// ========================================================================

// ضبط الهيدر لرد JSON وتشفير الأحرف
header('Content-Type: application/json; charset=utf-8');

// بدء الجلسة إذا لم تكن بادئة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// مصفوفة الرد الافتراضية
$response = [
    'success' => false, 
    'message' => 'حدث خطأ غير متوقع.',
    'discount_amount' => 0
];

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'يجب عليك تسجيل الدخول لتتمكن من استخدام كود الحسم.';
    echo json_encode($response);
    exit;
}

// 2. التحقق من طريقة الطلب والبيانات المرسلة
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['promo_code']) || !isset($_POST['items_total'])) {
    $response['message'] = 'الرجاء إدخال كود الحسم.';
    echo json_encode($response);
    exit;
}

// تنظيف المدخلات
$code = trim($_POST['promo_code']);
$items_total = floatval($_POST['items_total']);
// استقبال عملة المتجر من الجافاسكريبت (مهم جداً للتحويل)
$currency = $_POST['currency'] ?? 'SYP'; 

try {
    // 3. الاستعلام عن الكود في قاعدة البيانات
    // الشرط الأمني: applicable_to يجب أن يكون 'all' أو 'marketplace_only'
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1 AND applicable_to IN ('all', 'marketplace_only')");
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. التحقق من وجود الكود وصلاحيته
    if (!$promo) {
        $response['message'] = 'كود الحسم غير صحيح أو غير مخصص للمتاجر.';
        echo json_encode($response);
        exit;
    }

    // التحقق من تاريخ الانتهاء
    if ($promo['expiry_date'] && new DateTime() > new DateTime($promo['expiry_date'])) {
        $response['message'] = 'عذراً، لقد انتهت صلاحية كود الحسم هذا.';
        echo json_encode($response);
        exit;
    }

    // التحقق من الحد الأقصى للاستخدام العام
    if ($promo['max_uses'] !== null && $promo['times_used'] >= $promo['max_uses']) {
        $response['message'] = 'عذراً، تم الوصول للحد الأقصى لاستخدام هذا الكود.';
        echo json_encode($response);
        exit;
    }

    // 5. حساب قيمة الخصم (المنطق المحدث للعملات)
    $discount_amount = 0;

    if ($promo['discount_type'] === 'percentage') {
        // خصم نسبة مئوية (لا يتأثر بالعملة)
        $discount_amount = $items_total * ($promo['discount_value'] / 100);
    } else {
        // خصم قيمة ثابتة (مخزنة في قاعدة البيانات بالليرة السورية)
        $fixed_discount_syp = (float)$promo['discount_value'];

        if ($currency === 'USD') {
            // إذا كان المتجر بالدولار، والكوبون بالليرة، نحتاج للتحويل
            // جلب سعر الصرف الحالي
            $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
            $usd_rate = (float)($stmt_rate->fetchColumn() ?: 15000); // 15000 قيمة افتراضية للأمان
            
            // التحويل: القيمة بالدولار = القيمة بالليرة / سعر الصرف
            $discount_amount = $fixed_discount_syp / $usd_rate;
        } else {
            // المتجر بالليرة والكوبون بالليرة (لا حاجة للتحويل)
            $discount_amount = $fixed_discount_syp;
        }
    }
    
    // أمان: التأكد من أن الخصم لا يتجاوز قيمة المنتجات
    $discount_amount = min($items_total, $discount_amount);

    // تنسيق الرقم النهائي (تقريب)
    if ($currency === 'USD') {
        // للدولار: تقريب لأقرب سنت
        $discount_amount = round($discount_amount, 2);
    } else {
        // للليرة: عدد صحيح
        $discount_amount = floor($discount_amount);
    }

    // 6. إرسال الرد الناجح
    $response = [
        'success' => true,
        'message' => 'تم تطبيق كود الحسم بنجاح!',
        'discount_amount' => $discount_amount, 
        'code' => $promo['code']
    ];

} catch (Exception $e) {
    // تسجيل الخطأ في السيرفر
    error_log("Promo Code Error: " . $e->getMessage());
    $response['message'] = 'حدث خطأ فني أثناء التحقق من الكود.';
}

// طباعة الرد النهائي
echo json_encode($response);
?>