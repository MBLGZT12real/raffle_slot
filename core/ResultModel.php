<?php 
    require_once __DIR__ . '/../helpers/db.php';

    function deleteResultByDate($date) { 
        db_query("DELETE FROM table_result WHERE date_slot = '$date'");
    }

    function saveResult($date, $slot, array $slotData, $isRelaxed = 0, $collision = null) {
        $jsonSlot = json_encode($slotData, JSON_UNESCAPED_UNICODE);
        $jsonCol  = $collision ? json_encode($collision, JSON_UNESCAPED_UNICODE) : null;
        $now      = date('Y-m-d H:i:s');

        db_query("
            INSERT INTO table_result
            (date_slot, slot_number, slot_data, is_relaxed, collision_info, created_at)
            VALUES
            ('$date', $slot, '$jsonSlot', $isRelaxed, ".($jsonCol ? "'$jsonCol'" : "NULL").", '$now')
        ");
    }


    /**
     * Ambil semua tanggal result yang ada
     */
    function getResultDates() {
        $rows = db_fetch_all("
            SELECT DISTINCT date_slot
            FROM table_result
            ORDER BY date_slot ASC
        ");
        return array_column($rows, 'date_slot');
    }

    /**
     * Ambil result per tanggal
     */
    function getResultsByDate($date) {
        return db_fetch_all("
            SELECT *
            FROM table_result
            WHERE date_slot = '$date'
            ORDER BY slot_number ASC
        ");
    }

    /**
     * Rekap slot per group, brand=total (alphabetical brand)
     */
    function getGroupBrandRecapByDate($date) {
        $rows = getResultsByDate($date);

        $recap = [];
        foreach ($rows as $row) {
            $slotData = json_decode($row['slot_data'], true);

            foreach ($slotData as $group => $brand) {
                if (!isset($recap[$group][$brand])) {
                    $recap[$group][$brand] = 0;
                }
                $recap[$group][$brand]++;
            }
        }

        // urutkan group (A, B, C...)
        ksort($recap, SORT_STRING);

        // urutkan brand di tiap group secara alphabetical
        foreach ($recap as $group => $brands) {
            ksort($recap[$group], SORT_STRING | SORT_FLAG_CASE);
        }

        return $recap;
    }

    /**
     * Ambil slot terakhir pada tanggal tertentu
     */
    function getLastSlotNumberByDate($date) {
        $row = db_fetch("
            SELECT MAX(slot_number) AS last_slot
            FROM table_result
            WHERE date_slot = '$date'
        ");

        return (int)($row['last_slot'] ?? 0);
    }
?>