<?php
require_once __DIR__ . '/config.php';

$pageTitle = $pageTitle ?? TOURNIVOX_NAME . ' | Esports Tournament Platform';
$pageDescription = $pageDescription ?? TOURNIVOX_NAME . ' - Esports Tournament Management and Live Broadcast Overlay System';
$activeNav = $activeNav ?? '';
$liveAvailable = $liveAvailable ?? false;
$liveUrl = $liveUrl ?? TOURNIVOX_BASE_URL . '/live/index.php';
$extraStyles = $extraStyles ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(TOURNIVOX_LOGO_URL) ?>">
    <link rel="stylesheet" href="<?= e(TOURNIVOX_BASE_URL) ?>/style.css">
    <?php foreach ($extraStyles as $style): ?>
        <link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="page-noise"></div>

<header class="site-header">
    <div class="container navbar">
        <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#home" class="brand" aria-label="TOURNIVOX home">
            <img src="<?= e(TOURNIVOX_LOGO_URL) ?>" class="brand-logo-image" alt="TOURNIVOX logo">
            <span class="brand-copy">
                <strong><?= e(TOURNIVOX_NAME) ?></strong>
                <small>ESPORTS COMMAND SYSTEM</small>
            </span>
        </a>

        <nav class="nav-links" aria-label="Primary navigation">
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#home" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>

            <?php if ($liveAvailable): ?>
                <a href="<?= e($liveUrl) ?>" class="<?= $activeNav === 'live' ? 'active' : '' ?>">Live</a>
            <?php endif; ?>

            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/bracketing/index.php" class="<?= $activeNav === 'brackets' ? 'active' : '' ?>">Brackets</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#platform">Platform</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#features">Features</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#games">Games</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#about">About</a>
        </nav>

        <a class="nav-login" href="<?= e(TOURNIVOX_BASE_URL) ?>/auth/login.php">
            <span>Login</span>
            <span class="arrow">↗</span>
        </a>
    </div>
</header>
