<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin', 'organizer']);

$error = '';
$allowedGames = array_keys(supportedGames());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => uniqueSlug(trim($_POST['name'] ?? ''), 'tournaments'),
        'game' => in_array($_POST['game'] ?? '', $allowedGames, true) ? $_POST['game'] : 'MLBB',
        'description' => trim($_POST['description'] ?? ''),
        // Bracket type is selected later from the tournament Bracket Setup panel.
        'tournament_type' => 'single_elimination',
        'max_teams' => (int)($_POST['max_teams'] ?? 16),
        'registration_deadline' => $_POST['registration_deadline'] ?: null,
        'tournament_date' => $_POST['tournament_date'] ?? date('Y-m-d'),
        'start_time' => $_POST['start_time'] ?: null,
        'venue' => trim($_POST['venue'] ?? ''),
        'prize_pool' => moneyValue($_POST['prize_pool'] ?? 0),
        'registration_fee_type' => 'fixed',
        'registration_fee' => moneyValue($_POST['registration_fee'] ?? 0),
        'rules' => trim($_POST['rules'] ?? ''),
        'status' => $_POST['status'] ?? 'registration_open',
        'seeding_type' => $_POST['seeding_type'] ?? 'random',
        'organizer_id' => Auth::id(),
    ];

    if (empty($data['name'])) {
        $error = 'Tournament name is required.';
    } else {
        if (!empty($_FILES['logo']['name'])) {
            $logo = uploadFile($_FILES['logo'], 'tournaments');
            if ($logo) $data['logo'] = $logo;
        }
        if (!empty($_FILES['banner']['name'])) {
            $banner = uploadFile($_FILES['banner'], 'tournaments');
            if ($banner) $data['banner'] = $banner;
        }

        $id = Database::insert('tournaments', $data);
        logActivity('create_tournament', 'tournament', $id);
        setFlash('success', 'Tournament created successfully!');
        redirect(APP_URL . '/tournaments/view.php?id=' . $id);
    }
}

$pageTitle = 'Create Tournament';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Create Tournament</h1><p>Set up a new esports tournament</p></div>
</div>

<div class="card p-4">
    <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Tournament Name *</label>
                <input type="text" name="name" class="form-control" required value="<?= sanitize($_POST['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Game</label>
                <select name="game" class="form-select">
                    <?php foreach (supportedGames() as $code => $label): ?>
                    <option value="<?= sanitize($code) ?>" <?= ($_POST['game'] ?? 'MLBB') === $code ? 'selected' : '' ?>><?= sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tournament Logo</label>
                <input type="file" name="logo" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label">Banner</label>
                <input type="file" name="banner" class="form-control" accept="image/*">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Maximum Teams</label>
                <input type="number" name="max_teams" class="form-control" value="16" min="2" max="64">
            </div>
            <div class="col-md-6">
                <label class="form-label">Seeding Type</label>
                <select name="seeding_type" class="form-select">
                    <option value="random">Random Seeding</option>
                    <option value="manual">Manual Seeding</option>
                    <option value="auto">Auto Seeding</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Registration Deadline</label>
                <input type="datetime-local" name="registration_deadline" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tournament Date *</label>
                <input type="date" name="tournament_date" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Start Time</label>
                <input type="time" name="start_time" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Venue</label>
                <input type="text" name="venue" class="form-control" placeholder="e.g. TOURNIVOX Esports Arena">
            </div>
            <div class="col-md-6">
                <label class="form-label">Prize Pool</label>
                <input type="text" name="prize_pool" class="form-control money-input" inputmode="decimal" placeholder="e.g. 1,000" value="<?= sanitize($_POST['prize_pool'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Registration Fee</label>
                <input type="text" name="registration_fee" id="registrationFee" class="form-control money-input" inputmode="decimal" placeholder="e.g. 1,000" value="<?= sanitize($_POST['registration_fee'] ?? '') ?>">
                <small class="text-muted" id="feeHelp">Set the fixed fee that each team captain must pay personally to the admin.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="registration_open">Registration Open</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="finished">Finished</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Rules</label>
                <textarea name="rules" class="form-control" rows="4" placeholder="Tournament rules and regulations..."></textarea>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Tournament</button>
            <a href="index.php" class="btn btn-outline-primary ms-2">Cancel</a>
        </div>
    </form>
</div>

<script>
function formatMoneyInput(input) {
    const raw = input.value.replace(/[^0-9.]/g, '');
    if (!raw) return;
    const parts = raw.split('.');
    parts[0] = Number(parts[0] || 0).toLocaleString('en-US');
    input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('').slice(0, 2) : parts[0];
}
document.querySelectorAll('.money-input').forEach(input => input.addEventListener('input', () => formatMoneyInput(input)));
document.querySelectorAll('input[type=\"date\"], input[type=\"time\"], input[type=\"datetime-local\"]').forEach(input => {
    input.style.cursor = 'pointer';
    input.addEventListener('click', () => { if (typeof input.showPicker === 'function') input.showPicker(); });
    input.parentElement?.addEventListener('click', event => {
        if (event.target !== input) { input.focus(); if (typeof input.showPicker === 'function') input.showPicker(); }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
