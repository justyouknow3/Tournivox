<?php
declare(strict_types=1);

session_name('tournivox_install_session');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']),
    'path' => '/',
]);
session_start();

const TOURNIVOX_INSTALL_PASSWORD = 'TournivoxSetup@2026';

$error = '';
$message = '';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = preg_replace('#/bracketing/install\.php$#', '', $scriptName) ?: '';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Split the bundled SQL without breaking quoted strings or SQL comments. */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);
    $i = 0;

    while ($i < $length) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote === null) {
            if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                while ($i < $length && $sql[$i] !== "\n") $i++;
                $buffer .= "\n";
                continue;
            }
            if ($char === '#') {
                while ($i < $length && $sql[$i] !== "\n") $i++;
                $buffer .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $i += 2;
                while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++;
                $i += 2;
                $buffer .= ' ';
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $buffer .= $char;
                $i++;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') $statements[] = $statement;
                $buffer = '';
                $i++;
                continue;
            }

            $buffer .= $char;
            $i++;
            continue;
        }

        $buffer .= $char;
        if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
            $buffer .= $sql[$i + 1];
            $i += 2;
            continue;
        }
        if ($char === $quote) {
            // SQL escapes a quote by doubling it.
            if ($i + 1 < $length && $sql[$i + 1] === $quote && $quote !== '`') {
                $buffer .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            $quote = null;
        }
        $i++;
    }

    $tail = trim($buffer);
    if ($tail !== '') $statements[] = $tail;
    return $statements;
}

if (isset($_GET['lock'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: install.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
    if (hash_equals(TOURNIVOX_INSTALL_PASSWORD, (string)$_POST['access_password'])) {
        session_regenerate_id(true);
        $_SESSION['tournivox_install_authorized'] = true;
        header('Location: install.php');
        exit;
    }
    $error = 'Incorrect setup password.';
}

$authorized = !empty($_SESSION['tournivox_install_authorized']);

if ($authorized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_system'])) {
    try {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('PDO MySQL is required. Enable pdo_mysql in XAMPP PHP.');
        }

        $host = trim((string)($_POST['host'] ?? '127.0.0.1'));
        $port = trim((string)($_POST['port'] ?? '3306'));
        $user = trim((string)($_POST['user'] ?? 'root'));
        $pass = (string)($_POST['pass'] ?? '');

        $schemaPath = dirname(__DIR__) . '/database/tournivox.sql';
        if (!is_file($schemaPath)) {
            throw new RuntimeException('Missing database/tournivox.sql.');
        }

        $schema = (string)file_get_contents($schemaPath);
        if (trim($schema) === '') {
            throw new RuntimeException('database/tournivox.sql is empty.');
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]
        );

        foreach (splitSqlStatements($schema) as $statement) {
            $pdo->exec($statement);
        }

        $pdo->exec('USE `tournivox`');
        $requiredTables = [
            'users', 'teams', 'players', 'tournaments', 'registrations',
            'brackets', 'rounds', 'matches', 'standings', 'announcements',
            'notifications', 'logs'
        ];
        foreach ($requiredTables as $table) {
            $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
            if (!$exists) throw new RuntimeException("Required table '{$table}' was not created.");
        }

        $message = 'TOURNIVOX bracketing database installation completed successfully.';
    } catch (Throwable $e) {
        $error = 'Installation failed: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>TOURNIVOX Bracketing Setup</title>
    <link rel="icon" type="image/png" href="<?= h($appBase . '/assets/images/tournivox-logo.png') ?>">
    <style>
        *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font-family:Arial,sans-serif;color:#fff;background:linear-gradient(rgba(17,8,12,.86),rgba(17,8,12,.94)),url('<?=h($appBase . '/assets/images/tournivox-banner.png')?>') center/cover fixed}
        .box{width:min(820px,100%);padding:32px;border:1px solid #d9aa3c55;border-radius:22px;background:#160d12ee;box-shadow:0 30px 90px #000b}.logo{width:110px;height:110px;object-fit:contain;display:block;margin:0 auto 16px}.center{text-align:center}.muted{color:#d2c4c8;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{display:block;font-weight:700;margin:14px 0}.field input{display:block;width:100%;margin-top:8px;padding:13px;border:1px solid #75414f;border-radius:10px;background:#0e090b;color:#fff}.btn{display:inline-block;width:100%;border:0;border-radius:10px;padding:13px 16px;background:linear-gradient(90deg,#790020,#b58a2c);color:#fff;text-decoration:none;font-weight:800;cursor:pointer;text-align:center}.btn.secondary{width:auto;background:#173e2b}.alert{padding:13px;border-radius:10px;margin:16px 0;background:#6b1c2e;border:1px solid #ff8ca0}.success{background:#164832;border-color:#5fd99b}.credentials{padding:16px;border:1px solid #d9aa3c55;border-radius:12px;background:#0e090b;line-height:1.8}.top{text-align:right}@media(max-width:650px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="box">
<?php if (!$authorized): ?>
    <div class="center"><img class="logo" src="<?=h($appBase . '/assets/images/tournivox-logo.png')?>" alt="TOURNIVOX"><h1>TOURNIVOX Bracketing Setup</h1><p class="muted">Administration-only installer for the tournament and bracketing database.</p></div>
    <?php if ($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
    <form method="post"><label class="field">Setup Password<input type="password" name="access_password" required autofocus></label><button class="btn">Unlock Setup</button></form>
<?php else: ?>
    <div class="top"><a class="btn secondary" href="?lock=1">Lock</a></div>
    <div class="center"><img class="logo" src="<?=h($appBase . '/assets/images/tournivox-logo.png')?>" alt="TOURNIVOX"><h1>Bracketing Database Installer</h1><p class="muted">Installs the TOURNIVOX user-compatible tournament/bracketing schema only. No broadcasting or stream module is installed.</p></div>
    <div class="alert">Development note: the master SQL rebuilds bracketing data tables. Back up bracket data before running this installer again.</div>
    <?php if ($error): ?><div class="alert"><?=h($error)?></div><?php endif; ?>
    <?php if ($message): ?>
        <div class="alert success"><?=h($message)?></div>
        <div class="credentials"><b>Bracket Administrator</b><br>Username: bracketadmin<br>Password: TournivoxBracketAdmin@2026</div><br>
        <a class="btn" href="<?=h($appBase . '/auth/login.php')?>">Open TOURNIVOX Login</a>
    <?php else: ?>
        <form method="post"><input type="hidden" name="install_system" value="1"><div class="grid"><label class="field">MySQL Host<input name="host" value="127.0.0.1" required></label><label class="field">MySQL Port<input name="port" value="3306" required></label><label class="field">MySQL Username<input name="user" value="root" required></label><label class="field">MySQL Password<input type="password" name="pass"></label></div><button class="btn" style="margin-top:16px">Install TOURNIVOX Bracketing Database</button></form>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
