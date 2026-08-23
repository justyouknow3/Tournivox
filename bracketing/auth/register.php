<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (Auth::check()) redirect(APP_URL . '/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ];

        if ($data['first_name'] === '' || $data['last_name'] === '' || $data['username'] === '' || $data['email'] === '') {
            $error = 'Please complete all required fields.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($data['username']) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($data['password']) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($data['password'] !== ($_POST['password_confirm'] ?? '')) {
            $error = 'Passwords do not match.';
        } else {
            $result = Auth::register($data);
            if ($result) {
                setFlash('success', 'Team Captain account created. You can now login.');
                redirect(APP_URL . '/auth/login.php');
            }
            $error = 'Username or email already exists.';
        }
    }
}

$pageTitle = 'Create Team Captain Account';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-card">
    <div class="auth-logo">
        <img src="<?= SITE_URL ?>/assets/images/tournivox-logo.png" alt="TOURNIVOX" style="width:90px;height:90px;object-fit:contain;background:#fff;padding:4px;border-radius:16px;margin:0 auto 14px">
        <h2>Create Team Captain Account</h2>
        <p>Join TOURNIVOX tournament registration</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
    <form method="POST">
        <?= csrfField() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required value="<?= sanitize($_POST['first_name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required value="<?= sanitize($_POST['last_name'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required minlength="3" value="<?= sanitize($_POST['username'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
            <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="password_confirm" class="form-control" required minlength="8"></div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-4">Create Account</button>
        <p class="text-center mt-3 mb-0 small">Already registered? <a href="login.php">Sign in</a></p>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
