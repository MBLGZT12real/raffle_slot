<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../partials/auth_check.php';

$preview  = $_SESSION['import_brand_preview'] ?? null;
$fileName = $_SESSION['import_brand_file']    ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Import Brand Undian</title>
    <?php require_once '../partials/assets.php'; ?>
</head>
<body class="container mt-4">
    <?php require_once '../partials/header.php'; ?>

    <div class="mt-4">
        <h4><i class="bi bi-building-fill-add me-1"></i> Import Brand</h4>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> Import berhasil (<?= (int)$_GET['rows'] ?> brand).</div>
        <?php endif ?>

        <!-- PREVIEW STEP -->
        <?php if ($preview): ?>
            <div class="alert alert-info">
                <strong><i class="bi bi-eye me-1"></i> Preview:</strong> File <code><?= htmlspecialchars($fileName) ?></code>
                — <?= count($preview) ?> brand.
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered">
                    <thead class="table-secondary"><tr><th>name_brand</th><th>group_brand</th><th>not_allow_brand</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($preview, 0, 10) as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name_brand']) ?></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= $r['group_brand'] ?></span></td>
                            <td class="text-muted" style="font-size:12px"><?= htmlspecialchars($r['not_allow_brand']) ?: '<em>—</em>' ?></td>
                        </tr>
                        <?php endforeach ?>
                        <?php if (count($preview) > 10): ?>
                        <tr><td colspan="3" class="text-muted text-center">... dan <?= count($preview) - 10 ?> brand lagi</td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" action="process_import_brand.php">
                    <input type="hidden" name="step" value="confirm">
                    <button class="btn btn-success" onclick="return confirm('Data brand lama akan dihapus dan diganti. Lanjutkan?')">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi Import
                    </button>
                </form>
                <form method="POST" action="process_import_brand.php">
                    <input type="hidden" name="step" value="cancel">
                    <button class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Batal</button>
                </form>
            </div>
            <hr>
        <?php endif ?>

        <!-- UPLOAD FORM -->
        <form action="process_import_brand.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="preview">
            <div class="mb-3">
                <label class="form-label">File Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx" required>
            </div>
            <button class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i> Preview Data</button>
        </form>

        <div class="alert alert-warning mt-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Upload akan <strong>menghapus seluruh data brand sebelumnya</strong>.
        </div>

        <div class="card border-secondary mt-3">
            <div class="card-header fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Format File Excel</div>
            <div class="card-body">
                <table class="table table-sm table-bordered w-auto">
                    <thead class="table-secondary"><tr><th>Kolom</th><th>Wajib?</th><th>Keterangan</th><th>Contoh</th></tr></thead>
                    <tbody>
                        <tr><td><code>name_brand</code></td><td><span class="badge bg-danger">Wajib</span></td><td>Nama brand (maks 30 karakter)</td><td>Fuso</td></tr>
                        <tr><td><code>group_brand</code></td><td><span class="badge bg-danger">Wajib</span></td><td>Satu huruf (A–Z)</td><td>A</td></tr>
                        <tr><td><code>not_allow_brand</code></td><td><span class="badge bg-secondary">Opsional</span></td><td>Brand yang DILARANG satu slot (pisah koma). Kosong = bebas bertemu semua.</td><td>Sokonindo, MG</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once '../partials/footer.php'; ?>
</body>
</html>
