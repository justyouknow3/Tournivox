<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

// Filters
$game = $_GET['game'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];
if ($game) { $where[] = 't.game = ?'; $params[] = $game; }
if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
if ($type) { $where[] = 't.tournament_type = ?'; $params[] = $type; }
if ($date) { $where[] = 't.tournament_date = ?'; $params[] = $date; }

$whereStr = implode(' AND ', $where);
$total = Database::count('tournaments t', $whereStr, $params);
$pagination = paginate($total, $page);

$tournaments = Database::fetchAll(
    "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as organizer_name,
     (SELECT COUNT(*) FROM registrations WHERE tournament_id = t.id AND status = 'approved') as team_count,
     ct.name as champion_name
     FROM tournaments t 
     JOIN users u ON t.organizer_id = u.user_id
     LEFT JOIN teams ct ON t.champion_team_id = ct.id
     WHERE {$whereStr} ORDER BY t.tournament_date DESC 
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$games = Database::fetchAll("SELECT DISTINCT game FROM tournaments ORDER BY game");

$pageTitle = 'Tournaments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Tournaments</h1><p>Browse and manage esports tournaments</p></div>
    <?php if (Auth::isOrganizer()): ?>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Tournament</a>
    <?php endif; ?>
</div>

<div class="filter-bar">
    <form method="GET" class="d-flex gap-2 flex-wrap w-100">
        <select name="game" class="form-select">
            <option value="">All Games</option>
            <?php foreach ($games as $g): ?>
            <option value="<?= sanitize(gameLabel($g['game'])) ?>" <?= $game === $g['game'] ? 'selected' : '' ?>><?= sanitize(gameLabel($g['game'])) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="registration_open" <?= $status === 'registration_open' ? 'selected' : '' ?>>Registration Open</option>
            <option value="ongoing" <?= $status === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
            <option value="finished" <?= $status === 'finished' ? 'selected' : '' ?>>Finished</option>
        </select>
        <select name="type" class="form-select">
            <option value="">All Types</option>
            <option value="single_elimination" <?= $type === 'single_elimination' ? 'selected' : '' ?>>Single Elimination</option>
            <option value="double_elimination" <?= $type === 'double_elimination' ? 'selected' : '' ?>>Double Elimination</option>
            <option value="round_robin" <?= $type === 'round_robin' ? 'selected' : '' ?>>Round Robin</option>
            <option value="point_based" <?= $type === 'point_based' ? 'selected' : '' ?>>Point Based</option>
        </select>
        <input type="date" name="date" class="form-control" value="<?= sanitize($date) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="index.php" class="btn btn-outline-primary btn-sm">Clear</a>
    </form>
</div>

<?php if (empty($tournaments)): ?>
<div class="empty-state"><i class="bi bi-trophy"></i><p>No tournaments found</p></div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($tournaments as $t): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card tournament-card h-100" onclick="location='view.php?id=<?= $t['id'] ?>'">
            <div class="tournament-banner">
                <?php if ($t['banner']): ?><img src="<?= UPLOAD_URL ?>/<?= $t['banner'] ?>" alt=""><?php endif; ?>
                <span class="game-tag"><?= sanitize(gameLabel($t['game'])) ?></span>
            </div>
            <div class="tournament-body">
                <h5><?= sanitize($t['name']) ?></h5>
                <div class="tournament-meta mb-2">
                    <span><i class="bi bi-calendar"></i> <?= formatDate($t['tournament_date']) ?></span>
                    <span><i class="bi bi-people"></i> <?= $t['team_count'] ?>/<?= $t['max_teams'] ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <?= statusBadge($t['status']) ?>
                    <small class="text-muted"><?= tournamentTypeLabel($t['tournament_type']) ?></small>
                </div>
                <?php if ($t['champion_name']): ?>
                <div class="mt-2"><i class="bi bi-trophy-fill text-warning"></i> <?= sanitize($t['champion_name']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?= paginationHTML($pagination, 'index.php?' . http_build_query(array_filter(['game'=>$game,'status'=>$status,'type'=>$type,'date'=>$date]))) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
