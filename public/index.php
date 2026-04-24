<?php 
    require_once '../core/SettingModel.php';
    require_once '../core/BrandModel.php';

    $dates = getAllSettingDates();
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Undian Slot</title>
        <?php require_once '../partials/assets.php'; ?>
    </head>

    <body class="container mt-4">
        <?php require_once '../partials/header.php'; ?>

        <!-- SELECT TANGGAL -->
        <div class="row align-items-center mt-2">
            <div class="col-md-5 mt-4">
                <select id="dateSelect" class="form-control form-select">
                    <option value="">-- Pilih Tanggal --</option>
                    <?php foreach ($dates as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <!-- ACTION BUTTON -->
            <div class="row col-md-7 mt-4">
                <div class="col-md-9">
                    <button id="btnShuffle" class="btn btn-primary" title="Shortcut: Space" disabled><i class="bi bi-play-fill me-2"></i> Shuffle</button>
                    <button id="btnStop" class="btn btn-danger" title="Shortcut: Space" disabled><i class="bi bi-stop-fill me-2"></i> Stop</button>
                    <button id="btnReset" class="btn btn-warning" title="Shortcut: R" disabled><i class="bi bi-arrow-repeat me-2"></i> Reset</button>
                </div>
                
                <!-- STRICT / RELAX TOGGLE -->
                <div class="col-md-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="relaxToggle" checked>
                    <label class="form-check-label fw-bold" for="relaxToggle">
                        Relax Mode
                    </label>
                </div>
            </div>
        </div>

        <!-- GRID -->
        <div id="gridContainer" class="table-responsive-md mt-4"></div>
        
        <!-- LOADING OVERLAY -->
        <div id="loadingOverlay" class="loading-overlay d-none">
            <img src="../assets/images/loading.gif" alt="Loading...">
        </div>
        
        <audio id="soundSpin" loop>
            <source src="../assets/sounds/slot.wav" type="audio/wav">
        </audio>
        <audio id="soundStop">
            <source src="../assets/sounds/win.wav" type="audio/wav">
        </audio>
        
        <script>
            let FINAL_RESULT = [];
            let GROUPS = [];
            let INTERVALS = {};
            let loadingStart = 0;

            const spinSound = document.getElementById('soundSpin');
            const stopSound = document.getElementById('soundStop');
            const MIN_LOADING_TIME = 600; // ms (bebas, 400–800 enak)

            /* ======================
            MODE
            ====================== */
            function getDrawMode() {
                return document.getElementById('relaxToggle').checked ? 'relax' : 'strict';
            }
            
            /* ======================
            LOADING ANIMASI
            ====================== */
            function showLoading() {
                loadingStart = Date.now();

                const overlay = document.getElementById('loadingOverlay');
                overlay.classList.remove('d-none');
                overlay.classList.add('fade-in');
                
                document.querySelectorAll('#dateSelect, #btnShuffle, #btnStop, #btnReset, #relaxToggle').forEach(el => el.disabled = true);
            }

            function hideLoading() {
                const elapsed = Date.now() - loadingStart;
                const remaining = Math.max(MIN_LOADING_TIME - elapsed, 0);
                
                setTimeout(() => {
                    const overlay = document.getElementById('loadingOverlay');

                    overlay.classList.remove('fade-in');
                    overlay.classList.add('fade-out');

                    setTimeout(() => {
                        overlay.classList.add('d-none');
                        overlay.classList.remove('fade-out');

                        document.querySelectorAll('select').forEach(el => el.disabled = false);
                    }, 250); // waktu animasi fade-out
                }, remaining);
            }
            
            /* ======================
            LOAD GRID
            ====================== */
            document.getElementById('dateSelect').onchange = function () {
                const date = this.value;
                if (!date) return;
                
                showLoading();

                fetch('prepare_draw.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        date_slot: date,
                        mode: (getDrawMode())
                    })
                })
                .then(res => res.json())
                .then(res => {
                    hideLoading();

                    if (res.status !== 'ok') {
                        alert(res.message);
                        return;
                    }

                    FINAL_RESULT = res.result;
                    GROUPS = res.groups;

                    renderGrid(res.grid, GROUPS);

                    document.getElementById('btnShuffle').disabled = false;
                    document.getElementById('btnStop').disabled = true;
                    document.getElementById('btnReset').disabled = false;
                    document.getElementById('relaxToggle').disabled = false;
                })
                .catch(() => {
                    hideLoading();
                    alert('Gagal load data');
                });
            };

            /* ======================
            RENDER GRID
            ====================== */
            function renderGrid(grid, groups) {
                let html = `<table class="table table-bordered table-striped table-hover table-sm">
                    <thead>
                    <tr>
                        <th width="80">Slot</th>`;

                groups.forEach(g => {
                    html += `<th class="text-white bg-${getColor(g)}">Group ${g}</th>`;
                });

                html += `</tr></thead><tbody>`;

                grid.forEach(row => {
                    html += `<tr><td class="fw-bold text-end">${row.slot_number}</td>`;
                    groups.forEach(g => {
                        html += `
                            <td class="text-center slot-cell"
                                id="cell-${row.slot_number}-${g}">
                                —
                            </td>`;
                    });
                    html += `</tr>`;
                });

                html += `</tbody></table>`;
                document.getElementById('gridContainer').innerHTML = html;
            }

            /* ======================
            SHUFFLE
            ====================== */
            document.getElementById('btnShuffle').onclick = () => {
                document.getElementById('btnShuffle').disabled = true;
                document.getElementById('btnStop').disabled = false;
                document.getElementById('btnReset').disabled = true;

                spinSound.play();

                FINAL_RESULT.forEach((slot, i) => {
                    GROUPS.forEach(g => {
                        const cell = document.getElementById(`cell-${i+1}-${g}`);
                        INTERVALS[`${i}-${g}`] = setInterval(() => {
                            cell.classList.add('spinning');
                            cell.innerText = randomFrom(FINAL_RESULT, g);
                        }, 80);
                    });
                });
            };

            /* ======================
            STOP
            ====================== */
            document.getElementById('btnStop').onclick = stopWithEasing;

            function stopWithEasing() {
                document.getElementById('btnStop').disabled = true;

                spinSound.pause();
                spinSound.currentTime = 0;

                let delay = 0;
                const STEP = 200;

                FINAL_RESULT.forEach((slot, i) => {
                    setTimeout(() => {

                        GROUPS.forEach(g => {
                            const key = `${i}-${g}`;
                            if (INTERVALS[key]) {
                                clearInterval(INTERVALS[key]);
                                delete INTERVALS[key];
                            }

                            const cell = document.getElementById(`cell-${i+1}-${g}`);
                            cell.classList.remove('spinning');
                            cell.innerText = slot[g];

                            // ✅ FINAL RELAX FLAG
                            if (slot.__meta && slot.__meta.relaxed) {
                                cell.classList.add('relaxed');
                            }
                        });

                        stopSound.currentTime = 0;
                        stopSound.play();

                        if (i === FINAL_RESULT.length - 1) {
                            saveResult();
                        }

                    }, delay);
                    delay += STEP;
                });
            }

            /* ======================
            SAVE
            ====================== */
            function saveResult() {
                fetch('save_draw.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        date_slot: document.getElementById('dateSelect').value,
                        mode: getDrawMode(),
                        result: FINAL_RESULT
                    })
                })
                .then(() => {
                    document.getElementById('btnShuffle').disabled = true;
                    document.getElementById('btnStop').disabled = true;
                    document.getElementById('btnReset').disabled = false;
                });
            }

            /* ======================
            UTILS
            ====================== */
            function randomFrom(data, group) {
                const pool = data.map(s => s[group]);
                return pool[Math.floor(Math.random() * pool.length)];
            }

            function getColor(group) {
                const map = {
                    A: 'primary',
                    B: 'success',
                    C: 'danger',
                    D: 'warning',
                    E: 'info',
                    F: 'secondary'
                };
                return map[group] ?? 'dark';
            }
            
            /* RESET BUTTON */
            document.getElementById('btnReset').onclick = doReset;
            function doReset() {
                // stop interval
                Object.values(INTERVALS).forEach(clearInterval);
                INTERVALS = {};

                // stop sound
                spinSound.pause();
                spinSound.currentTime = 0;

                // reset data
                FINAL_RESULT = [];
                GROUPS = [];

                // clear grid
                document.getElementById('gridContainer').innerHTML = '';

                // reset UI
                document.getElementById('btnShuffle').disabled = true;
                document.getElementById('btnStop').disabled = true;
                document.getElementById('btnReset').disabled = true;
                document.getElementById('dateSelect').value = '';

                // optional
                // document.getElementById('relaxToggle').checked = false;
            }


            /* SPACE SHORTCUT */
            document.addEventListener('keydown', e => {
                // abaikan kalau lagi ngetik di input / select
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                    return;
                }

                // SPACE → Shuffle / Stop
                if (e.code === 'Space') {
                    e.preventDefault();

                    const shuffleBtn = document.getElementById('btnShuffle');
                    const stopBtn = document.getElementById('btnStop');

                    if (!shuffleBtn.disabled) shuffleBtn.click();
                    else if (!stopBtn.disabled) stopBtn.click();
                    return;
                }

                // R → RESET
                if (e.key === 'r' || e.key === 'R') {
                    const resetBtn = document.getElementById('btnReset');
                    if (!resetBtn.disabled) {
                        e.preventDefault();
                        resetBtn.click();
                    }
                }
                
                /*if (e.code !== 'Space') return;
                e.preventDefault();

                const shuffleBtn = document.getElementById('btnShuffle');
                const stopBtn = document.getElementById('btnStop');

                if (!shuffleBtn.disabled) shuffleBtn.click();
                else if (!stopBtn.disabled) stopBtn.click();*/
            });

            document.getElementById('relaxToggle').addEventListener('change', () => {
                const date = document.getElementById('dateSelect').value;
                if (!date) return;

                showLoading();
                document.getElementById('dateSelect').dispatchEvent(new Event('change'));
            });
        </script>

        <?php require_once '../partials/footer.php'; ?>
    </body>
</html>