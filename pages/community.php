<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Community - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-regular fa-comments"></i> Community</h1>
        <p class="page-lead">
            Connect around shared goals, challenges, and accountability. For many deployments this section introduces future forums or cohort programs—your team can link external communities here.
        </p>

        <div class="grid-container">
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-flag-checkered"></i></div>
                <h3>Monthly challenges</h3>
                <p>Join consistency streaks for logging meals and workouts. Ask your administrator how challenges are run for your cohort.</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-user-group"></i></div>
                <h3>Study groups</h3>
                <p>Pair dashboards with weekly check-ins through your institution&apos;s preferred channel (LMS, chat, or email).</p>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-life-ring"></i></div>
                <h3>Need help?</h3>
                <p>Visit support for FAQs and contact options.</p>
                <a class="btn-primary-inline" href="<?php echo htmlspecialchars(APP_URL . '/pages/support.php', ENT_QUOTES, 'UTF-8'); ?>">Open support</a>
            </div>
        </div>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/blog.php', ENT_QUOTES, 'UTF-8'); ?>">Blog</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/documentation.php', ENT_QUOTES, 'UTF-8'); ?>">Documentation</a>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo htmlspecialchars(APP_URL . '/pages/workouts.php', ENT_QUOTES, 'UTF-8'); ?>">Workouts</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
