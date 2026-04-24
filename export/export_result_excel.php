<?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/../partials/auth_check.php';
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../core/ResultModel.php';
    require_once __DIR__ . '/../core/BrandModel.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Fill;

    /* =========================
    VALIDASI PARAM
    ========================= */
    $date = $_GET['date'] ?? null;
    if (!$date) { 
        die('Tanggal tidak valid');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { 
        die('Format tanggal tidak valid');
    }

    /* =========================
    AMBIL DATA
    ========================= */
    $results    = getResultsByDate($date);
    $groupRecap = getGroupBrandRecapByDate($date);
    $groups     = array_keys($groupRecap);

    /* =========================
    INIT SPREADSHEET
    ========================= */
    $spreadsheet = new Spreadsheet();

    /* ======================================================
    SHEET 1 — DETAIL SLOT
    ====================================================== */
    $sheetDetail = $spreadsheet->getActiveSheet();
    $sheetDetail->setTitle('Detail Slot');

    /* =========================
    HEADER (STYLE SAMA DENGAN SHEET 2)
    ========================= */
    $colIndexA = 1; // mulai dari kolom A

    $headers = array_merge(['Slot'], array_map(
        fn($g) => "Group $g",
        $groups
    ));

    foreach ($headers as $text) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndexA);

        $sheetDetail->setCellValue($colLetter.'1', $text);

        $sheetDetail->getStyle($colLetter.'1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E9ECEF']
            ]
        ]);

        $sheetDetail->getColumnDimension($colLetter)->setAutoSize(true);
        $colIndexA++;
    }

    /* Data */
    $rowNum = 2;
    foreach ($results as $row) {
        $slotData = json_decode($row['slot_data'], true);

        $col = 'A';
        $sheetDetail->setCellValue($col++.$rowNum, $row['slot_number']);

        foreach ($groups as $g) {
            $sheetDetail->setCellValue(
                $col++.$rowNum,
                $slotData[$g] ?? '-'
            );
        }
        $rowNum++;
    }

    /* Auto width */
    foreach (range('A', $sheetDetail->getHighestColumn()) as $c) {
        $sheetDetail->getColumnDimension($c)->setAutoSize(true);
    }

    /* ======================================================
    SHEET 2 — REKAP DISTRIBUSI BRAND (HORIZONTAL)
    ====================================================== */
    $sheetRecap = $spreadsheet->createSheet();
    $sheetRecap->setTitle('Rekap Brand');

    /* =========================
    HEADER (GROUP)
    ========================= */
    $colIndexB = 1; // mulai dari kolom A
    foreach ($groupRecap as $group => $brands) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndexB);

        $sheetRecap->setCellValue($colLetter.'1', "Group $group");

        $sheetRecap->getStyle($colLetter.'1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E9ECEF']
            ]
        ]);

        $colIndexB++;
    }

    /* =========================
    DATA (VERTICAL PER GROUP)
    ========================= */
    $maxRow = 1;
    $colIndexB = 1;

    foreach ($groupRecap as $group => $brands) {
        $rowNum = 2;
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndexB);

        foreach ($brands as $brand => $total) {
            $sheetRecap->setCellValue(
                $colLetter.$rowNum,
                "{$brand} = {$total}"
            );
            $rowNum++;
        }

        $maxRow = max($maxRow, $rowNum);
        $sheetRecap->getColumnDimension($colLetter)->setAutoSize(true);
        $colIndexB++;
    }

    /* ======================================================
    OUTPUT
    ====================================================== */
    $filename = "Hasil_Undian_{$date}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
?>