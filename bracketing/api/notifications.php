<?php
/**
 * Notifications API
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($input['action'] === 'mark_read') {
        Database::update('notifications', ['is_read' => 1], 'id = ? AND user_id = ?', [
            (int)($input['id'] ?? 0), Auth::id()
        ]);
        jsonSuccess();
    }
    if ($input['action'] === 'mark_all_read') {
        Database::update('notifications', ['is_read' => 1], 'user_id = ?', [Auth::id()]);
        jsonSuccess();
    }
}

$notifications = Database::fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [Auth::id()]
);

jsonSuccess(['notifications' => $notifications]);
