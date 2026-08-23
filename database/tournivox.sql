-- ============================================================
-- TOURNIVOX DATABASE
-- Core users + complete bracketing/tournament management module
-- Bracketing/tournament management only; no broadcasting module is installed by this schema.
-- ============================================================

CREATE DATABASE IF NOT EXISTS tournivox
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE tournivox;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- CENTRAL TOURNIVOX USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(75) NOT NULL,
    last_name VARCHAR(75) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM(
        'team_captain',
        'staff',
        'broadcast_operator',
        'bracket_admin',
        'organizer',
        'admin'
    ) NOT NULL DEFAULT 'team_captain',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    avatar VARCHAR(255) DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    theme ENUM('dark','light') NOT NULL DEFAULT 'dark',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing TOURNIVOX installations may not yet have the additional
-- profile/remember/reset fields required by the complete bracket module.
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL AFTER status;
ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) DEFAULT NULL AFTER avatar;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL AFTER remember_token;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME DEFAULT NULL AFTER reset_token;
ALTER TABLE users ADD COLUMN IF NOT EXISTS theme ENUM('dark','light') NOT NULL DEFAULT 'dark' AFTER reset_expires;

-- Transitional support for the earliest TOURNIVOX development role.
ALTER TABLE users MODIFY COLUMN role ENUM(
    'user',
    'team_captain',
    'staff',
    'broadcast_operator',
    'bracket_admin',
    'organizer',
    'admin'
) NOT NULL DEFAULT 'team_captain';

UPDATE users SET role = 'team_captain' WHERE role = 'user';

ALTER TABLE users MODIFY COLUMN role ENUM(
    'team_captain',
    'staff',
    'broadcast_operator',
    'bracket_admin',
    'organizer',
    'admin'
) NOT NULL DEFAULT 'team_captain';

-- Default Bracket Administrator.
-- Username: bracketadmin
-- Password: TournivoxBracketAdmin@2026
INSERT INTO users (
    first_name, last_name, username, email, password, role, status
)
SELECT
    'Bracket',
    'Administrator',
    'bracketadmin',
    'bracketadmin@tournivox.local',
    '$argon2id$v=19$m=65536,t=4,p=1$YzBQUC9qSTEzemlaVDl5cA$arEYIjYp1XpVwVCCqOg/PGLKOsgZy0EkVaHkLIGvE7Y',
    'bracket_admin',
    'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users
    WHERE username = 'bracketadmin'
       OR email = 'bracketadmin@tournivox.local'
);

-- ------------------------------------------------------------
-- REBUILD BRACKETING TABLES
-- This intentionally resets development bracketing data so the schema
-- exactly matches the complete imported bracketing module.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS bracket_logs;
DROP TABLE IF EXISTS logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS standings;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS bracket_rounds;
DROP TABLE IF EXISTS rounds;
DROP TABLE IF EXISTS brackets;
DROP TABLE IF EXISTS tournament_stages;
DROP TABLE IF EXISTS tournament_format_presets;
DROP TABLE IF EXISTS tournament_registrations;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS team_players;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS tournaments;
DROP TABLE IF EXISTS teams;

-- ------------------------------------------------------------
-- TEAMS
-- ------------------------------------------------------------
CREATE TABLE teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    tag VARCHAR(15) DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    banner VARCHAR(255) DEFAULT NULL,
    captain_name VARCHAR(100) NOT NULL,
    captain_contact VARCHAR(150) NOT NULL,
    coach VARCHAR(100) DEFAULT NULL,
    captain_user_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_team_name (name),
    INDEX idx_team_captain_user (captain_user_id),
    CONSTRAINT fk_teams_captain_user FOREIGN KEY (captain_user_id)
        REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_teams_created_by FOREIGN KEY (created_by)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PLAYERS
-- ------------------------------------------------------------
CREATE TABLE players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    ign VARCHAR(100) NOT NULL,
    real_name VARCHAR(120) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Player',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_ign (ign),
    INDEX idx_player_team (team_id),
    CONSTRAINT fk_players_team FOREIGN KEY (team_id)
        REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TOURNAMENTS
