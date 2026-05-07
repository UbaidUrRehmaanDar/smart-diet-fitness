<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Blog - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-regular fa-newspaper"></i> Blog</h1>
        <p class="page-lead">
            Practical notes on building steady habits with Smart Diet &amp; Fitness. New articles can be added here as static content for your deployment.
        </p>

        <div class="grid-container">
            <div class="resource-card" id="article-balance">
                <div class="icon-wrap"><i class="fa-solid fa-scale-balanced"></i></div>
                <h3>Fuel balance without perfection</h3>
                <p>
                    Aim for consistency across the week rather than perfect single days. Logging honestly helps the dashboard reflect reality so targets stay useful.
                </p>
                <span class="content-meta">Habits · 5 min read</span>
            </div>
            <div class="resource-card" id="article-recovery">
                <div class="icon-wrap"><i class="fa-solid fa-heart-pulse"></i></div>
                <h3>Recovery is part of training</h3>
                <p>
                    Hydration, sleep, and light movement days matter. Pair harder workouts with mobility or walking sessions to stay resilient.
                </p>
                <span class="content-meta">Training · 4 min read</span>
            </div>
            <div class="resource-card" id="article-plans">
                <div class="icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
                <h3>Using your daily plan</h3>
                <p>
                    Recommendations are starting points. Adjust portions based on hunger, schedule, and guidance from your healthcare professional when needed.
                </p>
                <span class="content-meta">Nutrition · 6 min read</span>
            </div>
        </div>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/documentation.php', ENT_QUOTES, 'UTF-8'); ?>">Documentation</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/support.php', ENT_QUOTES, 'UTF-8'); ?>">Support</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
