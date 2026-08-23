<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin', 'organizer']);

$type = $_GET['type'] ?? 'tournament';
$tournamentId = (int)($_GET['tournament_id'] ?? 0);

// Export handler
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $data = [];
    $filename = 'tournivox_report_' . date('Y-m-d');

    if ($type === 'tournament' && $tournamentId) {
        $t = Database::fetch("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        $teams = Database::fetchAll(
            "SELECT t.name, r.seed, s.wins, s.losses, s.points, s.rank_position
             FROM teams t JOIN registrations r ON t.id = r.team_id
             LEFT JOIN standings s ON s.team_id = t.id AND s.tournament_id = ?
             WHERE r.tournament_id = ? ORDER BY s.rank_position",
            [$tournamentId, $tournamentId]
        );
        $data = $teams;
        $filename = 'tournament_' . generateSlug($t['name']);
    } elseif ($type === 'champions') {
        $data = Database::fetchAll(
            "SELECT t.name as tournament, tn.name as champion, t.tournament_date, t.prize_pool
             FROM tournaments t JOIN teams tn ON t.champion_team_id = tn.id
             WHERE t.champion_team_id IS NOT NULL ORDER BY t.tournament_date DESC"
        );
        $filename = 'champions_report';
    } elseif ($type === 'matches') {
        $data = Database::fetchAll(
            "SELECT tn.name as tournament, t1.name as team1, t2.name as team2, m.team1_score, m.team2_score,
             w.name as winner, m.status, m.best_of, r.round_name
             FROM matches m
             JOIN tournaments tn ON m.tournament_id = tn.id
             LEFT JOIN teams t1 ON m.team1_id = t1.id LEFT JOIN teams t2 ON m.team2_id = t2.id
             LEFT JOIN teams w ON m.winner_id = w.id LEFT JOIN rounds r ON m.round_id = r.id
             ORDER BY m.updated_at DESC LIMIT 500"
        );
        $filename = 'match_history';
    }

    if ($format === 'csv' && !empty($data)) {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename={$filename}.csv");
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0]));
        foreach ($data as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }
}

$tournaments = Database::fetchAll("SELECT id, name FROM tournaments ORDER BY tournament_date DESC");
$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Reports</h1><p>Generate and export tournament reports</p></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4">
            <h6><i class="bi bi-trophy"></i> Tournament Report</h6>
            <p class="text-muted" style="font-size:0.85rem">Standings and team data for a specific tournament</p>
            <form method="GET">
                <input type="hidden" name="type" value="tournament">
                <select name="tournament_id" class="form-select mb-2" required>
                    <option value="">Select Tournament</option>
                    <?php foreach ($tournaments as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="export" value="csv" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Export CSV</button>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4">
            <h6><i class="bi bi-award"></i> Champion Report</h6>
            <p class="text-muted" style="font-size:0.85rem">All tournament champions history</p>
            <a href="?type=champions&export=csv" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Export CSV</a>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-4">
            <h6><i class="bi bi-controller"></i> Match History</h6>
            <p class="text-muted" style="font-size:0.85rem">Complete match history across all tournaments</p>
            <a href="?type=matches&export=csv" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Export CSV</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
