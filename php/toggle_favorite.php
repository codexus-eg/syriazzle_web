<?php
// تفعيل عرض الأخطاء للتصحيح (يجب تعطيله في بيئة الإنتاج)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة للحصول على معرف المستخدم
session_start();

// تحديد نوع المحتوى كـ JSON للإستجابة
header('Content-Type: application/json');

// تضمين ملف الاتصال بقاعدة البيانات
require_once 'db_connect.php';

// التحقق من أن المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'يرجى تسجيل الدخول.']);
    exit;
}

$user_id = (int)$_SESSION['user_id']; // تحويل معرف المستخدم إلى عدد صحيح

// التحقق من أن الطلب هو من نوع POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من استقبال ad_id
    $ad_id = isset($_POST['ad_id']) ? (int)$_POST['ad_id'] : 0;

    // التحقق من صلاحية ad_id
    if ($ad_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ad ID provided.']);
        exit;
    }

    try {
        // تعيين وضع الأخطاء لـ PDO لرمي الاستثناءات
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // *** الخطوة الأهم: التحقق من وجود الإعلان في جدول form_submissions ***
        // هذا يمنع خطأ المفتاح الأجنبي (Foreign Key Constraint)
        $stmt_check_ad = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE id = :ad_id");
        $stmt_check_ad->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
        $stmt_check_ad->execute();
        $ad_exists = (bool)$stmt_check_ad->fetchColumn();

        if (!$ad_exists) {
            // إذا لم يكن الإعلان موجوداً في form_submissions، أرسل رسالة خطأ
            echo json_encode(['success' => false, 'message' => 'Ad does not exist in the form_submissions table.']);
            exit;
        }
        // *** نهاية التحقق من وجود الإعلان ***

        // التحقق مما إذا كان الإعلان مفضلاً لهذا المستخدم بالفعل
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :user_id AND ad_id = :ad_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
        $stmt->execute();
        $is_favorite = (bool)$stmt->fetchColumn();

        if ($is_favorite) {
            // إذا كان مفضلاً بالفعل، قم بإزالته من المفضلة (عملية إلغاء الإعجاب)
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = :user_id AND ad_id = :ad_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Ad removed from favorites.']);
        } else {
            // إذا لم يكن مفضلاً، قم بإضافته إلى المفضلة (عملية الإعجاب)
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, ad_id) VALUES (:user_id, :ad_id)");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':ad_id', $ad_id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Ad added to favorites.']);
        }

    } catch (PDOException $e) {
        // في حالة حدوث أي خطأ في قاعدة البيانات
        http_response_code(500); // إرسال رمز حالة HTTP 500 (Internal Server Error)
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    // إذا كان الطلب ليس من نوع POST
    http_response_code(405); // إرسال رمز حالة HTTP 405 (Method Not Allowed)
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>