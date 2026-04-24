<?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once '../partials/auth_check.php';
    require_once '../core/ResultModel.php';
    require_once '../core/BrandModel.php';

    $dates = getResultDates();
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Hasil Undian</title>
        <?php require_once '../partials/assets.php'; ?>
        <style>
            .number{
                text-align: right;
            }

            .badge{
                font-size: 14px;
            }
        </style>
    </head>
    <body class="container mt-4">
        <?php require_once '../partials/header.php'; ?>
        <h4 class="text-center">Hasil Undian</h4>

        <?php if (empty($dates)): ?>
            <div class="alert alert-warning">Belum ada hasil undian</div>
        <?php else: ?>

        <ul class="nav nav-tabs d-flex justify-content-center" role="tablist">
            <?php foreach ($dates as $i => $date): ?>
                <li class="nav-item bg-secondary-subtle border border-black rounded-top">
                    <button class="nav-link <?= $i === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab<?= $i ?>" type="button">
                        <?= $date ?>
                    </button>
                </li>
            <?php endforeach ?>
        </ul>

        <div class="tab-content mt-3">
            <?php foreach ($dates as $i => $date): ?>
                <?php 
                    $results    = getResultsByDate($date);
                    $groupRecap = getGroupBrandRecapByDate($date);

                    $groups = array_keys($groupRecap); // urutannya sudah ksort dari model
                ?>
                <div class="table-responsive-md tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="tab<?= $i ?>">
                    <div class="row">
                        <div class="col-md">
                            <h5><i class="bi bi-archive-fill"></i> Rekap Slot per Group Tanggal: <?= $date ?></h5>
                        </div>
                        <div class="col-md d-flex justify-content-end mb-2">
                            <?php if (!empty($results)): ?>
                                <a href="../export/export_result_excel.php?date=<?= urlencode($date) ?>" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
                                </a>
                            <?php endif ?>
                        </div>
                    </div>

                    <table id="tableRecap-<?= $i ?>" data-date="<?= $date ?>" class="table table-bordered table-striped table-hover table-sm">
                        <thead class="table-secondary">
                            <tr>
                                <th width="10%">Group</th>
                                <th>Distribusi Brand</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupRecap as $group => $brands): ?>
                                <tr>
                                    <th class="text-white text-center bg-<?= getGroupColorClass($group) ?>">
                                        <?= $group ?>
                                    </th>
                                    <td>
                                        <?php 
                                            $color = getGroupColorClass($group);
                                        ?>
                                        <?php foreach ($brands as $brand => $total): ?>
                                            <span class="badge bg-<?= $color ?> me-1 mb-1">
                                                <?= $brand ?> = <?= $total ?>
                                            </span>
                                        <?php endforeach ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                    
                    <table id="tableDetail-<?= $i ?>" data-date="<?= $date ?>" class="table table-bordered table-striped table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th width="10%" class="text-center">Slot</th>
                                <?php foreach ($groups as $g): ?>
                                    <th class="text-center text-white bg-<?= getGroupColorClass($g) ?>">
                                        Group <?= $g ?>
                                    </th>
                                <?php endforeach ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($results as $row): ?>
                            <?php 
                                $slotData = json_decode($row['slot_data'], true);
                            ?>
                            <tr>
                                <td class="fw-bold number">
                                    <?= $row['slot_number'] ?>
                                </td>

                                <?php foreach ($groups as $g): ?>
                                    <td class="text-center">
                                        <?php if (isset($slotData[$g])): ?>
                                            <span class="badge bg-<?= getGroupColorClass($g) ?>">
                                                <?= $slotData[$g] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif ?>
                                    </td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach ?>
        </div>

        <?php endif ?>
        
        <?php require_once '../partials/footer.php'; ?>
    </body>
</html>