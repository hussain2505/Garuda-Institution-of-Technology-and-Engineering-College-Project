<?php
require_once __DIR__ . '/../includes/auth-check.php';
// Any authenticated role (student, faculty, parent, admin) can use this board.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report') {
    verifyCsrf();

    $type = $_POST['item_type'] === 'found' ? 'found' : 'lost';
    $name = trim($_POST['item_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if ($name === '') {
        setFlash('error', 'Please enter the item name.');
    } else {
        $pdo->prepare(
            "INSERT INTO lost_found_items (reported_by, item_type, item_name, description, location)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$_SESSION['user_id'], $type, $name, $desc, $location]);
        logAudit($pdo, 'lost_found_report', 'lost_found', $pdo->lastInsertId(), "$type: $name");
        setFlash('success', 'Your report has been posted to the board.');
    }
    redirect(BASE_URL . '/campus/lost-found.php');
}

if (isset($_GET['claim'])) {
    $itemId = (int)$_GET['claim'];
    $pdo->prepare("UPDATE lost_found_items SET status = 'claimed' WHERE item_id = ?")->execute([$itemId]);
    redirect(BASE_URL . '/campus/lost-found.php');
}

$items = $pdo->query(
    "SELECT lf.*, u.username FROM lost_found_items lf
     JOIN users u ON u.user_id = lf.reported_by
     WHERE lf.status = 'open'
     ORDER BY lf.reported_date DESC"
)->fetchAll();

$pageTitle = APP_NAME . ' | Lost & Found';
include __DIR__ . '/../includes/header.php';
?>

<h2>Campus Lost &amp; Found</h2>

<div class="card">
    <h3>Report an Item</h3>
    <form method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="report">
        <div class="form-row">
            <div>
                <label>Type</label>
                <select name="item_type">
                    <option value="lost">Lost</option>
                    <option value="found">Found</option>
                </select>
            </div>
            <div>
                <label>Location</label>
                <input type="text" name="location" placeholder="e.g. Library, Block A">
            </div>
        </div>
        <label>Item Name</label>
        <input type="text" name="item_name" required>
        <label>Description</label>
        <textarea name="description" rows="3"></textarea>
        <button type="submit" class="btn btn-primary">Post to Board</button>
    </form>
</div>

<div class="card">
    <h3>Open Reports</h3>
    <table class="data-table">
        <tr><th>Type</th><th>Item</th><th>Location</th><th>Reported By</th><th>Date</th><th></th></tr>
        <?php foreach ($items as $i): ?>
            <tr>
                <td><span class="badge badge-<?= $i['item_type'] === 'lost' ? 'high' : 'success' ?>"><?= e(ucfirst($i['item_type'])) ?></span></td>
                <td><?= e($i['item_name']) ?><br><small class="muted"><?= e($i['description']) ?></small></td>
                <td><?= e($i['location']) ?></td>
                <td><?= e($i['username']) ?></td>
                <td><?= e(date('d M Y', strtotime($i['reported_date']))) ?></td>
                <td><a href="?claim=<?= (int)$i['item_id'] ?>" class="btn btn-sm btn-outline"
                       onclick="return confirm('Mark this item as claimed/resolved?')">Mark Claimed</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <tr><td colspan="6" class="muted">No open reports right now.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
