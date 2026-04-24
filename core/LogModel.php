<?php
    require_once __DIR__ . '/../helpers/db.php';

    /**
     * Catat aktivitas ke table_log.
     *
     * @param string      $action    draw_success | draw_fail | import_brand | import_setting | delete_result
     * @param string|null $dateSlot  Tanggal terkait (nullable)
     * @param array       $detail    Data tambahan (akan di-encode ke JSON)
     */
    function writeLog(string $action, ?string $dateSlot = null, array $detail = []): void {
        global $conn;
        $detailJson = !empty($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $conn->prepare("
            INSERT INTO table_log (action, date_slot, detail)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('sss', $action, $dateSlot, $detailJson);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Ambil log terbaru, limit N baris.
     */
    function getRecentLogs(int $limit = 30): array {
        global $conn;
        $stmt = $conn->prepare("
            SELECT * FROM table_log
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Label & ikon untuk tiap action.
     */
    function logMeta(string $action): array {
        $map = [
            'draw_success'   => ['label' => 'Undian Berhasil',  'icon' => 'bi-trophy-fill',        'color' => 'success'],
            'draw_fail'      => ['label' => 'Undian Gagal',     'icon' => 'bi-exclamation-octagon', 'color' => 'danger'],
            'import_brand'   => ['label' => 'Import Brand',     'icon' => 'bi-building-fill-add',   'color' => 'primary'],
            'import_setting' => ['label' => 'Import Setting',   'icon' => 'bi-database-fill-gear',  'color' => 'info'],
            'delete_result'  => ['label' => 'Hapus Hasil',      'icon' => 'bi-trash-fill',          'color' => 'warning'],
        ];
        return $map[$action] ?? ['label' => $action, 'icon' => 'bi-info-circle', 'color' => 'secondary'];
    }
?>
