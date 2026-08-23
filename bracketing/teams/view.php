<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$team = Database::fetch("SELECT * FROM teams WHERE id = ?", [$id]);
if (!$team) { setFlash('error', 'Team not found.'); redirect(APP_URL . '/teams/index.php'); }
$isOwner = (int)($team['captain_user_id'] ?? 0) === (int)Auth::id();
$isPubliclyApproved = Database::count('registrations', "team_id = ? AND status = 'approved'", [$id]) > 0;
if (!Auth::isOrganizer() && !$isOwner && !$isPubliclyApproved) {
    setFlash('error', 'This team is pending payment and is not publicly visible yet.');
    redirect(APP_URL . '/teams/index.php');
}

$players = Database::fetchAll("SELECT * FROM players WHERE team_id = ? ORDER BY role", [$id]);
$tournaments = Database::fetchAll(
    "SELECT tn.name, tn.tournament_date, tn.status, r.seed
     FROM registrations r JOIN tournaments tn ON r.tournament_id = tn.id
     WHERE r.team_id = ? ORDER BY tn.tournament_date DESC",
    [$id]
);

$pageTitle = $team['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1><?= sanitize($team['name']) ?></h1><p>Team Details</p></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4">
            <div class="user-avatar mx-auto mb-3" style="width:80px;height:80px;font-size:2rem"><?= strtoupper(substr($team['name'], 0, 1)) ?></div>
            <h5 class="text-center"><?= sanitize($team['name']) ?></h5>
            <hr>
            <p><strong>Captain:</strong> <?= sanitize($team['captain_name']) ?></p>
            <p><strong>Contact:</strong> <?= sanitize($team['captain_contact']) ?></p>
            <?php if ($team['coach']): ?><p><strong>Coach:</strong> <?= sanitize($team['coach']) ?></p><?php endif; ?>
            <p><strong>Registered:</strong> <?= formatDate($team['created_at']) ?></p>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card p-4 mb-4">
            <h6 class="mb-3">Roster</h6>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>IGN</th><th>Real Name</th><th>Role</th></tr></thead>
                    <tbody>
                    <?php foreach ($players as $p): ?>
                    <tr>
                        <td><strong><?= sanitize($p['ign']) ?></strong></td>
                        <td><?= sanitize($p['real_name']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize($p['role']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card p-4">
            <h6 class="mb-3">Tournament History</h6>
            <?php if (empty($tournaments)): ?>
            <p class="text-muted">No tournament history</p>
            <?php else: ?>
            <table class="table mb-0">
                <thead><tr><th>Tournament</th><th>Date</th><th>Seed</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($tournaments as $t): ?>
                <tr>
                    <td><?= sanitize($t['name']) ?></td>
                    <td><?= formatDate($t['tournament_date']) ?></td>
                    <td>#<?= $t['seed'] ?? '-' ?></td>
                    <td><?= statusBadge($t['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
