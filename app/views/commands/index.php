<?php require BASE_PATH . '/app/views/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold"><i class="bi bi-terminal me-2 text-primary"></i>Commands</h1>
    <?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCommandModal">
        <i class="bi bi-plus-lg me-1"></i>New Command
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?php if ($_GET['success'] === 'cancelled'): ?>Command cancelled successfully.
    <?php else: ?>Command created successfully.<?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php
    $errs = ['csrf'=>'CSRF validation failed.','invalid'=>'Invalid input.','nocomputer'=>'Computer not found.'];
    echo htmlspecialchars($errs[$_GET['error']] ?? 'An error occurred.');
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="/commands" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Computer</label>
                <select name="computer_id" class="form-select form-select-sm">
                    <option value="">All Computers</option>
                    <?php foreach ($computers as $comp): ?>
                    <option value="<?= (int)$comp['id'] ?>"
                        <?= (int)($filters['computer_id'] ?? 0) === (int)$comp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($comp['hostname']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['pending','sent','completed','failed','cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>>
                        <?= ucfirst($st) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Type</label>
                <select name="command_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach (['patch','install','uninstall','shell','restart','shutdown'] as $ct): ?>
                    <option value="<?= $ct ?>" <?= ($filters['command_type'] ?? '') === $ct ? 'selected' : '' ?>>
                        <?= ucfirst($ct) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="/commands" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
            </div>
            <div class="col-md-3 text-end">
                <span class="text-muted small"><?= (int)$total ?> commands found</span>
            </div>
        </form>
    </div>
</div>

<!-- Commands Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($commands)): ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-terminal fs-1 d-block mb-3 opacity-25"></i>
            <h5>No commands found</h5>
            <p class="mb-0">Create a new command to get started.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Computer</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Scheduled</th>
                        <th>Created At</th>
                        <?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
                        <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($commands as $cmd): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$cmd['id'] ?></td>
                    <td>
                        <a href="/computers/<?= (int)$cmd['computer_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($cmd['hostname'] ?? 'Unknown') ?>
                        </a>
                    </td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($cmd['command_type']) ?></span></td>
                    <td><?php
                        $sc = ['pending'=>'warning','sent'=>'info','completed'=>'success','failed'=>'danger','cancelled'=>'secondary'];
                        $st = $cmd['status'];
                        echo '<span class="badge bg-' . ($sc[$st] ?? 'secondary') . '">' . htmlspecialchars($st) . '</span>';
                    ?></td>
                    <td class="small"><?= htmlspecialchars($cmd['created_by_name'] ?? '-') ?></td>
                    <td class="small text-muted"><?= $cmd['scheduled_at'] ? htmlspecialchars($cmd['scheduled_at']) : '—' ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($cmd['created_at']) ?></td>
                    <?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
                    <td class="text-end">
                        <?php if ($cmd['status'] === 'pending'): ?>
                        <form method="POST" action="/commands/<?= (int)$cmd['id'] ?>/cancel"
                              class="d-inline"
                              onsubmit="return confirm('Cancel this command?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrfToken()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center py-3">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- New Command Modal -->
<?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
<div class="modal fade" id="newCommandModal" tabindex="-1" aria-labelledby="newCommandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="newCommandModalLabel">
                    <i class="bi bi-terminal me-2"></i>New Command
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/commands">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrfToken()) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Computer <span class="text-danger">*</span></label>
                        <select name="computer_id" class="form-select" required>
                            <option value="">— Select computer —</option>
                            <?php foreach ($computers as $comp): ?>
                            <option value="<?= (int)$comp['id'] ?>">
                                <?= htmlspecialchars($comp['hostname']) ?>
                                (<?= htmlspecialchars($comp['ip_address']) ?>)
                                — <?= htmlspecialchars($comp['status']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Command Type <span class="text-danger">*</span></label>
                        <select name="command_type" class="form-select" id="modalCommandType" required>
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
                        <label class="form-label fw-semibold">Payload <span class="text-muted fw-normal small">(JSON or text)</span></label>
                        <textarea name="payload" class="form-control font-monospace" rows="3"
                                  id="modalPayload"
                                  placeholder='{"package": "curl"}'></textarea>
                        <div class="form-text" id="modalPayloadHelp">Optional payload for the command.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Schedule At <span class="text-muted fw-normal small">(optional)</span></label>
                        <input type="datetime-local" name="scheduled_at" class="form-control">
                        <div class="form-text">Leave blank to run at next agent check-in.</div>
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
const modalHints = {
    patch:     '{}',
    install:   '{"package": "curl"}',
    uninstall: '{"package": "curl"}',
    shell:     '{"command": "uptime"}',
    restart:   '{"delay": 0}',
    shutdown:  '{"delay": 0}',
};
document.getElementById('modalCommandType').addEventListener('change', function() {
    const hint = modalHints[this.value] || '';
    document.getElementById('modalPayload').placeholder = hint || '{}';
    document.getElementById('modalPayloadHelp').textContent = hint ? 'Example: ' + hint : 'Optional payload.';
});
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/views/layouts/footer.php'; ?>