-- ------------------------------------------------------------
CREATE TABLE tournaments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    game VARCHAR(20) NOT NULL DEFAULT 'MLBB',
    logo VARCHAR(255) DEFAULT NULL,
    banner VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    tournament_type ENUM(
        'single_elimination',
        'double_elimination',
        'round_robin',
        'point_based'
    ) NOT NULL DEFAULT 'single_elimination',
    max_teams INT UNSIGNED NOT NULL DEFAULT 16,
    registration_deadline DATETIME DEFAULT NULL,
    tournament_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    venue VARCHAR(255) DEFAULT NULL,
    prize_pool DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    registration_fee_type ENUM('fixed','prize_pool_based') NOT NULL DEFAULT 'fixed',
    registration_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    rules TEXT DEFAULT NULL,
    status ENUM('registration_open','ongoing','finished') NOT NULL DEFAULT 'registration_open',
    seeding_type ENUM('random','manual','auto') NOT NULL DEFAULT 'random',
    organizer_id INT UNSIGNED NOT NULL,
    champion_team_id INT UNSIGNED DEFAULT NULL,
    mvp_player_id INT UNSIGNED DEFAULT NULL,
    qr_code VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tournament_status (status),
    INDEX idx_tournament_game (game),
    INDEX idx_tournament_type (tournament_type),
    INDEX idx_tournament_date (tournament_date),
    CONSTRAINT fk_tournaments_organizer FOREIGN KEY (organizer_id)
        REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_tournaments_champion FOREIGN KEY (champion_team_id)
        REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_tournaments_mvp FOREIGN KEY (mvp_player_id)
        REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TOURNAMENT REGISTRATIONS / PAYMENT CONFIRMATION
-- No online payment gateway is included. Payment status is manually
-- confirmed by a tournament/bracket administrator, matching the old flow.
-- ------------------------------------------------------------
CREATE TABLE registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    seed INT UNSIGNED DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    payment_status ENUM('needs_payment','paid','waived') NOT NULL DEFAULT 'needs_payment',
    payment_notes VARCHAR(255) DEFAULT NULL,
    approved_by INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_registration (tournament_id, team_id),
    INDEX idx_registration_seed (tournament_id, seed),
    INDEX idx_registration_status (tournament_id, status),
    CONSTRAINT fk_registrations_tournament FOREIGN KEY (tournament_id)
        REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_registrations_team FOREIGN KEY (team_id)
        REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_registrations_approved_by FOREIGN KEY (approved_by)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FLEXIBLE FORMAT PRESETS / STAGES
-- ------------------------------------------------------------
CREATE TABLE tournament_format_presets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    config_json LONGTEXT NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_format_preset_creator (created_by),
    CONSTRAINT fk_format_presets_creator FOREIGN KEY (created_by)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tournament_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    stage_order INT NOT NULL DEFAULT 1,
    stage_name VARCHAR(120) NOT NULL,
    game_code VARCHAR(20) NOT NULL DEFAULT 'MLBB',
    format_type ENUM(
        'best_of_series',
        'single_elimination',
        'double_elimination',
        'round_robin',
        'swiss',
        'group_stage',
        'hybrid',
        'gauntlet',
        'custom'
    ) NOT NULL DEFAULT 'custom',
    best_of ENUM('BO1','BO2','BO3','BO5','BO7') NOT NULL DEFAULT 'BO3',
    settings_json LONGTEXT DEFAULT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stage_tournament (tournament_id, stage_order),
    CONSTRAINT fk_stages_tournament FOREIGN KEY (tournament_id)
        REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- BRACKETS / ROUNDS
-- ------------------------------------------------------------
CREATE TABLE brackets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    bracket_type ENUM('winners','losers','grand_finals','round_robin') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_brackets_tournament (tournament_id),
    CONSTRAINT fk_brackets_tournament FOREIGN KEY (tournament_id)
        REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bracket_id INT UNSIGNED NOT NULL,
    round_number INT UNSIGNED NOT NULL,
    round_name VARCHAR(100) NOT NULL,
    game_code VARCHAR(20) DEFAULT NULL,
    format_type VARCHAR(40) NOT NULL DEFAULT 'best_of_series',
    best_of VARCHAR(10) NOT NULL DEFAULT 'BO3',
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rounds_bracket (bracket_id, round_number),
    CONSTRAINT fk_rounds_bracket FOREIGN KEY (bracket_id)
        REFERENCES brackets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- MATCHES
