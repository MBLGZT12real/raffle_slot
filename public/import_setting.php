<!DOCTYPE html>
<html lang="en">
<head>
    <title>Import Setting Undian</title>
    <?php require_once '../partials/assets.php'; ?>
</head>
<body class="container mt-4">
    <?php require_once '../partials/header.php'; ?>
    
    <div class="container mt-4">
        <h3><i class="bi bi-upload"></i> Import Setting</h3>

        <form class="mb-4" action="process_import_setting.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">File Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-outline-success btn-md">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Import
            </button>
        </form>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i> Upload akan <strong>menghapus seluruh data setting sebelumnya</strong> lalu menggantinya dari file Excel.
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success mt-3">
            ✅ Import setting berhasil
        </div>
    <?php endif ?>

    <?php require_once '../partials/footer.php'; ?>
</body>
</html>