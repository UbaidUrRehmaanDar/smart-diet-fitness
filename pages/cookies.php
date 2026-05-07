<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Cookie Policy - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-solid fa-cookie-bite"></i> Cookie Policy</h1>
        <p class="content-meta">Last updated: <?php echo htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="page-lead">
            This page explains how Smart Diet &amp; Fitness uses cookies and similar storage in the browser for a typical PHP session-based deployment.
        </p>

        <h2>1. What we use</h2>
        <p>We use strictly necessary mechanisms to:</p>
        <ul>
            <li><strong>Session cookie:</strong> Keeps you logged in after authentication and ties requests to your server-side session.</li>
            <li><strong>Security tokens:</strong> CSRF tokens may be stored in the session to protect form and API submissions.</li>
        </ul>

        <h2>2. Third-party content</h2>
        <p>
            Pages may load fonts and icons from Google Fonts and Font Awesome CDNs. Those providers may process technical data according to their own policies.
        </p>

        <h2>3. Managing cookies</h2>
        <p>
            You can block or delete cookies in your browser settings; doing so may prevent login or break parts of the application.
        </p>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/privacy.php', ENT_QUOTES, 'UTF-8'); ?>">Privacy Policy</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/terms.php', ENT_QUOTES, 'UTF-8'); ?>">Terms of Service</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
