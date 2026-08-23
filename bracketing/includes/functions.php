<?php
/**
 * TOURNIVOX Bracketing Manager - Helper Functions
 */

require_once __DIR__ . '/db.php';

// CSRF Protection
function generateCSRFToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken(?string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token ?? '');
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// Sanitization
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeArray(array $data): array {
    return array_map(fn($v) => is_string($v) ? sanitize($v) : $v, $data);
}

// Slug generation
function generateSlug(string $text): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function uniqueSlug(string $text, string $table, ?int $excludeId = null): string {
    $slug = generateSlug($text);
    $original = $slug;
    $counter = 1;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        if (!Database::fetch($sql, $params)) break;
        $slug = $original . '-' . $counter++;
    }
    return $slug;
}

// File upload
function uploadFile(array $file, string $subdir = ''): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_UPLOAD_SIZE) return null;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed = ALLOWED_IMAGE_TYPES;
    if (defined('STUDIO_ROOT')) {
        $allowed = array_merge($allowed, ['image/svg+xml', 'video/mp4']);
    }
    
    if (!in_array($mime, $allowed)) return null;
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('tournivox_') . '.' . $ext;
    $dir = UPLOAD_PATH . ($subdir ? '/' . $subdir : '');
    
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return ($subdir ? $subdir . '/' : '') . $filename;
    }
    return null;
}

function deleteFile(?string $path): void {
    if ($path && file_exists(UPLOAD_PATH . '/' . $path)) {
        unlink(UPLOAD_PATH . '/' . $path);
    }
}

// JSON response for API
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

function jsonSuccess(array $data = [], string $message = 'Success'): void {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

// Activity logging
function logActivity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void {
    Database::insert('logs', [
        'user_id' => $_SESSION['user_id'] ?? null,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

// Notifications
function createNotification(int $userId, string $title, string $message, string $type = 'system', ?string $link = null): void {
    Database::insert('notifications', [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link,
    ]);
}

function notifyOrganizer(int $tournamentId, string $title, string $message, string $type = 'tournament'): void {
    $tournament = Database::fetch("SELECT organizer_id FROM tournaments WHERE id = ?", [$tournamentId]);
    if ($tournament) {
        createNotification($tournament['organizer_id'], $title, $message, $type, APP_URL . '/tournaments/view.php?id=' . $tournamentId);
    }
}

// Pagination
function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array {
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

function paginationHTML(array $pagination, string $baseUrl): string {
    if ($pagination['total_pages'] <= 1) return '';
    $html = '<nav class="pagination-nav"><ul class="pagination">';
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$baseUrl}&page={$i}\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

// Date formatting
function formatDate(?string $date, string $format = 'M d, Y'): string {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

// Role labels
function roleLabel(string $role): string {
    return match($role) {
        'bracket_admin' => 'Bracket Administrator',
        'admin' => 'System Administrator',
        'organizer' => 'Tournament Organizer',
        'team_captain' => 'Team Captain',
        'staff' => 'Staff',
        'broadcast_operator' => 'Broadcast Operator',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}

function gameLabel(string $game): string {
    return match(strtoupper($game)) {
        'ML', 'MLBB' => 'Mobile Legends: Bang Bang',
        'CODM' => 'Call of Duty: Mobile',
        'HOK' => 'Honor of Kings',
        'VALORANT' => 'VALORANT',
        'DOTA2' => 'Dota 2',
        'LOL' => 'League of Legends',
        'PUBGM' => 'PUBG Mobile',
        default => $game,
    };
}

function supportedGames(): array {
    return [
        'MLBB' => 'Mobile Legends: Bang Bang',
        'CODM' => 'Call of Duty: Mobile',
        'HOK' => 'Honor of Kings',
        'VALORANT' => 'VALORANT',
        'DOTA2' => 'Dota 2',
        'LOL' => 'League of Legends',
        'PUBGM' => 'PUBG Mobile',
    ];
}

function statusBadge(string $status): string {
    $classes = [
        'registration_open' => 'badge-success',
        'ongoing' => 'badge-warning',
        'finished' => 'badge-secondary',
        'waiting' => 'badge-info',
        'live' => 'badge-danger pulse',
        'approved' => 'badge-success',
        'pending' => 'badge-warning',
        'rejected' => 'badge-danger',
    ];
    $labels = [
        'registration_open' => 'Registration Open',
        'ongoing' => 'Ongoing',
        'finished' => 'Finished',
        'waiting' => 'Waiting',
        'live' => 'LIVE',
    ];
    $class = $classes[$status] ?? 'badge-secondary';
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"badge {$class}\">{$label}</span>";
}

function tournamentTypeLabel(string $type): string {
    return match($type) {
        'single_elimination' => 'Single Elimination',
        'double_elimination' => 'Double Elimination',
        'round_robin' => 'Round Robin',
        'point_based' => 'Point Based',
        default => $type,
    };
}



// Convert formatted money input (for example 1,000) to a database-safe number.
function moneyValue(string|int|float|null $value): float {
    $clean = preg_replace('/[^0-9.]/', '', (string)$value);
    return $clean === '' ? 0.0 : round((float)$clean, 2);
}

// Display monetary values with comma separators while keeping the stored value numeric.
function formatMoney(string|int|float|null $value, bool $withCurrency = true): string {
    if ($value === null || $value === '') return 'TBA';
    $amount = (float)$value;
    $formatted = number_format($amount, $amount == floor($amount) ? 0 : 2, '.', ',');
    return ($withCurrency ? '₱' : '') . $formatted;
}


// Automatically start tournaments when their scheduled Manila date and time arrive.
function syncTournamentStatuses(): void {
    try {
        Database::query(
            "UPDATE tournaments SET status = 'ongoing'
             WHERE status = 'registration_open'
             AND start_time IS NOT NULL
             AND TIMESTAMP(tournament_date, start_time) <= NOW()"
        );
    } catch (Throwable $e) {
        // Keep pages available if an older database is still being upgraded.
    }
}

function isTournamentRegistrationOpen(array $tournament): bool {
    if (($tournament['status'] ?? '') !== 'registration_open') return false;
    if (!empty($tournament['registration_deadline']) && strtotime($tournament['registration_deadline']) <= time()) return false;
    if (!empty($tournament['tournament_date']) && !empty($tournament['start_time'])
        && strtotime($tournament['tournament_date'] . ' ' . $tournament['start_time']) <= time()) return false;
    return true;
}
// Redirect helper
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// Flash messages
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// Win rate calculation
function winRate(int $wins, int $losses): float {
    $total = $wins + $losses;
    return $total > 0 ? round(($wins / $total) * 100, 1) : 0;
}

// Next power of 2 for bracket sizing
function nextPowerOf2(int $n): int {
    $power = 1;
    while ($power < $n) $power *= 2;
    return $power;
}

// Round names for brackets
function getRoundName(int $roundNum, int $totalRounds, string $bracketType = 'winners'): string {
    return "Round {$roundNum}";
}
