<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../partials/auth_check.php';

$preview  = $_SESSION['import_setting_preview'] ?? null;
$fileName = $_SESSION['import_setting_file']    ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Import Setting Undian</title>
    <?php require_once '../partials/assets.php'; ?>
</head>
<body class="container mt-4">
    <?php require_once '../partials/header.php'; ?>

    <div class="mt-4">
        <h4><i class="bi bi-database-fill-gear me-1"></i> Import Setting</h4>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> Import berhasil (<?= (int)$_GET['rows'] ?> tanggal).</div>
        <?php endif ?>

        <!-- PREVIEW STEP -->
        <?php if ($preview): ?>
            <div class="alert alert-info">
                <strong><i class="bi bi-eye me-1"></i> Preview:</strong> File <code><?= htmlspecialchars($fileName) ?></code>
                — <?= count($preview) ?> baris data.
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead class="table-secondary"><tr><th>date_slot</th><th>min_slot</th><th>max_slot</th><th>total_slot</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($preview, 0, 10) as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['date_slot']) ?></td>
                            <td><?= $r['min_slot'] ?></td>
                            <td><?= $r['max_slot'] ?></td>
                            <td><?= $r['total_slot'] ?></td>
                        </tr>
                        <?php endforeach ?>
                        <?php if (count($preview) > 10): ?>
                        <tr><td colspan="4" class="text-muted text-center">... dan <?= count($preview) - 10 ?> baris lagi</td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" action="process_import_setting.php">
                    <input type="hidden" name="step" value="confirm">
                    <button class="btn btn-success" onclick="return confirm('Data setting lama akan dihapus dan diganti. Lanjutkan?')">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi Import
                    </button>
                </form>
                <form method="POST" action="process_import_setting.php">
                    <input type="hidden" name="step" value="cancel">
                    <button class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Batal</button>
                </form>
            </div>
            <hr>
        <?php endif ?>

        <!-- UPLOAD FORM -->
        <form action="process_import_setting.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="preview">
            <div class="mb-3">
                <label class="form-label">File Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx" required>
            </div>
            <button class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i> Preview Data</button>
        </form>

        <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Upload akan <strong>menghapus seluruh data setting sebelumnya</strong>.
        </div>
    </div>

    <?php require_once '../partials/footer.php'; ?>
</body>
</html>
