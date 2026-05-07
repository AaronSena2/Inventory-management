<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height:100vh;">

<div class="card shadow-lg border-0 login-card">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <i class="bi bi-pc-display-horizontal display-4 text-primary"></i>
            <h2 class="mt-2 fw-bold text-white"><?= htmlspecialchars(APP_NAME) ?></h2>
            <p class="text-muted">Sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/login" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCsrfToken()) ?>">

            <div class="mb-3">
                <label for="username" class="form-label text-light">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-secondary border-secondary">
                        <i class="bi bi-person text-light"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control bg-dark text-light border-secondary"
                        id="username"
                        name="username"
                        placeholder="Enter username"
                        autocomplete="username"
                        autofocus
                        required
                    >
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-light">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-secondary border-secondary">
                        <i class="bi bi-lock text-light"></i>
                    </span>
                    <input
                        type="password"
                        class="form-control bg-dark text-light border-secondary"
                        id="password"
                        name="password"
                        placeholder="Enter password"
                        autocomplete="current-password"
                        required
                    >
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </div>
        </form>
    </div>
    <div class="card-footer text-center bg-transparent border-secondary py-3">
        <small class="text-muted">
            Default credentials: <strong>admin</strong> / <strong>password</strong>
        </small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
