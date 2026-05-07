<?php require BASE_PATH . '/app/views/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
    <span class="text-muted small" id="lastRefreshed">Last refreshed: now</span>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4" id="statsRow">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                    <i class="bi bi-pc-display fs-3 text-primary"></i>
                </div>
                <div>
                    <p class="text-muted mb-0 small">Total Computers</p>
                    <h2 class="mb-0 fw-bold" id="statTotal"><?= (int)$stats['total'] ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 rounded-3 p-3 me-3">
                    <i class="bi bi-wifi fs-3 text-success"></i>
                </div>
                <div>
                    <p class="text-muted mb-0 small">Online</p>
                    <h2 class="mb-0 fw-bold text-success" id="statOnline"><?= (int)$stats['online'] ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-danger bg-opacity-10 rounded-3 p-3 me-3">
                    <i class="bi bi-wifi-off fs-3 text-danger"></i>
                </div>
                <div>
                    <p class="text-muted mb-0 small">Offline</p>
                    <h2 class="mb-0 fw-bold text-danger" id="statOffline"><?= (int)$stats['offline'] ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                    <i class="bi bi-hourglass-split fs-3 text-warning"></i>
                </div>
                <div>
                    <p class="text-muted mb-0 small">Pending Commands</p>
                    <h2 class="mb-0 fw-bold text-warning" id="statPending"><?= (int)$pendingCount ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Online/Offline Progress Bar -->
<?php $total = max(1, (int)$stats['total']); ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-line me-1"></i> Status Overview</h6>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-success"><i class="bi bi-circle-fill me-1"></i>Online</span>
            <span class="text-muted"><?= (int)$stats['online'] ?> / <?= (int)$stats['total'] ?></span>
        </div>
        <div class="progress mb-3" style="height:12px;" id="progressBar">
            <div class="progress-bar bg-success" role="progressbar"
                 style="width: <?= round($stats['online'] / $total * 100) ?>%"
                 aria-valuenow="<?= (int)$stats['online'] ?>"
                 aria-valuemin="0" aria-valuemax="<?= (int)$stats['total'] ?>">
            </div>
            <div class="progress-bar bg-danger" role="progressbar"
                 style="width: <?= round($stats['offline'] / $total * 100) ?>%"
                 aria-valuenow="<?= (int)$stats['offline'] ?>"
                 aria-valuemin="0" aria-valuemax="<?= (int)$stats['total'] ?>">
            </div>
            <div class="progress-bar bg-secondary" role="progressbar"
                 style="width: <?= round($stats['unknown'] / $total * 100) ?>%"
                 aria-valuenow="<?= (int)$stats['unknown'] ?>"
                 aria-valuemin="0" aria-valuemax="<?= (int)$stats['total'] ?>">
            </div>
        </div>
        <div class="d-flex gap-3 small">
            <span class="badge bg-success"><?= (int)$stats['online'] ?> Online</span>
            <span class="badge bg-danger"><?= (int)$stats['offline'] ?> Offline</span>
            <span class="badge bg-secondary"><?= (int)$stats['unknown'] ?> Unknown</span>
        </div>
    </div>
</div>

<!-- Recent Commands -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1"></i> Recent Commands</h6>
        <a href="/commands" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentCommands)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            No commands yet.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Computer</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentCommands as $cmd): ?>
                <tr>
                    <td>
                        <a href="/computers/<?= (int)$cmd['computer_id'] ?>">
                            <?= htmlspecialchars($cmd['hostname'] ?? 'Unknown') ?>
                        </a>
                    </td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($cmd['command_type']) ?></span></td>
                    <td><?php
                        $sc = ['pending'=>'warning','sent'=>'info','completed'=>'success','failed'=>'danger','cancelled'=>'secondary'];
                        $s  = $cmd['status'];
                        echo '<span class="badge bg-' . ($sc[$s] ?? 'secondary') . '">' . htmlspecialchars($s) . '</span>';
                    ?></td>
                    <td><?= htmlspecialchars($cmd['created_by_name'] ?? '-') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($cmd['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/views/layouts/footer.php'; ?>
