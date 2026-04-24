<?php
    require_once __DIR__ . '/../helpers/db.php';

    function deleteResultByDate(string $date): void {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM table_result WHERE date_slot = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $stmt->close();
    }

    function hasResultByDate(string $date): bool {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM table_result WHERE date_slot = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['n'] ?? 0) > 0;
    }

    function saveResult(string $date, int $slot, array $slotData, int $isRelaxed = 0, $collision = null): void {
        global $conn;
        $jsonSlot = json_encode($slotData, JSON_UNESCAPED_UNICODE);
        $jsonCol  = $collision ? json_encode($collision, JSON_UNESCAPED_UNICODE) : null;
        $now      = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("
            INSERT INTO table_result
                (date_slot, slot_number, slot_data, is_relaxed, collision_info, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sisiss', $date, $slot, $jsonSlot, $isRelaxed, $jsonCol, $now);
        $stmt->execute();
        $stmt->close();
    }

    function getResultDates(): array {
        $rows = db_fetch_all("SELECT DISTINCT date_slot FROM table_result ORDER BY date_slot ASC");
        return array_column($rows, 'date_slot');
    }

    function getResultsByDate(string $date): array {
        global $conn;
        $stmt = $conn->prepare("
            SELECT * FROM table_result WHERE date_slot = ? ORDER BY slot_number ASC
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    function getResultSummaryByDate(string $date): array {
        global $conn;
        $stmt = $conn->prepare("
            SELECT
                COUNT(*)                       AS total_slot,
                SUM(is_relaxed)                AS collision_count,
                MAX(created_at)                AS last_draw_at
            FROM table_result WHERE date_slot = ?
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?? [];
    }

    function getGroupBrandRecapByDate(string $date): array {
        $rows  = getResultsByDate($date);
        $recap = [];

        foreach ($rows as $row) {
            $slotData = json_decode($row['slot_data'], true);
            if (!is_array($slotData)) continue;
            foreach ($slotData as $group => $brand) {
                $recap[$group][$brand] = ($recap[$group][$brand] ?? 0) + 1;
            }
        }

        ksort($recap, SORT_STRING);
        foreach ($recap as $group => $brands) {
            ksort($recap[$group], SORT_STRING | SORT_FLAG_CASE);
        }

        return $recap;
    }

    function getLastSlotNumberByDate(string $date): int {
        global $conn;
        $stmt = $conn->prepare("
            SELECT MAX(slot_number) AS last_slot FROM table_result WHERE date_slot = ?
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['last_slot'] ?? 0);
    }
?>
