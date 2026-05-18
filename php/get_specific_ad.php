<?php
// تفعيل عرض الأخطاء لبيئة التطوير (قم بتعطيله في بيئة الإنتاج)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// تأكد أن هذا السطر هو أول سطر يرسل أي إخراج (قبل أي echo أو print)
header('Content-Type: application/json');

// تضمين ملف الاتصال بقاعدة البيانات
// افترض أن db_connect.php موجود في نفس المجلد
include 'db_connect.php'; 
require_once 'auth_check.php'; 

// مصفوفة للاستجابة التي سيتم إرجاعها كـ JSON
$response = ['success' => false, 'error' => ''];

// التحقق مما إذا كان معرف الإعلان (id) موجوداً في طلب GET
if (isset($_GET['id'])) {
    $adId = $_GET['id'];

    // استخدام Prepared Statement لمنع حقن SQL (SQL Injection)
    // جلب جميع الأعمدة اللازمة، بما في ذلك json_data
    $stmt = $conn->prepare("SELECT id, category, sub, subsub, subsubsub, username, user_id, submitted_at, json_data FROM form_submissions WHERE id = ?");
    
    // التحقق من نجاح إعداد الاستعلام
    if ($stmt === false) {
        $response['error'] = 'خطأ في إعداد استعلام جلب الإعلان: ' . $conn->error;
        echo json_encode($response);
        exit;
    }
    
    // ربط المعرف (ID) كبارامتر
    $stmt->bind_param("i", $adId); // 'i' تعني integer (عدد صحيح)

    // تنفيذ الاستعلام
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        // التحقق مما إذا تم العثور على إعلان
        if ($row = $result->fetch_assoc()) {
            // فك ترميز عمود json_data إلى مصفوفة PHP
            $json_data_decoded = json_decode($row['json_data'], true);
            
            // التحقق من وجود أخطاء في فك الترميز
            if ($json_data_decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                // سجل الخطأ ولكن استمر إذا كنت تريد إرجاع البيانات غير الديناميكية
                error_log("خطأ في فك ترميز JSON_DATA للإعلان ID {$adId}: " . json_last_error_msg());
                $json_data_decoded = []; // تعيينه كمصفوفة فارغة لتجنب الأخطاء
            }

            // ******* التعديل هنا: جلب الصور من json_data_decoded *******
            // افترض أن المفتاح الذي يحتوي على مصفوفة أسماء ملفات الصور هو 'images' داخل json_data
            $images = [];
            if (isset($json_data_decoded['images']) && is_array($json_data_decoded['images'])) {
                $images = $json_data_decoded['images'];
            }
            // ************************************************************

            // دمج البيانات الأساسية مع البيانات الديناميكية من json_data_decoded
            $merged_data = array_merge($row, $json_data_decoded);
            
            // إضافة مصفوفة الصور إلى البيانات المدمجة
            $merged_data['images'] = $images; // الآن 'images' ستحتوي على البيانات من json_data

            // إزالة عمود json_data الأصلي من البيانات المدمجة قبل الإرسال لتجنب تكرار البيانات
            unset($merged_data['json_data']); 

            // إضافة نسخة من json_data_decoded إذا كان JavaScript يريدها بشكل منفصل عن الـ merged_data
            // هذا مفيد إذا كنت تريد الوصول للحقول الأصلية داخل json_data_decoded بشكل مباشر في JS
            $merged_data['json_data_decoded'] = $json_data_decoded; 

            $response['success'] = true;
            $response['data'] = $merged_data;

        } else {
            $response['error'] = 'الإعلان المطلوب غير موجود.';
        }
    } else {
        $response['error'] = 'خطأ في تنفيذ استعلام جلب الإعلان: ' . $stmt->error;
    }
    $stmt->close(); // إغلاق الـ prepared statement
} else {
    $response['error'] = 'معرف الإعلان (ID) غير محدد في الطلب.';
}

// إرجاع الاستجابة كـ JSON
echo json_encode($response);
exit; // إنهاء السكريبت بعد إرسال الاستجابة
?>