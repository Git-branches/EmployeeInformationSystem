<?php
// includes/sidebar.php
// Navigation menu. Fixed sidebar on desktop; slide-in offcanvas drawer on
// mobile/tablet (opened by the hamburger button in the navbar).

$current = str_replace('\\', '/', $_SERVER['PHP_SELF']);

$menu = [
    ['label' => 'Dashboard',    'icon' => 'bi-speedometer2',       'href' => '/modules/dashboard/index.php',    'match' => '/modules/dashboard/'],
    ['label' => 'Employees',    'icon' => 'bi-people',             'href' => '/modules/employees/index.php',    'match' => '/modules/employees/'],
    ['label' => 'Departments',  'icon' => 'bi-diagram-3',          'href' => '/modules/departments/index.php',  'match' => '/modules/departments/'],
    ['label' => 'Import Data',  'icon' => 'bi-file-earmark-arrow-up', 'href' => '/modules/import/index.php',    'match' => '/modules/import/'],
    ['label' => 'Requirements', 'icon' => 'bi-card-checklist',     'href' => '/modules/requirements/monitor.php', 'match' => '/modules/requirements/'],
    ['label' => 'Reports',      'icon' => 'bi-file-earmark-text',  'href' => '/modules/reports/index.php',      'match' => '/modules/reports/'],
    ['label' => 'Backup',       'icon' => 'bi-cloud-arrow-up',     'href' => '/modules/backup/index.php',       'match' => '/modules/backup/'],
];
?>
<aside class="sidebar offcanvas-lg offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarMenu" aria-label="Main menu">
    <div class="offcanvas-header d-lg-none">
        <span class="offcanvas-title fw-bold"><span class="brand-bee">🐝</span>Menu</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-block p-3">
        <ul class="nav nav-pills flex-column gap-1">
        <?php foreach ($menu as $item): ?>
            <li class="nav-item">
                <a class="nav-link text-white<?= str_contains($current, $item['match']) ? ' active' : '' ?>"
                   href="<?= BASE_URL . $item['href'] ?>">
                    <i class="bi <?= $item['icon'] ?> me-2"></i><?= e($item['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
</aside>
