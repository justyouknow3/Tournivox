<?php
/**
 * Matches API
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bracket.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $match = Database::fetch(
            "SELECT m.*, t1.name as team1_name, t2.name as team2_name
             FROM matches m
             LEFT JOIN teams t1 ON m.team1_id = t1.id
             LEFT JOIN teams t2 ON m.team2_id = t2.id
             WHERE m.id = ?",
            [$id]
        );
        if (!$match) jsonError('Match not found', 404);
        $teams = Database::fetchAll(
            "SELECT DISTINCT t.id, t.name
             FROM registrations r
             JOIN teams t ON t.id = r.team_id
             WHERE r.tournament_id = ? AND r.status = 'approved'
             ORDER BY COALESCE(r.seed, 999999), t.name",
            [(int)$match['tournament_id']]
        );
        jsonSuccess(['match' => $match, 'teams' => $teams]);

    case 'update_teams':
        Auth::requireLogin();
        $matchId = (int)($input['match_id'] ?? 0);
        $team1Id = (int)($input['team1_id'] ?? 0);
        $team2Id = (int)($input['team2_id'] ?? 0);
        $match = Database::fetch("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) jsonError('Match not found', 404);
        if (!Auth::canManageTournament((int)$match['tournament_id'])) jsonError('Access denied', 403);
        if (in_array($match['status'], ['live','finished'], true)) jsonError('Teams cannot be changed after the match starts.');
        if ($team1Id <= 0 || $team2Id <= 0) jsonError('Select both teams.');
        if ($team1Id === $team2Id) jsonError('A team cannot play against itself.');
        $validCount = Database::count('registrations', "tournament_id=? AND status='approved' AND team_id IN (?,?)", [(int)$match['tournament_id'], $team1Id, $team2Id]);
        if ($validCount !== 2) jsonError('Both teams must be approved for this tournament.');

        Database::update('matches', [
            'team1_id' => $team1Id,
            'team2_id' => $team2Id,
            'team1_score' => 0,
            'team2_score' => 0,
            'winner_id' => null,
            'loser_id' => null,
            'status' => 'waiting',
        ], 'id = ?', [$matchId]);
        logActivity('update_match_teams', 'match', $matchId, "Teams: {$team1Id} vs {$team2Id}");
        jsonSuccess([], 'Bracket teams updated');

    case 'update':
        Auth::requireLogin();
        $matchId = (int)($input['match_id'] ?? 0);
        $match = Database::fetch("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) jsonError('Match not found', 404);
        if (!Auth::canManageTournament($match['tournament_id'])) jsonError('Access denied', 403);

        $update = array_filter([
            'best_of' => $input['best_of'] ?? null,
            'status' => $input['status'] ?? null,
            'notes' => $input['notes'] ?? null,
            'team1_bans' => $input['team1_bans'] ?? null,
            'team2_bans' => $input['team2_bans'] ?? null,
            'team1_picks' => $input['team1_picks'] ?? null,
            'team2_picks' => $input['team2_picks'] ?? null,
            'team1_score' => isset($input['team1_score']) ? (int)$input['team1_score'] : null,
            'team2_score' => isset($input['team2_score']) ? (int)$input['team2_score'] : null,
        ], fn($v) => $v !== null);

        Database::update('matches', $update, 'id = ?', [$matchId]);
        logActivity('update_match', 'match', $matchId);
        jsonSuccess([], 'Match updated');

    case 'declare_winner':
        Auth::requireLogin();
        $matchId = (int)($input['match_id'] ?? 0);
        $winnerId = (int)($input['winner_id'] ?? 0);
        $match = Database::fetch("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) jsonError('Match not found', 404);
        if (!Auth::canManageTournament($match['tournament_id'])) jsonError('Access denied', 403);

        $result = BracketGenerator::declareWinner(
            $matchId, $winnerId,
            (int)($input['team1_score'] ?? 0),
            (int)($input['team2_score'] ?? 0)
        );
        jsonResponse($result, $result['success'] ? 200 : 400);

    case 'declare_draw':
        Auth::requireLogin();
        $matchId = (int)($input['match_id'] ?? 0);
        $match = Database::fetch("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) jsonError('Match not found', 404);
        if (!Auth::canManageTournament((int)$match['tournament_id'])) jsonError('Access denied', 403);

        $result = BracketGenerator::declareDraw(
            $matchId,
            (int)($input['team1_score'] ?? 0),
            (int)($input['team2_score'] ?? 0)
        );
        jsonResponse($result, $result['success'] ? 200 : 400);

    case 'list':
        $tournamentId = (int)($_GET['tournament_id'] ?? 0);
        $matches = Database::fetchAll(
            "SELECT m.*, t1.name as team1_name, t2.name as team2_name
             FROM matches m
             LEFT JOIN teams t1 ON m.team1_id = t1.id
             LEFT JOIN teams t2 ON m.team2_id = t2.id
             WHERE m.tournament_id = ? ORDER BY m.match_number",
            [$tournamentId]
        );
        jsonSuccess(['matches' => $matches]);

    default:
        jsonError('Invalid action', 400);
}
