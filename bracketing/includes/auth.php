<?php
/**
 * TOURNIVOX Bracketing Manager - Authentication
 * Uses the central TOURNIVOX users table/session.
 */

require_once __DIR__ . '/functions.php';

class Auth {
    public static function login(string $identifier, string $password, bool $remember = false): bool {
        $user = Database::fetch(
            "SELECT *, CONCAT(first_name, ' ', last_name) AS full_name
             FROM users
             WHERE (email = ? OR username = ?) AND status = 'active'
             LIMIT 1",
            [$identifier, $identifier]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        self::setSession($user);
        unset($_SESSION['flash']);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            Database::update(
                'users',
                ['remember_token' => hash('sha256', $token)],
                'user_id = ?',
                [(int)$user['user_id']]
            );
            setcookie('tournivox_remember_token', $token, [
                'expires' => time() + REMEMBER_LIFETIME,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']),
            ]);
        }

        logActivity('login', 'user', (int)$user['user_id']);
        return true;
    }

    public static function register(array $data): int|false {
        if (Database::fetch('SELECT user_id FROM users WHERE email = ?', [$data['email']])) {
            return false;
        }
        if (Database::fetch('SELECT user_id FROM users WHERE username = ?', [$data['username']])) {
            return false;
        }

        $userId = Database::insert('users', [
            'first_name' => trim($data['first_name'] ?? ''),
            'last_name' => trim($data['last_name'] ?? ''),
            'username' => trim($data['username'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
            'role' => 'team_captain',
            'status' => 'active',
        ]);

        logActivity('register', 'user', $userId);
        return $userId;
    }

    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            logActivity('logout', 'user', (int)$_SESSION['user_id']);
        }

        if (isset($_COOKIE['tournivox_remember_token'])) {
            $options = [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']),
            ];
            setcookie('tournivox_remember_token', '', $options);
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function checkRememberToken(): void {
        if (self::check() || empty($_COOKIE['tournivox_remember_token'])) {
            return;
        }

        $token = hash('sha256', $_COOKIE['tournivox_remember_token']);
        $user = Database::fetch(
            "SELECT *, CONCAT(first_name, ' ', last_name) AS full_name
             FROM users
             WHERE remember_token = ? AND status = 'active'
             LIMIT 1",
            [$token]
        );

        if ($user) {
            self::setSession($user);
        }
    }

    private static function setSession(array $user): void {
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?? null;
        $_SESSION['theme'] = $user['theme'] ?? 'dark';
    }

    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        $user = Database::fetch(
            "SELECT *, CONCAT(first_name, ' ', last_name) AS full_name,
                    CASE WHEN status = 'active' THEN 1 ELSE 0 END AS is_active
             FROM users WHERE user_id = ? LIMIT 1",
            [(int)$_SESSION['user_id']]
        );

        if (!$user || $user['status'] !== 'active') {
            return null;
        }

        return $user;
    }

    public static function id(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function role(): ?string {
        return $_SESSION['role'] ?? null;
    }

    public static function requireLogin(): void {
        self::checkRememberToken();
        $user = self::check() ? self::user() : null;
        if (!$user) {
            setFlash('error', 'Please login to continue.');
            redirect(SITE_URL . '/auth/login.php');
        }

        // A user may arrive from the main TOURNIVOX login, which sets the
        // central identity fields but not the extra bracketing display fields.
        // Normalize the shared session from the database before rendering pages.
        self::setSession($user);
    }

    public static function requireRole(array $roles): void {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            setFlash('error', 'You do not have permission to access this page.');
            redirect(APP_URL . '/index.php');
        }
    }

    /** Bracket administrators have the complete legacy bracketing admin toolset. */
    public static function isAdmin(): bool {
        return in_array(self::role(), ['bracket_admin', 'admin'], true);
    }

    /** Tournament organizers may manage tournaments they created. */
    public static function isOrganizer(): bool {
        return in_array(self::role(), ['bracket_admin', 'admin', 'organizer'], true);
    }

    public static function isCaptain(): bool {
        return self::role() === 'team_captain';
    }

    public static function canManageTournament(int $tournamentId): bool {
        if (self::isAdmin()) {
            return true;
        }
        if (!self::isOrganizer()) {
            return false;
        }

        $tournament = Database::fetch(
            'SELECT organizer_id FROM tournaments WHERE id = ?',
            [$tournamentId]
        );

        return $tournament && (int)$tournament['organizer_id'] === (int)self::id();
    }

    public static function forgotPassword(string $email): bool {
        $user = Database::fetch('SELECT user_id FROM users WHERE email = ?', [$email]);
        if (!$user) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        Database::update('users', [
            'reset_token' => hash('sha256', $token),
            'reset_expires' => date('Y-m-d H:i:s', time() + 3600),
        ], 'user_id = ?', [(int)$user['user_id']]);

        // Development/demo behavior retained from the old capstone.
        // Replace with an email sender later if desired.
        $_SESSION['reset_token_display'] = $token;
        return true;
    }

    public static function resetPassword(string $token, string $password): bool {
        $hash = hash('sha256', $token);
        $user = Database::fetch(
            'SELECT user_id FROM users WHERE reset_token = ? AND reset_expires > NOW()',
            [$hash]
        );
        if (!$user) {
            return false;
        }

        Database::update('users', [
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'reset_token' => null,
            'reset_expires' => null,
        ], 'user_id = ?', [(int)$user['user_id']]);

        return true;
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword): bool {
        $user = Database::fetch('SELECT password FROM users WHERE user_id = ?', [$userId]);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return false;
        }

        Database::update(
            'users',
            ['password' => password_hash($newPassword, PASSWORD_ARGON2ID)],
            'user_id = ?',
            [$userId]
        );
        logActivity('change_password', 'user', $userId);
        return true;
    }

    public static function updateProfile(int $userId, array $data): bool {
        $allowed = ['first_name', 'last_name', 'email', 'avatar', 'theme'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) {
            return false;
        }

        if (isset($update['email'])) {
            $existing = Database::fetch(
                'SELECT user_id FROM users WHERE email = ? AND user_id != ?',
                [$update['email'], $userId]
            );
            if ($existing) {
                return false;
            }
        }

        Database::update('users', $update, 'user_id = ?', [$userId]);

        foreach (['first_name', 'last_name', 'email', 'avatar', 'theme'] as $key) {
            if (array_key_exists($key, $update)) {
                $_SESSION[$key] = $update[$key];
            }
        }
        $_SESSION['full_name'] = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

        logActivity('update_profile', 'user', $userId);
        return true;
    }
}

Auth::checkRememberToken();
syncTournamentStatuses();
