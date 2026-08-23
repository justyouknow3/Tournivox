<?php
/**
 * Brackets API
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bracket.php';

header('Content-Type: application/json');
Database::ensureFlexibleFormatSchema();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'get':
        $tournamentId = (int)($_GET['tournament_id'] ?? 0);
        $brackets = Database::fetchAll("SELECT * FROM brackets WHERE tournament_id = ? ORDER BY id", [$tournamentId]);
        
        $result = [];
        foreach ($brackets as $b) {
            $rounds = Database::fetchAll(
                "SELECT r.*, t.game AS tournament_game FROM rounds r JOIN brackets b2 ON b2.id=r.bracket_id JOIN tournaments t ON t.id=b2.tournament_id WHERE r.bracket_id = ? AND COALESCE(r.is_visible,1)=1 ORDER BY r.round_number",
                [$b['id']]
            );
            $roundData = [];
            foreach ($rounds as $r) {
                $matches = Database::fetchAll(
                    "SELECT m.*, t1.name as team1_name, t2.name as team2_name
                     FROM matches m
                     LEFT JOIN teams t1 ON m.team1_id = t1.id
                     LEFT JOIN teams t2 ON m.team2_id = t2.id
                     WHERE m.round_id = ? ORDER BY m.match_number",
                    [$r['id']]
                );
                $roundData[] = [
                    'id' => $r['id'],
                    'name' => $r['round_name'] ?: ('Round ' . (int)$r['round_number']),
                    'number' => $r['round_number'],
                    'game_code' => $r['game_code'] ?: $r['tournament_game'],
                    'format_type' => $r['format_type'] ?: 'best_of_series',
                    'best_of' => $r['best_of'] ?: 'BO3',
                    'matches' => $matches,
                ];
            }
            $result[] = [
                'id' => $b['id'],
                'type' => $b['bracket_type'],
                'rounds' => $roundData,
            ];
        }
        jsonSuccess(['brackets' => $result]);

    case 'generate':
        Auth::requireLogin();
        $tournamentId = (int)($input['tournament_id'] ?? 0);
        if (!Auth::canManageTournament($tournamentId)) jsonError('Access denied', 403);
        
        $seeding = $input['seeding_type'] ?? 'random';
        $result = BracketGenerator::generate($tournamentId, $seeding);
        jsonResponse($result, $result['success'] ? 200 : 400);


    case 'update_round':
    case 'rename_round':
        Auth::requireLogin();
        Database::ensureFlexibleFormatSchema();
        $roundId = (int)($input['round_id'] ?? 0);
        $round = Database::fetch("SELECT r.*, b.tournament_id FROM rounds r JOIN brackets b ON b.id=r.bracket_id WHERE r.id=?", [$roundId]);
        if (!$round || !Auth::canManageTournament((int)$round['tournament_id'])) jsonError('Access denied', 403);
        $started = Database::count('matches', "tournament_id=? AND status IN ('live','finished')", [(int)$round['tournament_id']]);
        if ($started > 0) jsonError('Round editing is locked because a match is already live or finished.');
        $name = trim($input['round_name'] ?? '');
        if ($name === '') jsonError('Round name is required');
        $games = array_keys(supportedGames());
        $formats = ['best_of_series','single_elimination','double_elimination','round_robin','swiss','group_stage','hybrid','gauntlet','custom'];
        $bestOf = ['BO1','BO2','BO3','BO5','BO7'];
        $data = ['round_name' => $name];
        if ($action === 'update_round') {
            $data['game_code'] = in_array($input['game_code'] ?? '', $games, true) ? $input['game_code'] : null;
            $data['format_type'] = in_array($input['format_type'] ?? '', $formats, true) ? $input['format_type'] : 'best_of_series';
            $data['best_of'] = in_array($input['best_of'] ?? '', $bestOf, true) ? $input['best_of'] : 'BO3';
            Database::update('matches', ['best_of' => $data['best_of']], "round_id=? AND status='waiting'", [$roundId]);
        }
        Database::update('rounds', $data, 'id=?', [$roundId]);
        jsonSuccess([], 'Round settings saved');

    case 'delete_round':
        Auth::requireLogin();
        $roundId = (int)($input['round_id'] ?? 0);
        $round = Database::fetch("SELECT r.*, b.tournament_id FROM rounds r JOIN brackets b ON b.id=r.bracket_id WHERE r.id=?", [$roundId]);
        if (!$round || !Auth::canManageTournament((int)$round['tournament_id'])) jsonError('Access denied', 403);
        if ((int)$round['round_number'] <= 1) jsonError('Round 1 cannot be removed.');
        $started = Database::count('matches', "tournament_id=? AND status IN ('live','finished')", [(int)$round['tournament_id']]);
        if ($started > 0) jsonError('Rounds cannot be removed because a match is already live or finished.');
        $lastRound = Database::fetch("SELECT MAX(round_number) AS max_round FROM rounds WHERE bracket_id=? AND COALESCE(is_visible,1)=1", [(int)$round['bracket_id']]);
        if ((int)$round['round_number'] !== (int)($lastRound['max_round'] ?? 0)) jsonError('Remove the last round first.');
        $matchIds = Database::fetchAll('SELECT id FROM matches WHERE round_id=?', [$roundId]);
        $ids = array_map(fn($m)=>(int)$m['id'], $matchIds);
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            Database::query("UPDATE matches SET next_match_id=NULL WHERE next_match_id IN ($marks)", $ids);
            Database::query("UPDATE matches SET loser_next_match_id=NULL WHERE loser_next_match_id IN ($marks)", $ids);
            Database::delete('matches', 'round_id=?', [$roundId]);
        }
        Database::delete('rounds', 'id=?', [$roundId]);
        jsonSuccess([], 'Round removed');

    case 'swap_matches':
        Auth::requireLogin();
        $sourceId = (int)($input['source_match_id'] ?? 0);
        $targetId = (int)($input['target_match_id'] ?? 0);
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) jsonError('Choose two different matches.');

        $source = Database::fetch("SELECT * FROM matches WHERE id = ?", [$sourceId]);
        $target = Database::fetch("SELECT * FROM matches WHERE id = ?", [$targetId]);
        if (!$source || !$target) jsonError('Match not found');
        if ((int)$source['tournament_id'] !== (int)$target['tournament_id']) jsonError('Matches must belong to the same tournament.');
        if (!Auth::canManageTournament((int)$source['tournament_id'])) jsonError('Access denied', 403);
        if (in_array($source['status'], ['live','finished'], true) || in_array($target['status'], ['live','finished'], true)) {
            jsonError('Live or finished matches cannot be moved.');
        }

        // True swap: the source pairing moves to the target and the target pairing moves back.
        // This prevents the old copy behavior that produced duplicate bracket teams.
        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            Database::update('matches', [
                'team1_id' => $target['team1_id'],
                'team2_id' => $target['team2_id'],
                'team1_score' => 0,
                'team2_score' => 0,
                'winner_id' => null,
                'loser_id' => null,
            ], 'id = ?', [$sourceId]);
            Database::update('matches', [
                'team1_id' => $source['team1_id'],
                'team2_id' => $source['team2_id'],
                'team1_score' => 0,
                'team2_score' => 0,
                'winner_id' => null,
                'loser_id' => null,
            ], 'id = ?', [$targetId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonError('Unable to swap the matches safely.');
        }
        logActivity('swap_matches', 'match', $sourceId, "Swapped with match {$targetId}");
        jsonSuccess([], 'Match pairings swapped without duplication');

    case 'update_seed':
        Auth::requireLogin();
        $tournamentId = (int)($input['tournament_id'] ?? 0);
        $teamId = (int)($input['team_id'] ?? 0);
        $seed = (int)($input['seed'] ?? 0);
        if (!Auth::canManageTournament($tournamentId)) jsonError('Access denied', 403);
        
        $result = BracketGenerator::updateSeed($tournamentId, $teamId, $seed);
        jsonResponse($result);

    default:
        jsonError('Invalid action', 400);
}
