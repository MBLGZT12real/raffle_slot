<?php
    require_once __DIR__ . '/../helpers/db.php';

    /**
     * Ambil semua brand dikelompokkan per group_brand
     * return:
     * [
     *   'A' => [
     *      ['name_brand' => 'Fuso', 'group_brand' => 'A'],
     *      ...
     *   ],
     *   'B' => [...]
     * ]
     */
    function getAllBrandsByGroup() {
        $rows = db_fetch_all("
            SELECT name_brand, group_brand, priority_brand
            FROM table_brand
            ORDER BY group_brand ASC, name_brand ASC
        ");

        $result = [];
        foreach ($rows as $row) {
            $result[$row['group_brand']][] = $row;
        }

        return $result;
    }

    /**
     * Ambil aturan NOT_ALLOW (blacklist) per brand.
     *
     * not_allow_brand = CSV brand yang TIDAK BOLEH bertemu.
     * Kosong / null = boleh ketemu semua brand di group lain.
     * Aturan bersifat MUTUAL: cukup satu pihak yang tulis larangan,
     * pihak lain otomatis juga terlarang bertemu.
     *
     * return:
     * [
     *   'BAIC'   => ['Sokonindo', 'MG'],
     *   'Subaru' => ['Sokonindo', 'MG'],
     *   'XPENG'  => ['GWM', 'Audi - VW', 'Maxus'],
     *   'Fuso'   => [],   // boleh ketemu semua
     * ]
     */
    function getNotAllowRules() {
        $rows = db_fetch_all("
            SELECT name_brand, not_allow_brand
            FROM table_brand
        ");

        $rules = [];
        foreach ($rows as $row) {
            $brand    = trim($row['name_brand']);
            $notAllow = trim($row['not_allow_brand'] ?? '');

            if ($notAllow === '') {
                $rules[$brand] = [];
            } else {
                $list = array_map('trim', explode(',', $notAllow));
                $rules[$brand] = array_values(array_filter($list, fn($v) => $v !== ''));
            }
        }
        return $rules;
    }

    /**
     * Warna bootstrap per group
     */
    function getGroupColorClass($group) {
        $colors = [
            'A' => 'primary',
            'B' => 'success',
            'C' => 'danger',
            'D' => 'warning',
            'E' => 'info',
            'F' => 'secondary'
        ];

        if (!isset($colors[$group])) {
            $list = array_values($colors);
            return $list[ord($group) % count($list)];
        }

        return $colors[$group];
    }
?>
