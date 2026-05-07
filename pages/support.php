<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Help & Support - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-regular fa-circle-question"></i> Help &amp; Support</h1>
        <p class="page-lead">
            Get oriented with Smart Diet &amp; Fitness, find answers to common questions, and learn where to go next.
        </p>

        <h2>Quick start</h2>
        <div class="grid-container">
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-user-check"></i></div>
                <h3>Finish onboarding</h3>
                <p>Complete all three steps so we can calculate targets and generate today&apos;s plan.</p>
                <?php if (is_logged_in()): ?>
                    <a class="btn-primary-inline" href="<?php echo htmlspecialchars(APP_URL . '/pages/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>">Open dashboard</a>
                <?php else: ?>
                    <a class="btn-primary-inline" href="<?php echo htmlspecialchars(APP_URL . '/auth/login.php', ENT_QUOTES, 'UTF-8'); ?>">Sign in</a>
                <?php endif; ?>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-book"></i></div>
                <h3>Documentation</h3>
                <p>Screen-by-screen overview of goals, logging, and reports.</p>
                <a class="btn-secondary-inline" href="<?php echo htmlspecialchars(APP_URL . '/pages/documentation.php', ENT_QUOTES, 'UTF-8'); ?>">View docs</a>
            </div>
            <div class="resource-card">
                <div class="icon-wrap"><i class="fa-solid fa-users"></i></div>
                <h3>Community</h3>
                <p>Challenges and motivation—see what&apos;s available.</p>
                <a class="btn-secondary-inline" href="<?php echo htmlspecialchars(APP_URL . '/pages/community.php', ENT_QUOTES, 'UTF-8'); ?>">Community</a>
            </div>
        </div>

        <h2 id="contact">Contact</h2>
        <p>
            For demo or classroom deployments, reach your instructor or system administrator.
            For product feedback, use the communication channel provided by your team (email, LMS, or ticket system).
        </p>
        <p>
            <a class="btn-secondary-inline" href="mailto:support@localhost"><i class="fa-regular fa-envelope"></i> support@localhost</a>
            <span style="color:var(--text-light);font-size:0.85rem;margin-left:0.75rem;">Replace with your real support address.</span>
        </p>

        <h2 id="faqs">Frequently asked questions</h2>

        <div class="faq-item">
            <strong>Why don&apos;t I see calorie targets?</strong>
            <p>Targets come from your profile and today&apos;s recommendation. Finish onboarding and ensure your profile has height, weight, activity level, and goal.</p>
        </div>
        <div class="faq-item">
            <strong>How do reminders work?</strong>
            <p>Reminder notifications are created when your plan is generated and respect toggles under Settings &gt; preferences where available.</p>
        </div>
        <div class="faq-item">
            <strong>How do I change my password?</strong>
            <p>Signed-in users can use <a href="<?php echo htmlspecialchars(APP_URL . '/auth/change_password.php', ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--primary-blue);font-weight:600;">Change password</a> from the account menu flow linked from Settings.</p>
        </div>
        <div class="faq-item">
            <strong>Is my health data secure?</strong>
            <p>We follow baseline practices (hashed passwords, prepared statements, session controls). Read the <a href="<?php echo htmlspecialchars(APP_URL . '/pages/privacy.php', ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--primary-blue);font-weight:600;">Privacy Policy</a> for details.</p>
        </div>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/privacy.php', ENT_QUOTES, 'UTF-8'); ?>">Privacy</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/terms.php', ENT_QUOTES, 'UTF-8'); ?>">Terms</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
