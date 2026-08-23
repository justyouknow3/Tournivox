<?php
/** TOURNIVOX Bracketing Search API */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
Auth::requireLogin();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) jsonSuccess(['results' => []]);

$like = "%{$q}%";
$results = [];

$tournaments = Database::fetchAll(
    "SELECT id, name, game FROM tournaments WHERE name LIKE ? OR game LIKE ? LIMIT 5",
    [$like, $like]
);
foreach ($tournaments as $t) {
    $results[] = [
        'title' => $t['name'],
        'subtitle' => gameLabel($t['game']),
        'type' => 'Tournament',
        'url' => APP_URL . '/tournaments/view.php?id=' . $t['id'],
    ];
}

$teams = Database::fetchAll(
    "SELECT id, name, captain_name FROM teams WHERE name LIKE ? OR captain_name LIKE ? LIMIT 5",
    [$like, $like]
);
foreach ($teams as $t) {
    $results[] = [
        'title' => $t['name'],
        'subtitle' => 'Captain: ' . $t['captain_name'],
        'type' => 'Team',
        'url' => APP_URL . '/teams/view.php?id=' . $t['id'],
    ];
}

$players = Database::fetchAll(
    "SELECT p.id, p.ign, p.real_name, t.name AS team_name, t.id AS team_id
     FROM players p JOIN teams t ON p.team_id = t.id
     WHERE p.ign LIKE ? OR p.real_name LIKE ? LIMIT 5",
    [$like, $like]
);
foreach ($players as $p) {
    $results[] = [
        'title' => $p['ign'],
        'subtitle' => $p['real_name'] . ' · ' . $p['team_name'],
        'type' => 'Player',
        'url' => APP_URL . '/teams/view.php?id=' . $p['team_id'],
    ];
}

$users = Database::fetchAll(
    "SELECT user_id, first_name, last_name, role
     FROM users
     WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
       AND role IN ('organizer','bracket_admin','admin')
     LIMIT 3",
    [$like, $like, $like]
);
foreach ($users as $u) {
    $name = trim($u['first_name'] . ' ' . $u['last_name']);
    $results[] = [
        'title' => $name,
        'subtitle' => roleLabel($u['role']),
        'type' => 'Organizer',
        'url' => APP_URL . '/admin/users.php',
    ];
}

jsonSuccess(['results' => $results]);
