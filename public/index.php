<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../partials/auth_check.php';
require_once '../core/SettingModel.php';
require_once '../core/BrandModel.php';

$dates = getAllSettingDates();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Undian Slot</title>
    <?php require_once '../partials/assets.php'; ?>
</head>
<body class="container mt-4">
    <?php require_once '../partials/header.php'; ?>

    <!-- CONTROLS ROW -->
    <div class="row align-items-end mt-2 g-2">
        <div class="col-md-4">
            <label class="form-label text-muted small mb-1">Pilih Tanggal Undian</label>
            <select id="dateSelect" class="form-control form-select">
                <option value="">-- Pilih Tanggal --</option>
                <?php foreach ($dates as $d): ?>
                    <option value="<?= $d ?>"><?= $d ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-5 d-flex align-items-center gap-2 flex-wrap">
            <button id="btnShuffle" class="btn btn-primary px-4" disabled title="Space">
                <i class="bi bi-play-fill me-1"></i> Shuffle
            </button>
            <button id="btnStop" class="btn btn-danger px-4" disabled title="Space">
                <i class="bi bi-stop-fill me-1"></i> Stop
            </button>
            <button id="btnReset" class="btn btn-outline-secondary" disabled title="R">
                <i class="bi bi-arrow-repeat"></i>
            </button>
        </div>

        <div class="col-md-3 d-flex align-items-center justify-content-end gap-3">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="relaxToggle" checked>
                <label class="form-check-label fw-semibold small" for="relaxToggle">Relax Mode</label>
            </div>
        </div>
    </div>

    <!-- DRAW ARENA -->
    <div class="draw-arena mt-3">
        <!-- status bar -->
        <div class="draw-status-bar">
            <span id="statusBadge" class="status-badge status-idle">
                <i class="bi bi-circle"></i> Pilih Tanggal
            </span>
            <span id="collisionBadge" class="d-none badge bg-warning text-dark ms-1">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Ada Slot Relax
            </span>
        </div>

        <div id="gridContainer" class="table-responsive-md"></div>
    </div>

    <!-- LOADING OVERLAY -->
    <div id="loadingOverlay" class="loading-overlay d-none">
        <img src="../assets/images/loading.gif" alt="Loading...">
        <p id="loadingText">Memproses undian...</p>
    </div>

    <audio id="soundSpin" loop>
        <source src="../assets/sounds/slot.wav" type="audio/wav">
    </audio>
    <audio id="soundWin">
        <source src="../assets/sounds/win.wav" type="audio/wav">
    </audio>

    <script>
    /* ============================================================
       STATE
       ============================================================ */
    let FINAL_RESULT = [];
    let GROUPS       = [];
    let INTERVALS    = {};
    let IS_SPINNING  = false;
    let loadingStart = 0;

    const spinSound = document.getElementById('soundSpin');
    const winSound  = document.getElementById('soundWin');

    const SUFFIX = { A: 'a', B: 'b', C: 'c', D: 'd', E: 'e', F: 'f' };
    const GROUP_BG = {
        A: '#1d4ed8', B: '#15803d', C: '#b91c1c',
        D: '#c2410c', E: '#7e22ce', F: '#334155'
    };
    const MIN_LOADING = 700;

    /* ============================================================
       HELPERS
       ============================================================ */
    function getMode() { return document.getElementById('relaxToggle').checked ? 'relax' : 'strict'; }

    function setStatus(type, text) {
        const el = document.getElementById('statusBadge');
        el.className = 'status-badge status-' + type;
        el.innerHTML = {
            idle:    '<i class="bi bi-circle"></i> ' + text,
            ready:   '<i class="bi bi-check-circle-fill"></i> ' + text,
            drawing: '<i class="bi bi-broadcast"></i> ' + text,
            done:    '<i class="bi bi-trophy-fill"></i> ' + text,
        }[type] || text;
    }

    function getSuffix(group) { return SUFFIX[group] || 'a'; }
    function getGlowClass(group) { return 'slot-glow-' + getSuffix(group); }
    function getBadgeClass(group) { return 'slot-badge slot-badge-' + getSuffix(group); }
    function getGroupBg(group) { return GROUP_BG[group] || '#1d4ed8'; }

    function randomFrom(data, group) {
        const pool = data.map(s => s[group]).filter(Boolean);
        return pool[Math.floor(Math.random() * pool.length)] ?? '—';
    }

    /* ============================================================
       LOADING
       ============================================================ */
    function showLoading(text) {
        loadingStart = Date.now();
        document.getElementById('loadingText').textContent = text || 'Memproses undian...';
        const ov = document.getElementById('loadingOverlay');
        ov.classList.remove('d-none', 'fade-out');
        ov.classList.add('fade-in');
        document.querySelectorAll('#dateSelect,#btnShuffle,#btnStop,#btnReset,#relaxToggle')
            .forEach(el => el.disabled = true);
    }

    function hideLoading() {
        const elapsed   = Date.now() - loadingStart;
        const remaining = Math.max(MIN_LOADING - elapsed, 0);
        setTimeout(() => {
            const ov = document.getElementById('loadingOverlay');
            ov.classList.remove('fade-in');
            ov.classList.add('fade-out');
            setTimeout(() => {
                ov.classList.add('d-none');
                ov.classList.remove('fade-out');
                document.querySelectorAll('select').forEach(el => el.disabled = false);
            }, 250);
        }, remaining);
    }

    /* ============================================================
       KONFIRMASI OVERWRITE
       ============================================================ */
    async function checkExistingResult(date) {
        try {
            const res = await fetch('check_result.php?date=' + encodeURIComponent(date));
            const data = await res.json();
            return data.exists === true;
        } catch { return false; }
    }

    /* ============================================================
       LOAD GRID (pilih tanggal)
       ============================================================ */
    document.getElementById('dateSelect').addEventListener('change', async function () {
        const date = this.value;
        if (!date) return;

        // Cek apakah hasil sudah ada
        const exists = await checkExistingResult(date);
        if (exists) {
            const ok = confirm(
                '⚠️ Tanggal ' + date + ' sudah pernah diundi.\n\n' +
                'Menjalankan undian baru akan MENGGANTIKAN hasil sebelumnya.\n\n' +
                'Lanjutkan?'
            );
            if (!ok) {
                this.value = '';
                return;
            }
        }

        loadGrid(date);
    });

    function loadGrid(date) {
        showLoading('Membuat undian...');
        setStatus('idle', 'Memuat...');

        fetch('prepare_draw.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date_slot: date, mode: getMode() })
        })
        .then(r => r.json())
        .then(res => {
            hideLoading();
            if (res.status !== 'ok') { alert('❌ ' + res.message); setStatus('idle', 'Error'); return; }

            FINAL_RESULT = res.result;
            GROUPS       = res.groups;

            renderGrid(res.grid, GROUPS);
            setStatus('ready', 'Siap · ' + res.total_slot + ' slot · Mode ' + res.mode.toUpperCase());

            document.getElementById('btnShuffle').disabled = false;
            document.getElementById('btnStop').disabled    = true;
            document.getElementById('btnReset').disabled   = false;
            document.getElementById('relaxToggle').disabled = false;
        })
        .catch(() => { hideLoading(); alert('Gagal load data'); setStatus('idle', 'Error'); });
    }

    /* ============================================================
       RENDER GRID
       ============================================================ */
    function renderGrid(grid, groups) {
        let html = `<table class="table table-sm mb-0">
            <thead><tr>
                <th class="slot-num-hd text-center" style="width:48px">#</th>`;

        groups.forEach(g => {
            const bg = getGroupBg(g);
            html += `<th class="text-white text-center"
                         style="background:${bg};letter-spacing:1px;font-size:11px;text-transform:uppercase">
                         <i class="bi bi-layers-fill me-1 opacity-75"></i>Group ${g}
                     </th>`;
        });

        html += `</tr></thead><tbody>`;

        grid.forEach(row => {
            html += `<tr><td class="slot-num text-center fw-bold">${row.slot_number}</td>`;
            groups.forEach(g => {
                html += `<td class="text-center slot-cell" id="cell-${row.slot_number}-${g}">·</td>`;
            });
            html += `</tr>`;
        });

        html += `</tbody></table>`;
        document.getElementById('gridContainer').innerHTML = html;
    }

    /* ============================================================
       SHUFFLE
       ============================================================ */
    document.getElementById('btnShuffle').addEventListener('click', () => {
        IS_SPINNING = true;
        document.getElementById('btnShuffle').disabled = true;
        document.getElementById('btnShuffle').classList.remove('pulsing');
        document.getElementById('btnStop').disabled    = false;
        document.getElementById('btnReset').disabled   = true;

        spinSound.play().catch(() => {});
        setStatus('drawing', 'Mengundi...');

        FINAL_RESULT.forEach((slot, i) => {
            GROUPS.forEach(g => {
                const cell = document.getElementById(`cell-${i + 1}-${g}`);
                INTERVALS[`${i}-${g}`] = setInterval(() => {
                    cell.classList.add('spinning');
                    cell.textContent = randomFrom(FINAL_RESULT, g); // gold text via .spinning CSS
                }, 80);
            });
        });
    });

    /* ============================================================
       STOP with easing
       ============================================================ */
    document.getElementById('btnStop').addEventListener('click', stopWithEasing);

    function stopWithEasing() {
        IS_SPINNING = false;
        document.getElementById('btnStop').disabled = true;
        spinSound.pause();
        spinSound.currentTime = 0;

        let delay = 0;
        const STEP = 180;
        let hasCollision = false;

        FINAL_RESULT.forEach((slot, i) => {
            setTimeout(() => {
                GROUPS.forEach(g => {
                    const key  = `${i}-${g}`;
                    const cell = document.getElementById(`cell-${i + 1}-${g}`);
                    if (INTERVALS[key]) { clearInterval(INTERVALS[key]); delete INTERVALS[key]; }

                    cell.classList.remove('spinning');
                    const brand = slot[g] ?? '—';
                    cell.innerHTML = `<span class="${getBadgeClass(g)}">${brand}</span>`;

                    // reveal + glow animation
                    cell.classList.remove('slot-reveal', getGlowClass(g));
                    void cell.offsetWidth; // reflow
                    cell.classList.add('slot-reveal', getGlowClass(g));

                    if (slot.__meta && slot.__meta.relaxed && slot.__meta.collision && slot.__meta.collision.length > 0) {
                        cell.classList.add('relaxed');
                        hasCollision = true;
                    }
                });

                winSound.currentTime = 0;
                winSound.play().catch(() => {});

                // Selesai semua slot
                if (i === FINAL_RESULT.length - 1) {
                    setTimeout(() => {
                        launchConfetti();
                        setStatus('done', 'Selesai!');
                        if (hasCollision) document.getElementById('collisionBadge').classList.remove('d-none');
                        document.getElementById('btnShuffle').disabled = true;
                        document.getElementById('btnReset').disabled   = false;
                        saveResult();
                    }, 400);
                }
            }, delay);
            delay += STEP;
        });
    }

    /* ============================================================
       CONFETTI 🎉
       ============================================================ */
    function launchConfetti() {
        const COLORS = ['#ff6b6b','#ffd93d','#6bcb77','#4d96ff','#c77dff','#ff9a3c','#fff'];
        const total  = 130;
        for (let i = 0; i < total; i++) {
            setTimeout(() => {
                const el   = document.createElement('div');
                const size = Math.random() * 11 + 5;
                const dur  = (Math.random() * 2 + 2).toFixed(2);
                el.className = 'confetti-piece';
                el.style.cssText = [
                    'left:'              + (Math.random() * 100) + 'vw',
                    'width:'             + size + 'px',
                    'height:'            + (Math.random() > .45 ? size : size * .45) + 'px',
                    'background:'        + COLORS[Math.floor(Math.random() * COLORS.length)],
                    'border-radius:'     + (Math.random() > .5 ? '50%' : '3px'),
                    'animation-duration:'+ dur + 's',
                    'animation-delay:'   + (Math.random() * .6).toFixed(2) + 's',
                    'transform:rotate('  + Math.floor(Math.random() * 360) + 'deg)',
                ].join(';');
                document.body.appendChild(el);
                setTimeout(() => el.remove(), (parseFloat(dur) + 1) * 1000);
            }, i * 18);
        }
    }

    /* ============================================================
       SAVE
       ============================================================ */
    function saveResult() {
        fetch('save_draw.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date_slot: document.getElementById('dateSelect').value,
                mode:      getMode(),
                result:    FINAL_RESULT
            })
        });
    }

    /* ============================================================
       RESET
       ============================================================ */
    document.getElementById('btnReset').addEventListener('click', doReset);
    function doReset() {
        Object.values(INTERVALS).forEach(clearInterval);
        INTERVALS = {};
        IS_SPINNING = false;
        spinSound.pause(); spinSound.currentTime = 0;
        FINAL_RESULT = []; GROUPS = [];
        document.getElementById('gridContainer').innerHTML = '';
        document.getElementById('collisionBadge').classList.add('d-none');
        setStatus('idle', 'Pilih Tanggal');
        document.getElementById('btnShuffle').disabled = true;
        document.getElementById('btnStop').disabled    = true;
        document.getElementById('btnReset').disabled   = true;
        document.getElementById('dateSelect').value    = '';
    }

    /* ============================================================
       RELAX TOGGLE
       ============================================================ */
    document.getElementById('relaxToggle').addEventListener('change', () => {
        const date = document.getElementById('dateSelect').value;
        if (!date) return;
        loadGrid(date);
    });

    /* ============================================================
       KEYBOARD SHORTCUTS
       ============================================================ */
    document.addEventListener('keydown', e => {
        if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;

        if (e.code === 'Space') {
            e.preventDefault();
            const s = document.getElementById('btnShuffle');
            const t = document.getElementById('btnStop');
            if (!s.disabled) s.click();
            else if (!t.disabled) t.click();
        }

        if (e.key === 'r' || e.key === 'R') {
            e.preventDefault();
            const r = document.getElementById('btnReset');
            if (!r.disabled) r.click();
        }
    });
    </script>

    <?php require_once '../partials/footer.php'; ?>
</body>
</html>
