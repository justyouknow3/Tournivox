<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (Auth::check()) {
    redirect(APP_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your login session expired. Please try again.';
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($identifier === '' || $password === '') {
            $error = 'Enter your email or username and password.';
        } elseif (Auth::login($identifier, $password, !empty($_POST['remember']))) {
            redirect(APP_URL . '/index.php');
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bracketing Login | TOURNIVOX</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/images/tournivox-logo.png">
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;font-family:"Segoe UI",Arial,sans-serif;color:#fff;display:grid;place-items:center;padding:28px;background:linear-gradient(rgba(24,0,7,.82),rgba(8,6,7,.94)),url('<?= SITE_URL ?>/assets/images/tournivox-banner.png') center/cover fixed}
        .shell{width:min(470px,100%);background:rgba(23,16,17,.94);border:1px solid rgba(243,182,31,.35);border-radius:24px;overflow:hidden;box-shadow:0 35px 100px rgba(0,0,0,.7);backdrop-filter:blur(15px)}
        .topline{height:5px;background:linear-gradient(90deg,#72001e,#f3b61f,#1f7a4d)}
        .panel{padding:40px}
        .logo{width:112px;height:112px;object-fit:contain;display:block;margin:0 auto 16px;border-radius:18px;background:#fff;padding:4px}
        .title{text-align:center}.title h1{letter-spacing:2px;font-size:26px;margin:0;color:#ffd45a}.title h2{font-size:27px;margin:8px 0}.muted{color:#aaa4a0;margin:0 0 28px;text-align:center}
        .field{display:block;margin-bottom:18px;font-weight:700;font-size:14px}.field input{display:block;width:100%;margin-top:8px;padding:14px 15px;border-radius:11px;border:1px solid #4d3439;background:#100b0c;color:#fff;font-size:15px;outline:none}.field input:focus{border-color:#f3b61f;box-shadow:0 0 0 3px rgba(243,182,31,.12)}
        .button{width:100%;border:0;border-radius:11px;padding:14px 16px;background:linear-gradient(135deg,#72001e,#8d0b2d);color:#fff;font-size:15px;font-weight:800;cursor:pointer;border-bottom:3px solid #f3b61f}.button:hover{filter:brightness(1.12)}
        .alert{padding:13px 14px;border-radius:10px;background:#531520;border:1px solid #b93b54;color:#ffe5e9;margin-bottom:18px}.links{text-align:center;margin-top:20px;color:#aaa4a0;font-size:14px}.links a{color:#ffd45a;font-weight:800;text-decoration:none}.remember{display:flex;gap:9px;align-items:center;margin:-4px 0 18px;color:#cfc8c5;font-size:14px}
    </style>
</head>
<body>
<main class="shell">
    <div class="topline"></div>
    <section class="panel">
        <img class="logo" src="<?= SITE_URL ?>/assets/images/tournivox-logo.png" alt="TOURNIVOX">
        <div class="title"><h1>TOURNIVOX</h1><h2>Bracketing Login</h2><p class="muted">Tournament and bracket management control center</p></div>
        <?php if ($error): ?><div class="alert"><?= sanitize($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <label class="field">Email or Username<input name="identifier" required autofocus autocomplete="username"></label>
            <label class="field">Password<input type="password" name="password" required autocomplete="current-password"></label>
            <label class="remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
            <button class="button">Login to Bracketing System</button>
        </form>
        <div class="links"><a href="register.php">Create Team Captain Account</a><br><br><a href="<?= SITE_URL ?>/index.php">Back to TOURNIVOX</a></div>
    </section>
</main>
</body>
</html>
