<!DOCTYPE html>
<html lang="en">
<head>
    <title>Import Brand Undian</title>
    <?php require_once '../partials/assets.php'; ?>
</head>
<body class="container mt-4">
    <?php require_once '../partials/header.php'; ?>

    <div class="container mt-4">
        <h3><i class="bi bi-building-fill-add"></i> Import Brand</h3>

        <form class="mb-4" action="process_import_brand.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">File Excel (.xlsx)</label>
                <input type="file" name="file" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-outline-success btn-md">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Import
            </button>
        </form>

        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i> Upload akan <strong>menghapus seluruh data brand sebelumnya</strong> lalu menggantinya dari file Excel.
        </div>

        <div class="card border-secondary">
            <div class="card-header fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Format File Excel</div>
            <div class="card-body">
                <p class="mb-2">File harus memiliki kolom berikut di baris pertama:</p>
                <table class="table table-bordered table-sm w-auto">
                    <thead class="table-secondary">
                        <tr>
                            <th>Kolom</th>
                            <th>Keterangan</th>
                            <th>Wajib?</th>
                            <th>Contoh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>name_brand</code></td>
                            <td>Nama brand (maks. 30 karakter)</td>
                            <td><span class="badge bg-danger">Wajib</span></td>
                            <td>Fuso</td>
                        </tr>
                        <tr>
                            <td><code>group_brand</code></td>
                            <td>Satu huruf (A–Z)</td>
                            <td><span class="badge bg-danger">Wajib</span></td>
                            <td>A</td>
                        </tr>
                        <tr>
                            <td><code>not_allow_brand</code></td>
                            <td>
                                Brand yang <strong>TIDAK BOLEH</strong> satu slot. Pisahkan dengan koma.<br>
                                <small class="text-muted">Kosongkan jika brand ini boleh bertemu semua brand lain.</small><br>
                                <small class="text-muted">Aturan bersifat mutual — cukup ditulis di satu sisi.</small>
                            </td>
                            <td><span class="badge bg-secondary">Opsional</span></td>
                            <td>Sokonindo, MG</td>
                        </tr>
                    </tbody>
                </table>
                <div class="alert alert-info mb-0 mt-2 py-2">
                    <strong>Contoh:</strong> BAIC mengisi <code>not_allow_brand</code> = <em>Sokonindo, MG</em><br>
                    Artinya BAIC tidak boleh satu slot dengan Sokonindo maupun MG.<br>
                    Sokonindo dan MG <strong>tidak perlu</strong> menulis BAIC di kolom mereka (sudah mutual otomatis).
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success mt-3">
            <i class="bi bi-check-circle-fill me-1"></i> Import brand berhasil
        </div>
    <?php endif ?>

    <?php require_once '../partials/footer.php'; ?>
</body>
</html>
