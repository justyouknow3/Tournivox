<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin', 'organizer']);

$id = (int)($_GET['id'] ?? 0);
$tournament = Database::fetch("SELECT * FROM tournaments WHERE id = ?", [$id]);
if (!$tournament || !Auth::canManageTournament($id)) {
    setFlash('error', 'Tournament not found or access denied.');
    redirect(APP_URL . '/tournaments/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'game' => in_array($_POST['game'] ?? '', array_keys(supportedGames()), true) ? $_POST['game'] : 'MLBB',
        'description' => trim($_POST['description'] ?? ''),
        'max_teams' => (int)($_POST['max_teams'] ?? 16),
        'registration_deadline' => $_POST['registration_deadline'] ?: null,
        'tournament_date' => $_POST['tournament_date'] ?? '',
        'start_time' => $_POST['start_time'] ?: null,
        'venue' => trim($_POST['venue'] ?? ''),
        'prize_pool' => moneyValue($_POST['prize_pool'] ?? 0),
        'registration_fee_type' => in_array($_POST['registration_fee_type'] ?? '', ['fixed','prize_pool_based'], true) ? $_POST['registration_fee_type'] : 'fixed',
        'registration_fee' => moneyValue($_POST['registration_fee'] ?? 0),
        'rules' => trim($_POST['rules'] ?? ''),
        'status' => $_POST['status'] ?? '',
        'seeding_type' => $_POST['seeding_type'] ?? 'random',
    ];

    if (!empty($_FILES['logo']['name'])) {
        deleteFile($tournament['logo']);
        $logo = uploadFile($_FILES['logo'], 'tournaments');
        if ($logo) $data['logo'] = $logo;
    }
    if (!empty($_FILES['banner']['name'])) {
        deleteFile($tournament['banner']);
        $banner = uploadFile($_FILES['banner'], 'tournaments');
        if ($banner) $data['banner'] = $banner;
    }

    Database::update('tournaments', $data, 'id = ?', [$id]);
    logActivity('update_tournament', 'tournament', $id);
    setFlash('success', 'Tournament updated!');
    redirect(APP_URL . '/tournaments/view.php?id=' . $id);
}

$pageTitle = 'Edit Tournament';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Edit Tournament</h1><p><?= sanitize($tournament['name']) ?></p></div>
</div>

<div class="card p-4">
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Tournament Name</label>
                <input type="text" name="name" class="form-control" required value="<?= sanitize($tournament['name']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Game</label>
                <select name="game" class="form-select">
                    <?php foreach (supportedGames() as $game => $label): ?><option value="<?= sanitize($game) ?>" <?= $tournament['game'] === $game ? 'selected' : '' ?>><?= sanitize($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Logo <?= $tournament['logo'] ? '(current uploaded)' : '' ?></label>
                <input type="file" name="logo" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label">Banner <?= $tournament['banner'] ? '(current uploaded)' : '' ?></label>
                <input type="file" name="banner" class="form-control" accept="image/*">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= sanitize($tournament['description']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Max Teams</label>
                <input type="number" name="max_teams" class="form-control" value="<?= $tournament['max_teams'] ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="registration_open" <?= $tournament['status'] === 'registration_open' ? 'selected' : '' ?>>Registration Open</option>
                    <option value="ongoing" <?= $tournament['status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="finished" <?= $tournament['status'] === 'finished' ? 'selected' : '' ?>>Finished</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Registration Deadline</label>
                <input type="datetime-local" name="registration_deadline" class="form-control" 
                       value="<?= $tournament['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($tournament['registration_deadline'])) : '' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="tournament_date" class="form-control" required value="<?= $tournament['tournament_date'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Start Time</label>
                <input type="time" name="start_time" class="form-control" value="<?= $tournament['start_time'] ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Venue</label>
                <input type="text" name="venue" class="form-control" value="<?= sanitize($tournament['venue']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Prize Pool</label>
                <input type="text" name="prize_pool" class="form-control money-input" value="<?= number_format((float)$tournament['prize_pool'], 0, '.', ',') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Registration Fee Type</label>
                <select name="registration_fee_type" id="registrationFeeType" class="form-select">
                    <option value="fixed" <?= $tournament['registration_fee_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                    <option value="prize_pool_based" <?= $tournament['registration_fee_type'] === 'prize_pool_based' ? 'selected' : '' ?>>Prize Pool ÷ Max Teams</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Registration Fee</label>
                <input type="text" name="registration_fee" id="registrationFee" class="form-control money-input" value="<?= number_format((float)$tournament['registration_fee'], 0, '.', ',') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Rules</label>
                <textarea name="rules" class="form-control" rows="4"><?= sanitize($tournament['rules']) ?></textarea>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="view.php?id=<?= $id ?>" class="btn btn-outline-primary ms-2">Cancel</a>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.money-input').forEach(input => input.addEventListener('input', () => {
    const raw = input.value.replace(/[^0-9.]/g, ''); if (!raw) return;
    const parts = raw.split('.'); parts[0] = Number(parts[0] || 0).toLocaleString('en-US');
    input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('').slice(0,2) : parts[0];
}));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
