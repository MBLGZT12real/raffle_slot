<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../partials/auth_check.php';
require_once '../core/SettingModel.php';
require_once '../core/ResultModel.php';
require_once '../core/LogModel.php';
require_once '../core/BrandModel.php';

$allSettings  = getAllSettings();
$resultDates  = getResultDates();
$resultDatesSet = array_flip($resultDates);
$logs         = getRecentLogs(20);

// Hitung statistik
$totalDates    = count($allSettings);
$drawnDates    = count($resultDates);
$pendingDates  = $totalDates - $drawnDates;
$totalCollision = 0;

$dateStats = [];
foreach ($allSettings as $s) {
    $date = $s['date_slot'];
    $drawn = isset($resultDatesSet[$date]);
    $summary = $drawn ? getResultSummaryByDate($date) : [];
    $col = (int)($summary['collision_count'] ?? 0);
    $totalCollision += $col;
    $dateStats[] = [
        'date'       => $date,
        'drawn'      => $drawn,
        'total_slot' => $s['total_slot'],
        'collision'  => $col,
        'last_draw'  => $summary['last_draw_at'] ?? null,
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Dashboard — Undian Slot</title>
    <?php require_once '../partials/assets.php'; ?>
    <style>
        .stat-card {
            border-radius: 16px;
            padding: 22px 20px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .stat-card .stat-icon { font-size: 36px; opacity: .85; }
        .stat-card .stat-num  { font-size: 30px; font-weight: 800; line-height: 1; }
        .stat-card .stat-lbl  { font-size: 13px; opacity: .85; margin-top: 2px; }
        .card-total     { background: linear-gradient(135deg,#4d96ff,#1a6bcc); }
        .card-drawn     { background: linear-gradient(135deg,#6bcb77,#278a30); }
        .card-pending   { background: linear-gradient(135deg,#ffd93d,#c49200); }
        .card-collision { background: linear-gradient(135deg,#ff6b6b,#c0392b); }
        .status-drawn   { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .status-pending { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
    </style>
</head>
<body class="container mt-4">
    <?php require_once '../partials/header.php'; ?>
    <h4 class="mb-4"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-total">
                <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
                <div>
                    <div class="stat-num"><?= $totalDates ?></div>
                    <div class="stat-lbl">Total Tanggal</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-drawn">
                <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $drawnDates ?></div>
                    <div class="stat-lbl">Sudah Diundi</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-pending">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-num"><?= $pendingDates ?></div>
                    <div class="stat-lbl">Belum Diundi</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-collision">
                <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="stat-num"><?= $totalCollision ?></div>
                    <div class="stat-lbl">Total Collision</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL STATUS PER TANGGAL -->
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="bi bi-calendar-check me-1"></i> Status per Tanggal</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-secondary">
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-center">Total Slot</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Collision</th>
                        <th class="text-center">Terakhir Diundi</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dateStats as $s): ?>
                    <tr>
                        <td class="fw-bold"><?= $s['date'] ?></td>
                        <td class="text-center"><?= $s['total_slot'] ?></td>
                        <td class="text-center">
                            <?php if ($s['drawn']): ?>
                                <span class="badge status-drawn">✓ Sudah</span>
                            <?php else: ?>
                                <span class="badge status-pending">⏳ Belum</span>
                            <?php endif ?>
                        </td>
                        <td class="text-center">
                            <?php if ($s['drawn']): ?>
                                <?php if ($s['collision'] > 0): ?>
                                    <span class="badge bg-danger"><?= $s['collision'] ?> slot</span>
                                <?php else: ?>
                                    <span class="text-success fw-bold">✓ 0</span>
                                <?php endif ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td class="text-center text-muted" style="font-size:12px">
                            <?= $s['last_draw'] ? date('d/m H:i', strtotime($s['last_draw'])) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($s['drawn']): ?>
                                <a href="result.php" class="btn btn-outline-primary btn-sm py-0">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php else: ?>
                                <a href="index.php" class="btn btn-outline-success btn-sm py-0">
                                    <i class="bi bi-play-fill"></i>
                                </a>
                            <?php endif ?>
                        </td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LOG AKTIVITAS -->
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="bi bi-journal-text me-1"></i> Log Aktivitas Terbaru</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-secondary">
                    <tr>
                        <th>Aktivitas</th>
                        <th>Tanggal</th>
                        <th>Detail</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada log</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log):
                            $meta   = logMeta($log['action']);
                            $detail = $log['detail'] ? json_decode($log['detail'], true) : [];
                        ?>
                        <tr>
                            <td>
                                <i class="bi <?= $meta['icon'] ?> text-<?= $meta['color'] ?> me-1"></i>
                                <span class="badge bg-<?= $meta['color'] ?> bg-opacity-10 text-<?= $meta['color'] ?> border border-<?= $meta['color'] ?>-subtle">
                                    <?= $meta['label'] ?>
                                </span>
                            </td>
                            <td><?= $log['date_slot'] ?? '—' ?></td>
                            <td style="font-size:12px; color:#555">
                                <?php if (!empty($detail)):
                                    $parts = [];
                                    if (isset($detail['total_slot']))    $parts[] = $detail['total_slot'] . ' slot';
                                    if (isset($detail['collision']))     $parts[] = $detail['collision'] . ' collision';
                                    if (isset($detail['mode']))         $parts[] = 'mode: ' . $detail['mode'];
                                    if (isset($detail['rows']))         $parts[] = $detail['rows'] . ' baris';
                                    echo implode(' · ', $parts);
                                endif ?>
                            </td>
                            <td style="font-size:12px; white-space:nowrap">
                                <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php require_once '../partials/footer.php'; ?>
</body>
</html>
