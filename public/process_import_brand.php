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
    unset($_SESSION['import_brand_preview'], $_SESSION['import_brand_file'], $_SESSION['import_brand_tmp']);
    header('Location: import_brand.php');
    exit;
}

/* ================================================================
   CONFIRM
   ================================================================ */
if ($step === 'confirm') {
    $data = $_SESSION['import_brand_preview'] ?? null;
    if (!$data) { header('Location: import_brand.php'); exit; }

    global $conn;
    $conn->begin_transaction();
    try {
        db_query("TRUNCATE TABLE table_brand");
        $stmt = $conn->prepare("INSERT INTO table_brand (name_brand, group_brand, not_allow_brand) VALUES (?,?,?)");
        foreach ($data as $r) {
            $stmt->bind_param('sss', $r['name_brand'], $r['group_brand'], $r['not_allow_brand']);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();

        writeLog('import_brand', null, ['rows' => count($data)]);

        unset($_SESSION['import_brand_preview'], $_SESSION['import_brand_file'], $_SESSION['import_brand_tmp']);
        header('Location: import_brand.php?success=1&rows=' . count($data));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die('Import gagal: ' . $e->getMessage());
    }
}

/* ================================================================
   PREVIEW
   ================================================================ */
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) die('Upload gagal');
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') die('Format harus .xlsx');

$tmpDest = __DIR__ . '/../storage/tmp_brand_import.xlsx';
if (!is_dir(dirname($tmpDest))) mkdir(dirname($tmpDest), 0755, true);
move_uploaded_file($_FILES['file']['tmp_name'], $tmpDest);

$spreadsheet = IOFactory::load($tmpDest);
$rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

$headerRow = array_map(fn($v) => strtolower(trim((string)$v)), $rows[1]);
$required  = ['name_brand', 'group_brand'];
$map       = [];
foreach ($required as $col) {
    $idx = array_search($col, $headerRow, true);
    if ($idx === false) die("Kolom '$col' tidak ditemukan");
    $map[$col] = $idx;
}
$notAllowIdx = array_search('not_allow_brand', $headerRow, true);

$data = [];
for ($i = 2; $i <= count($rows); $i++) {
    $row  = $rows[$i];
    $name = trim((string)($row[$map['name_brand']] ?? ''));
    if (!$name) continue;
    if (!preg_match('/^[A-Za-z0-9\s\-\.,]{1,30}$/', $name)) die("name_brand tidak valid di baris $i: \"$name\"");
    $grp = trim((string)($row[$map['group_brand']] ?? ''));
    if (!preg_match('/^[A-Za-z]{1}$/', $grp)) die("group_brand tidak valid di baris $i");
    $na = '';
    if ($notAllowIdx !== false && !empty($row[$notAllowIdx])) {
        $na = trim((string)$row[$notAllowIdx]);
        if (!preg_match('/^[A-Za-z0-9\s\-\.,]+$/', $na)) die("not_allow_brand tidak valid di baris $i");
    }
    $data[] = ['name_brand' => $name, 'group_brand' => $grp, 'not_allow_brand' => $na];
}

$_SESSION['import_brand_preview'] = $data;
$_SESSION['import_brand_file']    = $_FILES['file']['name'];
header('Location: import_brand.php');
exit;
?>
