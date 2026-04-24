<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once '../partials/auth_check_api.php';
require_once '../core/ResultModel.php';
require_once '../core/SettingModel.php';
require_once '../core/LogModel.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $date  = $input['date_slot'] ?? null;
    $mode  = $input['mode']      ?? 'strict';
    $slots = $input['result']    ?? null;

    if (!$date || !is_array($slots)) throw new Exception("Data undian tidak valid");

    $setting = getSettingByDate($date);
    if (!$setting) throw new Exception("Setting tanggal tidak ditemukan");

    $expectedTotal = (int)$setting['total_slot'];
    if (count($slots) !== $expectedTotal) throw new Exception("Jumlah slot tidak sesuai setting");

    deleteResultByDate($date);

    $collisionCount = 0;
    foreach ($slots as $i => $slot) {
        $isRelaxed = 0;
        $collision = null;

        if (isset($slot['__meta'])) {
            if (!empty($slot['__meta']['relaxed']))   $isRelaxed = 1;
            if (!empty($slot['__meta']['collision'])) {
                $collision = $slot['__meta']['collision'];
                $collisionCount += count($collision);
            }
            unset($slot['__meta']);
        }

        saveResult($date, $i + 1, $slot, $isRelaxed, $collision);
    }

    writeLog('draw_success', $date, [
        'total_slot' => $expectedTotal,
        'collision'  => $collisionCount,
        'mode'       => $mode,
    ]);

    echo json_encode(['status' => 'ok', 'date_slot' => $date, 'total_slot' => $expectedTotal], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    writeLog('draw_fail', $date ?? null, ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
