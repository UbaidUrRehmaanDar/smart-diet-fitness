<?php
/**
 * Footer Include File
 * Included at the bottom of every page
 */
$current_page = basename($_SERVER['PHP_SELF']);
$show_footer = ($current_page === 'index.php');
?>
    </main><!-- End main-content -->

    <?php if ($show_footer): ?>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column">
                <h4>Smart Diet & Fitness</h4>
                <p>Your personal nutrition and fitness recommendation system.</p>
                <div class="footer-socials">
                    <a href="#" title="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <div class="footer-column">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/nutrition.php">Nutrition</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/workouts.php">Workouts</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Resources</h4>
                <ul>
                    <li><a href="<?php echo APP_URL; ?>/pages/documentation.php">Documentation</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/blog.php">Blog</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/support.php#faqs">FAQs</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/support.php">Support</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Legal</h4>
                <ul>
                    <li><a href="<?php echo APP_URL; ?>/pages/privacy.php">Privacy Policy</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/terms.php">Terms of Service</a></li>
                    <li><a href="<?php echo APP_URL; ?>/pages/cookies.php">Cookie Policy</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 Smart Diet & Fitness. All rights reserved.</p>
        </div>
    </footer>
    <?php endif; ?>

    <div class="modal-overlay" id="logoutModal" aria-hidden="true" style="z-index:99999;">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" style="z-index:100000;">
            <div class="modal-header">
                <h3 id="logoutTitle">Confirm logout</h3>
                <button class="modal-close" type="button" data-logout-cancel="true"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p class="modal-text">You are about to end your session. Do you want to continue?</p>
            <div class="modal-actions">
                <button class="btn-modal-secondary" type="button" data-logout-cancel="true">Cancel</button>
                <a href="<?php echo APP_URL; ?>/auth/logout.php" class="btn-modal-primary" id="confirmLogout">Log out</a>
            </div>
        </div>
    </div>

    <div class="toast" id="appToast" role="status" aria-live="polite"></div>

    <style>
        .footer {
            background-color: var(--text-dark);
            color: #ffffff;
            padding: 3rem;
            margin-top: 4rem;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-column h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #ffffff;
        }

        .footer-column p {
            font-size: 0.9rem;
            line-height: 1.6;
            opacity: 0.8;
            margin-bottom: 1rem;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 0.7rem;
        }

        .footer-column a {
            color: #ffffff;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-column a:hover {
            opacity: 1;
            color: var(--primary-blue);
        }

        .footer-socials {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .footer-socials a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .footer-socials a:hover {
            background-color: var(--primary-blue);
            opacity: 1;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0.8;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .footer-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }

            .footer {
                padding: 2rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .footer-container {
                grid-template-columns: 1fr;
            }
        }

        .toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--bg-right);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
            padding: 0.85rem 1.4rem;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(27, 54, 121, 0.15);
            font-size: 0.9rem;
            font-weight: 600;
            display: none;
            z-index: 400;
        }

        .toast.show { display: block; }
        .toast.error { border-color: #fecaca; color: #991b1b; }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 30, 70, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            padding: 1.5rem;
            backdrop-filter: blur(2px);
        }

        .modal-overlay.active { display: flex; }

        .confirm-modal {
            width: 100%;
            max-width: 420px;
            background-color: #ffffff;
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(27, 54, 121, 0.25), 0 0 0 1px rgba(27,54,121,0.08);
            padding: 2rem;
            position: relative;
            z-index: 100000;
        }

        .confirm-modal .modal-header h3 {
            font-size: 1.2rem;
            color: #1b3679;
            font-weight: 700;
        }

        .confirm-modal .modal-text {
            font-size: 0.95rem;
            color: #4a6aa6;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .confirm-modal .modal-close {
            background: none;
            border: none;
            color: #4a6aa6;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .confirm-modal .btn-modal-secondary {
            background: #f0f5ff;
            border: 2px solid #e5edf9;
            color: #1b3679;
            border-radius: 50px;
            padding: 0.65rem 1.4rem;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .confirm-modal .btn-modal-secondary:hover {
            background: #e5edf9;
            border-color: #3d7bf4;
            color: #3d7bf4;
            border-radius: 12px;
        }

        /* Dark mode overrides for the logout modal */
        [data-theme="dark"] .confirm-modal {
            background-color: #1e2535;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255,255,255,0.06);
        }

        [data-theme="dark"] .confirm-modal .modal-header h3 {
            color: #e2e8f0;
        }

        [data-theme="dark"] .confirm-modal .modal-text {
            color: #94a3b8;
        }

        [data-theme="dark"] .confirm-modal .modal-close {
            color: #94a3b8;
        }

        [data-theme="dark"] .confirm-modal .modal-close:hover {
            color: #e2e8f0;
        }

        [data-theme="dark"] .confirm-modal .btn-modal-secondary {
            background: #2d3748;
            border-color: #4a5568;
            color: #e2e8f0;
        }

        [data-theme="dark"] .confirm-modal .btn-modal-secondary:hover {
            background: #374151;
            border-color: #60a5fa;
            color: #60a5fa;
            border-radius: 12px;
        }

        [data-theme="dark"] .confirm-modal .btn-modal-primary {
            background: #3b82f6;
            color: #ffffff;
        }

        [data-theme="dark"] .confirm-modal .btn-modal-primary:hover {
            background: #2563eb;
        }

        .confirm-modal .btn-modal-primary {
            background: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 50px;
            padding: 0.75rem 1.6rem;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .confirm-modal .btn-modal-primary:hover {
            background: #2563eb;
            border-radius: 12px;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .modal-header h3 { font-size: 1.2rem; color: var(--text-dark); }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-medium);
            font-size: 1.2rem;
            cursor: pointer;
        }

        .modal-text {
            font-size: 0.95rem;
            color: var(--text-medium);
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .btn-modal-primary {
            background: var(--btn-gradient);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 0.75rem 1.6rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s ease, border-radius 0.3s ease;
        }
        .btn-modal-primary:hover {
            background: var(--btn-gradient-hover);
            border-radius: 12px;
        }
        .btn-modal-secondary {
            background: transparent;
            border: 2px solid var(--border-light);
            color: var(--text-dark);
            border-radius: 50px;
            padding: 0.65rem 1.4rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, border-radius 0.3s ease, border-color 0.3s ease;
        }
        .btn-modal-secondary:hover {
            background: var(--input-bg);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            border-radius: 12px;
        }
    </style>

    <!-- Script: Notification Load -->
    <?php if (is_logged_in()): ?>
    <script>
        // Helper: always get fresh CSRF token
        function getCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        function showToast(message, type = 'info') {
            const toast = document.getElementById('appToast');
            if (!toast) {
                return;
            }
            toast.textContent = message;
            toast.classList.remove('error');
            if (type === 'error') {
                toast.classList.add('error');
            }
            toast.classList.add('show');
            clearTimeout(window.__toastTimer);
            window.__toastTimer = setTimeout(() => {
                toast.classList.remove('show');
            }, 2400);
        }

        // Load unread notification count on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetch('<?php echo APP_URL; ?>/api/notifications.php?action=count', {
                method: 'GET',
                headers: { 'X-CSRF-Token': getCsrf() }
            })
            .then(res => res.json())
            .then(data => {
                if (data.count > 0) {
                    document.getElementById('notif-count').textContent = data.count;
                    document.getElementById('notif-count').style.display = 'flex';
                }
            })
            .catch(err => console.error('Notification error:', err));

            const notifBtn = document.getElementById('notification-btn');
            if (notifBtn) {
                notifBtn.addEventListener('click', function () {
                    window.location.href = '<?php echo APP_URL; ?>/pages/notification.php';
                });
            }

            // ── Navbar theme toggle ────────────────────────────────────────
            const themeBtn = document.getElementById('themeToggleBtn');
            const themeIcon = document.getElementById('themeToggleIcon');
            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    const current = document.body.getAttribute('data-theme') || 'light';
                    const next = current === 'dark' ? 'light' : 'dark';
                    document.body.setAttribute('data-theme', next);
                    if (themeIcon) {
                        themeIcon.className = 'fas ' + (next === 'dark' ? 'fa-sun' : 'fa-moon');
                    }
                    // Persist to DB silently
                    fetch('<?php echo APP_URL; ?>/api/update_theme.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': getCsrf()
                        },
                        body: JSON.stringify({ theme: next })
                    }).catch(() => {});
                });
            }
        });

        const logoutModal = document.getElementById('logoutModal');

        document.addEventListener('DOMContentLoaded', function() {
            const logoutTriggers = document.querySelectorAll('[data-logout="true"]');
            const logoutCancelButtons = document.querySelectorAll('[data-logout-cancel="true"]');

            if (logoutModal && logoutTriggers.length > 0) {
                logoutTriggers.forEach(trigger => {
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        logoutModal.classList.add('active');
                    });
                });
            }

            if (logoutModal) {
                logoutCancelButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        logoutModal.classList.remove('active');
                    });
                });

                logoutModal.addEventListener('click', (event) => {
                    if (event.target === logoutModal) {
                        logoutModal.classList.remove('active');
                    }
                });

                // Keyboard: close on Escape
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                        logoutModal.classList.remove('active');
                    }
                });
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>
