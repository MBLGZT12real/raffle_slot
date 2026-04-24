<?php 
    require_once __DIR__ . '/../helpers/db.php';

    /**
     * Ambil semua data
     */
    function getAllSettings() { 
        return db_fetch_all("SELECT * FROM table_setting ORDER BY date_slot");
    }

    /**
     * Ambil semua tanggal setting (untuk dropdown)
     */
    function getAllSettingDates() { 
        $rows = db_fetch_all("
            SELECT date_slot 
            FROM table_setting 
            ORDER BY date_slot ASC
        ");

        return array_column($rows, 'date_slot');
    }

    /**
     * Ambil setting berdasarkan tanggal
     */
    function getSettingByDate($date) {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM table_setting WHERE date_slot = ? LIMIT 1");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Debug logger
     */
    function debugLog($file,$message){
        $files  = __DIR__.'/../storage/'.$file;
        $time   = date('Y-m-d H:i:s');
        file_put_contents($files, "[$time] $message\n", FILE_APPEND);
    }
?>