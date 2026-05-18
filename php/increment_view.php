<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'syriazzle_online';
$user = 'syriazzle';
$pass = 'Drj$,iEVQ_Bg';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ad_id'])) {
    $adId = $_POST['ad_id'];

    try {
        // زيادة عدد المشاهدات للإعلان المحدد
        $stmt = $pdo->prepare("UPDATE form_submissions SET views = views + 1 WHERE id = ?");
        $stmt->execute([$adId]);

        echo json_encode(['success' => true, 'message' => 'View count incremented.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث عدد المشاهدات: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'معرف الإعلان غير موجود أو طريقة طلب غير صحيحة.']);
}
?>