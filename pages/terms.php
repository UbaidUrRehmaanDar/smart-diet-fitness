<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Terms of Service - ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/partials/static_content_styles.php'; ?>

<div class="content-page-wrap fade-in">
    <div class="content-card">
        <h1><i class="fa-regular fa-file-lines"></i> Terms of Service</h1>
        <p class="content-meta">Last updated: <?php echo htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="page-lead">
            These terms govern your use of Smart Diet &amp; Fitness. This application may be deployed for educational or demonstration purposes;
            replace this text with institution-approved legal terms before any production launch.
        </p>

        <h2>1. Acceptance</h2>
        <p>By creating an account or using the application, you agree to these terms and to our Privacy Policy.</p>

        <h2>2. Not medical advice</h2>
        <p>
            The app provides general nutrition and fitness estimates only. It is not a substitute for professional medical advice,
            diagnosis, or treatment. Always consult a qualified health provider before changing diet or exercise if you have medical conditions.
        </p>

        <h2>3. Your account</h2>
        <p>You are responsible for safeguarding your password and for activity under your account. Notify your administrator if you suspect unauthorized access.</p>

        <h2>4. Acceptable use</h2>
        <p>You agree not to misuse the service, attempt unauthorized access, interfere with other users, or upload unlawful or harmful content.</p>

        <h2>5. Availability</h2>
        <p>We strive for reliable operation but do not guarantee uninterrupted access. Features may change as the product evolves.</p>

        <h2>6. Limitation of liability</h2>
        <p>To the maximum extent permitted by law, Smart Diet &amp; Fitness and its operators are not liable for indirect or consequential damages arising from use of the application.</p>

        <h2>7. Contact</h2>
        <p>Questions about these terms? Visit <a href="<?php echo htmlspecialchars(APP_URL . '/pages/support.php', ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--primary-blue);font-weight:600;">Support</a>.</p>

        <div class="inline-links">
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/privacy.php', ENT_QUOTES, 'UTF-8'); ?>">Privacy Policy</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/pages/cookies.php', ENT_QUOTES, 'UTF-8'); ?>">Cookie Policy</a>
            <a href="<?php echo htmlspecialchars(APP_URL . '/public/index.php', ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
