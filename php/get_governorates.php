<?php
require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $stmt = $pdo->query("SELECT id, name FROM governorates ORDER BY name ASC");
    $governorates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $governorates]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Governorates Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch governorates.']);
}
?>