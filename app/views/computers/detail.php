<?php require BASE_PATH . '/app/views/layouts/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/computers">Computers</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($computer['hostname']) ?></li>
    </ol>
</nav>

<!-- Computer Info Header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-pc-display me-2 text-primary"></i>
            <?= htmlspecialchars($computer['hostname']) ?>
        </h1>
        <p class="text-muted mb-0 small">
            MAC: <span class="font-monospace"><?= htmlspecialchars($computer['mac_address'] ?: 'N/A') ?></span>
            &bull; Added: <?= htmlspecialchars($computer['created_at']) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php
        $badges = ['online'=>'success','offline'=>'danger','unknown'=>'secondary'];
        $s = $computer['status'];
        echo '<span class="badge bg-' . ($badges[$s] ?? 'secondary') . ' fs-6 d-flex align-items-center gap-1">
            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>' . htmlspecialchars($s) . '</span>';
        ?>
        <?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sendCommandModal">
            <i class="bi bi-terminal me-1"></i>Send Command
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- System Info Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-info-circle me-1 text-primary"></i> System Info
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">IP Address</span>
                    <span class="font-monospace"><?= htmlspecialchars($computer['ip_address'] ?: 'N/A') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">OS</span>
                    <span><?= htmlspecialchars($computer['os_name']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">OS Version</span>
                    <span class="small"><?= htmlspecialchars($computer['os_version']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Architecture</span>
                    <span><?= htmlspecialchars($computer['os_arch']) ?></span>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-cpu me-1 text-warning"></i> Hardware
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <div class="text-muted small">CPU</div>
                    <div class="small"><?= htmlspecialchars($computer['cpu_info'] ?: 'N/A') ?></div>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">RAM</span>
                    <span><?= number_format((float)$computer['ram_gb'], 2) ?> GB</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Storage</span>
                    <span><?= number_format((float)$computer['storage_gb'], 2) ?> GB</span>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-clock me-1 text-success"></i> Connectivity
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Status</span>
                    <?php echo '<span class="badge bg-' . ($badges[$computer['status']] ?? 'secondary') . '">'
                        . htmlspecialchars($computer['status']) . '</span>'; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Last Check-in</span>
                    <span class="small"><?= $computer['last_checkin'] ? htmlspecialchars($computer['last_checkin']) : 'Never' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Last Updated</span>
                    <span class="small"><?= htmlspecialchars($computer['updated_at']) ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Software Inventory -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-box-seam me-1 text-info"></i> Software Inventory
            <span class="badge bg-secondary ms-1"><?= count($software) ?></span>
        </h6>
        <input type="text" id="swSearch" class="form-control form-control-sm w-auto" placeholder="Search software...">
    </div>
    <div class="card-body p-0">
        <?php if (empty($software)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-box-seam fs-3 d-block mb-2 opacity-25"></i>
            No software inventory available.
        </div>
        <?php else: ?>
        <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
            <table class="table table-sm table-hover mb-0" id="swTable">
                <thead class="table-light sticky-top">
                    <tr>
                        <th>Name</th>
                        <th>Version</th>
                        <th>Publisher</th>
                        <th>Install Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($software as $sw): ?>
                <tr>
                    <td><?= htmlspecialchars($sw['name']) ?></td>
                    <td class="font-monospace small"><?= htmlspecialchars($sw['version']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($sw['publisher']) ?></td>
                    <td class="text-muted small"><?= $sw['install_date'] ? htmlspecialchars($sw['install_date']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Command History -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-clock-history me-1 text-warning"></i> Command History
        </h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($commands)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-terminal fs-3 d-block mb-2 opacity-25"></i>
            No commands sent yet.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Scheduled</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($commands as $cmd): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$cmd['id'] ?></td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($cmd['command_type']) ?></span></td>
                    <td><?php
                        $sc = ['pending'=>'warning','sent'=>'info','completed'=>'success','failed'=>'danger','cancelled'=>'secondary'];
                        $st = $cmd['status'];
                        echo '<span class="badge bg-' . ($sc[$st] ?? 'secondary') . '">' . htmlspecialchars($st) . '</span>';
                    ?></td>
                    <td class="small"><?= htmlspecialchars($cmd['created_by_name'] ?? '-') ?></td>
                    <td class="small text-muted"><?= $cmd['scheduled_at'] ? htmlspecialchars($cmd['scheduled_at']) : '—' ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($cmd['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Send Command Modal -->
<?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
<div class="modal fade" id="sendCommandModal" tabindex="-1" aria-labelledby="sendCommandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="sendCommandModalLabel">
                    <i class="bi bi-terminal me-2"></i>Send Command to <?= htmlspecialchars($computer['hostname']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/commands">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrfToken()) ?>">
                    <input type="hidden" name="computer_id" value="<?= (int)$computer['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Command Type</label>
                        <select name="command_type" class="form-select" id="commandTypeSelect" required>
                            <option value="">— Select type —</option>
                            <option value="patch">patch – Run system updates</option>
                            <option value="install">install – Install package</option>
                            <option value="uninstall">uninstall – Uninstall package</option>
                            <option value="shell">shell – Run shell command</option>
                            <option value="restart">restart – Restart system</option>
                            <option value="shutdown">shutdown – Shutdown system</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Payload
                            <span class="text-muted fw-normal small">(JSON or plain text)</span>
                        </label>
                        <textarea name="payload" class="form-control font-monospace" rows="4"
                                  placeholder='{"package": "curl"} or plain text'></textarea>
                        <div class="form-text" id="payloadHelp">Enter command parameters as JSON or plain text.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Schedule At <span class="text-muted fw-normal small">(optional)</span></label>
                        <input type="datetime-local" name="scheduled_at" class="form-control">
                        <div class="form-text">Leave blank to execute immediately at next agent check-in.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Send Command
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Payload helper hints
const hints = {
    patch:     '{}',
    install:   '{"package": "curl"}',
    uninstall: '{"package": "curl"}',
    shell:     '{"command": "ls -la /"}',
    restart:   '{"delay": 0}',
    shutdown:  '{"delay": 0}',
};
document.getElementById('commandTypeSelect').addEventListener('change', function() {
    const h = hints[this.value] || '';
    document.querySelector('textarea[name="payload"]').placeholder = h;
    document.getElementById('payloadHelp').textContent = h ? 'Example: ' + h : 'Enter command parameters.';
});
</script>
<?php endif; ?>

<!-- Software search script -->
<script>
document.getElementById('swSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#swTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php require BASE_PATH . '/app/views/layouts/footer.php'; ?>
