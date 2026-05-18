<?php
// ========================================================================
// Syriazzle - Mark Notifications as Read
// ========================================================================

require_once 'db_connect.php';
header('Content-Type: application/json');

$user_id = 0;
$user_type = '';

if (isset($_SESSION['driver_id'])) {
    $user_id = (int)$_SESSION['driver_id'];
    $user_type = 'driver';
} elseif (isset($_SESSION['business_id'])) {
    $user_id = (int)$_SESSION['business_id'];
    $user_type = 'business';
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $user_type = 'user';
}

if ($user_id === 0) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE site_notifications SET is_read = 1 WHERE user_id = ? AND user_type = ? AND is_read = 0");
    $stmt->execute([$user_id, $user_type]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
}