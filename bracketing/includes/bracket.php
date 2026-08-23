<?php
/**
 * TOURNIVOX Bracketing Manager - Bracket Generator
 *
 * Handles Single Elimination, Double Elimination, Round Robin and Point-Based
 * tournament flows. Broadcasting/streaming is intentionally outside this module.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class BracketGenerator
{
    /**
     * Generate a fresh bracket from approved tournament registrations.
     */
    public static function generate(int $tournamentId, string $seedingType = 'random'): array
    {
        $tournament = Database::fetch("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$tournament) {
            return ['success' => false, 'message' => 'Tournament not found'];
        }

        $teams = Database::fetchAll(
            "SELECT t.*, r.seed
             FROM teams t
             JOIN registrations r ON t.id = r.team_id
             WHERE r.tournament_id = ? AND r.status = 'approved'
             ORDER BY COALESCE(r.seed, 999999), r.registered_at, t.id",
            [$tournamentId]
        );

        if (count($teams) < 2) {
            return ['success' => false, 'message' => 'At least 2 approved teams are required to generate a bracket.'];
        }

        if ($seedingType === 'random') {
            shuffle($teams);
        }

        // Persist the exact order used for generation.
        foreach ($teams as $i => $team) {
            Database::update(
                'registrations',
                ['seed' => $i + 1],
                'tournament_id = ? AND team_id = ?',
                [$tournamentId, $team['id']]
            );
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // Re-generating is destructive only to this tournament's bracket data.
            Database::delete('matches', 'tournament_id = ?', [$tournamentId]);
            Database::delete('rounds', 'bracket_id IN (SELECT id FROM brackets WHERE tournament_id = ?)', [$tournamentId]);
            Database::delete('brackets', 'tournament_id = ?', [$tournamentId]);
            Database::delete('standings', 'tournament_id = ?', [$tournamentId]);

            $result = match ($tournament['tournament_type']) {
                'single_elimination' => self::generateSingleElimination($tournamentId, $teams),
                'double_elimination' => self::generateDoubleElimination($tournamentId, $teams),
                'round_robin', 'point_based' => self::generateRoundRobin($tournamentId, $teams),
                default => ['success' => false, 'message' => 'Unknown tournament type'],
            };

            if (!($result['success'] ?? false)) {
                $pdo->rollBack();
                return $result;
            }

            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('TOURNIVOX bracket generation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to generate the bracket. Check the tournament setup and try again.'];
        }
    }

    /**
     * Distribute byes without creating empty-vs-empty first-round matches.
     * In manual seeding, the earliest seeds receive the byes.
     */
    private static function buildFirstRoundPairs(array $teams, int $bracketSize): array
    {
        $teamCount = count($teams);
        $byeCount = max(0, $bracketSize - $teamCount);
        $pairs = [];
        $index = 0;

        for ($i = 0; $i < $byeCount; $i++) {
            $pairs[] = [$teams[$index++] ?? null, null];
        }

        while ($index < $teamCount) {
            $pairs[] = [
                $teams[$index++] ?? null,
                $teams[$index++] ?? null,
            ];
        }

        return $pairs;
    }

    private static function matchStateForPair(?array $team1, ?array $team2): array
    {
        if ($team1 && !$team2) {
            return ['status' => 'finished', 'winner_id' => (int)$team1['id']];
        }
        if (!$team1 && $team2) {
            return ['status' => 'finished', 'winner_id' => (int)$team2['id']];
        }

        return ['status' => 'waiting', 'winner_id' => null];
    }

    /**
     * Populate a known slot in a later match from an already-resolved bye match.
     */
    private static function copyResolvedWinnerToSlot(int $sourceMatchId, int $targetMatchId, string $slot): void
    {
        $source = Database::fetch("SELECT status, winner_id FROM matches WHERE id = ?", [$sourceMatchId]);
        if ($source && $source['status'] === 'finished' && !empty($source['winner_id'])) {
            Database::update('matches', [$slot => (int)$source['winner_id']], 'id = ?', [$targetMatchId]);
        }
    }

    private static function initializeStandings(int $tournamentId, array $teams): void
    {
        foreach ($teams as $team) {
            Database::insert('standings', [
                'tournament_id' => $tournamentId,
                'team_id' => (int)$team['id'],
            ]);
        }
    }

    /**
     * Resolve bracket byes after all source matches feeding a slot are known.
     * This also handles non-power-of-two double-elimination brackets where a
     * losers-bracket match may receive only one team (or no team) from byes.
     */
    private static function autoResolveByes(int $tournamentId): void
    {
        $changed = true;
        $guard = 0;

        while ($changed && $guard++ < 200) {
            $changed = false;
            $candidates = Database::fetchAll(
                "SELECT id, team1_id, team2_id, next_match_id
                 FROM matches
                 WHERE tournament_id = ? AND status = 'waiting'",
                [$tournamentId]
            );

            foreach ($candidates as $candidate) {
                $matchId = (int)$candidate['id'];
                $sources = Database::fetchAll(
                    "SELECT id, status
                     FROM matches
                     WHERE tournament_id = ?
                       AND (next_match_id = ? OR loser_next_match_id = ?)",
                    [$tournamentId, $matchId, $matchId]
                );

                // First-round/manual matches have no upstream source and must not
                // be auto-completed merely because one team slot is empty.
                if (!$sources) {
                    continue;
                }

                $allResolved = true;
                foreach ($sources as $source) {
                    if ($source['status'] !== 'finished') {
                        $allResolved = false;
                        break;
                    }
                }
                if (!$allResolved) {
                    continue;
                }

                $team1Id = (int)($candidate['team1_id'] ?? 0);
                $team2Id = (int)($candidate['team2_id'] ?? 0);

                // A normal two-team match still needs to be played.
                if ($team1Id > 0 && $team2Id > 0) {
                    continue;
                }

                $winnerId = $team1Id > 0 ? $team1Id : ($team2Id > 0 ? $team2Id : null);
                Database::update('matches', [
                    'status' => 'finished',
                    'winner_id' => $winnerId,
                    'loser_id' => null,
                ], 'id = ?', [$matchId]);

                if ($winnerId && !empty($candidate['next_match_id'])) {
                    self::placeTeamInMatch((int)$candidate['next_match_id'], $winnerId);
                }

                $changed = true;
            }
        }
    }

    /**
     * Single Elimination
     */
    private static function generateSingleElimination(int $tournamentId, array $teams): array
    {
        $bracketId = Database::insert('brackets', [
            'tournament_id' => $tournamentId,
            'bracket_type' => 'winners',
        ]);

        $teamCount = count($teams);
        $bracketSize = nextPowerOf2($teamCount);
        $totalRounds = max(1, (int)log($bracketSize, 2));

        $roundIds = [];
        for ($round = 1; $round <= $totalRounds; $round++) {
            $roundIds[$round] = Database::insert('rounds', [
                'bracket_id' => $bracketId,
                'round_number' => $round,
                'round_name' => getRoundName($round, $totalRounds),
            ]);
        }

        $allMatches = [];
        $matchNumber = 1;
        $pairs = self::buildFirstRoundPairs($teams, $bracketSize);

        $allMatches[1] = [];
        foreach ($pairs as $index => [$team1, $team2]) {
            $state = self::matchStateForPair($team1, $team2);

            $matchId = Database::insert('matches', [
                'tournament_id' => $tournamentId,
                'bracket_id' => $bracketId,
                'round_id' => $roundIds[1],
                'team1_id' => $team1['id'] ?? null,
                'team2_id' => $team2['id'] ?? null,
                'match_number' => $matchNumber++,
                'position_x' => $index,
                'position_y' => 0,
                'status' => $state['status'],
                'winner_id' => $state['winner_id'],
            ]);
            $allMatches[1][] = $matchId;
        }

        for ($round = 2; $round <= $totalRounds; $round++) {
            $allMatches[$round] = [];
            $previous = $allMatches[$round - 1];

            for ($i = 0; $i < count($previous); $i += 2) {
                $matchId = Database::insert('matches', [
                    'tournament_id' => $tournamentId,
                    'bracket_id' => $bracketId,
                    'round_id' => $roundIds[$round],
                    'match_number' => $matchNumber++,
                    'position_x' => (int)($i / 2),
                    'position_y' => $round - 1,
                ]);
                $allMatches[$round][] = $matchId;

                Database::update('matches', ['next_match_id' => $matchId], 'id = ?', [$previous[$i]]);
                self::copyResolvedWinnerToSlot($previous[$i], $matchId, 'team1_id');

                if (isset($previous[$i + 1])) {
                    Database::update('matches', ['next_match_id' => $matchId], 'id = ?', [$previous[$i + 1]]);
                    self::copyResolvedWinnerToSlot($previous[$i + 1], $matchId, 'team2_id');
                }
            }
        }

        self::initializeStandings($tournamentId, $teams);
        self::autoResolveByes($tournamentId);
        Database::update('tournaments', ['status' => 'ongoing', 'champion_team_id' => null], 'id = ?', [$tournamentId]);
        notifyOrganizer($tournamentId, 'Bracket Generated', 'The single-elimination bracket has been generated successfully.');

        return [
            'success' => true,
            'message' => 'Single elimination bracket generated',
            'matches' => $bracketSize - 1,
        ];
    }

    /**
     * Double Elimination
     *
     * Winner flow follows the winners bracket. Losers from Winners Round 1
     * enter Losers Round 1; losers from later Winners rounds enter every even
     * Losers round. Losers-bracket winners then feed forward to Grand Finals.
     */
    private static function generateDoubleElimination(int $tournamentId, array $teams): array
    {
        $winnersBracketId = Database::insert('brackets', [
            'tournament_id' => $tournamentId,
            'bracket_type' => 'winners',
        ]);
        $losersBracketId = Database::insert('brackets', [
            'tournament_id' => $tournamentId,
            'bracket_type' => 'losers',
        ]);
        $grandFinalsBracketId = Database::insert('brackets', [
            'tournament_id' => $tournamentId,
            'bracket_type' => 'grand_finals',
        ]);

        $teamCount = count($teams);
        $bracketSize = nextPowerOf2($teamCount);
        $winnersRounds = max(1, (int)log($bracketSize, 2));
        $matchNumber = 1;

        // ---------------- Winners Bracket ----------------
        $winnersRoundIds = [];
        for ($round = 1; $round <= $winnersRounds; $round++) {
            $winnersRoundIds[$round] = Database::insert('rounds', [
                'bracket_id' => $winnersBracketId,
                'round_number' => $round,
                'round_name' => getRoundName($round, $winnersRounds, 'winners'),
            ]);
        }

        $winnersMatches = [1 => []];
        $pairs = self::buildFirstRoundPairs($teams, $bracketSize);

        foreach ($pairs as $index => [$team1, $team2]) {
            $state = self::matchStateForPair($team1, $team2);
            $matchId = Database::insert('matches', [
                'tournament_id' => $tournamentId,
                'bracket_id' => $winnersBracketId,
                'round_id' => $winnersRoundIds[1],
                'team1_id' => $team1['id'] ?? null,
                'team2_id' => $team2['id'] ?? null,
                'match_number' => $matchNumber++,
                'position_x' => $index,
                'position_y' => 0,
                'status' => $state['status'],
                'winner_id' => $state['winner_id'],
            ]);
            $winnersMatches[1][] = $matchId;
        }

        for ($round = 2; $round <= $winnersRounds; $round++) {
            $winnersMatches[$round] = [];
            $previous = $winnersMatches[$round - 1];

            for ($i = 0; $i < count($previous); $i += 2) {
                $matchId = Database::insert('matches', [
                    'tournament_id' => $tournamentId,
                    'bracket_id' => $winnersBracketId,
                    'round_id' => $winnersRoundIds[$round],
                    'match_number' => $matchNumber++,
                    'position_x' => (int)($i / 2),
                    'position_y' => $round - 1,
                ]);
                $winnersMatches[$round][] = $matchId;

                Database::update('matches', ['next_match_id' => $matchId], 'id = ?', [$previous[$i]]);
                self::copyResolvedWinnerToSlot($previous[$i], $matchId, 'team1_id');

                if (isset($previous[$i + 1])) {
                    Database::update('matches', ['next_match_id' => $matchId], 'id = ?', [$previous[$i + 1]]);
                    self::copyResolvedWinnerToSlot($previous[$i + 1], $matchId, 'team2_id');
                }
            }
        }

        // ---------------- Losers Bracket ----------------
        // For a two-team tournament there is no separate losers round; the
        // Winners Final loser feeds directly into Grand Finals below.
        $losersRounds = max(0, ($winnersRounds - 1) * 2);
        $losersRoundIds = [];
        $losersMatches = [];

        if ($losersRounds > 0) {
            $baseCount = max(1, (int)($bracketSize / 4));

            for ($round = 1; $round <= $losersRounds; $round++) {
                $losersRoundIds[$round] = Database::insert('rounds', [
                    'bracket_id' => $losersBracketId,
                    'round_number' => $round,
                    'round_name' => "Losers Round {$round}",
                ]);

                // Counts follow B/4, B/4, B/8, B/8, ...
                $count = max(1, (int)($baseCount / pow(2, floor(($round - 1) / 2))));
                $losersMatches[$round] = [];

                for ($i = 0; $i < $count; $i++) {
                    $losersMatches[$round][] = Database::insert('matches', [
                        'tournament_id' => $tournamentId,
                        'bracket_id' => $losersBracketId,
                        'round_id' => $losersRoundIds[$round],
                        'match_number' => $matchNumber++,
                        'position_x' => $i,
                        'position_y' => $round - 1,
                    ]);
                }
            }

            // Winners Round 1 losers are paired into Losers Round 1.
            foreach ($winnersMatches[1] as $index => $winnerMatchId) {
                $targetIndex = (int)floor($index / 2);
                if (isset($losersMatches[1][$targetIndex])) {
                    Database::update(
                        'matches',
                        ['loser_next_match_id' => $losersMatches[1][$targetIndex]],
                        'id = ?',
                        [$winnerMatchId]
                    );
                }
            }

            // Losers from Winners Round 2+ enter Losers Round 2, 4, 6, ...
            for ($winnerRound = 2; $winnerRound <= $winnersRounds; $winnerRound++) {
                $loserRound = 2 * ($winnerRound - 1);
                foreach ($winnersMatches[$winnerRound] as $index => $winnerMatchId) {
                    if (isset($losersMatches[$loserRound][$index])) {
                        Database::update(
                            'matches',
                            ['loser_next_match_id' => $losersMatches[$loserRound][$index]],
                            'id = ?',
                            [$winnerMatchId]
                        );
                    }
                }
            }

            // Winners inside the losers bracket move either one-to-one into an
            // even round, or pair together when the next round halves in size.
            for ($round = 1; $round < $losersRounds; $round++) {
                $current = $losersMatches[$round];
                $next = $losersMatches[$round + 1];
                $sameSize = count($current) === count($next);

                foreach ($current as $index => $currentMatchId) {
                    $targetIndex = $sameSize ? $index : (int)floor($index / 2);
                    if (isset($next[$targetIndex])) {
                        Database::update(
                            'matches',
                            ['next_match_id' => $next[$targetIndex]],
                            'id = ?',
                            [$currentMatchId]
                        );
                    }
                }
            }
        }

        // ---------------- Grand Finals ----------------
        $grandFinalRoundId = Database::insert('rounds', [
            'bracket_id' => $grandFinalsBracketId,
            'round_number' => 1,
            'round_name' => 'Grand Finals',
        ]);

        $grandFinalMatchId = Database::insert('matches', [
            'tournament_id' => $tournamentId,
            'bracket_id' => $grandFinalsBracketId,
            'round_id' => $grandFinalRoundId,
            'match_number' => $matchNumber++,
            'position_x' => 0,
            'position_y' => 0,
        ]);

        $winnersFinalMatchId = end($winnersMatches[$winnersRounds]);
        Database::update('matches', ['next_match_id' => $grandFinalMatchId], 'id = ?', [$winnersFinalMatchId]);

        if ($losersRounds > 0) {
            $losersFinalMatchId = end($losersMatches[$losersRounds]);
            Database::update('matches', ['next_match_id' => $grandFinalMatchId], 'id = ?', [$losersFinalMatchId]);
        } else {
            // Two-team double elimination: the loser of the Winners Final becomes
            // the second Grand Finals participant.
            Database::update('matches', ['loser_next_match_id' => $grandFinalMatchId], 'id = ?', [$winnersFinalMatchId]);
        }

        self::initializeStandings($tournamentId, $teams);
        self::autoResolveByes($tournamentId);
        Database::update('tournaments', ['status' => 'ongoing', 'champion_team_id' => null], 'id = ?', [$tournamentId]);
        notifyOrganizer($tournamentId, 'Bracket Generated', 'The double-elimination bracket has been generated successfully.');

        return [
            'success' => true,
            'message' => 'Double elimination bracket generated',
            'matches' => $matchNumber - 1,
        ];
    }

    /**
     * Round Robin / Point-Based schedule using the circle method.
     * Each team appears at most once in a generated round.
     */
    private static function generateRoundRobin(int $tournamentId, array $teams): array
    {
        $bracketId = Database::insert('brackets', [
            'tournament_id' => $tournamentId,
            'bracket_type' => 'round_robin',
        ]);

        $rotation = array_values($teams);
        if (count($rotation) % 2 !== 0) {
            $rotation[] = null; // bye placeholder
        }

        $slotCount = count($rotation);
        $totalRounds = max(1, $slotCount - 1);
        $matchNumber = 1;

        for ($round = 1; $round <= $totalRounds; $round++) {
            $roundId = Database::insert('rounds', [
                'bracket_id' => $bracketId,
                'round_number' => $round,
                'round_name' => "Round {$round}",
                'best_of' => 'BO2',
            ]);

            for ($i = 0; $i < (int)($slotCount / 2); $i++) {
                $team1 = $rotation[$i];
                $team2 = $rotation[$slotCount - 1 - $i];

                if ($team1 === null || $team2 === null) {
                    continue;
                }

                Database::insert('matches', [
                    'tournament_id' => $tournamentId,
                    'bracket_id' => $bracketId,
                    'round_id' => $roundId,
                    'team1_id' => (int)$team1['id'],
                    'team2_id' => (int)$team2['id'],
                    'match_number' => $matchNumber++,
                    'position_x' => $i,
                    'position_y' => $round - 1,
                    'best_of' => 'BO2',
                ]);
            }

            // Keep the first slot fixed and rotate the rest clockwise.
            if ($slotCount > 2) {
                $last = array_pop($rotation);
                array_splice($rotation, 1, 0, [$last]);
            }
        }

        self::initializeStandings($tournamentId, $teams);
        Database::update('tournaments', ['status' => 'ongoing', 'champion_team_id' => null], 'id = ?', [$tournamentId]);
        notifyOrganizer($tournamentId, 'Bracket Generated', 'The round-robin schedule has been generated successfully.');

        return [
            'success' => true,
            'message' => 'Round robin schedule generated',
            'matches' => $matchNumber - 1,
        ];
    }

    /**
     * Declare a winner, update standings and route winner/loser to their next slots.
     */
    public static function declareWinner(int $matchId, int $winnerId, int $team1Score = 0, int $team2Score = 0): array
    {
        $match = Database::fetch("SELECT * FROM matches WHERE id = ?", [$matchId]);
        if (!$match) {
            return ['success' => false, 'message' => 'Match not found'];
        }

        if ($match['status'] === 'finished') {
            return ['success' => false, 'message' => 'This match is already finished.'];
        }

        $team1Id = (int)($match['team1_id'] ?? 0);
        $team2Id = (int)($match['team2_id'] ?? 0);
        if ($team1Id <= 0 || $team2Id <= 0 || !in_array($winnerId, [$team1Id, $team2Id], true)) {
            return ['success' => false, 'message' => 'Invalid winner or incomplete team pairing.'];
        }

        if ($team1Score < 0 || $team2Score < 0 || $team1Score === $team2Score) {
            return ['success' => false, 'message' => 'A winning result must have a non-tied, non-negative score.'];
        }

        if (($winnerId === $team1Id && $team1Score <= $team2Score) ||
            ($winnerId === $team2Id && $team2Score <= $team1Score)) {
            return ['success' => false, 'message' => 'The selected winner does not match the entered score.'];
        }

        $loserId = ($winnerId === $team1Id) ? $team2Id : $team1Id;

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            Database::update('matches', [
                'winner_id' => $winnerId,
                'loser_id' => $loserId,
                'team1_score' => $team1Score,
                'team2_score' => $team2Score,
                'status' => 'finished',
            ], 'id = ?', [$matchId]);

            if (!empty($match['next_match_id'])) {
                self::placeTeamInMatch((int)$match['next_match_id'], $winnerId);
            }

            if (!empty($match['loser_next_match_id']) && $loserId > 0) {
                self::placeTeamInMatch((int)$match['loser_next_match_id'], $loserId);
            }

            self::updateStandings((int)$match['tournament_id'], $winnerId, $loserId);
            self::autoResolveByes((int)$match['tournament_id']);
            self::finishTournamentIfComplete((int)$match['tournament_id'], $matchId, $winnerId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('TOURNIVOX declareWinner failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save the match result safely.'];
        }

        logActivity('declare_winner', 'match', $matchId, "Winner: {$winnerId}; Score: {$team1Score}-{$team2Score}");
        return ['success' => true, 'message' => 'Winner declared and advanced'];
    }

    /**
     * Complete a drawn BO2 Round Robin/Point-Based match.
     */
    public static function declareDraw(int $matchId, int $team1Score, int $team2Score): array
    {
        $match = Database::fetch(
            "SELECT m.*, t.tournament_type
             FROM matches m
             JOIN tournaments t ON t.id = m.tournament_id
             WHERE m.id = ?",
            [$matchId]
        );

        if (!$match) {
            return ['success' => false, 'message' => 'Match not found'];
        }
        if ($match['status'] === 'finished') {
            return ['success' => false, 'message' => 'This match is already finished.'];
        }
        if (!in_array($match['tournament_type'], ['round_robin', 'point_based'], true)) {
            return ['success' => false, 'message' => 'Draws are only available for Round Robin or Point-Based tournaments.'];
        }
        if (empty($match['team1_id']) || empty($match['team2_id'])) {
            return ['success' => false, 'message' => 'Both teams are required.'];
        }
        if ($team1Score < 0 || $team2Score < 0 || $team1Score !== $team2Score) {
            return ['success' => false, 'message' => 'A draw must have equal non-negative scores.'];
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            Database::update('matches', [
                'winner_id' => null,
                'loser_id' => null,
                'team1_score' => $team1Score,
                'team2_score' => $team2Score,
                'status' => 'finished',
            ], 'id = ?', [$matchId]);

            self::updateDrawStandings(
                (int)$match['tournament_id'],
                (int)$match['team1_id'],
                (int)$match['team2_id']
            );
            self::finishTournamentIfComplete((int)$match['tournament_id'], $matchId, null);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('TOURNIVOX declareDraw failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save the draw safely.'];
        }

        logActivity('declare_draw', 'match', $matchId, "Draw: {$team1Score}-{$team2Score}");
        return ['success' => true, 'message' => 'Draw recorded'];
    }

    /**
     * Fill the first open team slot in a target match without duplicating a team.
     */
    private static function placeTeamInMatch(int $targetMatchId, int $teamId): void
    {
        $target = Database::fetch("SELECT team1_id, team2_id FROM matches WHERE id = ?", [$targetMatchId]);
        if (!$target) {
            return;
        }

        if ((int)($target['team1_id'] ?? 0) === $teamId || (int)($target['team2_id'] ?? 0) === $teamId) {
            return;
        }

        if (empty($target['team1_id'])) {
            Database::update('matches', ['team1_id' => $teamId], 'id = ?', [$targetMatchId]);
        } elseif (empty($target['team2_id'])) {
            Database::update('matches', ['team2_id' => $teamId], 'id = ?', [$targetMatchId]);
        } else {
            throw new RuntimeException("Target match {$targetMatchId} has no open team slot.");
        }
    }

    /**
     * Winner/loss standings update. Round Robin uses the TOURNIVOX 3/1/0 model.
     */
    public static function updateStandings(int $tournamentId, int $winnerId, ?int $loserId): void
    {
        $tournament = Database::fetch("SELECT tournament_type FROM tournaments WHERE id = ?", [$tournamentId]);
        $type = $tournament['tournament_type'] ?? '';
        $winPoints = ($type === 'point_based') ? 1 : 3;

        Database::query(
            "UPDATE standings
             SET played = played + 1,
                 wins = wins + 1,
                 points = points + ?
             WHERE tournament_id = ? AND team_id = ?",
            [$winPoints, $tournamentId, $winnerId]
        );

        if ($loserId) {
            Database::query(
                "UPDATE standings
                 SET played = played + 1,
                     losses = losses + 1
                 WHERE tournament_id = ? AND team_id = ?",
                [$tournamentId, $loserId]
            );
        }

        self::recalculateRanks($tournamentId);
    }

    private static function updateDrawStandings(int $tournamentId, int $team1Id, int $team2Id): void
    {
        foreach ([$team1Id, $team2Id] as $teamId) {
            Database::query(
                "UPDATE standings
                 SET played = played + 1,
                     draws = draws + 1,
                     points = points + 1
                 WHERE tournament_id = ? AND team_id = ?",
                [$tournamentId, $teamId]
            );
        }

        self::recalculateRanks($tournamentId);
    }

    private static function recalculateRanks(int $tournamentId): void
    {
        $standings = Database::fetchAll(
            "SELECT id
             FROM standings
             WHERE tournament_id = ?
             ORDER BY points DESC, wins DESC, losses ASC, team_id ASC",
            [$tournamentId]
        );

        foreach ($standings as $rank => $standing) {
            Database::update('standings', ['rank_position' => $rank + 1], 'id = ?', [(int)$standing['id']]);
        }
    }

    /**
     * Finalize the tournament at the correct endpoint for each format.
     */
    private static function finishTournamentIfComplete(int $tournamentId, int $completedMatchId, ?int $winnerId): void
    {
        $tournament = Database::fetch("SELECT * FROM tournaments WHERE id = ?", [$tournamentId]);
        if (!$tournament || $tournament['status'] === 'finished') {
            return;
        }

        $type = $tournament['tournament_type'];
        $finished = false;
        $championId = null;

        if ($type === 'single_elimination') {
            $match = Database::fetch(
                "SELECT m.next_match_id, b.bracket_type
                 FROM matches m
                 LEFT JOIN brackets b ON b.id = m.bracket_id
                 WHERE m.id = ?",
                [$completedMatchId]
            );
            if ($match && $match['bracket_type'] === 'winners' && empty($match['next_match_id']) && $winnerId) {
                $finished = true;
                $championId = $winnerId;
            }
        } elseif ($type === 'double_elimination') {
            $match = Database::fetch(
                "SELECT b.bracket_type
                 FROM matches m
                 LEFT JOIN brackets b ON b.id = m.bracket_id
                 WHERE m.id = ?",
                [$completedMatchId]
            );
            if ($match && $match['bracket_type'] === 'grand_finals' && $winnerId) {
                $finished = true;
                $championId = $winnerId;
            }
        } elseif (in_array($type, ['round_robin', 'point_based'], true)) {
            $remaining = Database::count('matches', "tournament_id = ? AND status <> 'finished'", [$tournamentId]);
            if ($remaining === 0) {
                self::recalculateRanks($tournamentId);
                $top = Database::fetch(
                    "SELECT team_id
                     FROM standings
                     WHERE tournament_id = ?
                     ORDER BY rank_position ASC, points DESC, wins DESC
                     LIMIT 1",
                    [$tournamentId]
                );
                $finished = true;
                $championId = $top ? (int)$top['team_id'] : null;
            }
        }

        if ($finished) {
            Database::update('tournaments', [
                'status' => 'finished',
                'champion_team_id' => $championId,
            ], 'id = ?', [$tournamentId]);

            notifyOrganizer($tournamentId, 'Tournament Finished!', 'The tournament has concluded and the champion has been recorded.');
        } else {
            notifyOrganizer($tournamentId, 'Match Completed', 'A match result has been recorded and the bracket/standings were updated.');
        }
    }

    /**
     * Swap teams in a bracket match.
     */
    public static function swapTeams(int $matchId, int $team1Id, int $team2Id): array
    {
        Database::update('matches', [
            'team1_id' => $team1Id,
            'team2_id' => $team2Id,
        ], 'id = ?', [$matchId]);

        logActivity('swap_teams', 'match', $matchId);
        return ['success' => true, 'message' => 'Teams swapped'];
    }

    /**
     * Update manual tournament seed.
     */
    public static function updateSeed(int $tournamentId, int $teamId, int $newSeed): array
    {
        if ($newSeed < 1) {
            return ['success' => false, 'message' => 'Seed must be 1 or greater.'];
        }

        Database::update(
            'registrations',
            ['seed' => $newSeed],
            'tournament_id = ? AND team_id = ?',
            [$tournamentId, $teamId]
        );

        logActivity('update_seed', 'registration', $teamId, "Tournament {$tournamentId}; Seed {$newSeed}");
        return ['success' => true, 'message' => 'Seed updated'];
    }
}
