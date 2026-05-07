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

    <div class="modal-overlay" id="logoutModal" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
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
            background: rgba(27, 54, 121, 0.25);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 350;
            padding: 1.5rem;
        }

        .modal-overlay.active { display: flex; }

        .confirm-modal {
            width: 100%;
            max-width: 420px;
            background-color: var(--bg-right);
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(27, 54, 121, 0.15);
            padding: 2rem;
            position: relative;
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
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
        }

        .btn-modal-primary:hover {
            background: var(--btn-gradient-hover);
            border-radius: 12px;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(61, 123, 244, 0.4);
        }

        .btn-modal-secondary {
            background: transparent;
            border: 2px solid var(--border-light);
            color: var(--text-dark);
            border-radius: 50px;
            padding: 0.65rem 1.4rem;
            font-weight: 600;
            cursor: pointer;
        }
    </style>

    <!-- Script: Notification Load -->
    <?php if (is_logged_in()): ?>
    <script>
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
                headers: {
                    'X-CSRF-Token': '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'
                }
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
        });

        const logoutModal = document.getElementById('logoutModal');
        const logoutTriggers = document.querySelectorAll('[data-logout="true"]');
        const logoutCancelButtons = document.querySelectorAll('[data-logout-cancel="true"]');

        if (logoutModal && logoutTriggers.length > 0) {
            logoutTriggers.forEach(trigger => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    logoutModal.classList.add('active');
                });
            });

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
        }
    </script>
    <?php endif; ?>

</body>
</html>
