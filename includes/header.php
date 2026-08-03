<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (isLoggedIn()): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <?php if (isLoggedIn()): ?>
            <button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Toggle menu">&#9776;</button>
        <?php endif; ?>
        <a class="brand" href="<?= BASE_URL ?>/index.php">
            <span class="brand-mark">GITEC</span>
            <span class="brand-full">Garuda Institute of Technology &amp; Engineering College</span>
        </a>

        <nav class="topnav">
            <?php if (isLoggedIn()): ?>
                <span class="user-chip"><?= e($_SESSION['username'] ?? '') ?> · <?= e(currentRole()) ?></span>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-link">Logout</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/index.php">Home</a>
                <a href="<?= BASE_URL ?>/auth/login.php">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if (isLoggedIn()):
    $role = currentRole();
    $here = basename($_SERVER['PHP_SELF']);

    $navByRole = [
        'Student' => [
            ['student/dashboard.php', 'Dashboard', 'grid'],
            ['student/attendance.php', 'Attendance', 'check'],
            ['student/results.php', 'Results', 'award'],
            ['student/fees.php', 'Fees', 'card'],
            ['student/library.php', 'Library', 'book'],
            ['student/timetable.php', 'Timetable', 'clock'],
            ['student/hostel.php', 'Hostel', 'home'],
            ['student/placements.php', 'Placements', 'briefcase'],
            ['student/grievances.php', 'Grievances', 'flag'],
            ['campus/lost-found.php', 'Lost & Found', 'search'],
        ],
        'Faculty' => [
            ['faculty/dashboard.php', 'Dashboard', 'grid'],
            ['faculty/attendance.php', 'Mark Attendance', 'check'],
            ['faculty/marks.php', 'Enter Marks', 'award'],
            ['faculty/timetable.php', 'Timetable', 'clock'],
            ['campus/lost-found.php', 'Lost & Found', 'search'],
        ],
        'Parent' => [
            ['parent/dashboard.php', 'Dashboard', 'grid'],
        ],
        'Admin' => [
            ['admin/dashboard.php', 'Dashboard', 'grid'],
            ['admin/students.php', 'Students', 'user'],
            ['admin/library.php', 'Library', 'book'],
            ['admin/fees.php', 'Fees', 'card'],
            ['admin/timetable.php', 'Timetable', 'clock'],
            ['admin/hostel.php', 'Hostel', 'home'],
            ['admin/placements.php', 'Placements', 'briefcase'],
            ['admin/notices.php', 'Notices', 'bell'],
            ['admin/audit-log.php', 'Security & Audit', 'shield'],
            ['campus/lost-found.php', 'Lost & Found', 'search'],
        ],
    ];
    $navByRole['Super Admin'] = $navByRole['Admin'];
    $links = $navByRole[$role] ?? [];
    ?>
<div class="app-shell">
    <aside class="sidebar">
        <nav>
            <?php foreach ($links as [$href, $label, $icon]): ?>
                <a href="<?= BASE_URL ?>/<?= $href ?>" class="<?= basename($href) === $here ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="page-container">
    <?php renderFlashes(); ?>
<?php else: ?>
    <main class="page-container page-container-public">
    <?php renderFlashes(); ?>
<?php endif; ?>
