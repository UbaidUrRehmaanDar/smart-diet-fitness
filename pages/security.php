<?php
/**
 * Privacy & security hub (signed-in): password + links to policies.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Privacy & Security - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-solid fa-lock"></i> Privacy &amp; Security</h1>
        <p class="page-lead">
            Manage how you sign in and review how your data is handled in Smart Diet &amp; Fitness.
        </p>

        <h2>Password</h2>
        <p>Use a unique passphrase with uppercase, lowercase, numbers, and symbols. Change it periodically—especially on shared devices.</p>
        <a class="btn-primary-inline" href="<?php echo htmlspecialchars(APP_URL . '/auth/change_password.php', ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-key"></i> Change password
        </a>

        <h2>Policies</h2>
        <p>Review how we describe collection, use, cookies, and terms of use.</p>
        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/privacy.php', ENT_QUOTES, 'UTF-8'); ?>">Privacy Policy</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/terms.php', ENT_QUOTES, 'UTF-8'); ?>">Terms of Service</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/cookies.php', ENT_QUOTES, 'UTF-8'); ?>">Cookie Policy</a>
        </div>

        <h2>Sessions</h2>
        <p>Sessions expire after a period of inactivity. Use Log out when you finish on a shared computer.</p>
        <a class="btn-secondary-inline" href="<?php echo htmlspecialchars(APP_URL . '/pages/settings.php', ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-arrow-left"></i> Back to settings
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
