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
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/dashboard">
      <i class="bi bi-pc-display me-1"></i> IT Inventory
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="/dashboard"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/computers"><i class="bi bi-laptop me-1"></i>Computers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/commands"><i class="bi bi-terminal me-1"></i>Commands</a>
        </li>
      </ul>
      <?php if (!empty($currentUser)): ?>
      <ul class="navbar-nav">
        <li class="nav-item">
          <span class="nav-link text-light">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($currentUser['username']) ?>
            <?php if ($currentUser['role'] === 'admin'): ?>
              <span class="badge bg-warning text-dark ms-1">Admin</span>
            <?php endif; ?>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/logout"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container-fluid mt-3 px-4">
