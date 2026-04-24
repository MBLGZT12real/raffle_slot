<?php
// Middleware auth untuk endpoint API (JSON) — return 401 jika belum masuk
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION[AUTH_SESSION_KEY])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login kembali.']);
    exit;
}
?>
