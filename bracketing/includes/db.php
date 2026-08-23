<?php
/**
 * TOURNIVOX Bracketing Manager - PDO Database Connection
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die('TOURNIVOX database connection failed. Check XAMPP MySQL and includes/config.php.');
            }
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int {
        $columns = implode(', ', array_map(fn($key) => "`{$key}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})", array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(', ', array_map(fn($key) => "`{$key}` = ?", array_keys($data)));
        $stmt = self::query(
            "UPDATE `{$table}` SET {$set} WHERE {$where}",
            array_merge(array_values($data), $whereParams)
        );
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int {
        return self::query("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    public static function count(string $table, string $where = '1=1', array $params = []): int {
        // Support a normal table name (e.g. "teams") or a table with an alias
        // (e.g. "tournaments t" / "tournaments AS t") without quoting the
        // entire expression as one table name.
        $table = trim($table);

        if (!preg_match('/^([A-Za-z0-9_]+)(?:\s+(?:AS\s+)?([A-Za-z0-9_]+))?$/i', $table, $matches)) {
            throw new InvalidArgumentException('Invalid table expression.');
        }

        $tableSql = '`' . $matches[1] . '`';

        if (!empty($matches[2])) {
            $tableSql .= ' `' . $matches[2] . '`';
        }

        $row = self::fetch("SELECT COUNT(*) AS cnt FROM {$tableSql} WHERE {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Keeps older imported TOURNIVOX databases compatible with flexible stages/rounds.
     * This creates structure only; it never inserts bracket values.
     */
    public static function ensureFlexibleFormatSchema(): void {
        $pdo = self::getInstance();

        $pdo->exec("CREATE TABLE IF NOT EXISTS tournament_format_presets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            config_json LONGTEXT NOT NULL,
            created_by INT UNSIGNED NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_preset_creator (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tournament_stages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT UNSIGNED NOT NULL,
            stage_order INT NOT NULL DEFAULT 1,
            stage_name VARCHAR(120) NOT NULL,
            game_code VARCHAR(20) NOT NULL DEFAULT 'MLBB',
            format_type ENUM('best_of_series','single_elimination','double_elimination','round_robin','swiss','group_stage','hybrid','gauntlet','custom') NOT NULL DEFAULT 'custom',
            best_of ENUM('BO1','BO2','BO3','BO5','BO7') NOT NULL DEFAULT 'BO3',
            settings_json LONGTEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_stage_tournament (tournament_id, stage_order),
            CONSTRAINT fk_stage_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::addColumnIfMissing('rounds', 'game_code', "VARCHAR(20) DEFAULT NULL AFTER round_name");
        self::addColumnIfMissing('rounds', 'format_type', "VARCHAR(40) DEFAULT 'best_of_series' AFTER game_code");
        self::addColumnIfMissing('rounds', 'best_of', "VARCHAR(10) DEFAULT 'BO3' AFTER format_type");
        self::addColumnIfMissing('rounds', 'is_visible', "TINYINT(1) DEFAULT 1 AFTER best_of");
        self::addColumnIfMissing('teams', 'banner', "VARCHAR(255) DEFAULT NULL AFTER logo");
        self::addColumnIfMissing('players', 'avatar', "VARCHAR(255) DEFAULT NULL AFTER real_name");
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void {
        $exists = self::fetch(
            'SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB_NAME, $table, $column]
        );

        if ((int)($exists['cnt'] ?? 0) === 0) {
            self::getInstance()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}