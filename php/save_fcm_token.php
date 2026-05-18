<?php
// ========================================================================
// Syriazzle - Unified FCM Token Saver
// ========================================================================
require_once 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$token = $_POST['fcm_token'] ?? null;
$type = $_POST['type'] ?? null; // 'user', 'driver', or 'business'

if (!$token || !$type) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

// تحديد معرف الشخص من الجلسة بناءً على نوعه
$id = null;
$table = '';

if ($type === 'driver' && isset($_SESSION['driver_id'])) {
    $id = $_SESSION['driver_id'];
    $table = 'drivers';
} elseif ($type === 'business' && isset($_SESSION['business_id'])) {
    $id = $_SESSION['business_id'];
    $table = 'businesses';
} elseif ($type === 'user' && isset($_SESSION['user_id'])) {
    $id = $_SESSION['user_id'];
    $table = 'users';
}

if ($id && $table) {
    try {
        $stmt = $pdo->prepare("UPDATE {$table} SET fcm_token = ? WHERE id = ?");
        $stmt->execute([$token, $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in or invalid type']);
}