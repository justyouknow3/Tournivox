<?php
/**
 * Auth API
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($input['action'] ?? '') {
    case 'update_theme':
        if (!Auth::check()) jsonError('Not authenticated', 401);
        $theme = in_array($input['theme'], ['dark', 'light']) ? $input['theme'] : 'dark';
        Auth::updateProfile(Auth::id(), ['theme' => $theme]);
        jsonSuccess(['theme' => $theme]);

    default:
        jsonError('Invalid action', 400);
}
