<?php
require_once __DIR__ . '/../includes/auth-check.php';
requireRole('Super Admin', 'Admin');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT al.*, u.username
     FROM audit_logs al LEFT JOIN users u ON u.user_id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = APP_NAME . ' | Security & Audit Log';
include __DIR__ . '/../includes/header.php';
?>

<h2>Security &amp; Audit Log</h2>
<p class="muted">Showing page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> total events)</p>

<div class="card">
    <table class="data-table">
        <tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP</th><th>Details</th></tr>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= e(date('d M Y H:i:s', strtotime($l['created_at']))) ?></td>
                <td><?= e($l['username'] ?? 'system') ?></td>
                <td><?= e($l['action']) ?></td>
                <td><?= e($l['module']) ?></td>
                <td><?= e($l['record_id']) ?></td>
                <td><?= e($l['ip_address']) ?></td>
                <td><?= e($l['description']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
            <tr><td colspan="7" class="muted">No log entries yet.</td></tr>
        <?php endif; ?>
    </table>

    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">&larr; Prev</a><?php endif; ?>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">Next &rarr;</a><?php endif; ?>
    </div>
</div>

<a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>
