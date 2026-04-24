<div class="d-flex align-items-center justify-content-between mb-2">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-dark me-2" data-bs-toggle="offcanvas" data-bs-target="#mainMenu">
            <i class="bi bi-list"></i>
        </button>
        <h5 class="m-0 fw-bold"><i class="bi bi-stars me-1 text-warning"></i> Undian Slot Acara</h5>
    </div>
    <a href="logout.php" class="btn btn-outline-secondary btn-sm" title="Keluar">
        <i class="bi bi-box-arrow-right"></i>
    </a>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mainMenu">
    <div class="offcanvas-header" style="background:linear-gradient(135deg,#1a1a2e,#16213e)">
        <span class="offcanvas-title text-white fw-bold">
            <i class="bi bi-stars me-1 text-warning"></i> Menu
        </span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-2">
        <ul class="list-group list-group-flush">
            <a href="index.php"        class="list-group-item list-group-item-action"><i class="bi bi-controller me-2 text-primary"></i> Undian</a>
            <a href="dashboard.php"    class="list-group-item list-group-item-action"><i class="bi bi-speedometer2 me-2 text-info"></i> Dashboard</a>
            <a href="result.php"       class="list-group-item list-group-item-action"><i class="bi bi-card-checklist me-2 text-success"></i> Hasil Undian</a>
            <a href="import_setting.php" class="list-group-item list-group-item-action"><i class="bi bi-database-fill-gear me-2 text-warning"></i> Setting</a>
            <a href="import_brand.php" class="list-group-item list-group-item-action"><i class="bi bi-building-fill-add me-2 text-danger"></i> Brand</a>
            <a href="logout.php"       class="list-group-item list-group-item-action text-secondary"><i class="bi bi-box-arrow-right me-2"></i> Keluar</a>
        </ul>
    </div>
</div>
