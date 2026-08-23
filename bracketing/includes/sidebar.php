<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="sidebar" id="sidebar">
    <a href="<?= APP_URL ?>/index.php" class="sidebar-brand text-decoration-none">
        <div class="brand-icon"><img src="<?= SITE_URL ?>/assets/images/tournivox-logo.png" alt="TOURNIVOX"></div>
        <div class="brand-text"><span class="brand-name">TOURNIVOX</span><span class="brand-sub">Bracketing Control</span></div>
    </a>
    <nav class="sidebar-nav">
        <a href="<?= APP_URL ?>/index.php" class="nav-item <?= $currentPage === 'index' && $currentDir === 'bracketing' ? 'active' : '' ?>"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
        <a href="<?= APP_URL ?>/tournaments/index.php" class="nav-item <?= $currentDir === 'tournaments' ? 'active' : '' ?>"><i class="bi bi-trophy"></i><span>Tournaments</span></a>
        <?php if (Auth::isOrganizer()): ?><a href="<?= APP_URL ?>/tournaments/create.php" class="nav-item"><i class="bi bi-plus-circle"></i><span>Create Tournament</span></a><?php endif; ?>
        <a href="<?= APP_URL ?>/teams/index.php" class="nav-item <?= $currentDir === 'teams' ? 'active' : '' ?>"><i class="bi bi-people-fill"></i><span>Teams</span></a>
        <?php if (Auth::isCaptain() || Auth::isOrganizer()): ?><a href="<?= APP_URL ?>/teams/register.php" class="nav-item"><i class="bi bi-person-plus"></i><span>Register Team</span></a><?php endif; ?>
        <a href="<?= APP_URL ?>/matches/index.php" class="nav-item <?= $currentDir === 'matches' ? 'active' : '' ?>"><i class="bi bi-controller"></i><span>Matches</span></a>
        <a href="<?= APP_URL ?>/brackets/view.php" class="nav-item <?= $currentDir === 'brackets' && $currentPage === 'view' ? 'active' : '' ?>"><i class="bi bi-diagram-3"></i><span>Brackets</span></a>
        <?php if (Auth::isOrganizer()): ?><a href="<?= APP_URL ?>/admin/announcements.php" class="nav-item <?= $currentPage === 'announcements' ? 'active' : '' ?>"><i class="bi bi-megaphone"></i><span>Announcements</span></a><?php endif; ?>

        <?php if (Auth::isAdmin()): ?>
        <div class="nav-divider">Administration</div>
        <a href="<?= APP_URL ?>/admin/index.php" class="nav-item <?= $currentDir === 'admin' && $currentPage === 'index' ? 'active' : '' ?>"><i class="bi bi-shield-lock"></i><span>Admin Panel</span></a>
        <a href="<?= APP_URL ?>/admin/users.php" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>"><i class="bi bi-person-gear"></i><span>Users</span></a>
        <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span></a>
        <a href="<?= APP_URL ?>/admin/logs.php" class="nav-item <?= $currentPage === 'logs' ? 'active' : '' ?>"><i class="bi bi-journal-text"></i><span>Activity Logs</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer"><small>&copy; <?= date('Y') ?> TOURNIVOX</small></div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
