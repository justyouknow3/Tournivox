<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$page = max(1, (int)($_GET['page'] ?? 1));
$countVisibility = Auth::isOrganizer() ? '1=1' : "(EXISTS (SELECT 1 FROM registrations rv WHERE rv.team_id = teams.id AND rv.status = 'approved') OR captain_user_id = " . (int)Auth::id() . ')';
$queryVisibility = Auth::isOrganizer() ? '1=1' : "(EXISTS (SELECT 1 FROM registrations rv WHERE rv.team_id = t.id AND rv.status = 'approved') OR t.captain_user_id = " . (int)Auth::id() . ')';
$total = Database::count('teams', $countVisibility);
$pagination = paginate($total, $page);

$teams = Database::fetchAll(
    "SELECT t.*, (SELECT COUNT(*) FROM players WHERE team_id = t.id) as player_count,
     (SELECT COUNT(*) FROM registrations WHERE team_id = t.id AND status = 'approved') as tournament_count
     FROM teams t WHERE {$queryVisibility} ORDER BY t.created_at DESC
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);

$pageTitle = 'Teams';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Teams</h1><p>Registered esports teams</p></div>
    <?php if (in_array(Auth::role(), ['bracket_admin','admin','organizer','team_captain'], true)): ?><a href="register.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Register Team</a><?php endif; ?>
</div>

<?php if (empty($teams)): ?>
<div class="empty-state"><i class="bi bi-people"></i><p>No teams registered yet</p></div>
<?php else: ?>
<div class="table-responsive card">
    <table class="table mb-0">
        <thead><tr><th>Team</th><th>Captain</th><th>Contact</th><th>Players</th><th>Tournaments</th><th>Registered</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $t): ?>
        <tr style="cursor:pointer" onclick="location='view.php?id=<?= $t['id'] ?>'">
            <td><strong><?= sanitize($t['name']) ?></strong></td>
            <td><?= sanitize($t['captain_name']) ?></td>
            <td><?= sanitize($t['captain_contact']) ?></td>
            <td><?= $t['player_count'] ?></td>
            <td><?= $t['tournament_count'] ?></td>
            <td><?= formatDate($t['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= paginationHTML($pagination, 'index.php?') ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
