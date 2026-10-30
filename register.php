<?php

require_once "includes/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $department = trim($_POST["department"]);
    $password = $_POST["password"];


    if (
        empty($name) ||
        empty($email) ||
        empty($department) ||
        empty($password)
    ) {

        $message = "Please fill in all fields.";
        $message_type = "error";

    } else {

        // Check whether email already exists
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();


        if ($result->num_rows > 0) {

            $message = "This email is already registered.";
            $message_type = "error";

        } else {

            // Securely hash the password
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // Insert new user
            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, email, department, password)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $department,
                $hashed_password
            );


            if ($stmt->execute()) {

                $message =
                    "Registration successful! You can now log in.";
                $message_type = "success";

            } else {

                $message =
                    "Registration failed. Please try again.";
                $message_type = "error";
            }


            $stmt->close();
        }


        $check->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MeetSync - Register</title>

<link rel="stylesheet" href="css/style.css">

<script src="js/app.js"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ========================================
   MEETSYNC REGISTER — Centered form
   Same design system as profile.php and change_password.php
======================================== */

:root {
  --cp-ink: #0F172A;
  --cp-text: #334155;
  --cp-surface: #FFFFFF;
  --cp-bg: #F8FAFC;
  --cp-border: #E2E8F0;
  --cp-accent: #0D9488;
  --cp-accent-hover: #0F766E;
  --cp-accent-soft: rgb(13 148 136 / 10%);
  --cp-destructive: #DC2626;
  --cp-destructive-soft: rgb(220 38 38 / 10%);
  --cp-focus: 0 0 0 3px rgb(13 148 136 / 30%);
  --cp-font-display: "Crimson Pro", "Times New Roman", serif;
  --cp-font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

[data-theme="dark"] {
  --cp-ink: #F8FAFC;
  --cp-text: #94A3B8;
  --cp-surface: #0F172A;
  --cp-bg: #020617;
  --cp-border: #334155;
  --cp-accent: #2DD4BF;
  --cp-accent-hover: #5EEAD4;
  --cp-accent-soft: rgb(45 212 191 / 15%);
  --cp-destructive: #FCA5A5;
  --cp-destructive-soft: rgb(239 68 68 / 15%);
  --cp-focus: 0 0 0 3px rgb(45 212 191 / 35%);
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
  background: var(--cp-bg);
  color: var(--cp-ink);
  font-family: var(--cp-font-body);
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
  background: var(--cp-accent-soft);
  color: var(--cp-accent);
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
  color: var(--cp-ink);
  font-family: var(--cp-font-display);
}

.navbar-brand p {
  margin: 0;
  font-size: 11px;
  color: var(--cp-text);
}

.navbar-links {
  display: flex;
  gap: 6px;
  align-items: center;
}

.navbar-links a {
  font-size: 13px;
  color: var(--cp-text);
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 8px;
  transition: background 150ms ease, color 150ms ease;
}

.navbar-links a:hover {
  background: var(--cp-surface);
  color: var(--cp-accent);
}

.navbar-links a.active {
  background: var(--cp-accent);
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
  color: var(--cp-ink);
}

.navbar-user .user-role {
  font-size: 11px;
  color: var(--cp-text);
}

.navbar-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--cp-accent-soft);
  color: var(--cp-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}

.navbar-logout {
  font-size: 13px;
  color: var(--cp-accent);
  text-decoration: none;
  padding: 6px 12px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  transition: color 150ms ease;
}

.navbar-logout:hover {
  color: var(--cp-accent-hover);
}

.mobile-toggle {
  display: none;
  background: none;
  border: none;
  color: var(--cp-ink);
  font-size: 20px;
  cursor: pointer;
}

