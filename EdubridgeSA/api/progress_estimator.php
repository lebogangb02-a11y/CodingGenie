<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../progress_utils.php';

header('Content-Type: application/json');

// Identify current user; fallback to query param
session_start();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if (!$userId && isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
}
if (!$userId) {
    echo json_encode(['error' => 'user_not_identified']);
    exit;
}

try {
    $pdo = db();
    $result = computeProgressEstimates($pdo, $userId);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
?>