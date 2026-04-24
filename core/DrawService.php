<?php
    require_once __DIR__ . '/SettingModel.php';
    require_once __DIR__ . '/BrandModel.php';

    function generateSlotsByDate(string $date, bool $isRelax = false): array {
        $setting = getSettingByDate($date);
        if (!$setting) throw new Exception("Setting tanggal $date tidak ditemukan");

        $totalSlot = (int)$setting['total_slot'];
        $min       = (int)$setting['min_slot'];
        $max       = (int)$setting['max_slot'];

        $groups = getAllBrandsByGroup();
        if (empty($groups)) throw new Exception("Data brand kosong");

        validateSlotCount($groups, $min, $max, $totalSlot);

        $pools = [];
        foreach ($groups as $group => $brands) {
            $quota        = buildQuota($brands, $min, $max, $totalSlot);
            $pools[$group] = expandPool($quota);
        }

        $rules = getNotAllowRules();
        $slots = buildMultiGroupSlots($pools, $rules, $totalSlot, $isRelax);

        $groupKeys = array_values(array_filter(array_keys($slots[0]), fn($k) => $k !== '__meta'));
        return reorderNonConsecutive($slots, $groupKeys);
    }

    /* ===================================================================
     * VALIDASI
     * =================================================================== */
    function validateSlotCount(array $groups, int $min, int $max, int $totalSlot): void {
        foreach ($groups as $g => $brands) {
            $count = count($brands);
            if ($totalSlot < $count * $min) throw new Exception("Group $g: total slot < minimum ({$count}×{$min})");
            if ($totalSlot > $count * $max) throw new Exception("Group $g: total slot > maksimum ({$count}×{$max})");
        }
    }

    /* ===================================================================
     * QUOTA & POOL
     * =================================================================== */
    function buildQuota(array $brands, int $min, int $max, int $totalSlot): array {
        $quota   = [];
        foreach ($brands as $b) $quota[$b['name_brand']] = $min;
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

    function expandPool(array $quota): array {
        $pool = [];
        foreach ($quota as $brand => $count) {
            for ($i = 0; $i < $count; $i++) $pool[] = $brand;
        }
        shuffle($pool);
        return $pool;
    }

    /* ===================================================================
     * ATURAN PERTEMUAN — BLACKLIST MUTUAL
     * canMeet() → true = boleh satu slot
     * Jika salah satu pihak mencantumkan lawan di not_allow → false
     * =================================================================== */
    function canMeet(string $a, string $b, array $rules): bool {
        if (!empty($rules[$a]) && in_array($b, $rules[$a], true)) return false;
        if (!empty($rules[$b]) && in_array($a, $rules[$b], true)) return false;
        return true;
    }

    /* ===================================================================
     * BACKTRACKING — pasang satu slot dengan semua group
     *
     * Berbeda dari greedy: jika pilihan brand di group X menyebabkan
     * kegagalan di group berikutnya, algoritma mundur dan mencoba
     * kandidat lain di group X (tidak langsung restart seluruh undian).
     *
     * $pools dilewatkan by-value sehingga backtrack otomatis terjadi
     * tanpa perlu restore manual.
     * =================================================================== */
    function buildSlotBacktrack(array $groups, int $gIdx, array $currentSlot, array $pools, array $rules): ?array {
        if ($gIdx >= count($groups)) return $currentSlot;

        $g = $groups[$gIdx];

        foreach ($pools[$g] as $k => $candidate) {
            $ok = true;
            foreach ($currentSlot as $existingBrand) {
                if (!canMeet($candidate, $existingBrand, $rules)) { $ok = false; break; }
            }

            if ($ok) {
                $newSlot       = $currentSlot;
                $newSlot[$g]   = $candidate;
                $newPools      = $pools;
                array_splice($newPools[$g], $k, 1);

                $result = buildSlotBacktrack($groups, $gIdx + 1, $newSlot, $newPools, $rules);
                if ($result !== null) return $result;
                // kandidat ini jalan buntu → coba kandidat berikutnya (backtrack otomatis)
            }
        }

        return null; // tidak ada kombinasi valid untuk group ini
    }

    /* ===================================================================
     * PAIRING MULTI-GROUP
     * Menggunakan backtracking per slot.
     * Retry tetap ada untuk menangani kegagalan antar-slot
     * (pool yang terlalu homogen setelah beberapa slot terisi).
     * Jumlah retry jauh lebih sedikit dibanding greedy murni.
     * =================================================================== */
    function buildMultiGroupSlots(array $pools, array $rules, int $totalSlot, bool $isRelax, int $maxTry = 500): array {
        $RELAX_AFTER = 50;

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
                $slot = buildSlotBacktrack($groups, 0, [], $workingPools, $rules);

                if ($slot === null) {
                    // Backtracking gagal untuk slot ini
                    if ($isRelax && $try >= $RELAX_AFTER) {
                        // Ambil brand pertama yang tersedia, log collision
                        $slot      = [];
                        $collision = [];
                        foreach ($groups as $g) {
                            $chosen = reset($workingPools[$g]);
                            foreach ($slot as $existing) {
                                if (!canMeet($chosen, $existing, $rules)) {
                                    $collision[] = ['group' => $g, 'brand' => $chosen, 'conflict' => $existing, 'slot' => $i + 1];
                                }
                            }
                            $slot[$g] = $chosen;
                        }
                        $slot['__meta'] = ['relaxed' => true, 'collision' => $collision];
                    } else {
                        $failed = true;
                        break;
                    }
                } elseif ($isRelax) {
                    $slot['__meta'] = ['relaxed' => true, 'collision' => []];
                }

                // Keluarkan brand yang terpakai dari workingPools
                foreach ($groups as $g) {
                    if (!isset($slot[$g])) continue;
                    $idx = array_search($slot[$g], $workingPools[$g]);
                    if ($idx !== false) array_splice($workingPools[$g], $idx, 1);
                }

                if (!$isRelax) unset($slot['__meta']);
                $slots[] = $slot;
            }

            if (!$failed) return $slots;
        }

        throw new Exception("Undian gagal setelah $maxTry percobaan. Periksa aturan not_allow atau total_slot.");
    }

    /* ===================================================================
     * REORDER NON-KONSEKUTIF
     * Susun ulang urutan slot agar tidak ada brand yang sama di dua
     * slot berurutan pada group yang sama (greedy + random restart).
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

                if (!$placed) $arranged[] = array_shift($remaining);
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

    function hasConsecutiveConflict(array $prev, array $next, array $groups): bool {
        foreach ($groups as $g) {
            if (isset($prev[$g], $next[$g]) && $prev[$g] === $next[$g]) return true;
        }
        return false;
    }

    function countConsecutiveConflicts(array $slots, array $groups): int {
        $count = 0;
        $n     = count($slots);
        for ($i = 1; $i < $n; $i++) {
            if (hasConsecutiveConflict($slots[$i - 1], $slots[$i], $groups)) $count++;
        }
        return $count;
    }
?>
