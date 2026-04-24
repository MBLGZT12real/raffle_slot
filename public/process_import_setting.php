<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../partials/auth_check.php';
require_once '../vendor/autoload.php';
require_once '../helpers/db.php';
require_once '../core/LogModel.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$step = $_POST['step'] ?? 'preview';

/* ================================================================
   CANCEL
   ================================================================ */
if ($step === 'cancel') {
    unset($_SESSION['import_setting_preview'], $_SESSION['import_setting_file'], $_SESSION['import_setting_tmp']);
    header('Location: import_setting.php');
    exit;
}

/* ================================================================
   CONFIRM — proses dari data session
   ================================================================ */
if ($step === 'confirm') {
    $data = $_SESSION['import_setting_preview'] ?? null;
    if (!$data) { header('Location: import_setting.php'); exit; }

    global $conn;
    $conn->begin_transaction();
    try {
        db_query("TRUNCATE TABLE table_setting");
        $stmt = $conn->prepare("INSERT INTO table_setting (date_slot, min_slot, max_slot, total_slot) VALUES (?,?,?,?)");
        foreach ($data as $r) {
            $stmt->bind_param('siii', $r['date_slot'], $r['min_slot'], $r['max_slot'], $r['total_slot']);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();

        writeLog('import_setting', null, ['rows' => count($data)]);

        unset($_SESSION['import_setting_preview'], $_SESSION['import_setting_file'], $_SESSION['import_setting_tmp']);
        header('Location: import_setting.php?success=1&rows=' . count($data));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die('Import gagal: ' . $e->getMessage());
    }
}

/* ================================================================
   PREVIEW — parse Excel dan simpan ke session
   ================================================================ */
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) die('Upload gagal');
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') die('Format harus .xlsx');

$tmpDest = __DIR__ . '/../storage/tmp_setting_import.xlsx';
if (!is_dir(dirname($tmpDest))) mkdir(dirname($tmpDest), 0755, true);
move_uploaded_file($_FILES['file']['tmp_name'], $tmpDest);

$spreadsheet = IOFactory::load($tmpDest);
$rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

$headerRow = array_map(fn($v) => strtolower(trim((string)$v)), $rows[1]);
$required  = ['date_slot', 'min_slot', 'max_slot', 'total_slot'];
$map       = [];
foreach ($required as $col) {
    $idx = array_search($col, $headerRow, true);
    if ($idx === false) die("Kolom '$col' tidak ditemukan");
    $map[$col] = $idx;
}

$data = [];
for ($i = 2; $i <= count($rows); $i++) {
    $row  = $rows[$i];
    $date = trim((string)($row[$map['date_slot']] ?? ''));
    if (!$date) continue;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) die("Format date_slot salah di baris $i");
    $data[] = [
        'date_slot'  => $date,
        'min_slot'   => (int)$row[$map['min_slot']],
        'max_slot'   => (int)$row[$map['max_slot']],
        'total_slot' => (int)$row[$map['total_slot']],
    ];
}

$_SESSION['import_setting_preview'] = $data;
$_SESSION['import_setting_file']    = $_FILES['file']['name'];
header('Location: import_setting.php');
exit;
?>
