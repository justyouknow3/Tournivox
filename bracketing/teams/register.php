<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin', 'organizer', 'team_captain']);

$error = '';
$tournamentId = (int)($_GET['tournament'] ?? $_POST['tournament_id'] ?? 0);

$tournaments = Database::fetchAll(
    "SELECT id, name, registration_fee, registration_fee_type, prize_pool, max_teams, registration_deadline, tournament_date, start_time, status FROM tournaments WHERE status = 'registration_open' AND (registration_deadline IS NULL OR registration_deadline > NOW()) AND (start_time IS NULL OR TIMESTAMP(tournament_date, start_time) > NOW()) ORDER BY tournament_date"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $tournamentId = (int)($_POST['tournament_id'] ?? 0);
    $teamName = trim($_POST['team_name'] ?? '');

    if (empty($teamName) || !$tournamentId) {
        $error = 'Team name and tournament are required.';
    } else {
        $tournament = Database::fetch("SELECT * FROM tournaments WHERE id = ? AND status = 'registration_open' AND (registration_deadline IS NULL OR registration_deadline > NOW()) AND (start_time IS NULL OR TIMESTAMP(tournament_date, start_time) > NOW())", [$tournamentId]);
        if (!$tournament) {
            $error = 'Tournament not found or registration closed.';
        } else {
            $existingRegistration = Database::fetch("SELECT r.id, r.status FROM registrations r JOIN teams t ON r.team_id = t.id WHERE r.tournament_id = ? AND t.captain_user_id = ?", [$tournamentId, Auth::id()]);
            if ($existingRegistration) {
                $error = 'You have already registered a team for this tournament. Its current status is ' . str_replace('_', ' ', $existingRegistration['status']) . '.';
            } else {
            $regCount = Database::count('registrations', "tournament_id = ? AND status = 'approved'", [$tournamentId]);
            if ($regCount >= $tournament['max_teams']) {
                $error = 'Tournament is full.';
            } else {
                // Create team
                $teamId = Database::insert('teams', [
                    'name' => $teamName,
                    'captain_name' => trim($_POST['captain_name'] ?? ''),
                    'captain_contact' => trim($_POST['captain_contact'] ?? ''),
                    'coach' => trim($_POST['coach'] ?? '') ?: null,
                    'captain_user_id' => Auth::id(),
                    'created_by' => Auth::id(),
                ]);

                if (!empty($_FILES['team_logo']['name'])) {
                    $logo = uploadFile($_FILES['team_logo'], 'teams');
                    if ($logo) Database::update('teams', ['logo' => $logo], 'id = ?', [$teamId]);
                }

                // Add players
                $players = $_POST['players'] ?? [];
                $playerCount = 0;
                foreach ($players as $p) {
                    if (empty($p['ign']) || empty($p['real_name'])) continue;
                    if ($playerCount >= 6) break;
                    Database::insert('players', [
                        'team_id' => $teamId,
                        'ign' => trim($p['ign']),
                        'real_name' => trim($p['real_name']),
                        'role' => $p['role'] ?? 'Substitute',
                    ]);
                    $playerCount++;
                }

                // Register for tournament
                Database::insert('registrations', [
                    'tournament_id' => $tournamentId,
                    'team_id' => $teamId,
                    'status' => 'pending',
                    'payment_status' => 'needs_payment',
                ]);

                notifyOrganizer($tournamentId, 'Team Registration Needs Payment', "Team '{$teamName}' registered and is pending personal payment confirmation.");
                logActivity('register_team', 'team', $teamId);
                setFlash('success', 'Team submitted. Status: Pending / Needs Payment. Pay the registration fee personally to the admin for approval.');
                redirect(APP_URL . '/tournaments/view.php?id=' . $tournamentId);
            }
            }
        }
    }
}

$pageTitle = 'Register Team';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Register Team</h1><p>Sign up your team for a tournament</p></div>
</div>

<div class="card p-4">
    <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
    <?php if (empty($tournaments)): ?>
    <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No tournaments open for registration</p></div>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Tournament *</label>
                <select name="tournament_id" class="form-select" required>
                    <option value="">Select Tournament</option>
                    <?php foreach ($tournaments as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $tournamentId === $t['id'] ? 'selected' : '' ?>><?= sanitize($t['name']) ?> — Fee: <?= formatMoney($t['registration_fee_type'] === 'prize_pool_based' ? ceil((float)$t['prize_pool'] / max(1, (int)$t['max_teams'])) : $t['registration_fee']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Team Name *</label>
                <input type="text" name="team_name" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Team Logo</label>
                <input type="file" name="team_logo" class="form-control" accept="image/*">
            </div>
            <div class="col-md-4">
                <label class="form-label">Captain Name *</label>
                <input type="text" name="captain_name" class="form-control" required value="<?= sanitize($_SESSION['full_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Captain Contact *</label>
                <input type="text" name="captain_contact" class="form-control" required placeholder="Phone or email">
            </div>
            <div class="col-md-6">
                <label class="form-label">Coach (optional)</label>
                <input type="text" name="coach" class="form-control">
            </div>
        </div>

        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Players (max 6)</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPlayerRow()"><i class="bi bi-plus"></i> Add Player</button>
        </div>
        <div id="playersContainer">
            <div class="player-row">
                <div><label class="form-label">IGN</label><input type="text" name="players[0][ign]" class="form-control" required></div>
                <div><label class="form-label">Real Name</label><input type="text" name="players[0][real_name]" class="form-control" required></div>
                <div><label class="form-label">Role</label>
                    <select name="players[0][role]" class="form-select" required>
                        <option value="EXP">EXP</option><option value="Jungler">Jungler</option>
                        <option value="Mid">Mid</option><option value="Gold">Gold</option>
                        <option value="Roam">Roam</option><option value="Substitute">Substitute</option>
                    </select>
                </div>
                <div></div>
            </div>
        </div>

        <div class="alert alert-warning mt-4"><strong>Payment process:</strong> Your team will be saved as <strong>Pending / Needs Payment</strong>. It will not appear publicly in the tournament until the admin confirms your personal payment.</div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Submit Team Registration</button>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
