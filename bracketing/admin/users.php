<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin']);

$allowedRoles = ['team_captain', 'staff', 'broadcast_operator', 'bracket_admin', 'organizer', 'admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'update_role' && $userId) {
        $newRole = $_POST['role'] ?? '';
        if (!in_array($newRole, $allowedRoles, true)) {
            setFlash('error', 'Invalid account role.');
        } elseif ($userId === Auth::id()) {
            setFlash('error', 'You cannot change your own role from this screen.');
        } else {
            Database::update('users', ['role' => $newRole], 'user_id = ?', [$userId]);
            logActivity('update_user_role', 'user', $userId, 'Role changed to ' . $newRole);
            setFlash('success', 'User role updated.');
        }
    }

    if ($action === 'toggle_active' && $userId) {
        if ($userId === Auth::id()) {
            setFlash('error', 'You cannot deactivate your own account.');
        } else {
            $user = Database::fetch('SELECT status FROM users WHERE user_id = ?', [$userId]);
            if ($user) {
                $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
                Database::update('users', ['status' => $newStatus], 'user_id = ?', [$userId]);
                logActivity('update_user_status', 'user', $userId, 'Status changed to ' . $newStatus);
                setFlash('success', 'User status updated.');
            }
        }
    }

    redirect(APP_URL . '/admin/users.php');
}

$users = Database::fetchAll(
    "SELECT *, CONCAT(first_name, ' ', last_name) AS full_name
     FROM users ORDER BY created_at DESC"
);
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Manage Users</h1><p><?= count($users) ?> registered TOURNIVOX users</p></div></div>
<div class="table-responsive card">
<table class="table mb-0">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
        <td><strong><?= sanitize($u['full_name']) ?></strong><br><small class="text-muted">@<?= sanitize($u['username']) ?></small></td>
        <td><?= sanitize($u['email']) ?></td>
        <td>
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                <select name="role" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()" <?= (int)$u['user_id'] === Auth::id() ? 'disabled' : '' ?>>
                    <?php foreach ($allowedRoles as $role): ?>
                    <option value="<?= sanitize($role) ?>" <?= $u['role'] === $role ? 'selected' : '' ?>><?= sanitize(roleLabel($role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </td>
        <td><?= $u['status'] === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?></td>
        <td><?= formatDate($u['created_at']) ?></td>
        <td>
            <?php if ((int)$u['user_id'] !== Auth::id()): ?>
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
            <?php else: ?><span class="text-muted small">Current user</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
