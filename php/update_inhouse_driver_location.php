<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['driver_id'])) {
    exit; // خروج صامت
}

$driver_id = (int)$_SESSION['driver_id'];
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

if ($latitude !== null && $longitude !== null) {
    try {
        $stmt = $pdo->prepare("UPDATE drivers SET current_latitude = ?, current_longitude = ?, last_seen = NOW() WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $driver_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("In-house driver location update failed: " . $e->getMessage());
    }
}
?>