/* ---------- Page wrapper ---------- */
.page-wrap {
  min-height: calc(100svh - 68px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

/* ---------- Centered hero ---------- */
.hero {
  max-width: 46ch;
  text-align: center;
  margin-bottom: 40px;
}

.hero h1 {
  margin: 0 0 12px;
  font-size: clamp(30px, 6vw, 44px);
  font-weight: 700;
  color: var(--cp-ink);
  line-height: 1.1;
  font-family: var(--cp-font-display);
  letter-spacing: -0.02em;
}

.hero p {
  margin: 0;
  font-size: 17px;
  color: var(--cp-text);
  line-height: 1.7;
}

/* ---------- Centered form ---------- */
.form-wrap {
  width: 100%;
  max-width: 420px;
  background: var(--cp-glass-bg);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border: 1px solid var(--cp-glass-border);
  border-radius: 12px;
  padding: 40px 32px;
}

.form-message {
  margin-bottom: 24px;
}

.message {
  padding: 14px 18px;
  border-radius: 0;
  font-size: 14px;
  line-height: 1.5;
  border: 1px solid;
}

.message-success {
  background: rgb(22 163 74 / 10%);
  border-color: #15803d;
  color: #15803d;
}

.message-error {
  background: var(--cp-destructive-soft);
  border-color: var(--cp-destructive);
  color: var(--cp-destructive);
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-field label {
  font-size: 13px;
  font-weight: 600;
  color: var(--cp-text);
}

.form-field input {
  width: 100%;
  min-height: 48px;
  padding: 0 16px;
  border: 1px solid var(--cp-border);
  border-radius: 0;
  background: rgba(255, 255, 255, 0.55);
  color: var(--cp-ink);
  font-family: inherit;
  font-size: 15px;
  outline: none;
  transition: border-color 150ms ease, outline 150ms ease, background 150ms ease;
}

.form-field input:focus {
  border-color: var(--cp-accent);
  outline: 3px solid rgb(13 148 136 / 30%);
  outline-offset: 2px;
}

.form-field input[aria-invalid="true"] {
  border-color: var(--cp-destructive);
}

.form-field input::placeholder {
  color: var(--cp-text);
  opacity: 0.45;
}

.field-hint {
  font-size: 11.5px;
  color: var(--cp-text);
  margin-top: 4px;
}

[data-theme="dark"] .form-field input {
  background: rgba(255, 255, 255, 0.12);
  color: var(--cp-ink);
}

/* ---------- Actions ---------- */
.form-actions {
  margin-top: 32px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  border: 0;
  border-radius: 0;
  background: var(--cp-accent);
  color: #ffffff;
  font-family: inherit;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  width: 100%;
  transition: background 150ms ease, transform 100ms ease;
}

.btn:hover {
  background: var(--cp-accent-hover);
}

.btn:active {
  transform: translateY(1px);
}

.btn:focus-visible {
  outline: 2px solid var(--cp-accent);
  outline-offset: 2px;
}

.btn-secondary {
  background: var(--cp-surface);
  color: var(--cp-ink);
  border: 1px solid var(--cp-border);
}

.btn-secondary:hover {
  background: var(--cp-bg);
  border-color: var(--cp-border);
}

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
  .navbar-links {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--cp-surface);
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
    padding: 20px 16px;
  }
}

@media (max-width: 560px) {
  .page-wrap {
    padding: 20px 16px;
  }

  .form-wrap {
    padding: 32px 24px;
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
      <a href="login.php">Log in</a>
    </div>
    <div class="navbar-user">
      <div class="navbar-avatar"><?php echo strtoupper(substr(htmlspecialchars($_SESSION["user_name"] ?? "Guest"), 0, 1)); ?></div>
    </div>
    <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation" aria-expanded="false">&#9776;</button>
  </div>
</nav>

<div class="page-wrap">

  <div class="hero">
    <h1>Create your account</h1>
    <p>Register to use the MeetSync meeting scheduling system.</p>
  </div>

  <div class="form-wrap">
    <?php if (!empty($message)): ?>
      <div class="message message-<?php echo $message_type; ?>" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="">

      <div class="form-grid">
        <div class="form-field">
          <label for="name">Full Name</label>
          <input
            type="text"
            id="name"
            name="name"
            autocomplete="name"
            required
            value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
          >
        </div>
        <div class="form-field">
          <label for="email">Email Address</label>
          <input
            type="email"
            id="email"
            name="email"
            autocomplete="email"
            required
            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
          >
        </div>
        <div class="form-field">
          <label for="department">Department</label>
          <input
            type="text"
            id="department"
            name="department"
            placeholder="e.g. ICT"
            required
            value="<?php echo htmlspecialchars($_POST["department"] ?? ""); ?>"
          >
        </div>
        <div class="form-field">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            autocomplete="new-password"
            required
          >
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn">Create account</button>
      </div>

      <p class="login-link">
        Already have an account?
        <a href="login.php">Log in</a>
      </p>

    </form>
  </div>

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
