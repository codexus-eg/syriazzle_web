<?php
// 1. بدء الجلسة أولاً وقبل كل شيء
session_start();

// 2. تعيين نوع المحتوى لضمان استجابة JSON صحيحة باللغة العربية
header('Content-Type: application/json; charset=utf-8');

// 3. تضمين ملف الاتصال بقاعدة البيانات
require_once 'db_connect.php'; 

// 4. إعداد هيكل الاستجابة الافتراضي
$response = ['success' => false, 'error' => '', 'message' => ''];

try {
    // =======================================================
    // 5. التحقق من المصادقة وصلاحية الطلب
    // =======================================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("خطأ: الطلب يجب أن يكون من نوع POST.");
    }

    // التحقق من أن المستخدم مسجل دخوله
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('يجب عليك تسجيل الدخول أولاً لتنفيذ هذه العملية.');
    }

    // >> تعديل هام: التحقق من وجود اسم المستخدم في الجلسة <<
    // هذه هي النقطة التي تسبب مشكلتك على الأغلب.
    // تأكد من أنك تقوم بتخزين اسم المستخدم في الجلسة عند تسجيل الدخول.
    if (!isset($_SESSION['username'])) {
        // إذا لم يكن اسم المستخدم موجودًا، فهذا يعني أن هناك مشكلة في ملف تسجيل الدخول لديك
        throw new Exception('خطأ في الجلسة: اسم المستخدم غير موجود. يرجى التحقق من كود تسجيل الدخول.');
    }
    
    // استخراج بيانات المستخدم من الجلسة
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // =======================================================
    // 6. التحقق من البيانات المرسلة
    // =======================================================
    if (!isset($_POST['json_data'])) {
        throw new Exception("خطأ: بيانات الإعلان الأساسية مفقودة.");
    }

    $json_data_raw = $_POST['json_data'];
    $ad_data = json_decode($json_data_raw, true);

    if ($ad_data === null) {
        throw new Exception("خطأ: صيغة بيانات JSON غير صحيحة: " . json_last_error_msg());
    }

    $category = $ad_data['category'] ?? '';
    $sub = $ad_data['sub'] ?? '';
    
    // =======================================================
    // 7. معالجة رفع الصور
    // =======================================================
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            throw new Exception('فشل في إنشاء مجلد لرفع الصور.');
        }
    }

    $newly_uploaded_paths = [];
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if (empty($tmp_name) || $_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }
            $file_name = $_FILES['images']['name'][$key];
            // إنشاء اسم فريد وآمن للملف
            $unique_name = uniqid('img_', true) . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
            $destination = $upload_dir . $unique_name;
            
            if (move_uploaded_file($tmp_name, $destination)) {
                // حفظ المسار بدون '../' ليكون متوافقًا مع الويب
                $newly_uploaded_paths[] = 'uploads/' . $unique_name;
            } else {
                // تسجيل الخطأ للمساعدة في التصحيح
                error_log("فشل في نقل الملف المرفوع: {$tmp_name} إلى {$destination}");
            }
        }
    }
    
    // دمج الصور القديمة (في حال التعديل) مع الصور المرفوعة حديثًا
    $existing_images = isset($_POST['existing_images']) ? json_decode($_POST['existing_images'], true) : [];
    $final_image_paths = array_merge($existing_images, $newly_uploaded_paths);
    $images_paths_json = json_encode($final_image_paths, JSON_UNESCAPED_UNICODE);

    // =======================================================
    // 8. تنفيذ العملية في قاعدة البيانات (تحديث أو إضافة)
    // =======================================================
    $ad_id = $_POST['ad_id'] ?? null;

    if ($ad_id) {
        // --- وضع التحديث (UPDATE) ---
        $stmt = $pdo->prepare(
            "UPDATE form_submissions 
             SET json_data = :json_data, 
                 images_paths = :images_paths,
                 category = :category,
                 sub = :sub,
                 submitted_at = NOW(),
                 status1 = 'pending' -- إعادة الإعلان للمراجعة بعد التعديل
             WHERE id = :ad_id AND user_id = :user_id -- شرط الأمان لمنع تعديل إعلانات الآخرين"
        );
        $stmt->execute([
            ':json_data' => $json_data_raw,
            ':images_paths' => $images_paths_json,
            ':category' => $category,
            ':sub' => $sub,
            ':ad_id' => $ad_id,
            ':user_id' => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'تم تحديث الإعلان بنجاح، وهو الآن قيد المراجعة.';
        } else {
            throw new Exception('فشل تحديث الإعلان. قد لا تملك الصلاحية أو أن الإعلان غير موجود.');
        }

    } else {
        // --- وضع الإنشاء (INSERT) ---
        $stmt = $pdo->prepare(
            "INSERT INTO form_submissions 
             (user_id, username, form_id, category, sub, json_data, images_paths, submitted_at, status1) 
             VALUES (:user_id, :username, 'ad_form', :category, :sub, :json_data, :images_paths, NOW(), 'pending')"
        );
        $stmt->execute([
            ':user_id' => $user_id,  
            ':username' => $username,     
            ':category' => $category,
            ':sub' => $sub,
            ':json_data' => $json_data_raw,
            ':images_paths' => $images_paths_json
        ]);
        
        $response['success'] = true;
        $response['message'] = 'تم نشر الإعلان بنجاح، وهو الآن قيد المراجعة.';
    }

} catch (PDOException $e) {
    // التعامل مع أخطاء قاعدة البيانات
    error_log("PDO Error: " . $e->getMessage()); 
    $response['error'] = 'حدث خطأ فني أثناء الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.';
} catch (Exception $e) {
    // التعامل مع الأخطاء العامة
    $response['error'] = $e->getMessage();
}

// 9. إرسال الاستجابة النهائية كـ JSON
echo json_encode($response);
?>