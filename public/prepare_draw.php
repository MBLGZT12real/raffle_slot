<?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    header('Content-Type: application/json');

    require_once '../partials/auth_check_api.php';
    require_once '../core/DrawService.php';
    require_once '../core/SettingModel.php';
    require_once '../core/BrandModel.php';
    require_once '../core/LogModel.php';

    /**
     * PHP 7 SAFE helper
     */
    if (!function_exists('startsWith')) {
        function startsWith($haystack, $needle) {
            return substr($haystack, 0, strlen($needle)) === $needle;
        }
    }

    try {
        /* =========================
        * 1️⃣ Ambil input JSON
        * ========================= */
        $input = json_decode(file_get_contents('php://input'), true);

        $date      = $input['date_slot'] ?? null;
        $relaxMode = ($input['mode'] ?? 'strict') === 'relax'; // true = relax, false = strict

        if (!$date) {
            throw new Exception("Tanggal slot wajib dipilih");
        }

        /* =========================
        * 2️⃣ Ambil setting
        * ========================= */
        $setting = getSettingByDate($date);
        if (!$setting) {
            throw new Exception("Setting tanggal tidak ditemukan");
        }
        $totalSlot = (int)$setting['total_slot'];
        $min       = (int)$setting['min_slot'];
        $max       = (int)$setting['max_slot'];

        /* =========================
        * 2.5 Cek priority terdegradasi
        * Jika slot ekstra tidak cukup untuk semua brand prioritas dapat
        * max_slot, catat di log (tanpa error/alert ke frontend).
        * ========================= */
        $allBrands = getAllBrandsByGroup();
        foreach ($allBrands as $g => $brands) {
            $priCount  = count(array_filter($brands, fn($b) => !empty($b['priority_brand'])));
            if ($priCount === 0) continue;
            $minNeeded = $priCount * $max + (count($brands) - $priCount) * $min;
            if ($totalSlot < $minNeeded) {
                $extra = $totalSlot - count($brands) * $min;
                writeLog('priority_degraded', $date, [
                    'group'          => $g,
                    'priority_count' => $priCount,
                    'total_slot'     => $totalSlot,
                    'extra_slots'    => max(0, $extra),
                    'note'           => "Hanya {$extra} slot ekstra tersedia, tidak cukup untuk semua {$priCount} brand prioritas group {$g} mendapat max ({$max})",
                ]);
            }
        }

        /* =========================
        * 3️⃣ Generate slot FINAL
        * ========================= */
        $slots = generateSlotsByDate($date, $relaxMode);
        if (count($slots) !== $totalSlot) {
            throw new Exception("Jumlah slot tidak sesuai setting");
        }

        /* =========================
        * 4️⃣ Ambil daftar GROUP
        * (skip __meta)
        * ========================= */
        $groups = array_keys($slots[0]);
        $groups = array_values(array_filter($groups, function ($g) {
            return !startsWith($g, '_');
        }));

        /* =========================
        * 5️⃣ Bentuk GRID KOSONG
        * ========================= */
        $grid = [];
        for ($i = 0; $i < $totalSlot; $i++) {
            $row = [
                'slot_number' => $i + 1
            ];

            foreach ($groups as $g) {
                $row[$g] = null;
            }
            $grid[] = $row;
        }

        /* =========================
        * 6️⃣ Response ke Frontend
        * ========================= */
        echo json_encode([
            'status'     => 'ok',
            'date_slot'  => $date,
            'mode'       => $relaxMode ? 'relax' : 'strict',
            'groups'     => $groups,
            'total_slot' => $totalSlot,
            'grid'       => $grid,
            'result'     => $slots // ⛔ hasil FINAL (untuk shuffle → stop)
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
?>