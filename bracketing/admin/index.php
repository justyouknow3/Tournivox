<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin']);

$stats = [
    'users' => Database::count('users'),
    'tournaments' => Database::count('tournaments'),
    'teams' => Database::count('teams'),
    'matches' => Database::count('matches'),
];

$recentLogs = Database::fetchAll(
    "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) AS full_name FROM logs l LEFT JOIN users u ON l.user_id = u.user_id ORDER BY l.created_at DESC LIMIT 10"
);

$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Admin Panel</h1><p>System administration</p></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="stat-icon blue"><i class="bi bi-people"></i></div>
        <div class="stat-info"><h3><?= $stats['users'] ?></h3><p>Users</p></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="stat-icon purple"><i class="bi bi-trophy"></i></div>
        <div class="stat-info"><h3><?= $stats['tournaments'] ?></h3><p>Tournaments</p></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info"><h3><?= $stats['teams'] ?></h3><p>Teams</p></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="stat-icon red"><i class="bi bi-controller"></i></div>
        <div class="stat-info"><h3><?= $stats['matches'] ?></h3><p>Matches</p></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card p-4">
            <h6 class="mb-3">Quick Links</h6>
            <div class="row g-3">
                <div class="col-md-4"><a href="users.php" class="card p-3 text-decoration-none d-block"><i class="bi bi-person-gear"></i> Manage Users</a></div>
                <div class="col-md-4"><a href="<?= APP_URL ?>/tournaments/index.php" class="card p-3 text-decoration-none d-block"><i class="bi bi-trophy"></i> Tournaments</a></div>
                <div class="col-md-4"><a href="announcements.php" class="card p-3 text-decoration-none d-block"><i class="bi bi-megaphone"></i> Announcements</a></div>
                <div class="col-md-4"><a href="reports.php" class="card p-3 text-decoration-none d-block"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></div>
                <div class="col-md-4"><a href="logs.php" class="card p-3 text-decoration-none d-block"><i class="bi bi-journal-text"></i> Activity Logs</a></div>
                <div class="col-md-4"><a href="<?= APP_URL ?>/teams/index.php" class="card p-3 text-decoration-none d-block"><i class="bi bi-people"></i> Teams</a></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4">
            <h6 class="mb-3">Recent Activity</h6>
            <?php foreach ($recentLogs as $log): ?>
            <div class="py-2 border-bottom" style="border-color:var(--border-color)!important;font-size:0.85rem">
                <strong><?= sanitize($log['full_name'] ?? 'System') ?></strong> · <?= sanitize($log['action']) ?>
                <br><small class="text-muted"><?= formatDateTime($log['created_at']) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
