<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

$user = Auth::user();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];
    if (!empty($_FILES['avatar']['name'])) {
        $avatar = uploadFile($_FILES['avatar'], 'avatars');
        if ($avatar) $data['avatar'] = $avatar;
    }
    if (Auth::updateProfile((int)Auth::id(), $data)) {
        $message = 'Profile updated successfully.';
        $user = Auth::user();
    } else {
        $error = 'Profile could not be updated. The email may already be in use.';
    }
}

$pageTitle = 'Profile Settings';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Profile Settings</h1><p>Manage your TOURNIVOX account information</p></div></div>
<div class="row g-4">
    <div class="col-lg-8"><div class="card p-4">
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required value="<?= sanitize($user['first_name']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required value="<?= sanitize($user['last_name']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= sanitize($user['email']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Username</label><input type="text" class="form-control" disabled value="<?= sanitize($user['username']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Role</label><input type="text" class="form-control" disabled value="<?= sanitize(roleLabel($user['role'])) ?>"></div>
                <div class="col-12"><label class="form-label">Avatar</label><input type="file" name="avatar" class="form-control" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
        </form>
    </div></div>
    <div class="col-lg-4"><div class="card p-4 text-center">
        <div class="user-avatar mx-auto mb-3" style="width:80px;height:80px;font-size:2rem;overflow:hidden"><?php if(!empty($user['avatar'])):?><img src="<?= UPLOAD_URL ?>/<?= sanitize($user['avatar']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover"><?php else:?><?= strtoupper(substr($user['first_name'],0,1)) ?><?php endif;?></div>
        <h5><?= sanitize($user['full_name']) ?></h5><p class="text-muted"><?= sanitize(roleLabel($user['role'])) ?></p><small class="text-muted">Member since <?= formatDate($user['created_at']) ?></small>
    </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
