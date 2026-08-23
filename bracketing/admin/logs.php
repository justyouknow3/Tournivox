<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$total = Database::count('logs');
$pagination = paginate($total, $page, 25);

$logs = Database::fetchAll(
    "SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.username FROM logs l
     LEFT JOIN users u ON l.user_id = u.user_id
     ORDER BY l.created_at DESC
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);

$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Activity Logs</h1><p><?= $total ?> total entries</p></div>
</div>

<div class="table-responsive card">
    <table class="table mb-0">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><small><?= formatDateTime($log['created_at']) ?></small></td>
            <td><?= sanitize($log['full_name'] ?? 'System') ?></td>
            <td><span class="badge badge-info"><?= sanitize($log['action']) ?></span></td>
            <td><?= $log['entity_type'] ? sanitize($log['entity_type']) . ' #' . $log['entity_id'] : '-' ?></td>
            <td><small><?= sanitize($log['details'] ?? '') ?></small></td>
            <td><small><?= sanitize($log['ip_address'] ?? '') ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= paginationHTML($pagination, 'logs.php?') ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
