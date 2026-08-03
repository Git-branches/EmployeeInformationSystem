<?php
// includes/header.php
// Common <head>, top navbar, and opening layout markup.
// Expects: $page_title (string) — set before including.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= BASE_URL ?>/assets/css/fonts/baloo2-latin.woff2">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= BASE_URL ?>/assets/css/fonts/bootstrap-icons.woff2">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body data-base-url="<?= BASE_URL ?>">
<nav class="navbar navbar-dark bg-danger px-3 flex-nowrap">
    <div class="d-flex align-items-center min-w-0">
    <button class="btn text-white d-lg-none p-0 me-2 fs-2 lh-1 border-0" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>
    <span class="navbar-brand fw-bold text-truncate">
        <?php if (is_file(ROOT_PATH . '/assets/img/logo.png')): ?>
            <span class="navbar-logo"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Jollibee" height="38"></span>
        <?php else: ?>
            <span class="brand-bee">🐝</span>
        <?php endif; ?><span class="d-none d-sm-inline"><?= e(APP_NAME) ?></span><span class="d-sm-none">EIS</span>
    </span>
    </div>
    <div class="d-flex align-items-center gap-3">
    <?php [$notif_count, $notif_latest] = get_unread_notifications($pdo); ?>
    <div class="dropdown">
        <a class="text-white text-decoration-none position-relative" href="#" data-bs-toggle="dropdown" title="Notifications">
            <i class="bi bi-bell-fill fs-5"></i>
            <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-warning"
                  <?= $notif_count > 0 ? '' : 'style="display:none"' ?>><?= $notif_count ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" id="notif-menu" style="min-width:320px">
            <li class="dropdown-header d-flex justify-content-between">
                <span>Notifications</span>
                <a class="small" href="<?= BASE_URL ?>/modules/requirements/notifications_read.php"
                   id="notif-mark-read" <?= $notif_count > 0 ? '' : 'style="display:none"' ?>>Mark all read</a>
            </li>
            <?php if (!$notif_latest): ?>
                <li class="notif-empty"><span class="dropdown-item-text text-muted">No pending alerts.</span></li>
            <?php endif; ?>
            <?php foreach ($notif_latest as $n): ?>
                <li class="notif-item">
                    <a class="dropdown-item text-wrap" href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= (int)$n['employee_id'] ?>">
                        <i class="bi bi-exclamation-circle text-danger me-1"></i><?= e($n['message']) ?>
                        <div class="small text-muted"><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></div>
                    </a>
                </li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item small" href="<?= BASE_URL ?>/modules/requirements/monitor.php">View requirements monitor</a></li>
        </ul>
    </div>
    <div class="dropdown">
        <a class="text-white text-decoration-none dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['full_name'] ?? '') ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a class="dropdown-item" href="<?= BASE_URL ?>/modules/auth/logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </li>
        </ul>
    </div>
    </div>
</nav>
<div class="d-flex">
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="flex-grow-1 p-4">
<?php if ($flash = flash_get()): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
