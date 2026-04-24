<?php
// Middleware auth untuk halaman HTML — redirect ke login jika belum masuk
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION[AUTH_SESSION_KEY])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header("Location: login.php?r=$redirect");
    exit;
}
?>
