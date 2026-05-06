<?php require BASE_PATH . '/app/views/layouts/header.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold"><i class="bi bi-laptop me-2 text-primary"></i>Computers</h1>
    <span class="badge bg-secondary fs-6"><?= (int)$total ?> total</span>
</div>

<!-- Search / Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="/computers" id="searchForm" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input
                        type="text"
                        name="search"
                        id="searchInput"
                        class="form-control"
                        placeholder="Hostname, IP, or MAC..."
                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                    >
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" id="statusFilter" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="online"  <?= ($filters['status'] ?? '') === 'online'  ? 'selected' : '' ?>>Online</option>
                    <option value="offline" <?= ($filters['status'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline</option>
                    <option value="unknown" <?= ($filters['status'] ?? '') === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="/computers" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Computers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($computers)): ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-laptop fs-1 d-block mb-3 opacity-25"></i>
            <h5>No computers found</h5>
            <p class="mb-0">Try adjusting your search or deploy the agent on a machine.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hostname</th>
                        <th>IP Address</th>
                        <th>OS</th>
                        <th>CPU</th>
                        <th>RAM (GB)</th>
                        <th>Status</th>
                        <th>Last Check-in</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($computers as $c): ?>
                <tr>
                    <td class="fw-semibold">
                        <i class="bi bi-pc-display me-1 text-muted"></i>
                        <a href="/computers/<?= (int)$c['id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($c['hostname']) ?>
                        </a>
                    </td>
                    <td class="font-monospace small"><?= htmlspecialchars($c['ip_address']) ?></td>
                    <td>
                        <span class="small"><?= htmlspecialchars($c['os_name']) ?></span><br>
                        <span class="text-muted small"><?= htmlspecialchars($c['os_version']) ?></span>
                    </td>
                    <td class="small text-truncate" style="max-width:150px;"
                        title="<?= htmlspecialchars($c['cpu_info']) ?>">
                        <?= htmlspecialchars($c['cpu_info']) ?>
                    </td>
                    <td><?= number_format((float)$c['ram_gb'], 1) ?></td>
                    <td>
                        <?php
                        $badges = ['online'=>'success','offline'=>'danger','unknown'=>'secondary'];
                        $s = $c['status'];
                        echo '<span class="badge bg-' . ($badges[$s] ?? 'secondary') . '">'
                            . '<i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>'
                            . htmlspecialchars($s) . '</span>';
                        ?>
                    </td>
                    <td class="small text-muted">
                        <?= $c['last_checkin'] ? htmlspecialchars($c['last_checkin']) : 'Never' ?>
                    </td>
                    <td class="text-end">
                        <a href="/computers/<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteModal"
                                data-id="<?= (int)$c['id'] ?>"
                                data-hostname="<?= htmlspecialchars($c['hostname'], ENT_QUOTES) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center py-3">
            <nav aria-label="Computers pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($filters['search'] ?? '') ?>&status=<?= urlencode($filters['status'] ?? '') ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($filters['search'] ?? '') ?>&status=<?= urlencode($filters['status'] ?? '') ?>">
                            <?= $p ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($filters['search'] ?? '') ?>&status=<?= urlencode($filters['status'] ?? '') ?>">
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

<!-- Delete Confirmation Modal -->
<?php if (!empty($currentUser) && $currentUser['role'] === 'admin'): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-trash me-2"></i>Delete Computer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteHostname"></strong>?</p>
                <p class="text-muted small mb-0">This will permanently remove all data including software inventory and command history.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrfToken()) ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('deleteHostname').textContent = btn.getAttribute('data-hostname');
    document.getElementById('deleteForm').action = '/computers/' + btn.getAttribute('data-id') + '/delete';
});
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/views/layouts/footer.php'; ?>
