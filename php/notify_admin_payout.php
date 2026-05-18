<?php
// ========================================================================
// Syriazzle - Payout Notification API (Final Production Build V2.0)
// ========================================================================

// 1. استدعاء الملفات الأساسية مع حماية المسارات الجسدية
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/NotificationManager.php';

// ضبط ترويسة الاستجابة لتكون JSON دائماً لضمان توافق الـ Fetch
header('Content-Type: application/json; charset=utf-8');

// منع أي مخرجات نصية عشوائية قد تفسد الـ JSON
ob_start();

// 2. التحقق الأمني: هل السائق مسجل دخول؟
if (!isset($_SESSION['driver_id'])) {
    ob_end_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'انتهت الجلسة الأمنية، يرجى إعادة تسجيل الدخول.'
    ]);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];

// 3. استقبال ومعالجة المبلغ المُدخل من السائق
$raw_amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
$payout_amount = filter_var($raw_amount, FILTER_VALIDATE_FLOAT);

if ($payout_amount === false || $payout_amount <= 0) {
    ob_end_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'يرجى إدخال مبلغ صحيح أكبر من الصفر.'
    ]);
    exit;
}

try {
    // 4. جلب بيانات السائق (الاسم والمحافظة) لضمان التوجيه الجغرافي الصحيح
    $stmt_driver = $pdo->prepare("SELECT full_name, governorate_id FROM drivers WHERE id = ? LIMIT 1");
    $stmt_driver->execute([$driver_id]);
    $driver = $stmt_driver->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        throw new Exception("بيانات السائق غير موجودة في النظام.");
    }

    // 5. منطق التوجيه الجغرافي الذكي (Geographic Routing)
    // نبحث عن الأدمن المسؤول عن محافظة هذا السائق لكي يصله الإشعار حصراً
    $target_admin_id = 1; // القيمة الافتراضية: السوبر أدمن الرئيسي
    
    if (!empty($driver['governorate_id'])) {
        try {
            // البحث عن أدمن نشط مرتبط بنفس المحافظة
            $stmt_admin = $pdo->prepare("SELECT id FROM admins WHERE governorate_id = ? AND status = 'active' LIMIT 1");
            $stmt_admin->execute([$driver['governorate_id']]);
            $found_admin_id = $stmt_admin->fetchColumn();
            
            if ($found_admin_id) {
                $target_admin_id = (int)$found_admin_id;
            }
        } catch (PDOException $geo_error) {
            // في حال فشل الاستعلام الجغرافي، نرسل للسوبر أدمن كحل احتياطي
            error_log("Geographic Routing Error: " . $geo_error->getMessage());
        }
    }

    // 6. بناء رسالة البلاغ بتنسيق هيكلي دقيق (Structured Format)
    // هام جداً: هذا التنسيق هو ما تعتمد عليه صفحة payout_requests.php لاستخراج البيانات
    $formatted_val = number_format($payout_amount);
    
    $title = "💰 طلب تسوية رصيد";
    $body = "[ID:{$driver_id}] الكابتن ({$driver['full_name']}) سدد مبلغ: {$formatted_val} ل.س";
    $link = "payout_requests.php"; // الرابط الذي سيفتحه الأدمن

    // 7. إرسال الإشعار عبر النظام الموحد (يُحفظ في القاعدة ويُرسل للهاتف)
    $notification_sent = NotificationManager::sendNotification(
        $target_admin_id, 
        'user', 
        $title, 
        $body, 
        $link
    );

    // 8. الرد النهائي للسائق بالنجاح
    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'message' => 'تم إرسال بلاغك بمبلغ ' . $formatted_val . ' ل.س بنجاح. يرجى انتظار تدقيق الإدارة لتصفر رصيدك.'
    ]);

} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    error_log("Payout API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'فشل إرسال البلاغ: ' . $e->getMessage()
    ]);
} catch (Throwable $t) {
    if (ob_get_length()) ob_end_clean();
    error_log("Payout API Critical Failure: " . $t->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ فني غير متوقع في الخادم.'
    ]);
}

exit;