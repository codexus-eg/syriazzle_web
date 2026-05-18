<?php

session_start();

header('Content-Type: application/json');



require_once 'db_connect.php'; // تأكد من المسار الصحيح

require_once 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['success' => false, 'message' => 'الطلب غير مسموح.']);

    exit;

}



$ad_id = isset($_POST['ad_id']) ? (int)$_POST['ad_id'] : 0;



if ($ad_id <= 0) {
 
    http_response_code(400);

    echo json_encode(['success' => false, 'message' => 'معرف الإعلان غير صالح.']);

    exit;
}


try {

    $logged_in_user_id = $_SESSION['user_id'] ?? null;

    if ($logged_in_user_id === null) {

        http_response_code(401);

        echo json_encode(['success' => false, 'message' => 'يرجى تسجيل الدخول لتعديل الإعلانات.']);

        exit;

    }



    $stmt = $pdo->prepare("SELECT user_id, json_data FROM form_submissions WHERE id = ?");

    $stmt->execute([$ad_id]);

    $ad_row = $stmt->fetch(PDO::FETCH_ASSOC);



    if (!$ad_row) {

        http_response_code(404);

        echo json_encode(['success' => false, 'message' => 'الإعلان غير موجود.']);

        exit;

    }



    if ((int)$ad_row['user_id'] !== (int)$logged_in_user_id) {

        http_response_code(403);

        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل هذا الإعلان.']);

        exit;

    }



    $existing_json_data = json_decode($ad_row['json_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {

        $existing_json_data = []; 

    }



    $updated_ad_data = $existing_json_data; 

    $fixed_db_fields = ['ad_id', 'category', 'sub', 'subsub', 'subsubsub'];

    $image_field_name = 'images';

    $deleted_images_field_name = 'deleted_images';



    foreach ($_POST as $key => $value) {

        if (!in_array($key, $fixed_db_fields) && $key !== $image_field_name && $key !== $deleted_images_field_name) {
            $updated_ad_data[$key] = trim($value);
        }
    }
    $deleted_image_paths_json = $_POST['deleted_images'] ?? '[]';

    $deleted_image_paths = json_decode($deleted_image_paths_json, true);



    if (json_last_error() === JSON_ERROR_NONE && is_array($deleted_image_paths) && !empty($deleted_image_paths)) {

        if (isset($updated_ad_data['images']) && is_array($updated_ad_data['images'])) {

            $current_images = $updated_ad_data['images'];

            $remaining_images = [];

            foreach ($current_images as $image_path) {

               

                $image_file = str_replace('uploads/', '', $image_path); 

                

                $is_deleted = false;

                foreach($deleted_image_paths as $deleted_path) {

                   

                    $normalized_image_path = ltrim($image_path, '/');

                    $normalized_deleted_path = ltrim($deleted_path, '/');



                    if ($normalized_image_path === $normalized_deleted_path) {

                        $is_deleted = true;

                        $full_file_path = __DIR__ . '/../' . $image_path;  

                        if (file_exists($full_file_path)) {

                            unlink($full_file_path);

                        }

                        break;

                    }

                }

                if (!$is_deleted) {

                    $remaining_images[] = $image_path;

                }

            }

            $updated_ad_data['images'] = $remaining_images;

        }

    }





    // معالجة تحميل صور جديدة

    if (isset($_FILES['images']) && count($_FILES['images']['name']) > 0 && $_FILES['images']['error'][0] === UPLOAD_ERR_OK) {

        $new_image_paths = [];

        $upload_dir = __DIR__ . '/../uploads/'; // المسار المطلق لمجلد التحميل



        // تأكد من وجود مجلد التحميل

        if (!is_dir($upload_dir)) {

            mkdir($upload_dir, 0755, true);

        }



        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {

            $file_extension = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);

            $new_file_name = uniqid('img_') . '.' . $file_extension;

            $target_file = $upload_dir . $new_file_name;



            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($_FILES['images']['type'][$key], $allowed_types)) {

                error_log("Invalid file type uploaded: " . $_FILES['images']['type'][$key]);

                continue; 

            }

            if ($_FILES['images']['size'][$key] > 5 * 1024 * 1024) { // 5MB limit

                error_log("File size too large: " . $_FILES['images']['size'][$key]);

                continue;

            }





            if (move_uploaded_file($tmp_name, $target_file)) {

                $new_image_paths[] = 'uploads/' . $new_file_name; // المسار النسبي الذي سيتم حفظه في DB

            } else {

                error_log("Failed to move uploaded file: " . $_FILES['images']['name'][$key] . " to " . $target_file);

            }

        }

        if (!isset($updated_ad_data['images'])) {

            $updated_ad_data['images'] = [];

        }

        // دمج الصور الجديدة مع الصور الموجودة

        $updated_ad_data['images'] = array_merge($updated_ad_data['images'], $new_image_paths);

    }



    $json_to_store = json_encode($updated_ad_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (json_last_error() !== JSON_ERROR_NONE) {

        http_response_code(500);

        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تحويل البيانات إلى JSON: ' . json_last_error_msg()]);

        exit;

    }



    // تحديث البيانات في قاعدة البيانات

    $stmt = $pdo->prepare("UPDATE form_submissions SET json_data = ?, category = ?, sub = ?, subsub = ?, subsubsub = ? WHERE id = ?");

    $stmt->execute([

        $json_to_store, 
        $_POST['category'] ?? $ad_row['category'], // استخدم القيمة الجديدة أو القديمة إذا لم ترسل

        $_POST['sub'] ?? $ad_row['sub'],

        $_POST['subsub'] ?? $ad_row['subsub'],

        $_POST['subsubsub'] ?? $ad_row['subsubsub'],
        $ad_id
    ]);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'تم حفظ التعديلات بنجاح!']);
} catch (PDOException $e) {

    http_response_code(500);

    error_log("Database Error in update_ad.php: " . $e->getMessage());

    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات أثناء حفظ التعديلات.']);

} catch (Exception $e) {

    http_response_code(500);

    error_log("General Error in update_ad.php: " . $e->getMessage());

    echo json_encode(['success' => false, 'message' => 'حدث خطأ غير متوقع أثناء حفظ التعديلات: ' . $e->getMessage()]);

}

?>