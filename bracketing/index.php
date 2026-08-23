<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
Auth::requireLogin();

// Dashboard statistics
$stats = [
    'active' => Database::count('tournaments', "status = 'ongoing'"),
    'upcoming' => Database::count('tournaments', "status = 'registration_open'"),
    'finished' => Database::count('tournaments', "status = 'finished'"),
    'teams' => Database::count('teams'),
    'matches' => Database::count('matches'),
    'champions' => Database::count('tournaments', 'champion_team_id IS NOT NULL'),
];

// Monthly match data for chart
$monthly = Database::fetchAll(
    "SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as cnt 
     FROM matches WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
     GROUP BY MONTH(created_at) ORDER BY MIN(created_at) LIMIT 6"
);
$stats['monthly'] = [
    'labels' => array_column($monthly, 'month') ?: ['Jan','Feb','Mar','Apr','May','Jun'],
    'data' => array_column($monthly, 'cnt') ?: [0,0,0,0,0,0],
];

// Recent tournaments
$recentTournaments = Database::fetchAll(
    "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) AS organizer_name,
     (SELECT COUNT(*) FROM registrations WHERE tournament_id = t.id) AS team_count
     FROM tournaments t JOIN users u ON t.organizer_id = u.user_id
     ORDER BY t.created_at DESC LIMIT 6"
);

// Recent matches
$recentMatches = Database::fetchAll(
    "SELECT m.*, t1.name as team1_name, t2.name as team2_name, tn.name as tournament_name
     FROM matches m 
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     JOIN tournaments tn ON m.tournament_id = tn.id
     ORDER BY m.updated_at DESC LIMIT 5"
);

// Bracketing announcements only. Broadcasting/overlay modules are intentionally not imported.
$announcements = Database::fetchAll(
    "SELECT a.id, a.title, a.content, a.created_at,
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'TOURNIVOX Administrator') AS author
     FROM announcements a
     LEFT JOIN users u ON u.user_id = a.created_by
     WHERE a.is_active = 1
     ORDER BY a.created_at DESC
     LIMIT 3"
);

$pageTitle = 'Dashboard';
$extraJS = ['dashboard.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= sanitize($_SESSION['full_name']) ?>!</p>
    </div>
    <?php if (Auth::isOrganizer()): ?>
    <a href="<?= APP_URL ?>/tournaments/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Tournament</a>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card stat-card card-glow">
            <div class="stat-icon blue"><i class="bi bi-lightning-fill"></i></div>
            <div class="stat-info"><h3><?= $stats['active'] ?></h3><p>Active</p></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card">
            <div class="stat-icon yellow"><i class="bi bi-clock"></i></div>
            <div class="stat-info"><h3><?= $stats['upcoming'] ?></h3><p>Upcoming</p></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card">
            <div class="stat-icon purple"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info"><h3><?= $stats['finished'] ?></h3><p>Finished</p></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card">
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info"><h3><?= $stats['teams'] ?></h3><p>Teams</p></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card">
            <div class="stat-icon blue"><i class="bi bi-controller"></i></div>
            <div class="stat-info"><h3><?= $stats['matches'] ?></h3><p>Matches</p></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card">
            <div class="stat-icon red"><i class="bi bi-trophy"></i></div>
            <div class="stat-info"><h3><?= $stats['champions'] ?></h3><p>Champions</p></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Charts -->
    <div class="col-lg-4">
        <div class="card p-4">
            <h6 class="mb-3">Tournament Status</h6>
            <canvas id="statusChart" height="200"></canvas>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card p-4">
            <h6 class="mb-3">Matches Overview</h6>
            <canvas id="matchesChart" height="100"></canvas>
        </div>
    </div>

    <!-- Recent Tournaments -->
    <div class="col-lg-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Recent Tournaments</h6>
                <a href="<?= APP_URL ?>/tournaments/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <?php if (empty($recentTournaments)): ?>
            <div class="empty-state"><i class="bi bi-trophy"></i><p>No tournaments yet</p></div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($recentTournaments as $t): ?>
                <div class="col-md-6">
                    <div class="card tournament-card" onclick="location='<?= APP_URL ?>/tournaments/view.php?id=<?= $t['id'] ?>'">
                        <div class="tournament-banner">
                            <?php if ($t['banner']): ?><img src="<?= UPLOAD_URL ?>/<?= $t['banner'] ?>" alt=""><?php endif; ?>
                            <span class="game-tag"><?= sanitize(gameLabel($t['game'])) ?></span>
                        </div>
                        <div class="tournament-body">
                            <h5><?= sanitize($t['name']) ?></h5>
                            <div class="tournament-meta">
                                <span><i class="bi bi-calendar"></i> <?= formatDate($t['tournament_date']) ?></span>
                                <span><i class="bi bi-people"></i> <?= $t['team_count'] ?> teams</span>
                            </div>
                            <div class="mt-2"><?= statusBadge($t['status']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar info -->
    <div class="col-lg-4">
        <!-- Recent Matches -->
        <div class="card p-4 mb-4">
            <h6 class="mb-3">Recent Matches</h6>
            <?php if (empty($recentMatches)): ?>
            <p class="text-muted">No matches yet</p>
            <?php else: foreach ($recentMatches as $m): ?>
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color:var(--border-color)!important">
                <div>
                    <small class="text-muted"><?= sanitize($m['tournament_name']) ?></small><br>
                    <strong><?= sanitize($m['team1_name'] ?? 'TBD') ?></strong> vs <strong><?= sanitize($m['team2_name'] ?? 'TBD') ?></strong>
                </div>
                <?= statusBadge($m['status']) ?>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Announcements -->
        <div class="card p-4">
            <h6 class="mb-3">Announcements</h6>
            <?php foreach ($announcements as $a): ?>
            <div class="mb-3 pb-3 border-bottom" style="border-color:var(--border-color)!important">
                <strong><?= sanitize($a['title']) ?></strong>
                <p class="mb-1 text-muted" style="font-size:0.85rem"><?= sanitize(mb_strimwidth((string)$a['content'], 0, 105, '...')) ?></p>
                <small class="text-muted"><?= formatDate($a['created_at']) ?> · <?= sanitize($a['author'] ?? 'TOURNIVOX') ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', () => initDashboardCharts(<?= json_encode($stats) ?>));</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
