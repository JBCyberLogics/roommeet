<?php

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$userId = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT two_factor_enabled
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    http_response_code(404);
    exit("User account not found.");
}

$twoFactorEnabled = (int) $user["two_factor_enabled"] === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetSync - Two-Factor Authentication</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ========================================
           MEETSYNC TWO-FACTOR AUTHENTICATION
           Editorial security page — no card kit,
           no eyebrow labels, no monospace tells.
        ======================================== */

        :root {
            --mfa-ink: #0F172A;
            --mfa-text: #334155;
            --mfa-surface: #FFFFFF;
            --mfa-bg: #F8FAFC;
            --mfa-border: #E2E8F0;
            --mfa-accent: #0D9488;
            --mfa-accent-hover: #0F766E;
            --mfa-accent-soft: rgb(13 148 136 / 10%);
            --mfa-unread: #FEF3C7;
            --mfa-focus: 0 0 0 3px rgb(13 148 136 / 30%);
            --mfa-font-display: "Crimson Pro", "Times New Roman", serif;
            --mfa-font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        [data-theme="dark"] {
            --mfa-ink: #F8FAFC;
            --mfa-text: #94A3B8;
            --mfa-surface: #0F172A;
            --mfa-bg: #020617;
            --mfa-border: #334155;
            --mfa-accent: #2DD4BF;
            --mfa-accent-hover: #5EEAD4;
            --mfa-accent-soft: rgb(45 212 191 / 15%);
            --mfa-focus: 0 0 0 3px rgb(45 212 191 / 35%);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100svh;
            background: var(--mfa-bg);
            color: var(--mfa-ink);
            font-family: var(--mfa-font-body);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ---------- Navbar ---------- */
        .navbar-top {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(8px);
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand .logo-box {
            width: 36px;
            height: 36px;
            background: var(--mfa-accent-soft);
            color: var(--mfa-accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
        }

        .navbar-brand h1 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: var(--mfa-ink);
            font-family: var(--mfa-font-display);
        }

        .navbar-brand p {
            margin: 0;
            font-size: 11px;
            color: var(--mfa-text);
        }

        .navbar-links {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .navbar-links a {
            font-size: 13px;
            color: var(--mfa-text);
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            transition: background 150ms ease, color 150ms ease;
        }

        .navbar-links a:hover {
            background: var(--mfa-surface);
            color: var(--mfa-accent);
        }

        .navbar-links a.active {
            background: var(--mfa-accent);
            color: #ffffff;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-user .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .navbar-user .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--mfa-ink);
        }

        .navbar-user .user-role {
            font-size: 11px;
            color: var(--mfa-text);
        }

        .navbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--mfa-accent-soft);
            color: var(--mfa-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .navbar-logout {
            font-size: 13px;
            color: var(--mfa-accent);
            text-decoration: none;
            padding: 6px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            cursor: pointer;
            transition: color 150ms ease;
        }

        .navbar-logout:hover {
            color: var(--mfa-accent-hover);
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--mfa-ink);
            font-size: 20px;
            cursor: pointer;
        }

        /* ---------- Page wrapper ---------- */
        .page-wrap {
            padding: 48px 24px;
            max-width: 720px;
            margin: 0 auto;
            width: 100%;
        }

        /* ---------- Hero statement ---------- */
        .hero {
            margin-bottom: 48px;
            max-width: 48ch;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 6vw, 48px);
            font-weight: 700;
            color: var(--mfa-ink);
            line-height: 1.1;
            font-family: var(--mfa-font-display);
            letter-spacing: -0.02em;
        }

        .hero p {
            margin-top: 16px;
            font-size: 17px;
            color: var(--mfa-text);
            line-height: 1.6;
        }

        /* ---------- Status panel ---------- */
        .status-panel {
            border: 1px solid var(--mfa-border);
            border-radius: 8px;
            background: var(--mfa-surface);
            padding: 32px 24px;
            margin-bottom: 32px;
        }

        .status-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .status-head h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: var(--mfa-ink);
            font-family: var(--mfa-font-display);
            letter-spacing: -0.02em;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: none;
            letter-spacing: 0.04em;
        }

        .status-pill.enabled {
            background: rgb(22 163 74 / 10%);
            color: #15803d;
        }

        .status-pill.disabled {
            background: rgb(220 38 38 / 10%);
            color: #b91c1c;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-dot.enabled { background: #15803d; }
        .status-dot.disabled { background: #b91c1c; }

        .status-description {
            font-size: 15px;
            color: var(--mfa-text);
            line-height: 1.6;
            margin: 0 0 24px;
        }

        /* ---------- Message ---------- */
        .message {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
            border: 1px solid;
            margin-bottom: 20px;
        }

        .message-success {
            background: rgb(22 163 74 / 10%);
            border-color: #15803d;
            color: #15803d;
        }

        .message-error {
            background: rgb(220 38 38 / 10%);
            border-color: #b91c1c;
            color: #b91c1c;
        }

        /* ---------- Actions ---------- */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 24px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: var(--mfa-accent);
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 150ms ease, transform 100ms ease;
            text-align: left;
        }

        .btn:hover {
            background: var(--mfa-accent-hover);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn:focus-visible {
            outline: 2px solid var(--mfa-accent);
            outline-offset: 2px;
        }

        .btn-outline {
            background: var(--mfa-surface);
            color: var(--mfa-ink);
            border: 1px solid var(--mfa-border);
        }

        .btn-outline:hover {
            background: var(--mfa-bg);
            border-color: var(--mfa-border);
        }

        .btn-danger {
            background: #b91c1c;
            color: #ffffff;
            border: 1px solid #b91c1c;
        }

        .btn-danger:hover {
            background: #991b1b;
        }

        .btn-secondary {
            background: var(--mfa-surface);
            color: var(--mfa-ink);
            border: 1px solid var(--mfa-border);
        }

        .btn-secondary:hover {
            background: var(--mfa-bg);
            border-color: var(--mfa-border);
        }

        .actions p {
            margin: 0;
        }

        .footer-link {
            margin-top: 32px;
            display: inline-block;
            font-size: 14px;
            color: var(--mfa-accent);
            text-decoration: none;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .navbar-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--mfa-surface);
                padding: 16px 24px;
                flex-direction: column;
                gap: 8px;
            }

            .navbar-links.open {
                display: flex;
            }

            .mobile-toggle {
                display: block;
            }

            .page-wrap {
                padding: 24px 16px;
            }
        }

        @media (max-width: 560px) {
            .page-wrap {
                padding: 20px 16px;
            }

            .status-panel {
                padding: 24px 16px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="uploads/profile_pictures/favicon.jpeg">
</head>
<body>

    <nav class="navbar-top">
        <div class="navbar-container">
            <div class="navbar-brand">
                <div class="logo-box">MS</div>
                <div>
                    <h1>MeetSync</h1>
                    <p>Meeting Coordination</p>
                </div>
            </div>
            <div class="navbar-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">My Profile</a>
                <a href="change_password.php">Change Password</a>
                <a href="two_factor_settings.php" class="active">Two-Factor Auth</a>
                <a href="../logout.php">Logout</a>
            </div>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($_SESSION["user_role"]); ?></div>
                </div>
                <div class="navbar-avatar"><?php echo strtoupper(substr(htmlspecialchars($_SESSION["user_name"]), 0, 1)); ?></div>
                <a href="../logout.php" class="navbar-logout">Logout</a>
            </div>
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation" aria-expanded="false">&#9776;</button>
        </div>
    </nav>

    <div class="page-wrap">

        <!-- Hero statement -->
        <div class="hero">
            <h1>Two-factor authentication</h1>
            <p>Add a second step to your login. Even if someone has your password, they can’t get in without the code from your authenticator app.</p>
        </div>

        <div class="status-panel">
            <div class="status-head">
                <h2><?php echo $twoFactorEnabled ? "Enabled" : "Disabled"; ?></h2>
                <span class="status-pill <?php echo $twoFactorEnabled ? "enabled" : "disabled"; ?>">
                    <span class="status-dot <?php echo $twoFactorEnabled ? "enabled" : "disabled"; ?>"></span>
                    <?php echo $twoFactorEnabled ? "Active" : "Inactive"; ?>
                </span>
            </div>

            <?php if ($twoFactorEnabled): ?>
                <p class="status-description">Your account requires a six-digit authenticator code at login. You can also sign in with a recovery code.</p>

                <div class="actions">
                    <p>
                        <a href="regenerate_recovery_codes.php" class="btn btn-outline">Regenerate recovery codes</a>
                    </p>
                    <p>
                        <a href="disable_two_factor.php" class="btn btn-danger">Disable two-factor authentication</a>
                    </p>
                </div>

            <?php else: ?>
                <p class="status-description">Two-factor authentication is off. Your account is protected by password only.</p>

                <div class="actions">
                    <p>
                        <a href="two_factor_setup.php" class="btn">Enable two-factor authentication</a>
                    </p>
                </div>

            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="footer-link">Back to dashboard</a>

    </div>

    <script>
        document.getElementById("mobileToggle").addEventListener("click", function () {
            const links = document.querySelector(".navbar-links");
            const open = links.classList.toggle("open");
            this.setAttribute("aria-expanded", String(open));
        });
    </script>
</body>
</html>

<?php
$stmt->close();
?>