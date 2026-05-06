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
        return reorderSpread($slots, $groupKeys);
    }

    /* ===================================================================
     * VALIDASI
     * =================================================================== */
    function validateSlotCount(array $groups, int $min, int $max, int $totalSlot): void {
        foreach ($groups as $g => $brands) {
            $count = count($brands);
            if ($totalSlot < $count * $min)
                throw new Exception("Group $g: total slot < minimum ({$count}×{$min})");
            if ($totalSlot > $count * $max)
                throw new Exception("Group $g: total slot > maksimum ({$count}×{$max})");
        }
    }

    /* ===================================================================
     * QUOTA & POOL
     * =================================================================== */
    function buildQuota(array $brands, int $min, int $max, int $totalSlot): array {
        $priority = array_values(array_filter($brands, fn($b) => !empty($b['priority_brand'])));
        $regular  = array_values(array_filter($brands, fn($b) =>  empty($b['priority_brand'])));

        // Semua brand mulai dari min
        $quota = [];
        foreach ($brands as $b) $quota[$b['name_brand']] = $min;

        $remaining = $totalSlot - count($brands) * $min;

        // Pass 1 — priority brand dijamin +1 di atas min (bukan langsung max).
        // Diacak agar tidak selalu brand yang sama yang dapat jika ekstra kurang.
        shuffle($priority);
        foreach ($priority as $b) {
            if ($remaining <= 0) break;
            $quota[$b['name_brand']]++;
            $remaining--;
        }

        // Pass 2 — sisa ekstra: regular brand dapat giliran pertama,
        // baru priority boleh naik lagi jika masih ada sisa.
        // $extraOrder di-reshuffle tiap iterasi agar tidak ada brand
        // yang selalu mendapat giliran terdepan ketika max-min > 1.
        $prev = -1;
        while ($remaining > 0 && $remaining !== $prev) {
            $prev = $remaining;
            shuffle($regular);
            shuffle($priority);
            $extraOrder = array_merge($regular, $priority); // regular dulu
            foreach ($extraOrder as $b) {
                if ($remaining <= 0) break;
                $name = $b['name_brand'];
                if ($quota[$name] < $max) {
                    $quota[$name]++;
                    $remaining--;
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
                            $chosen = !empty($workingPools[$g]) ? reset($workingPools[$g]) : null;
                            if ($chosen === null) continue;
                            foreach ($slot as $existing) {
                                if (!canMeet($chosen, $existing, $rules)) {
                                    $collision[] = ['group' => $g, 'brand' => $chosen, 'conflict' => $existing, 'slot' => $i + 1];
                                }
                            }
                            if ($chosen !== null) $slot[$g] = $chosen;
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
     * REORDER SPREAD
     *
     * Susun ulang urutan slot agar setiap brand tersebar merata di
     * seluruh posisi slot, bukan menumpuk di awal/tengah/akhir.
     *
     * Strategi: "most-stale first" greedy.
     * Di setiap posisi p, pilih slot yang brand-brandnya paling lama
     * tidak muncul (gap terbesar sejak penampilan terakhir). Brand yang
     * belum pernah muncul mendapat bonus gap = n sehingga diprioritaskan
     * di posisi awal.
     *
     * Contoh: brand kuota 3 di 16 slot → idealnya muncul di ~1, 6, 11
     * bukan 1, 2, 3.
     *
     * Algoritma ini sekaligus menangani consecutive (gap ≥ 2 berarti
     * tidak berturutan), sehingga menggantikan reorderNonConsecutive.
     *
     * $maxAttempts random restart digunakan untuk keluar dari local
     * optimum — tiap restart mengacak urutan pool awal.
     * =================================================================== */
    function reorderSpread(array $slots, array $groups, int $maxAttempts = 50): array {
        $n = count($slots);
        if ($n <= 1) return $slots;

        $bestSlots = $slots;
        $bestScore = -1;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $pool    = $slots;
            if ($attempt > 0) shuffle($pool);

            $arranged = [];
            $lastPos  = []; // [g][brand] => indeks terakhir dalam $arranged

            for ($pos = 0; $pos < $n; $pos++) {
                $bestIdx   = 0;
                $bestLocal = PHP_INT_MIN;

                foreach ($pool as $idx => $candidate) {
                    $score = 0;
                    foreach ($groups as $g) {
                        $brand  = $candidate[$g] ?? null;
                        if ($brand === null) continue;
                        // gap sejak muncul terakhir; belum pernah muncul = bonus n
                        $score += $pos - ($lastPos[$g][$brand] ?? -$n);
                    }
                    if ($score > $bestLocal) {
                        $bestLocal = $score;
                        $bestIdx   = $idx;
                    }
                }

                $picked     = $pool[$bestIdx];
                $arranged[] = $picked;
                array_splice($pool, $bestIdx, 1);

                foreach ($groups as $g) {
                    $brand = $picked[$g] ?? null;
                    if ($brand !== null) $lastPos[$g][$brand] = $pos;
                }
            }

            $score = spreadScore($arranged, $groups, $n);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSlots = $arranged;
            }
        }

        return $bestSlots;
    }

    /* Skor kualitas sebaran: jumlah gap-minimum antar kemunculan
     * brand yang sama. Semakin besar = semakin merata. */
    function spreadScore(array $slots, array $groups, int $n): int {
        $positions = [];
        foreach ($slots as $i => $slot) {
            foreach ($groups as $g) {
                $brand = $slot[$g] ?? null;
                if ($brand !== null) $positions[$g][$brand][] = $i;
            }
        }

        $score = 0;
        foreach ($positions as $brands) {
            foreach ($brands as $indices) {
                if (count($indices) < 2) {
                    $score += $n; // brand muncul sekali → bonus penuh
                    continue;
                }
                $minGap = PHP_INT_MAX;
                for ($i = 1; $i < count($indices); $i++) {
                    $minGap = min($minGap, $indices[$i] - $indices[$i - 1]);
                }
                $score += $minGap;
            }
        }

        return $score;
    }
?>
