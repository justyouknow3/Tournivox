<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$tournamentId = (int)($_GET['tournament_id'] ?? 0);
$tournaments = Database::fetchAll("SELECT id, name FROM tournaments WHERE status IN ('registration_open','ongoing','finished') ORDER BY tournament_date DESC");

$pageTitle = 'Brackets';
$extraJS = ['bracket.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Brackets</h1><p>View brackets or open a tournament to register your team, logo, players, and roles.</p></div>
    <a href="<?= APP_URL ?>/tournaments/index.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Register My Team</a>
</div>

<div class="filter-bar mb-3">
    <select id="bracketTournamentSelect" class="form-select" style="max-width:400px" onchange="loadSelectedBracket()">
        <option value="">Select Tournament</option>
        <?php foreach ($tournaments as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $tournamentId === $t['id'] ? 'selected' : '' ?>><?= sanitize($t['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="bracket-controls" id="bracketControls" style="display:none">
    <button class="btn btn-sm btn-outline-primary" data-action="zoom-in"><i class="bi bi-zoom-in"></i></button>
    <button class="btn btn-sm btn-outline-primary" data-action="zoom-out"><i class="bi bi-zoom-out"></i></button>
    <button class="btn btn-sm btn-outline-primary" data-action="reset"><i class="bi bi-arrows-fullscreen"></i></button>
    <button class="btn btn-sm btn-outline-primary" data-action="fullscreen" title="Full screen"><i class="bi bi-fullscreen"></i></button>
    <button class="btn btn-sm btn-success" data-action="export-png" title="Save bracket only as PNG"><i class="bi bi-image"></i> Save PNG</button>
</div>

<div class="bracket-container" id="bracketContainer">
    <div class="empty-state"><i class="bi bi-diagram-3"></i><p>Select a tournament to view its bracket</p></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<?php include __DIR__ . '/../includes/match-modal.php'; ?>

<script>
function loadSelectedBracket() {
    const id = document.getElementById('bracketTournamentSelect').value;
    if (!id) return;
    document.getElementById('bracketControls').style.display = 'flex';
    if (window.bracketViewer) window.bracketViewer.destroy();
    window.bracketViewer = new BracketViewer('bracketContainer', parseInt(id));
}
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($tournamentId): ?>loadSelectedBracket();<?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
