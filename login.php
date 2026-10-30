<?php

session_start();

require_once "includes/db.php";
require_once "includes/activity_log.php";

$message = "";

/*
 * Process the login form.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    /*
     * Clear any previous incomplete 2FA login attempt.
     */
    unset(
        $_SESSION["pending_2fa_user_id"],
        $_SESSION["pending_2fa_started_at"],
        $_SESSION["csrf_2fa_verify"]
    );

    if ($email === "" || $password === "") {

        $message = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role,
                    two_factor_enabled
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {

                /*
                 * Remove existing authenticated session data.
                 */
                unset(
                    $_SESSION["user_id"],
                    $_SESSION["user_name"],
                    $_SESSION["user_email"],
                    $_SESSION["user_role"]
                );

                /*
                 * Regenerate the session ID after successful
                 * password verification.
                 */
                session_regenerate_id(true);

                /*
                 * Accounts with 2FA must verify before access.
                 */
                if ((int) $user["two_factor_enabled"] === 1) {

                    $_SESSION["pending_2fa_user_id"] =
                        (int) $user["id"];

                    $_SESSION["pending_2fa_started_at"] =
                        time();

                    $stmt->close();

                    header("Location: verify_two_factor.php");
                    exit;

                } else {

                    $_SESSION["user_id"] = (int) $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = $user["role"];

                    logActivity(
                        $conn,
                        $user["id"],
                        "User Login",
                        "User logged into the system."
                    );

                    $stmt->close();

                    header("Location: dashboard.php");
                    exit;
                }

            } else {

                $message = "Invalid email or password.";
            }

        } else {

            $message = "Invalid email or password.";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MeetSync - Sign In</title>

    <link rel="stylesheet" href="css/style.css">

    <script src="js/app.js" defer></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        /* ========================================
           MEETSYNC LOGIN — 21st.dev shadcnstyle
           Professional card-centric design
        ======================================== */

        :root {
            --ms-primary: #2563eb;
            --ms-primary-hover: #1d4ed8;
            --ms-primary-soft: rgb(37 99 235 / 10%);
            --ms-background: #f8fafc;
            --ms-surface: #ffffff;
            --ms-text: #0f172a;
            --ms-text-secondary: #64748b;
            --ms-border: #e2e8f0;
            --ms-radius: 8px;
            --ms-shadow: 0 1px 3px rgb(0 0 0 / 0.1),
                0 4px 12px rgb(0 0 0 / 0.08);
            --ms-focus-ring: 0 0 0 3px rgb(37 99 235 / 30%);
            --ms-destructive: #dc2626;
            --ms-destructive-soft: rgb(220 38 38 / 10%);
            --ms-font: "Inter", -apple-system, BlinkMacSystemFont,
                "Segoe UI", Roboto, sans-serif;
        }

        [data-theme="dark"] {
            --ms-primary: #3b82f6;
            --ms-primary-hover: #2563eb;
            --ms-primary-soft: rgb(59 130 246 / 15%);
            --ms-background: #0f172a;
            --ms-surface: #1e293b;
            --ms-text: #f8fafc;
            --ms-text-secondary: #94a3b8;
            --ms-border: #334155;
            --ms-destructive: #fca5a5;
            --ms-destructive-soft: rgb(239 68 68 / 15%);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body.login-page {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--ms-background);
            color: var(--ms-text);
            font-family: var(--ms-font);
            line-height: 1.5;
        }

        /* ========================================
           BRAND + CARD LAYOUT
        ======================================== */

        .login-wrapper {
            width: 100%;
            max-width: 480px;
            padding: 32px 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            margin-bottom: 12px;
            background: var(--ms-primary-soft);
            color: var(--ms-primary);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -2px;
            border-radius: 12px;
        }

        .login-brand h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--ms-text);
        }

        .login-brand p {
            margin: 6px 0 0;
            color: var(--ms-text-secondary);
            font-size: 14px;
        }

        /* ========================================
           LOGIN CARD
        ======================================== */

        .login-card {
            width: 100%;
            border: 1px solid var(--ms-border);
            border-radius: var(--ms-radius);
            background: var(--ms-surface);
            box-shadow: var(--ms-shadow);
            padding: 32px 28px;
            box-sizing: border-box;
        }

        .login-card h2 {
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--ms-text);
            letter-spacing: -0.25px;
        }

        .login-subtitle {
            margin: 0 0 24px;
            color: var(--ms-text-secondary);
            font-size: 14px;
        }

        /* ========================================
           ERROR SUMMARY
        ======================================== */

        .login-error {
            margin-bottom: 16px;
            padding: 10px 14px;
            border: 1px solid var(--ms-destructive);
            border-radius: 6px;
            background: var(--ms-destructive-soft);
            color: var(--ms-destructive);
            font-size: 13px;
            line-height: 1.45;
        }

        /* ========================================
           FORM FIELDS
        ======================================== */

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .login-field {
            position: relative;
        }

        .login-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ms-text-secondary);
        }

        .login-input {
            width: 100%;
            min-height: 44px;
            padding: 0 12px 0 40px;
            box-sizing: border-box;
            border: 1px solid var(--ms-border);
            border-radius: 6px;
            background: var(--ms-surface);
            color: var(--ms-text);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .login-input::placeholder {
            color: var(--ms-text-secondary);
            opacity: 0.5;
        }

        .login-input:focus {
            border-color: var(--ms-primary);
            box-shadow: var(--ms-focus-ring);
        }

        .login-input[aria-invalid="true"] {
            border-color: var(--ms-destructive);
        }

        .login-input[aria-invalid="true"]:focus {
            box-shadow: 0 0 0 3px rgb(220 38 38 / 20%);
        }

        .field-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--ms-text-secondary);
            pointer-events: none;
        }

        .field-error-text {
            margin-top: 6px;
            font-size: 11.5px;
            color: var(--ms-destructive);
        }

        /* ========================================
           PASSWORD TOGGLE
        ======================================== */

        .login-password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            padding: 6px;
            border: 0;
            background: transparent;
            color: var(--ms-text-secondary);
            font-size: 13px;
            cursor: pointer;
            line-height: 1;
            transition: color 150ms ease;
        }

        .login-password-toggle:hover {
            color: var(--ms-primary);
        }

        .login-password-toggle:focus-visible {
            outline: 2px solid var(--ms-primary);
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* ========================================
           REMEMBER / FORGOT ROW
        ======================================== */

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 4px;
            margin-bottom: 20px;
        }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--ms-text-secondary);
            cursor: pointer;
        }

        .login-remember input {
            width: 16px;
            height: 16px;
            accent-color: var(--ms-primary);
        }

        .login-forgot {
            font-size: 13px;
            color: var(--ms-primary);
            text-decoration: none;
        }

        .login-forgot:hover {
            text-decoration: underline;
        }

        /* ========================================
           SIGN IN BUTTON
        ======================================== */

        .login-submit {
            width: 100%;
            min-height: 44px;
            border: 0;
            border-radius: 6px;
            background: var(--ms-primary);
            color: #ffffff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition:
                background 150ms ease,
                transform 100ms ease;
        }

        .login-submit:hover {
            background: var(--ms-primary-hover);
        }

        .login-submit:active {
            transform: translateY(1px);
        }

        .login-submit:focus-visible {
            outline: 2px solid var(--ms-primary);
            outline-offset: 2px;
        }

        .login-submit.is-loading {
            pointer-events: none;
            opacity: 0.85;
        }

        /* ========================================
           DIVIDER
        ======================================== */

        .login-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 24px 0;
            color: var(--ms-text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-divider::before,
        .login-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--ms-border);
        }

        /* ========================================
           GOOGLE SIGN IN
        ======================================== */

        .login-google-button {
            width: 100%;
            min-height: 44px;
            padding: 0 16px;
            box-sizing: border-box;
            border: 1px solid var(--ms-border);
            border-radius: 6px;
            background: var(--ms-surface);
            color: var(--ms-text);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition:
                border-color 150ms ease,
                background 150ms ease;
        }

        .login-google-button:hover {
            border-color: var(--ms-primary);
            background: var(--ms-primary-soft);
        }

        .login-google-button:focus-visible {
            outline: 2px solid var(--ms-primary);
            outline-offset: 2px;
        }

        .login-google-button svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* ========================================
           FOOTER LINKS
        ======================================== */

        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: var(--ms-text-secondary);
        }

        .login-footer a {
            color: var(--ms-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* ========================================
           DARK THEME
        ======================================== */

        body.login-page.login-dark .login-card {
            background: var(--ms-surface);
            border-color: var(--ms-border);
        }

        body.login-page.login-dark .login-brand-logo {
            background: var(--ms-primary-soft);
            color: var(--ms-primary);
        }

        body.login-page.login-dark .login-error {
            background: var(--ms-destructive-soft);
            border-color: var(--ms-destructive);
            color: var(--ms-destructive);
        }

        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 560px) {
            .login-wrapper {
                padding: 20px 16px;
            }

            .login-card {
                padding: 24px 20px;
            }

            .login-brand {
                margin-bottom: 32px;
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

<body class="login-page">

    <div class="login-wrapper">

        <div class="login-brand">

            <div class="login-brand-logo" aria-hidden="true">
                MS
            </div>

            <h1>MeetSync</h1>

            <p>Meeting Scheduling &amp; Coordination</p>

        </div>

        <section class="login-card" aria-labelledby="login-heading">

            <h2 id="login-heading">Sign in</h2>

            <p class="login-subtitle">
                Welcome back. Enter your credentials below.
            </p>

            <?php if ($message !== ""): ?>

                <div class="login-error" role="alert">

                    <?= htmlspecialchars(
                        $message,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>

            <form method="POST" action="" id="loginForm" class="login-form">

                <div class="login-field">

                    <label for="email">
                        Email address
                    </label>

                    <div class="login-input-wrapper" style="position: relative;">

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="login-input"
                            autocomplete="username"
                            placeholder="you@company.com"
                            value="<?= htmlspecialchars(
                                $_POST["email"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            required
                            autofocus
                        >

                        <svg
                            class="field-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>

                    </div>

                </div>

                <div class="login-field">

                    <label for="password">
                        Password
                    </label>

                    <div class="login-input-wrapper" style="position: relative;">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="login-input has-toggle"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            required
                        >

                        <svg
                            class="field-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>

                        <button
                            type="button"
                            class="login-password-toggle"
                            id="loginPasswordToggle"
                            aria-controls="password"
                            aria-pressed="false"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <div class="login-options">

                    <label class="login-remember">

                        <input
                            type="checkbox"
                            name="remember"
                            id="remember"
                        >

                        Remember me

                    </label>

                    <a href="forgot_password.php" class="login-forgot">
                        Forgot password?
                    </a>

                </div>

                <button type="submit" class="login-submit">
                    Sign in
                </button>

            </form>

            <div class="login-divider">
                <span>or</span>
            </div>

            <a
                href="google_login.php"
                class="login-google-button"
            >

                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 48 48"
                    aria-hidden="true"
                >
                    <path
                        fill="#EA4335"
                        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.01 13.22l7.98 6.19C11.89 13.72 17.45 9.5 24 9.5z"
                    />

                    <path
                        fill="#4285F4"
                        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 37.96 46.98 31.84 46.98 24.55z"
                    />

                    <path
                        fill="#FBBC05"
                        d="M9.99 28.59A14.4 14.4 0 0 1 9.22 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.87.93 7.52 2.01 10.78l7.98-6.19z"
                    />

                    <path
                        fill="#34A853"
                        d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.14 1.45-4.89 2.3-8.18 2.3-6.55 0-12.11-4.22-14.01-10.09l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                    />
                </svg>

                Sign in with Google

            </a>

            <p class="login-footer">
                Don't have an account?
                <a href="register.php">
                    Register here
                </a>
            </p>

        </section>

    </div>

    <!-- Login page theme toggle -->

    <button
        type="button"
        class="login-theme-toggle"
        id="loginThemeToggle"
        aria-label="Switch to dark theme"
        title="Switch theme"
        style="
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 50;
            width: 44px;
            height: 44px;
            padding: 0;
            border: 1px solid var(--ms-border);
            border-radius: 50%;
            background: var(--ms-surface);
            color: var(--ms-text-secondary);
            cursor: pointer;
            box-shadow: var(--ms-shadow);
            transition: color 150ms ease, border-color 150ms ease;
        "
    >

        <span class="active" data-theme-choice="light" aria-hidden="true" style="
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--ms-primary);
            color: #ffffff;
        ">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
        </span>

        <span data-theme-choice="dark" aria-hidden="true" style="
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            border-radius: 50%;
        ">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </span>

    </button>

    <script>
        /* Password visibility toggle */
        (function () {
            const passwordInput = document.getElementById("password");
            const passwordToggle = document.getElementById("loginPasswordToggle");

            passwordToggle.addEventListener("click", function () {
                const showingPassword =
                    passwordInput.type === "text";

                passwordInput.type = showingPassword
                    ? "password"
                    : "text";

                passwordToggle.textContent = showingPassword
                    ? "Show"
                    : "Hide";

                passwordToggle.setAttribute(
                    "aria-pressed",
                    String(!showingPassword)
                );
            });
        })();

        /* Login-page theme toggle */
        (function () {
            const themeButton = document.getElementById("loginThemeToggle");

            const body = document.body;
            const themeChoices = document.querySelectorAll(
                "[data-theme-choice]"
            );

            function applyLoginTheme(theme) {
                const isDark = theme === "dark";

                body.classList.toggle("login-dark", isDark);

                themeChoices.forEach(function (choice) {
                    const isActive =
                        choice.dataset.themeChoice === theme;

                    choice.style.background = isActive
                        ? "var(--ms-primary)"
                        : "var(--ms-surface)";
                    choice.style.color = isActive
                        ? "#ffffff"
                        : "var(--ms-text-secondary)";
                });

                themeButton.setAttribute(
                    "aria-label",
                    isDark
                        ? "Switch to light theme"
                        : "Switch to dark theme"
                );

                try {
                    localStorage.setItem("meetsync-login-theme", theme);
                } catch (error) {
                    // The page still works if storage is unavailable.
                }
            }

            let savedTheme = "light";

            try {
                savedTheme =
                    localStorage.getItem("meetsync-login-theme") || "light";
            } catch (error) {
                savedTheme = "light";
            }

            applyLoginTheme(savedTheme);

            themeButton.addEventListener("click", function () {
                const nextTheme = body.classList.contains("login-dark")
                    ? "light"
                    : "dark";

                applyLoginTheme(nextTheme);
            });
        })();
    </script>

</body>

</html>