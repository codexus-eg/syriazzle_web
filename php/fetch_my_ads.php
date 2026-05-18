<?php
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_lifetime', 2592000);

// بدء الجلسة
session_start();
require_once 'auth_check.php';
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "المستخدم غير مسجل دخول"]);
    exit;
}

$conn = new mysqli("localhost", "syriazzle", "Drj$,iEVQ_Bg", "syriazzle_online");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "فشل الاتصال بقاعدة البيانات"]);
    exit;
}
mysqli_set_charset($conn, "utf8mb4");


$user_id = $_SESSION['user_id'];

$sql = "SELECT id, form_id, json_data, views, status1, submitted_at FROM form_submissions WHERE user_id = ? ORDER BY submitted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$ads = [];

while ($row = $result->fetch_assoc()) {
    $json = json_decode($row['json_data'], true);
    if (!$json) {
        $json = []; 
    }

    $json['db_id'] = $row['id'];
    $json['form_id'] = $row['form_id'];
    $json['status1'] = $row['status1']; 

    $json['مشاهدات'] = $row['views']; 

  
    $json['images_uploaded'] = $json['images'] ?? []; 


    $ads[] = $json;
}

echo json_encode(["success" => true, "ads" => $ads], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();

?>