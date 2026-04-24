<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once '../partials/auth_check_api.php';
require_once '../core/ResultModel.php';

$date = $_GET['date'] ?? '';
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['exists' => false]);
    exit;
}

echo json_encode(['exists' => hasResultByDate($date)]);
?>
