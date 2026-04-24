<?php
    require_once __DIR__ . '/SettingModel.php';
    require_once __DIR__ . '/BrandModel.php';

    /**
     * Generate slot undian berdasarkan tanggal.
     *
     * return:
     * [
     *   0 => ['A' => 'Fuso', 'B' => 'Hyundai', 'C' => 'GWM'],
     *   1 => ['A' => 'Isuzu', 'B' => 'KIA',    'C' => 'Changan', '__meta' => [...]], // relax mode
     * ]
     */
    function generateSlotsByDate($date, $isRelax = false) {
        // 1. Ambil setting
        $setting = getSettingByDate($date);
        if (!$setting) {
            throw new Exception("Setting tanggal $date tidak ditemukan");
        }

        $totalSlot = (int)$setting['total_slot'];
        $min       = (int)$setting['min_slot'];
        $max       = (int)$setting['max_slot'];

        // 2. Ambil brand per group
        $groups = getAllBrandsByGroup();
        if (empty($groups)) {
            throw new Exception("Data brand kosong");
        }

        // 3. Validasi slot min / max
        validateSlotCount($groups, $min, $max, $totalSlot);

        // 4. Build pool per group
        $pools = [];
        foreach ($groups as $group => $brands) {
            $quota        = buildQuota($brands, $min, $max, $totalSlot);
            $pools[$group] = expandPool($quota);
        }

        // 5. Not-allow rules (blacklist)
        $rules = getNotAllowRules();

        // 6. Pairing multi-group
        $slots = buildMultiGroupSlots($pools, $rules, $totalSlot, $isRelax);

        // 7. Reorder agar tidak ada brand yang sama berurutan
        $groupKeys = array_values(array_filter(
            array_keys($slots[0]),
            fn($k) => $k !== '__meta'
        ));
        return reorderNonConsecutive($slots, $groupKeys);
    }

    /* ===================================================================
     * VALIDASI
     * =================================================================== */

    function validateSlotCount($groups, $min, $max, $totalSlot) {
        foreach ($groups as $g => $brands) {
            $count = count($brands);

            if ($totalSlot < $count * $min) {
                throw new Exception("Group $g: total slot kurang dari minimum ({$count} brand × min={$min})");
            }

            if ($totalSlot > $count * $max) {
                throw new Exception("Group $g: total slot melebihi maksimum ({$count} brand × max={$max})");
            }
        }
    }

    /* ===================================================================
     * QUOTA & POOL
     * =================================================================== */

    function buildQuota(array $brands, $min, $max, $totalSlot) {
        $quota = [];
        foreach ($brands as $b) {
            $quota[$b['name_brand']] = $min;
        }

        $current = count($brands) * $min;

        while ($current < $totalSlot) {
            shuffle($brands);
            foreach ($brands as $b) {
                $name = $b['name_brand'];
                if ($quota[$name] < $max) {
                    $quota[$name]++;
                    $current++;
                    if ($current >= $totalSlot) break;
                }
            }
        }
        return $quota;
    }

    function expandPool(array $quota) {
        $pool = [];
        foreach ($quota as $brand => $count) {
            for ($i = 0; $i < $count; $i++) {
                $pool[] = $brand;
            }
        }
        shuffle($pool);
        return $pool;
    }

    /* ===================================================================
     * ATURAN PERTEMUAN — BLACKLIST (not_allow)
     *
     * canMeet() mengembalikan TRUE jika dua brand BOLEH berada di
     * slot yang sama.
     *
     * Logika MUTUAL: jika salah satu pihak mencantumkan pihak lain
     * di not_allow_brand, keduanya tidak boleh bertemu.
     * Kosong/null → boleh ketemu siapa saja.
     * =================================================================== */

    function canMeet($a, $b, $rules) {
        // A melarang B
        if (!empty($rules[$a]) && in_array($b, $rules[$a], true)) return false;
        // B melarang A (mutual)
        if (!empty($rules[$b]) && in_array($a, $rules[$b], true)) return false;
        return true;
    }

    /* ===================================================================
     * PAIRING MULTI-GROUP (STRICT / RELAX)
     * =================================================================== */

    function buildMultiGroupSlots(array $pools, array $rules, $totalSlot, $isRelax, $maxTry = 2000) {
        $RELAX_AFTER_TRY = 300;

        // Heuristic: group dengan pool lebih kecil diproses dulu
        $groups = array_keys($pools);
        usort($groups, fn($a, $b) => count($pools[$a]) <=> count($pools[$b]));

        for ($try = 0; $try < $maxTry; $try++) {
            $workingPools = [];
            foreach ($pools as $g => $p) {
                $workingPools[$g] = $p;
                shuffle($workingPools[$g]);
            }

            $slots  = [];
            $failed = false;

            for ($i = 0; $i < $totalSlot; $i++) {
                $slot = [];
                if ($isRelax) {
                    $slot['__meta'] = ['relaxed' => true, 'collision' => []];
                }

                foreach ($groups as $g) {
                    $found = false;
                    foreach ($workingPools[$g] as $k => $candidate) {
                        $ok = true;
                        foreach ($slot as $key => $existing) {
                            if ($key === '__meta') continue;

                            if (!canMeet($candidate, $existing, $rules)) {
                                if ($isRelax && $try >= $RELAX_AFTER_TRY) {
                                    // Catat collision tapi tetap lanjut
                                    $slot['__meta']['collision'][] = [
                                        'group'    => $g,
                                        'brand'    => $candidate,
                                        'conflict' => $existing,
                                        'slot'     => $i + 1
                                    ];
                                    continue;
                                }
                                $ok = false;
                                break;
                            }
                        }

                        if ($ok) {
                            $slot[$g] = $candidate;
                            unset($workingPools[$g][$k]);
                            $workingPools[$g] = array_values($workingPools[$g]);
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $failed = true;
                        break;
                    }
                }

                if ($failed) break;

                if (!$isRelax && isset($slot['__meta'])) {
                    unset($slot['__meta']);
                }

                $slots[] = $slot;
            }

            if (!$failed) return $slots;
        }

        throw new Exception("Undian gagal setelah $maxTry percobaan. Aturan not_allow terlalu ketat atau total_slot tidak sesuai.");
    }

    /* ===================================================================
     * REORDER NON-KONSEKUTIF
     *
     * Mengatur ulang urutan slot agar tidak ada brand yang sama muncul
     * di dua slot berurutan dalam group yang sama.
     * Menggunakan greedy + random restart (max $maxAttempts kali).
     * =================================================================== */

    function reorderNonConsecutive(array $slots, array $groups, int $maxAttempts = 300): array {
        $n = count($slots);
        if ($n <= 1) return $slots;

        $bestSlots     = $slots;
        $bestConflicts = countConsecutiveConflicts($slots, $groups);

        if ($bestConflicts === 0) return $slots;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $remaining = $slots;
            shuffle($remaining);
            $arranged = [];

            while (!empty($remaining)) {
                $last   = !empty($arranged) ? end($arranged) : null;
                $placed = false;

                foreach ($remaining as $idx => $candidate) {
                    if ($last === null || !hasConsecutiveConflict($last, $candidate, $groups)) {
                        $arranged[] = $candidate;
                        array_splice($remaining, $idx, 1);
                        $placed = true;
                        break;
                    }
                }

                // Tidak ada kandidat bebas konflik → ambil yang pertama (minimum damage)
                if (!$placed) {
                    $arranged[] = array_shift($remaining);
                }
            }

            $conflicts = countConsecutiveConflicts($arranged, $groups);
            if ($conflicts < $bestConflicts) {
                $bestConflicts = $conflicts;
                $bestSlots     = $arranged;
            }

            if ($bestConflicts === 0) break;
        }

        return $bestSlots;
    }

    /**
     * Cek apakah dua slot berurutan memiliki brand yang sama di group manapun.
     */
    function hasConsecutiveConflict(array $prev, array $next, array $groups): bool {
        foreach ($groups as $g) {
            if (isset($prev[$g]) && isset($next[$g]) && $prev[$g] === $next[$g]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hitung total pasangan berurutan yang memiliki brand sama.
     */
    function countConsecutiveConflicts(array $slots, array $groups): int {
        $count = 0;
        $n     = count($slots);
        for ($i = 1; $i < $n; $i++) {
            if (hasConsecutiveConflict($slots[$i - 1], $slots[$i], $groups)) {
                $count++;
            }
        }
        return $count;
    }
?>
