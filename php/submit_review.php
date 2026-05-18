<?php

session_start();

require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}


$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5; // قيمة افتراضية 5 نجوم حاليًا

if ($business_id === 0 || empty($review_text)) {
    header('Location: ../profile.php?id=' . $business_id . '&error=empty_review');
    exit;
}

try {
    $sql = "INSERT INTO business_reviews (business_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([$business_id, $user_id, $rating, $review_text]);

    header('Location: ../profile.php?id=' . $business_id . '&success=review_added');
    exit;

} catch (PDOException $e) {
    error_log("Error saving review: " . $e->getMessage()); 
    header('Location: ../profile.php?id=' . $business_id . '&error=db_error');
    exit;
}
?>