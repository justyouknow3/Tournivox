<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$tournament = Database::fetch(
    "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as organizer_name, ct.name as champion_name
     FROM tournaments t JOIN users u ON t.organizer_id = u.user_id
     LEFT JOIN teams ct ON t.champion_team_id = ct.id WHERE t.id = ?",
    [$id]
);
if (!$tournament) { setFlash('error', 'Tournament not found.'); redirect(APP_URL . '/tournaments/index.php'); }

$canManage = Auth::canManageTournament($id);
$registrationOpen = isTournamentRegistrationOpen($tournament);

// Process destructive and approval actions before any HTML output to prevent header warnings.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_tournament') {
        Database::delete('tournaments', 'id = ?', [$id]);
        logActivity('delete_tournament', 'tournament', $id);
        setFlash('success', 'Tournament deleted.');
        redirect(APP_URL . '/tournaments/index.php');
    }
    if ($action === 'save_bracket_setup') {
        $allowedBracketTypes = ['single_elimination','double_elimination','round_robin','point_based'];
        $newType = $_POST['tournament_type'] ?? 'single_elimination';
        if (!in_array($newType, $allowedBracketTypes, true)) $newType = 'single_elimination';
        $startedMatches = Database::count('matches', "tournament_id = ? AND status IN ('live','finished')", [$id]);
        $existingBrackets = Database::count('brackets', 'tournament_id = ?', [$id]);
        if ($startedMatches > 0) {
            setFlash('error', 'Bracket type is locked because a match is already live or finished.');
        } elseif ($existingBrackets > 0 && $newType !== $tournament['tournament_type']) {
            setFlash('error', 'Remove or reset the existing bracket before changing its main type.');
        } else {
            Database::update('tournaments', ['tournament_type' => $newType], 'id = ?', [$id]);
            setFlash('success', 'Bracket setup saved.');
        }
        redirect(APP_URL . '/tournaments/view.php?id=' . $id);
    }
    if ($action === 'save_manual_seeds') {
        $seeds = $_POST['seeds'] ?? [];
        $used = [];
        foreach ($seeds as $teamId => $seed) {
            $teamId = (int)$teamId;
            $seed = (int)$seed;
            if ($seed < 1 || in_array($seed, $used, true)) continue;
            $used[] = $seed;
            Database::update('registrations', ['seed' => $seed], "tournament_id = ? AND team_id = ? AND status = 'approved'", [$id, $teamId]);
        }
        setFlash('success', 'Manual seeds saved.');
        redirect(APP_URL . '/tournaments/view.php?id=' . $id);
    }
    if (in_array($action, ['approve_registration', 'reject_registration'], true)) {
        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $status = $action === 'approve_registration' ? 'approved' : 'rejected';
        $paymentStatus = $action === 'approve_registration' ? 'paid' : 'needs_payment';
        Database::update('registrations', [
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_notes' => trim($_POST['payment_notes'] ?? '') ?: null,
            'approved_by' => Auth::id(),
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ], 'id = ? AND tournament_id = ?', [$registrationId, $id]);
        setFlash('success', $status === 'approved' ? 'Payment confirmed and team approved.' : 'Registration rejected.');
        redirect(APP_URL . '/tournaments/view.php?id=' . $id);
    }
}

// Registered teams
$teams = Database::fetchAll(
    "SELECT t.*, r.seed, r.status as reg_status, r.registered_at,
     (SELECT COUNT(*) FROM players WHERE team_id = t.id) as player_count
     FROM teams t JOIN registrations r ON t.id = r.team_id
     WHERE r.tournament_id = ? AND r.status = 'approved' ORDER BY r.seed ASC, r.registered_at ASC",
    [$id]
);

$pendingRegistrations = $canManage ? Database::fetchAll(
    "SELECT r.*, t.name, t.logo, t.captain_name, t.captain_contact,
     (SELECT COUNT(*) FROM players WHERE team_id = t.id) AS player_count
     FROM registrations r JOIN teams t ON r.team_id = t.id
     WHERE r.tournament_id = ? AND r.status = 'pending' ORDER BY r.registered_at ASC",
    [$id]
) : [];

// Standings
$standings = Database::fetchAll(
    "SELECT s.*, t.name as team_name, t.logo FROM standings s
     JOIN teams t ON s.team_id = t.id WHERE s.tournament_id = ?
     ORDER BY s.rank_position ASC, s.points DESC",
    [$id]
);

