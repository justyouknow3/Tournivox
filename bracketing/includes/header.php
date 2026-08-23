<?php
/** TOURNIVOX Bracketing Manager - Shared Header */
if (!defined('APP_NAME')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = Auth::check() ? Auth::user() : null;
$theme = $currentUser['theme'] ?? $_SESSION['theme'] ?? 'dark';
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= sanitize($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> | <?= APP_NAME ?></title>
    <meta name="theme-color" content="#5b0018">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/images/tournivox-logo.png">
    <link href="<?= APP_URL ?>/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <script>const APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body>
<?php if (Auth::check() && $currentUser): ?>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <header class="top-bar">
            <button class="sidebar-toggle d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="globalSearch" placeholder="Search tournaments, teams and players..." autocomplete="off">
                <div class="search-results" id="searchResults"></div>
            </div>
            <div class="top-bar-actions">
                <a class="btn-icon" href="<?= SITE_URL ?>/index.php" title="TOURNIVOX Home"><i class="bi bi-house-door"></i></a>
                <button class="btn-icon" id="themeToggle" title="Toggle theme"><i class="bi bi-<?= $theme === 'dark' ? 'sun' : 'moon' ?>"></i></button>
                <div class="notification-dropdown">
                    <button class="btn-icon" id="notifToggle"><i class="bi bi-bell"></i><span class="notif-badge" id="notifCount" style="display:none">0</span></button>
                    <div class="dropdown-menu-custom" id="notifDropdown"><div class="dropdown-header">Notifications</div><div id="notifList"><p class="text-muted p-3">No notifications</p></div></div>
                </div>
                <div class="user-dropdown">
                    <button class="user-btn" id="userToggle">
                        <div class="user-avatar" style="overflow:hidden"><?php if(!empty($currentUser['avatar'])):?><img src="<?= UPLOAD_URL ?>/<?= sanitize($currentUser['avatar']) ?>" style="width:100%;height:100%;object-fit:cover" alt=""><?php else:?><?= strtoupper(substr($currentUser['first_name'] ?? 'T',0,1)) ?><?php endif;?></div>
                        <span class="d-none d-md-inline"><?= sanitize($currentUser['full_name']) ?></span><i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu-custom" id="userDropdown">
                        <a href="<?= APP_URL ?>/auth/profile.php"><i class="bi bi-person"></i> Profile</a>
                        <a href="<?= APP_URL ?>/auth/change-password.php"><i class="bi bi-key"></i> Change Password</a>
                        <?php if (Auth::isAdmin()): ?><a href="<?= APP_URL ?>/admin/index.php"><i class="bi bi-shield"></i> Admin Panel</a><?php endif; ?>
                        <hr><a href="<?= APP_URL ?>/auth/logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>
        <main class="page-content">
            <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : sanitize($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= sanitize($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
<?php else: ?>
<div class="auth-wrapper">
<?php endif; ?>
