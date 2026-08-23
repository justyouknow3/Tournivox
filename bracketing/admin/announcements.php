<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireRole(['bracket_admin', 'admin', 'organizer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'create') {
        Database::insert('announcements', [
            'title' => trim($_POST['title'] ?? ''),
            'content' => trim($_POST['content'] ?? ''),
            'tournament_id' => $_POST['tournament_id'] ?: null,
            'created_by' => Auth::id(),
        ]);
        setFlash('success', 'Announcement created.');
    }
    if (($_POST['action'] ?? '') === 'delete') {
        Database::delete('announcements', 'id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Announcement deleted.');
    }
    redirect(APP_URL . '/admin/announcements.php');
}

$announcements = Database::fetchAll(
    "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author, t.name as tournament_name
     FROM announcements a JOIN users u ON a.created_by = u.user_id
     LEFT JOIN tournaments t ON a.tournament_id = t.id ORDER BY a.created_at DESC"
);
$tournaments = Database::fetchAll("SELECT id, name FROM tournaments ORDER BY name");

$pageTitle = 'Announcements';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Announcements</h1></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus"></i> New</button>
</div>

<?php foreach ($announcements as $a): ?>
<div class="card p-3 mb-3">
    <div class="d-flex justify-content-between">
        <div>
            <h6><?= sanitize($a['title']) ?></h6>
            <p class="mb-1"><?= sanitize($a['content']) ?></p>
            <small class="text-muted"><?= formatDateTime($a['created_at']) ?> · <?= sanitize($a['author']) ?>
            <?= $a['tournament_name'] ? ' · ' . sanitize($a['tournament_name']) : '' ?></small>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">New Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="3" required></textarea></div>
                    <div class="mb-3"><label class="form-label">Tournament (optional)</label>
                        <select name="tournament_id" class="form-select"><option value="">Global</option>
                        <?php foreach ($tournaments as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Create</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
