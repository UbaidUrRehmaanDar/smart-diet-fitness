<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Privacy Policy - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-solid fa-shield-halved"></i> Privacy &amp; Policy</h1>
        <p class="content-meta">Last updated: <?php echo htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="page-lead">
            Smart Diet &amp; Fitness respects your privacy. This policy describes how we handle information when you use our web application.
            It is provided for transparency in a student / demonstration context; adapt it with legal review before production use.
        </p>

        <h2>1. Information we collect</h2>
        <p>We collect information you provide directly, including:</p>
        <ul>
            <li>Account details such as email address and authentication credentials (stored as a secure password hash).</li>
            <li>Health and preference data you enter during onboarding or settings (for example height, weight targets, activity level, and dietary preferences).</li>
            <li>Activity you log in the app (meals, workouts, hydration, progress metrics, and related notes).</li>
        </ul>

        <h2>2. How we use information</h2>
        <p>We use your information to:</p>
        <ul>
            <li>Authenticate you and maintain your session securely.</li>
            <li>Generate personalized calorie and macro targets and display dashboards and reports.</li>
            <li>Send in-app notifications and reminders when those features are enabled in your preferences.</li>
            <li>Maintain auditability and troubleshoot technical issues (for example via server error logs).</li>
        </ul>

        <h2>3. Cookies and similar technologies</h2>
        <p>
            We use strictly necessary cookies for session management and security (including CSRF protection).
            See our <a href="<?php echo htmlspecialchars(APP_URL . '/pages/cookies.php', ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--primary-blue);font-weight:600;">Cookie Policy</a> for details.
        </p>

        <h2>4. Data retention</h2>
        <p>
            We retain your data while your account is active and as needed to provide the service.
            You may request deletion or export of your data according to processes offered by your administrator or institution.
        </p>

        <h2>5. Security</h2>
        <p>
            We apply reasonable technical measures such as hashed passwords, prepared SQL statements, HTTPS where configured,
            and session hardening. No method of transmission over the Internet is completely secure.
        </p>

        <h2>6. Contact</h2>
        <p>
            For privacy-related questions, contact your project administrator or visit our
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/support.php', ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--primary-blue);font-weight:600;">Support</a> page.
        </p>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/terms.php', ENT_QUOTES, 'UTF-8'); ?>">Terms of Service</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/cookies.php', ENT_QUOTES, 'UTF-8'); ?>">Cookie Policy</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
