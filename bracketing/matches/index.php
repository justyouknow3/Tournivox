<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = '1=1';
$params = [];
if ($status) { $where .= ' AND m.status = ?'; $params[] = $status; }

$total = Database::count('matches m', $where, $params);
$pagination = paginate($total, $page);

$matches = Database::fetchAll(
    "SELECT m.*, t1.name as team1_name, t2.name as team2_name, w.name as winner_name,
     tn.name as tournament_name, r.round_name
     FROM matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN teams w ON m.winner_id = w.id
     JOIN tournaments tn ON m.tournament_id = tn.id
     LEFT JOIN rounds r ON m.round_id = r.id
     WHERE {$where} ORDER BY m.updated_at DESC
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$pageTitle = 'Matches';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Matches</h1><p>All tournament matches</p></div>
</div>

<div class="filter-bar">
    <a href="index.php" class="btn btn-sm <?= !$status ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
    <a href="?status=waiting" class="btn btn-sm <?= $status === 'waiting' ? 'btn-primary' : 'btn-outline-primary' ?>">Waiting</a>
    <a href="?status=live" class="btn btn-sm <?= $status === 'live' ? 'btn-primary' : 'btn-outline-primary' ?>">Live</a>
    <a href="?status=finished" class="btn btn-sm <?= $status === 'finished' ? 'btn-primary' : 'btn-outline-primary' ?>">Finished</a>
</div>

<div class="table-responsive card">
    <table class="table mb-0">
        <thead><tr><th>Match</th><th>Tournament</th><th>Teams</th><th>Score</th><th>Round</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($matches as $m): ?>
        <tr>
            <td>#<?= $m['match_number'] ?></td>
            <td><a href="<?= APP_URL ?>/tournaments/view.php?id=<?= $m['tournament_id'] ?>"><?= sanitize($m['tournament_name']) ?></a></td>
            <td><?= sanitize($m['team1_name'] ?? 'TBD') ?> vs <?= sanitize($m['team2_name'] ?? 'TBD') ?></td>
            <td><?= $m['team1_score'] ?> - <?= $m['team2_score'] ?></td>
            <td><?= sanitize($m['round_name'] ?? '-') ?></td>
            <td><?= statusBadge($m['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= paginationHTML($pagination, 'index.php?' . ($status ? "status={$status}&" : '')) ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
