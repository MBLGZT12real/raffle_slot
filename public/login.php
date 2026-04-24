<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/auth.php';

// Sudah login → langsung ke dashboard
if (!empty($_SESSION[AUTH_SESSION_KEY])) {
    header('Location: index.php');
    exit;
}

$error    = '';
$redirect = $_GET['r'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if ($pass === AUTH_PASSWORD) {
        $_SESSION[AUTH_SESSION_KEY] = true;
        header('Location: ' . (filter_var($_POST['redirect'], FILTER_VALIDATE_URL) ? 'index.php' : $_POST['redirect']));
        exit;
    }
    $error = 'Password salah. Coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Login — Undian Slot</title>
    <?php require_once '../partials/assets.php'; ?>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
        }
        .login-icon {
            font-size: 52px;
            display: block;
            text-align: center;
            margin-bottom: 8px;
            filter: drop-shadow(0 0 16px rgba(255,215,0,0.6));
        }
        .login-title {
            color: #fff;
            text-align: center;
            font-weight: 700;
            font-size: 22px;
            margin-bottom: 4px;
        }
        .login-sub {
            color: rgba(255,255,255,0.45);
            text-align: center;
            font-size: 13px;
            margin-bottom: 28px;
        }
        .form-control {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.12);
            border-color: #ffd700;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #ffd700, #ff9a3c);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            font-size: 15px;
            color: #1a1a2e;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }
        .btn-login:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }
    </style>
</head>
<body>
    <div class="login-card">
        <span class="login-icon">🎰</span>
        <div class="login-title">Undian Slot Acara</div>
        <div class="login-sub">Masukkan password untuk melanjutkan</div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3" style="border-radius:10px; font-size:14px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif ?>

        <form method="POST">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <div class="mb-3">
                <input type="password" name="password" class="form-control"
                    placeholder="Password" autofocus autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-unlock-fill me-2"></i> Masuk
            </button>
        </form>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
