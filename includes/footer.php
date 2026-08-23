<?php
$currentYear = $currentYear ?? date('Y');
?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#home" class="brand">
                <img src="<?= e(TOURNIVOX_LOGO_URL) ?>" class="brand-logo-image" alt="TOURNIVOX logo">
                <span class="brand-copy">
                    <strong><?= e(TOURNIVOX_NAME) ?></strong>
                    <small>ESPORTS COMMAND SYSTEM</small>
                </span>
            </a>

            <p>An integrated tournament management and live broadcast platform developed for academic capstone research.</p>
        </div>

        <div class="footer-links">
            <strong>EXPLORE</strong>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#platform">Platform</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#features">Features</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#games">Supported Games</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/bracketing/index.php">Brackets</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/index.php#about">Capstone</a>
        </div>

        <div class="footer-links">
            <strong>SYSTEM</strong>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/auth/login.php">Login</a>
            <a href="<?= e(TOURNIVOX_BASE_URL) ?>/bracketing/index.php">Public Brackets</a>
            <?php if ($liveAvailable): ?>
                <a href="<?= e($liveUrl) ?>">View Live</a>
            <?php endif; ?>
        </div>

        <div class="footer-campus">
            <strong>ACADEMIC PROJECT</strong>
            <p>Eastern Visayas State University</p>
            <p>Ormoc City Campus</p>
            <span>BS Information Technology</span>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© <?= e((string)$currentYear) ?> <?= e(TOURNIVOX_NAME) ?>. Capstone Project.</span>
        <span class="footer-colors">
            <i class="maroon"></i>
            <i class="gold"></i>
            <i class="green"></i>
            <i class="white"></i>
            EVSU-INSPIRED VISUAL THEME
        </span>
    </div>
</footer>
</body>
</html>