-- ------------------------------------------------------------
CREATE TABLE matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    bracket_id INT UNSIGNED DEFAULT NULL,
    round_id INT UNSIGNED DEFAULT NULL,
    team1_id INT UNSIGNED DEFAULT NULL,
    team2_id INT UNSIGNED DEFAULT NULL,
    winner_id INT UNSIGNED DEFAULT NULL,
    loser_id INT UNSIGNED DEFAULT NULL,
    team1_score INT NOT NULL DEFAULT 0,
    team2_score INT NOT NULL DEFAULT 0,
    best_of ENUM('BO1','BO2','BO3','BO5','BO7') NOT NULL DEFAULT 'BO3',
    status ENUM('waiting','live','finished') NOT NULL DEFAULT 'waiting',
    match_number INT UNSIGNED NOT NULL DEFAULT 0,
    scheduled_date DATE DEFAULT NULL,
    scheduled_time TIME DEFAULT NULL,
    venue VARCHAR(255) DEFAULT NULL,
    team1_bans TEXT DEFAULT NULL,
    team2_bans TEXT DEFAULT NULL,
    team1_picks TEXT DEFAULT NULL,
    team2_picks TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    next_match_id INT UNSIGNED DEFAULT NULL,
    loser_next_match_id INT UNSIGNED DEFAULT NULL,
    position_x INT NOT NULL DEFAULT 0,
    position_y INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_matches_tournament (tournament_id),
    INDEX idx_matches_status (status),
    INDEX idx_matches_round (round_id),
    CONSTRAINT fk_matches_tournament FOREIGN KEY (tournament_id)
        REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_bracket FOREIGN KEY (bracket_id)
        REFERENCES brackets(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_round FOREIGN KEY (round_id)
        REFERENCES rounds(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_team1 FOREIGN KEY (team1_id)
        REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_team2 FOREIGN KEY (team2_id)
        REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_winner FOREIGN KEY (winner_id)
        REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_matches_loser FOREIGN KEY (loser_id)
        REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- STANDINGS
-- ------------------------------------------------------------
CREATE TABLE standings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    played INT UNSIGNED NOT NULL DEFAULT 0,
    wins INT UNSIGNED NOT NULL DEFAULT 0,
    draws INT UNSIGNED NOT NULL DEFAULT 0,
    losses INT UNSIGNED NOT NULL DEFAULT 0,
    points INT NOT NULL DEFAULT 0,
    rank_position INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_standings (tournament_id, team_id),
    CONSTRAINT fk_standings_tournament FOREIGN KEY (tournament_id)
        REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_standings_team FOREIGN KEY (team_id)
        REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ANNOUNCEMENTS / NOTIFICATIONS / AUDIT LOGS
-- ------------------------------------------------------------
CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    tournament_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_tournament FOREIGN KEY (tournament_id)
        REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcements_creator FOREIGN KEY (created_by)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('registration','match','winner','tournament','system') NOT NULL DEFAULT 'system',
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_action (action),
    INDEX idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14 DEVELOPMENT EXAMPLE TEAMS
-- ------------------------------------------------------------
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Crimson Vanguard', 'CRV', 'Adrian Cruz', 'crv@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Crimson Vanguard');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Golden Titans', 'GTT', 'Marco Reyes', 'gtt@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Golden Titans');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Emerald Wolves', 'EMW', 'Joshua Lim', 'emw@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Emerald Wolves');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Shadow Reapers', 'SHR', 'Kevin Santos', 'shr@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Shadow Reapers');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Phoenix Core', 'PHX', 'Daniel Garcia', 'phx@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Phoenix Core');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Night Raiders', 'NTR', 'Carl Mendoza', 'ntr@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Night Raiders');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Iron Dominion', 'IRD', 'Miguel Torres', 'ird@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Iron Dominion');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Storm Breakers', 'STB', 'Nathan Flores', 'stb@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Storm Breakers');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Royal Sentinels', 'RYS', 'James Navarro', 'rys@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Royal Sentinels');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Void Hunters', 'VDH', 'Paolo Ramos', 'vdh@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Void Hunters');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Arcane Legion', 'ACL', 'Ryan Castillo', 'acl@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Arcane Legion');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Cyber Knights', 'CYK', 'Christian dela Cruz', 'cyk@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Cyber Knights');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Inferno Squad', 'IFS', 'John Villanueva', 'ifs@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Inferno Squad');
INSERT INTO teams (name, tag, captain_name, captain_contact)
SELECT 'Celestial Force', 'CLF', 'Mark Aquino', 'clf@tournivox.test'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE name = 'Celestial Force');

SET FOREIGN_KEY_CHECKS = 1;
