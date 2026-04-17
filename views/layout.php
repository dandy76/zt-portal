<!DOCTYPE html>
<html lang="el" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZT Portal — Zero Trust Access</title>
    <link rel="stylesheet" href="/assets/style.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(Router::csrfToken()) ?>">
</head>
<body>
<?php if (Auth::isLoggedIn()): ?>
<nav class="main-nav">
    <div class="nav-brand">
        <span class="nav-logo">&#9671;</span>
        <span class="nav-title">ZT Portal</span>
    </div>
    <div class="nav-links">
        <a href="/portal" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/portal') ? 'active' : '' ?>">Portal</a>
        <?php if (Auth::isAdmin()): ?>
        <span class="nav-separator">|</span>
        <a href="/admin/dashboard" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/dashboard') ? 'active' : '' ?>">Dashboard</a>
        <a href="/admin/users" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/users') ? 'active' : '' ?>">Users</a>
        <a href="/admin/resources" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/resources') ? 'active' : '' ?>">Resources</a>
        <a href="/admin/permissions" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/permissions') ? 'active' : '' ?>">Permissions</a>
        <a href="/admin/sessions" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/sessions') ? 'active' : '' ?>">Sessions</a>
        <a href="/admin/audit" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/audit') ? 'active' : '' ?>">Audit</a>
        <a href="/admin/settings" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/settings') ? 'active' : '' ?>">Settings</a>
        <?php endif; ?>
    </div>
    <div class="nav-user">
        <span class="nav-username"><?= htmlspecialchars(Auth::username() ?? '') ?></span>
        <button id="theme-toggle" class="btn-icon" title="Toggle theme">&#9788;</button>
        <button id="btn-logout" class="btn btn-sm btn-danger">Logout</button>
    </div>
</nav>
<?php endif; ?>

<main class="container">
    <?php require $content; ?>
</main>

<script src="/assets/qrcode.min.js"></script>
<script src="/assets/app.js"></script>
</body>
</html>
