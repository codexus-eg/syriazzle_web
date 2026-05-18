<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

$response = ['success' => false, 'data' => [], 'error' => ''];

try {
    $sql = "
        WITH RankedAds AS (
            SELECT 
                id, 
                user_id, 
                json_data, 
                category, 
                sub, 
                subsub, 
                subsubsub, 
                submitted_at, 
                images_paths,
                ROW_NUMBER() OVER(PARTITION BY category ORDER BY submitted_at DESC) as rn
            FROM 
                form_submissions
        )
        SELECT 
            id, user_id, json_data, category, sub, subsub, subsubsub, submitted_at, images_paths
        FROM 
            RankedAds
        WHERE 
            rn <= 10;
    ";

    $stmt = $pdo->query($sql);
    $ads_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categorizedAds = [];
    
    foreach ($ads_from_db as $row) {
        $ad_data = json_decode($row['json_data'], true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $images_from_db = json_decode($row['images_paths'] ?? '[]', true);
            $ad_data['images'] = is_array($images_from_db) ? $images_from_db : [];
            
            // إضافة باقي البيانات
            $ad_data['db_id'] = $row['id']; 
            $ad_data['user_id'] = $row['user_id'];
            $ad_data['created_at'] = $row['submitted_at'];
            $ad_data['category'] = $row['category'];
            $ad_data['sub'] = $row['sub'];
            $ad_data['subsub'] = $row['subsub'];
            $ad_data['subsubsub'] = $row['subsubsub'];

            $ad_category = $row['category'];

            if (!isset($categorizedAds[$ad_category])) {
                $categorizedAds[$ad_category] = [];
            }
            $categorizedAds[$ad_category][] = $ad_data;
        }
    }
    
    $response['success'] = true;
    $response['data'] = $categorizedAds;

} catch (PDOException $e) {
    http_response_code(500);
    $response['error'] = "خطأ في قاعدة البيانات: " . $e->getMessage();
    error_log("Database Error in fetch_categories.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>