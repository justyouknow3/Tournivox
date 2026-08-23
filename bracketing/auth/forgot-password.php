<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (Auth::check()) redirect(APP_URL . '/index.php');

$message = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request.';
    } elseif (isset($_POST['step']) && $_POST['step'] === 'reset') {
        if (strlen($_POST['password'] ?? '') < 8) {
            $message = 'New password must be at least 8 characters.';
        } elseif (Auth::resetPassword($_POST['token'] ?? '', $_POST['password'] ?? '')) {
            setFlash('success', 'Password reset successful! Please login.');
            redirect(APP_URL . '/auth/login.php');
        } else {
            $message = 'Invalid or expired reset token.';
        }
    } elseif (isset($_POST['email'])) {
        if (Auth::forgotPassword(trim($_POST['email']))) {
            $token = $_SESSION['reset_token_display'] ?? '';
            $message = 'If the email exists, a reset link has been sent. For demo, use the token below.';
        } else {
            $message = 'If the email exists, a reset link has been sent.';
        }
    }
}

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-card">
    <div class="auth-logo">
        <div class="brand-icon"><i class="bi bi-key"></i></div>
        <h2>Reset Password</h2>
        <p>Enter your email to receive a reset link</p>
    </div>

    <?php if ($message): ?><div class="alert alert-info"><?= sanitize($message) ?></div><?php endif; ?>

    <?php if ($token): ?>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="reset">
        <div class="mb-3">
            <label class="form-label">Reset Token</label>
            <input type="text" name="token" class="form-control" required value="<?= sanitize($token) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
    </form>
    <?php else: ?>
    <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Link</button>
        <p class="text-center mb-0"><a href="login.php" style="color:var(--accent-blue);font-size:0.85rem">Back to Login</a></p>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
