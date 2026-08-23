<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$pageTitle = 'Public Brackets | TOURNIVOX';
$pageDescription = 'View TOURNIVOX tournament brackets without logging in.';
$activeNav = 'brackets';

// TEMPORARY DEVELOPMENT SWITCH. Keep this aligned with the homepage until it becomes database-driven.
$liveAvailable = true;
$liveUrl = TOURNIVOX_BASE_URL . '/live/index.php';
$extraStyles = [TOURNIVOX_BASE_URL . '/bracketing/bracket.css'];

$requiredTables = ['tournaments', 'brackets', 'rounds', 'matches', 'teams'];
$bracketTablesReady = true;
foreach ($requiredTables as $table) {
    if (!tournivox_table_exists($conn, $table)) {
        $bracketTablesReady = false;
        break;
    }
}

$tournaments = [];
$selectedTournamentId = (int)($_GET['tournament_id'] ?? 0);
$selectedTournament = null;
$bracketGroups = [];

if ($bracketTablesReady) {
    $tournamentResult = $conn->query(
        "SELECT id, name, game, tournament_type, status, tournament_date
         FROM tournaments
         WHERE status IN ('registration_open', 'ongoing', 'finished')
         ORDER BY CASE WHEN status = 'ongoing' THEN 0 ELSE 1 END, tournament_date DESC, id DESC"
    );

    if ($tournamentResult) {
        while ($row = $tournamentResult->fetch_assoc()) {
            $tournaments[] = $row;
        }
    }

    if ($selectedTournamentId <= 0 && !empty($tournaments)) {
        $selectedTournamentId = (int)$tournaments[0]['id'];
    }

    if ($selectedTournamentId > 0) {
        $tournamentStmt = $conn->prepare(
            "SELECT id, name, game, tournament_type, status, tournament_date
             FROM tournaments WHERE id = ? LIMIT 1"
        );
        $tournamentStmt->bind_param('i', $selectedTournamentId);
        $tournamentStmt->execute();
        $selectedTournament = $tournamentStmt->get_result()->fetch_assoc() ?: null;
        $tournamentStmt->close();

        $matchStmt = $conn->prepare(
            "SELECT
                b.id AS bracket_id,
                b.bracket_type,
                r.id AS round_id,
                r.round_number,
                r.round_name,
                m.id AS match_id,
                m.match_number,
                m.team1_id,
                m.team2_id,
                m.team1_score,
                m.team2_score,
                m.winner_id,
                m.status,
                m.best_of,
                m.next_match_id,
                m.scheduled_date,
                m.scheduled_time,
                t1.name AS team1_name,
                t2.name AS team2_name
             FROM brackets b
             JOIN rounds r ON r.bracket_id = b.id
             LEFT JOIN matches m ON m.round_id = r.id AND m.tournament_id = b.tournament_id
             LEFT JOIN teams t1 ON t1.id = m.team1_id
             LEFT JOIN teams t2 ON t2.id = m.team2_id
             WHERE b.tournament_id = ?
             ORDER BY
                FIELD(b.bracket_type, 'winners', 'losers', 'grand_finals', 'round_robin'),
                r.round_number ASC,
                m.match_number ASC"
        );
        $matchStmt->bind_param('i', $selectedTournamentId);
        $matchStmt->execute();
        $result = $matchStmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $type = $row['bracket_type'] ?? 'winners';
            $roundId = (int)$row['round_id'];

            if (!isset($bracketGroups[$type])) {
                $bracketGroups[$type] = [];
            }

            if (!isset($bracketGroups[$type][$roundId])) {
                $bracketGroups[$type][$roundId] = [
                    'round_number' => (int)$row['round_number'],
                    'round_name' => $row['round_name'],
                    'matches' => [],
                ];
            }

            if (!empty($row['match_id'])) {
                $bracketGroups[$type][$roundId]['matches'][] = $row;
            }
        }

        $matchStmt->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="public-bracket-page">
    <section class="bracket-hero">
        <div class="container bracket-hero-inner">
            <div>
                <span class="bracket-kicker">PUBLIC TOURNAMENT VIEW</span>
                <h1>TOURNAMENT <span>BRACKETS</span></h1>
                <p>Read-only bracket viewing for spectators. No login is required.</p>
            </div>
            <div class="public-badge"><span></span> PUBLIC VIEW</div>
        </div>
    </section>

    <section class="bracket-workspace-section">
        <div class="container">
            <div class="bracket-window">
                <div class="bracket-window-bar">
                    <div class="window-title">
                        <img src="<?= e(TOURNIVOX_LOGO_URL) ?>" alt="" class="window-logo">
                        <div>
                            <strong>BRACKET VIEWER</strong>
                            <small>TOURNIVOX COMPETITION DISPLAY</small>
                        </div>
                    </div>
                    <div class="window-status"><i></i> READ ONLY</div>
                </div>

                <div class="bracket-toolbar">
                    <form method="get" class="tournament-picker">
                        <label for="tournament_id">Tournament</label>
                        <select id="tournament_id" name="tournament_id" onchange="this.form.submit()" <?= !$bracketTablesReady || empty($tournaments) ? 'disabled' : '' ?>>
                            <?php if (empty($tournaments)): ?>
                                <option>No tournament available</option>
                            <?php else: ?>
                                <?php foreach ($tournaments as $tournament): ?>
                                    <option value="<?= (int)$tournament['id'] ?>" <?= (int)$tournament['id'] === $selectedTournamentId ? 'selected' : '' ?>>
                                        <?= e($tournament['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </form>

                    <div class="viewer-controls" aria-label="Bracket view controls">
                        <button type="button" data-bracket-action="zoom-out" title="Zoom out">−</button>
                        <button type="button" data-bracket-action="reset" title="Reset view">100%</button>
                        <button type="button" data-bracket-action="zoom-in" title="Zoom in">+</button>
                    </div>
                </div>

                <?php if (!$bracketTablesReady): ?>
                    <div class="bracket-empty">
                        <div class="empty-symbol">⌁</div>
                        <h2>Bracket module is ready for its database tables.</h2>
                        <p>The public bracket window is installed, but the tournament/bracket tables have not been added to the TOURNIVOX database yet.</p>
                    </div>
                <?php elseif (!$selectedTournament): ?>
                    <div class="bracket-empty">
                        <div class="empty-symbol">◇</div>
                        <h2>No tournament bracket is available yet.</h2>
                        <p>Once an organizer publishes a bracket, spectators will be able to view it here without signing in.</p>
                    </div>
                <?php else: ?>
                    <div class="selected-tournament-header">
                        <div>
                            <span><?= e($selectedTournament['game'] ?? 'GAME') ?></span>
                            <h2><?= e($selectedTournament['name']) ?></h2>
                        </div>
                        <div class="tournament-meta-public">
                            <span><?= e(ucwords(str_replace('_', ' ', $selectedTournament['tournament_type'] ?? ''))) ?></span>
                            <span class="status-<?= e($selectedTournament['status'] ?? 'registration_open') ?>"><?= e(ucwords(str_replace('_', ' ', $selectedTournament['status'] ?? ''))) ?></span>
                        </div>
                    </div>

                    <?php if (empty($bracketGroups)): ?>
                        <div class="bracket-empty compact">
                            <div class="empty-symbol">◇</div>
                            <h2>Bracket not generated yet.</h2>
                            <p>This tournament exists, but no public bracket matches are available yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="bracket-viewport" id="bracketViewport">
                            <div class="bracket-stage" id="bracketStage">
                                <?php foreach ($bracketGroups as $type => $rounds): ?>
                                    <section class="bracket-group" data-bracket-type="<?= e($type) ?>">
                                        <div class="bracket-group-title">
                                            <span><?= e(bracket_type_label($type)) ?></span>
                                        </div>

                                        <div class="rounds-row">
                                            <?php foreach ($rounds as $round): ?>
                                                <div class="round-column">
                                                    <div class="round-heading">
                                                        <span>ROUND <?= (int)$round['round_number'] ?></span>
                                                        <strong><?= e($round['round_name']) ?></strong>
                                                    </div>

                                                    <div class="round-matches">
                                                        <?php if (empty($round['matches'])): ?>
                                                            <div class="match-card placeholder-match">
                                                                <span class="match-number">TBD</span>
                                                                <div class="team-line"><span>Awaiting match</span><b>–</b></div>
                                                                <div class="team-line"><span>Awaiting match</span><b>–</b></div>
                                                            </div>
                                                        <?php else: ?>
                                                            <?php foreach ($round['matches'] as $match): ?>
                                                                <?php
                                                                $team1Winner = !empty($match['winner_id']) && (int)$match['winner_id'] === (int)$match['team1_id'];
                                                                $team2Winner = !empty($match['winner_id']) && (int)$match['winner_id'] === (int)$match['team2_id'];
                                                                ?>
                                                                <article
                                                                    class="match-card status-<?= e($match['status'] ?? 'waiting') ?>"
                                                                    data-match-id="<?= (int)$match['match_id'] ?>"
                                                                    data-next-match-id="<?= (int)($match['next_match_id'] ?? 0) ?>"
                                                                >
                                                                    <div class="match-topline">
                                                                        <span class="match-number">MATCH <?= (int)$match['match_number'] ?></span>
                                                                        <span class="match-state"><?= e(strtoupper($match['status'] ?? 'WAITING')) ?></span>
                                                                    </div>

                                                                    <div class="team-line <?= $team1Winner ? 'winner' : '' ?>">
                                                                        <span><?= e($match['team1_name'] ?: 'TBD') ?></span>
                                                                        <b><?= (int)$match['team1_score'] ?></b>
                                                                    </div>
                                                                    <div class="team-line <?= $team2Winner ? 'winner' : '' ?>">
                                                                        <span><?= e($match['team2_name'] ?: 'TBD') ?></span>
                                                                        <b><?= (int)$match['team2_score'] ?></b>
                                                                    </div>

                                                                    <div class="match-footer">
                                                                        <span><?= e($match['best_of'] ?? 'BO3') ?></span>
                                                                        <?php if (!empty($match['scheduled_time'])): ?>
                                                                            <span><?= e(date('g:i A', strtotime($match['scheduled_time']))) ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </article>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script src="<?= e(TOURNIVOX_BASE_URL) ?>/bracketing/bracket.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