// Schedule
$schedule = Database::fetchAll(
    "SELECT m.*, t1.name as team1_name, t2.name as team2_name, r.round_name
     FROM matches m
     LEFT JOIN teams t1 ON m.team1_id = t1.id
     LEFT JOIN teams t2 ON m.team2_id = t2.id
     LEFT JOIN rounds r ON m.round_id = r.id
     WHERE m.tournament_id = ? ORDER BY m.scheduled_date, m.scheduled_time, m.match_number",
    [$id]
);

$matchCount = Database::count('matches', 'tournament_id = ?', [$id]);
$hasBracket = Database::count('brackets', 'tournament_id = ?', [$id]) > 0;

$pageTitle = $tournament['name'];
$extraJS = ['bracket.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="tournament-hero">
    <?php if ($tournament['banner']): ?><img src="<?= UPLOAD_URL ?>/<?= $tournament['banner'] ?>" alt=""><?php endif; ?>
    <div class="tournament-hero-overlay">
        <div>
            <h1><?= sanitize($tournament['name']) ?></h1>
            <div class="hero-meta">
                <span><i class="bi bi-controller"></i> <?= sanitize(gameLabel($tournament['game'])) ?></span>
                <span><i class="bi bi-calendar"></i> <?= formatDate($tournament['tournament_date']) ?></span>
                <span><i class="bi bi-geo-alt"></i> <?= sanitize($tournament['venue'] ?: 'TBA') ?></span>
                <span><i class="bi bi-cash"></i> Prize Pool: <?= formatMoney($tournament['prize_pool']) ?></span>
                <span><i class="bi bi-receipt"></i> Registration Fee: <?= formatMoney($tournament['registration_fee_type'] === 'prize_pool_based' ? ceil((float)$tournament['prize_pool'] / max(1, (int)$tournament['max_teams'])) : $tournament['registration_fee']) ?></span>
                <?= statusBadge($tournament['status']) ?>
            </div>
        </div>
    </div>
</div>

<?php
$myRegistration = Database::fetch("SELECT r.status, r.payment_status FROM registrations r JOIN teams t ON r.team_id=t.id WHERE r.tournament_id=? AND t.captain_user_id=?", [$id, Auth::id()]);
?>
<?php if (!$canManage && Auth::role() === 'team_captain' && $registrationOpen): ?>
<div class="mb-3">
<?php if ($myRegistration): ?>
<div class="alert alert-warning">Your team registration is <strong><?= sanitize(ucwords(str_replace('_',' ', $myRegistration['status']))) ?></strong> / <strong><?= sanitize(ucwords(str_replace('_',' ', $myRegistration['payment_status']))) ?></strong>. You cannot register another team in this tournament.</div>
<?php else: ?><a href="<?= APP_URL ?>/teams/register.php?tournament=<?= $id ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Register My Team</a><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
    <a href="<?= APP_URL ?>/brackets/format-manager.php?tournament_id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders"></i> Format Manager</a>
    <?php if (!$hasBracket && count($teams) >= 2): ?>
    <button class="btn btn-sm btn-primary" onclick="generateBracket(<?= $id ?>, '<?= $tournament['seeding_type'] ?>')">
        <i class="bi bi-diagram-3"></i> Generate Bracket
    </button>
    <?php endif; ?>
    <?php if ($hasBracket): ?>
    <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <?php endif; ?>
    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this tournament?')"><?= csrfField() ?><input type="hidden" name="action" value="delete_tournament"><button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</button></form>
</div>
<?php endif; ?>

<?php if ($canManage && !empty($pendingRegistrations)): ?>
<div class="card p-4 mb-3">
    <h5>Pending / Needs Payment (<?= count($pendingRegistrations) ?>)</h5>
    <p class="text-muted">These teams are hidden from viewers until payment is personally confirmed by an admin or organizer.</p>
    <?php foreach ($pendingRegistrations as $pending): ?>
    <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div><strong><?= sanitize($pending['name']) ?></strong><br><small><?= sanitize($pending['captain_name']) ?> · <?= sanitize($pending['captain_contact']) ?> · <?= $pending['player_count'] ?> players</small></div>
        <form method="POST" class="d-flex gap-2 align-items-center flex-wrap">
            <?= csrfField() ?><input type="hidden" name="registration_id" value="<?= $pending['id'] ?>">
            <input type="text" name="payment_notes" class="form-control form-control-sm" placeholder="Receipt/reference or note">
            <button name="action" value="approve_registration" class="btn btn-sm btn-success">Confirm Paid & Approve</button>
            <button name="action" value="reject_registration" class="btn btn-sm btn-danger">Reject</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($canManage && !$hasBracket && $tournament['seeding_type'] === 'manual' && count($teams) >= 2): ?>
<div class="card p-4 mb-3">
    <h5>Manual Seeding</h5>
    <p class="text-muted">Assign a unique seed number before generating the bracket.</p>
    <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="save_manual_seeds">
        <div class="row g-2">
        <?php foreach ($teams as $index => $team): ?>
            <div class="col-md-6">
                <label class="form-label"><?= sanitize($team['name']) ?></label>
                <input type="number" name="seeds[<?= $team['id'] ?>]" class="form-control" min="1" max="<?= count($teams) ?>" value="<?= (int)($team['seed'] ?: $index + 1) ?>" required>
            </div>
        <?php endforeach; ?>
        </div>
        <button class="btn btn-outline-primary mt-3">Save Manual Seeds</button>
    </form>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="card p-4 mb-3 bracket-setup-card">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h5 class="mb-1"><i class="bi bi-diagram-3"></i> Bracket Setup</h5>
            <p class="text-muted mb-0">Choose the main bracket type here. After generating it, use the pencil icon beside each round title to edit the round name, game, format, and best-of series.</p>
        </div>
        <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
            <?= csrfField() ?><input type="hidden" name="action" value="save_bracket_setup">
            <label class="mb-0"><span class="form-label d-block">Bracket Type</span>
                <select name="tournament_type" class="form-select" <?= $hasBracket ? 'disabled' : '' ?>>
                    <option value="single_elimination" <?= $tournament['tournament_type']==='single_elimination'?'selected':'' ?>>Single Elimination</option>
                    <option value="double_elimination" <?= $tournament['tournament_type']==='double_elimination'?'selected':'' ?>>Double Elimination</option>
                    <option value="round_robin" <?= $tournament['tournament_type']==='round_robin'?'selected':'' ?>>Round Robin</option>
                    <option value="point_based" <?= $tournament['tournament_type']==='point_based'?'selected':'' ?>>Point Based</option>
                </select>
            </label>
            <?php if (!$hasBracket): ?><button class="btn btn-primary"><i class="bi bi-check2"></i> Save Type</button><?php else: ?><span class="badge bg-secondary p-2">Locked after generation</span><?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="nav-tabs-custom" id="tournamentTabs">
    <button class="tab-item active" data-tab="tab-bracket">Bracket</button>
    <button class="tab-item" data-tab="tab-teams">Teams (<?= count($teams) ?>)</button>
    <button class="tab-item" data-tab="tab-standings">Standings</button>
    <button class="tab-item" data-tab="tab-schedule">Schedule</button>
    <button class="tab-item" data-tab="tab-rules">Rules</button>
    <?php if ($tournament['champion_name']): ?>
    <button class="tab-item" data-tab="tab-champion"><i class="bi bi-trophy-fill text-warning"></i> Champion</button>
    <?php endif; ?>
</div>

<!-- Bracket Tab -->
<div class="tab-content-panel active" id="tab-bracket">
    <?php if ($hasBracket): ?>
    <div class="bracket-controls" id="bracketControls">
        <button class="btn btn-sm btn-outline-primary" data-action="zoom-in"><i class="bi bi-zoom-in"></i></button>
        <button class="btn btn-sm btn-outline-primary" data-action="zoom-out"><i class="bi bi-zoom-out"></i></button>
        <button class="btn btn-sm btn-outline-primary" data-action="reset"><i class="bi bi-arrows-fullscreen"></i> Reset</button>
        <button class="btn btn-sm btn-outline-primary" data-action="fullscreen"><i class="bi bi-fullscreen"></i></button>
        <button class="btn btn-sm btn-outline-primary" data-action="print"><i class="bi bi-printer"></i></button>
    </div>
    <div class="bracket-container" id="bracketContainer"></div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-diagram-3"></i>
        <p>Bracket not generated yet.<?= count($teams) >= 2 ? ' Click "Generate Bracket" to start.' : ' Need at least 2 registered teams.' ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Teams Tab -->
<div class="tab-content-panel" id="tab-teams">
    <?php if (empty($teams)): ?>
    <div class="empty-state"><i class="bi bi-people"></i><p>No teams registered yet</p>
        <?php if (Auth::role() === 'team_captain' && $registrationOpen): ?><a href="<?= APP_URL ?>/teams/register.php?tournament=<?= $id ?>" class="btn btn-primary mt-2">Register Team</a><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($teams as $team): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar"><?= strtoupper(substr($team['name'], 0, 1)) ?></div>
                    <div>
                        <h6 class="mb-0"><?= sanitize($team['name']) ?></h6>
                        <small class="text-muted">Seed #<?= $team['seed'] ?? '-' ?> · <?= $team['player_count'] ?> players</small>
                    </div>
                </div>
                <div class="mt-2"><small>Captain: <?= sanitize($team['captain_name']) ?></small></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Standings Tab -->
<div class="tab-content-panel" id="tab-standings">
    <?php if (empty($standings)): ?>
    <div class="empty-state"><i class="bi bi-bar-chart"></i><p>Standings will appear after matches are played</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Rank</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>Win Rate</th><th>Points</th></tr></thead>
            <tbody>
            <?php foreach ($standings as $s):
                $rankClass = match($s['rank_position']) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => '' };
            ?>
            <tr>
                <td><span class="standing-rank <?= $rankClass ?>"><?= $s['rank_position'] ?></span></td>
                <td><strong><?= sanitize($s['team_name']) ?></strong></td>
                <td><?= $s['played'] ?></td>
                <td><?= $s['wins'] ?></td>
                <td><?= $s['draws'] ?></td>
                <td><?= $s['losses'] ?></td>
                <td><?= winRate($s['wins'], $s['losses']) ?>%</td>
                <td><strong><?= $s['points'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Schedule Tab -->
<div class="tab-content-panel" id="tab-schedule">
    <?php if (empty($schedule)): ?>
    <div class="empty-state"><i class="bi bi-calendar"></i><p>No matches scheduled yet</p></div>
    <?php else: foreach ($schedule as $m): ?>
    <div class="schedule-item">
        <div class="schedule-date">
            <div class="day"><?= $m['scheduled_date'] ? date('d', strtotime($m['scheduled_date'])) : '#' ?></div>
            <div class="month"><?= $m['scheduled_date'] ? date('M', strtotime($m['scheduled_date'])) : 'TBD' ?></div>
        </div>
        <div class="flex-grow-1">
            <strong><?= sanitize($m['team1_name'] ?? 'TBD') ?> vs <?= sanitize($m['team2_name'] ?? 'TBD') ?></strong>
            <div class="text-muted" style="font-size:0.85rem">
                <?= sanitize($m['round_name'] ?? 'Match #' . $m['match_number']) ?>
                <?= $m['scheduled_time'] ? ' · ' . date('h:i A', strtotime($m['scheduled_time'])) : '' ?>
                <?= $m['venue'] ? ' · ' . sanitize($m['venue']) : '' ?>
            </div>
        </div>
        <?= statusBadge($m['status']) ?>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Rules Tab -->
<div class="tab-content-panel" id="tab-rules">
    <div class="card p-4">
        <h5>Tournament Rules</h5>
        <?php if ($tournament['rules']): ?>
        <div style="white-space:pre-wrap"><?= sanitize($tournament['rules']) ?></div>
        <?php else: ?>
        <p class="text-muted">No rules specified.</p>
        <?php endif; ?>
        <hr>
        <div class="row g-3 mt-2">
            <div class="col-md-4"><strong>Type:</strong> <?= tournamentTypeLabel($tournament['tournament_type']) ?></div>
            <div class="col-md-4"><strong>Max Teams:</strong> <?= $tournament['max_teams'] ?></div>
            <div class="col-md-4"><strong>Organizer:</strong> <?= sanitize($tournament['organizer_name']) ?></div>
        </div>
    </div>
</div>

<?php if ($tournament['champion_name']): ?>
<div class="tab-content-panel" id="tab-champion">
    <div class="card p-5 text-center">
        <i class="bi bi-trophy-fill" style="font-size:4rem;color:#FFD700"></i>
        <h2 class="mt-3"><?= sanitize($tournament['champion_name']) ?></h2>
        <p class="text-muted">Champion of <?= sanitize($tournament['name']) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Match Modal -->
<?php include __DIR__ . '/../includes/match-modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.canManage = '<?= $canManage ? '1' : '0' ?>';
    initTabs('#tournamentTabs');
    <?php if ($hasBracket): ?>
    window.bracketViewer = new BracketViewer('bracketContainer', <?= $id ?>);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
