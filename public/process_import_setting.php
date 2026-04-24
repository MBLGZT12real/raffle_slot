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
    ========================= */
    $headerRow = array_map(
        fn($v) => strtolower(trim($v)),
        $rows[1]
    );

    $required = ['date_slot', 'min_slot', 'max_slot', 'total_slot'];
    $map      = [];

    foreach ($required as $col) {
        $index = array_search($col, $headerRow, true);
        if ($index === false) {
            die("Kolom '$col' wajib ada di file Excel");
        }
        $map[$col] = $index;
    }

    /* =========================
    TRANSACTION START
    ========================= */
    global $conn;
    $conn->begin_transaction();

    try {
        /* TRUNCATE TABLE */
        db_query("TRUNCATE TABLE table_setting");

        $stmt = $conn->prepare("
            INSERT INTO table_setting (date_slot, min_slot, max_slot, total_slot)
            VALUES (?, ?, ?, ?)
        ");

        /* =========================
        LOOP DATA
        ========================= */
        for ($i = 2; $i <= count($rows); $i++) {
            $row = $rows[$i];

            if (empty($row[$map['date_slot']])) {
                continue;
            }

            $date      = trim($row[$map['date_slot']]);
            $minSlot   = (int)$row[$map['min_slot']];
            $maxSlot   = (int)$row[$map['max_slot']];
            $totalSlot = (int)$row[$map['total_slot']];

            /* VALIDASI DATE */
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception("Format date_slot salah di baris $i");
            }

            /* VALIDASI MIN ≤ MAX */
            if ($minSlot > $maxSlot) {
                throw new Exception("min_slot > max_slot di baris $i");
            }

            /* INSERT */
            $stmt->bind_param(
                "siii",
                $date,
                $minSlot,
                $maxSlot,
                $totalSlot
            );

            if (!$stmt->execute()) {
                throw new Exception("Gagal insert baris $i");
            }
        }

        $stmt->close();
        $conn->commit();

        header('Location: import_setting.php?success=1');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die('Import gagal: ' . $e->getMessage());
    }
?>