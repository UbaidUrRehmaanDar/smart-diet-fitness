<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Documentation - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-regular fa-book"></i> Documentation</h1>
        <p class="page-lead">
            A concise guide to the main areas of Smart Diet &amp; Fitness. Use the top navigation when you are signed in.
        </p>

        <div class="grid-container">
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-chart-pie"></i></div>
                <h3>Dashboard</h3>
                <p>Daily snapshot: calorie progress, macros, hydration, workouts logged today, and quick actions.</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-utensils"></i></div>
                <h3>Nutrition</h3>
                <p>Log meals by type, review today&apos;s intake against targets, and keep history aligned with recommendations.</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-dumbbell"></i></div>
                <h3>Workouts</h3>
                <p>Record cardio and strength sessions with duration and intensity to track burn estimates.</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Progress</h3>
                <p>Visualize weight and measurement trends over selectable ranges.</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-file-lines"></i></div>
                <h3>Reports</h3>
                <p>Export-friendly summaries of adherence, balance, and nutrient distribution across weeks or months.</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-trophy"></i></div>
                <h3>Achievements</h3>
                <p>Milestone badges unlock as you log meals and workouts consistently.</p>
            </div>
        </div>

        <h2>Account &amp; settings</h2>
        <p>
            Update your name and reminder preferences under Settings. Notifications appear on the dedicated notifications page and via the bell indicator when enabled.
            Security-sensitive changes use the change-password flow linked from Settings &gt; Privacy &amp; security.
        </p>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/support.php', ENT_QUOTES, 'UTF-8'); ?>">Support &amp; FAQs</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/blog.php', ENT_QUOTES, 'UTF-8'); ?>">Blog</a>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo htmlspecialchars(APP_URL . '/pages/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
