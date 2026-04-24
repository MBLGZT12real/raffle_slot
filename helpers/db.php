<?php 
    require_once __DIR__ . '/../config/database.php';
    date_default_timezone_set("Asia/Jakarta");

    function db_query($sql) { 
        global $conn;
        return $conn->query($sql);
    }

    function db_fetch($sql) {
        $res = db_query($sql);
        return mysqli_fetch_assoc($res);
    }

    function db_fetch_all($sql) { 
        $result = db_query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
?>