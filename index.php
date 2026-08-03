<?php
require_once __DIR__ . '/config/app.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = APP_NAME . ' | Home';

// Pull a couple of published notices for the homepage
$notices = $pdo->query(
    "SELECT title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 3"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>Garuda Institute of Technology &amp; Engineering College</h1>
    <p>Empowering students through engineering education, innovation and research.</p>
    <?php if (!isLoggedIn()): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/auth/login.php">Login to ERP Portal</a>
    <?php endif; ?>
</section>

<section class="cards-grid">
    <div class="card">
        <h3>About GITEC</h3>
        <p>Garuda Institute of Technology &amp; Engineering College (GITEC) offers undergraduate and postgraduate
        engineering programs across Computer Science, Electronics, Electrical, Mechanical and Civil Engineering,
        with a strong focus on research, placements and industry-ready skills.</p>
    </div>
    <div class="card">
        <h3>Departments</h3>
        <ul class="plain-list">
            <li>Computer Science &amp; Engineering</li>
            <li>Electronics &amp; Communication Engineering</li>
            <li>Electrical &amp; Electronics Engineering</li>
            <li>Mechanical Engineering</li>
            <li>Civil Engineering</li>
        </ul>
    </div>
    <div class="card">
        <h3>Latest Notices</h3>
        <?php if ($notices): ?>
            <ul class="plain-list">
                <?php foreach ($notices as $n): ?>
                    <li>
                        <strong><?= e($n['title']) ?></strong><br>
                        <small><?= e(date('d M Y', strtotime($n['created_at']))) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No notices yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
