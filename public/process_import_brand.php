<?php
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../helpers/db.php';

    use PhpOffice\PhpSpreadsheet\IOFactory;

    /* =========================
    VALIDASI FILE
    ========================= */
    if (!isset($_FILES['file'])) {
        die('File tidak ditemukan');
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('Upload gagal');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        die('Format file harus .xlsx');
    }

    /* =========================
    LOAD EXCEL
    ========================= */
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) {
        die('File Excel kosong');
    }

    /* =========================
    VALIDASI HEADER
    Kolom wajib: name_brand, group_brand
    Kolom opsional: not_allow_brand (kosong = boleh ketemu semua)
    ========================= */
    $headerRow = array_map(
        fn($v) => strtolower(trim((string)$v)),
        $rows[1]
    );

    $required = ['name_brand', 'group_brand'];
    $map      = [];
    foreach ($required as $col) {
        $index = array_search($col, $headerRow, true);
        if ($index === false) {
            die("Kolom '$col' wajib ada di file Excel");
        }
        $map[$col] = $index;
    }

    // not_allow_brand opsional
    $notAllowIdx = array_search('not_allow_brand', $headerRow, true);
    $map['not_allow_brand'] = $notAllowIdx !== false ? $notAllowIdx : null;

    /* =========================
    TRANSACTION START
    ========================= */
    global $conn;
    $conn->begin_transaction();

    try {
        /* TRUNCATE TABLE */
        db_query("TRUNCATE TABLE table_brand");

        $stmt = $conn->prepare("
            INSERT INTO table_brand (name_brand, group_brand, not_allow_brand)
            VALUES (?, ?, ?)
        ");

        /* =========================
        LOOP DATA
        ========================= */
        for ($i = 2; $i <= count($rows); $i++) {
            $row = $rows[$i];

            if (empty($row[$map['name_brand']])) {
                continue;
            }

            $nameBrand  = trim((string)$row[$map['name_brand']]);
            $groupBrand = trim((string)$row[$map['group_brand']]);

            // not_allow_brand boleh kosong
            $notAllow = '';
            if ($map['not_allow_brand'] !== null && !empty($row[$map['not_allow_brand']])) {
                $notAllow = trim((string)$row[$map['not_allow_brand']]);
            }

            /* =========================
            VALIDASI name_brand
            ========================= */
            if (!preg_match('/^[A-Za-z0-9\s\-\.,]{1,30}$/', $nameBrand)) {
                throw new Exception("name_brand tidak valid di baris $i: \"$nameBrand\"");
            }

            /* =========================
            VALIDASI group_brand
            ========================= */
            if (!preg_match('/^[A-Za-z]{1}$/', $groupBrand)) {
                throw new Exception("group_brand harus 1 huruf alfabet di baris $i");
            }

            /* =========================
            VALIDASI not_allow_brand (jika diisi)
            ========================= */
            if ($notAllow !== '' && !preg_match('/^[A-Za-z0-9\s\-\.,]+$/', $notAllow)) {
                throw new Exception("not_allow_brand tidak valid di baris $i");
            }

            /* INSERT */
            $stmt->bind_param("sss", $nameBrand, $groupBrand, $notAllow);

            if (!$stmt->execute()) {
                throw new Exception("Gagal insert baris $i");
            }
        }

        $stmt->close();
        $conn->commit();

        header('Location: import_brand.php?success=1');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die('Import gagal: ' . $e->getMessage());
    }
?>
