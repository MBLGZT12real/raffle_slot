<?php 
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    header('Content-Type: application/json');

    require_once '../core/ResultModel.php';
    require_once '../core/SettingModel.php';

    try {
        // 1️⃣ Ambil input JSON
        $input = json_decode(file_get_contents('php://input'), true);

        $date  = $input['date_slot'] ?? null;
        $slots = $input['result'] ?? null;

        if (!$date || !is_array($slots)) {
            throw new Exception("Data undian tidak valid");
        }

        // 2️⃣ Validasi setting
        $setting = getSettingByDate($date);
        if (!$setting) {
            throw new Exception("Setting tanggal tidak ditemukan");
        }

        $expectedTotal = (int)$setting['total_slot'];
        if (count($slots) !== $expectedTotal) {
            throw new Exception("Jumlah slot tidak sesuai setting");
        }

        // 3️⃣ Hapus data lama (1 tanggal = 1 hasil)
        deleteResultByDate($date);

        // 4️⃣ Simpan PER SLOT
        foreach ($slots as $i => $slot) {
            // default
            $isRelaxed = 0;
            $collision = null;

            // ambil meta jika ada
            if (isset($slot['__meta'])) {
                if (!empty($slot['__meta']['relaxed'])) {
                    $isRelaxed = 1;
                }

                if (!empty($slot['__meta']['collision'])) {
                    $collision = $slot['__meta']['collision'];
                }

                // ⛔ jangan simpan meta ke slot_data
                unset($slot['__meta']);
            }

            // simpan ke DB
            saveResult(
                $date,
                $i + 1,     // slot_number
                $slot,      // slot_data (JSON)
                $isRelaxed, // is_relaxed
                $collision  // collision_info (JSON / null)
            );
        }

        // 5️⃣ Response
        echo json_encode([
            'status'     => 'ok',
            'date_slot'  => $date,
            'total_slot' => $expectedTotal
        ], JSON_UNESCAPED_UNICODE);
        
        //debugLog("save_debug.log", print_r($slots, true));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
?>