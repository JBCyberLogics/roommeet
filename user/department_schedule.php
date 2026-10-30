<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];


/*
 * Get the logged-in user's department.
 */
$user_stmt = $conn->prepare(
    "SELECT department
     FROM users
     WHERE id = ?"
);

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();

$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_stmt->close();


$department = $user["department"] ?? "";


/*
 * Get scheduled meetings involving users
 * from the same department.
 */
$stmt = $conn->prepare(
    "SELECT DISTINCT
        m.id,
        m.title,
        m.meeting_date,
        m.start_time,
        m.end_time,
        m.location,
        u.name AS organizer_name
     FROM meetings m
     INNER JOIN users u
        ON m.created_by = u.id
     LEFT JOIN meeting_participants mp
        ON m.id = mp.meeting_id
     LEFT JOIN users participant
        ON mp.user_id = participant.id
     WHERE m.status = 'scheduled'
     AND (
        u.department = ?
        OR participant.department = ?
     )
     ORDER BY m.meeting_date ASC,
               m.start_time ASC"
);

$stmt->bind_param(
    "ss",
    $department,
    $department
);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MeetSync - Department Schedule</title>

<link rel="stylesheet" href="../css/style.css">

<script src="../js/app.js"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ========================================
   MEETSYNC DEPARTMENT SCHEDULE
   Editorial schedule list — serif headlines,
   sans body, centered composition, no card kit.
======================================== */

:root {
  --ds-ink: #0F172A;
  --ds-text: #334155;
  --ds-surface: #FFFFFF;
  --cs-bg: #F8FAFC;
  --ds-border: #E2E8F0;
  --ds-accent: #0D9488;
  --ds-accent-hover: #0F766E;
  --ds-accent-soft: rgb(13 148 136 / 10%);
  --ds-destructive: #DC2626;
  --ds-destructive-soft: rgb(220 38 38 / 10%);
  --ds-focus: 0 0 0 3px rgb(13 148 136 / 30%);
  --ds-font-display: "Crimson Pro", "Times New Roman", serif;
  --ds-font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --ds-bg: #F8FAFC;
}

[data-theme="dark"] {
  --ds-ink: #F8FAFC;
  --ds-text: #94A3B8;
  --ds-surface: #0F172A;
  --ds-bg: #020617;
  --ds-border: #334155;
  --ds-accent: #2DD4BF;
  --ds-accent-hover: #5EEAD4;
  --ds-accent-soft: rgb(45 212 191 / 15%);
  --ds-destructive: #FCA5A5;
  --ds-destructive-soft: rgb(239 68 68 / 15%);
  --ds-focus: 0 0 0 3px rgb(45 212 191 / 35%);
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
  background: var(--ds-bg);
  color: var(--ds-ink);
  font-family: var(--ds-font-body);
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
  background: var(--ds-accent-soft);
  color: var(--ds-accent);
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
  color: var(--ds-ink);
  font-family: var(--ds-font-display);
}

.navbar-brand p {
  margin: 0;
  font-size: 11px;
  color: var(--ds-text);
}

.navbar-links {
  display: flex;
  gap: 6px;
  align-items: center;
}

.navbar-links a {
  font-size: 13px;
  color: var(--ds-text);
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 8px;
  transition: background 150ms ease, color 150ms ease;
}

.navbar-links a:hover {
  background: var(--ds-surface);
  color: var(--ds-accent);
}

.navbar-links a.active {
  background: var(--ds-accent);
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
  color: var(--ds-ink);
}

.navbar-user .user-role {
  font-size: 11px;
  color: var(--ds-text);
}

.navbar-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--ds-accent-soft);
  color: var(--ds-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}

.navbar-logout {
  font-size: 13px;
  color: var(--ds-accent);
  text-decoration: none;
  padding: 6px 12px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  transition: color 150ms ease;
}

.navbar-logout:hover {
  color: var(--ds-accent-hover);
}

.mobile-toggle {
  display: none;
  background: none;
  border: none;
  color: var(--ds-ink);
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

/* ---------- Centered hero statement ---------- */
.hero {
  max-width: 46ch;
  text-align: center;
  margin-bottom: 40px;
}

.hero h1 {
  margin: 0 0 12px;
  font-size: clamp(30px, 6vw, 44px);
  font-weight: 700;
  color: var(--ds-ink);
  line-height: 1.1;
  font-family: var(--ds-font-display);
  letter-spacing: -0.02em;
}

.hero p {
  margin: 0;
  font-size: 17px;
  color: var(--ds-text);
  line-height: 1.7;
}

/* ---------- Centered department card ---------- */
.dept-wrap {
  width: 100%;
  max-width: 420px;
  background: var(--ds-surface);
  border: 1px solid var(--ds-border);
  border-radius: 8px;
  padding: 40px 32px;
}

/* ---------- Messages ---------- */
.message {
  padding: 14px 18px;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.5;
  border: 1px solid;
}

.message-error {
  background: var(--ds-destructive-soft);
  border-color: var(--ds-destructive);
  color: var(--ds-destructive);
}

.message-success {
  background: rgb(22 163 74 / 10%);
  border-color: #15803d;
  color: #15803d;
}

.message-info {
  background: rgb(13 148 136 / 10%);
  border-color: var(--ds-accent);
  color: var(--ds-ink);
}

/* ---------- Department info ---------- */
.department-info {
  margin-bottom: 24px;
  padding: 16px 20px;
  background: var(--ds-accent-soft);
  border-radius: 8px;
  border-left: 4px solid var(--ds-accent);
}

.department-info .dept-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--ds-accent);
  letter-spacing: 0.08em;
  text-transform: none;
  margin-bottom: 4px;
}

.department-info .dept-value {
  font-size: 16px;
  font-weight: 700;
  color: var(--ds-ink);
  margin: 0;
}

/* ---------- Schedule list ---------- */
.schedule-list {
  margin-bottom: 32px;
}

.schedule-list .empty {
  text-align: center;
  padding: 48px 24px;
  color: var(--ds-text);
}

.schedule-list .empty h2 {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 700;
  color: var(--ds-ink);
  font-family: var(--ds-font-display);
}

.schedule-list .empty p {
  margin: 0;
  font-size: 15px;
  color: var(--ds-text);
}

.schedule-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid var(--ds-border);
}

.schedule-table th {
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--ds-text);
  letter-spacing: 0.08em;
  padding: 12px 16px;
  border-bottom: 1px solid var(--ds-border);
  background: var(--cs-bg);
}

.schedule-table td {
  padding: 14px 16px;
  font-size: 14px;
  color: var(--ds-ink);
  border-bottom: 1px solid var(--ds-border);
  vertical-align: top;
}

.schedule-table tr:last-child td {
  border-bottom: none;
}

.schedule-table tr:hover {
  background: var(--cs-bg);
}

.schedule-title {
  font-weight: 700;
  color: var(--ds-ink);
}

.schedule-meeting {
  font-weight: 600;
  color: var(--ds-accent);
  text-decoration: none;
}

.schedule-meeting:hover {
  text-decoration: underline;
}

.schedule-time {
  font-size: 13px;
  color: var(--ds-text);
}

.schedule-location {
  font-size: 13px;
  color: var(--ds-text);
}

.schedule-organizer {
  font-size: 13px;
  color: var(--ds-text);
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
  border-radius: 8px;
  background: var(--ds-accent);
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
  background: var(--ds-accent-hover);
}

.btn:active {
  transform: translateY(1px);
}

.btn:focus-visible {
  outline: 2px solid var(--ds-accent);
  outline-offset: 2px;
}

.btn-secondary {
  background: var(--ds-surface);
  color: var(--ds-ink);
  border: 1px solid var(--ds-border);
}

.btn-secondary:hover {
  background: var(--cs-bg);
  border-color: var(--ds-border);
}

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
  .navbar-links {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--ds-surface);
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

  .dept-wrap {
    padding: 32px 24px;
  }

  .schedule-table {
    font-size: 13px;
  }

  .schedule-table th,
  .schedule-table td {
    padding: 12px 14px;
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
    <link rel="stylesheet" href="../css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="../uploads/profile_pictures/favicon.jpeg">
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
      <a href="../dashboard.php">Dashboard</a>
      <a href="my_meetings.php">My Meetings</a>
      <a href="my_invitations.php">My Invitations</a>
      <a href="calendar.php">Calendar</a>
      <a href="notifications.php">Notifications</a>
      <a href="reminders.php">Reminders</a>
      <a href="department_schedule.php" class="active">Department Schedule</a>
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

  <div class="hero">
    <h1>Department Schedule</h1>
    <p>Scheduled meetings for your department, <strong><?php echo htmlspecialchars($department ?: "Not specified"); ?></strong>.</p>
  </div>

  <div class="dept-wrap">
    <?php if (!empty($message)): ?>
      <div class="message message-<?php echo $message_type; ?>" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="department-info">
      <p class="dept-label">Department</p>
      <p class="dept-value"><?php echo htmlspecialchars($department ?: "Not specified"); ?></p>
    </div>

    <div class="schedule-list">
      <?php if ($result->num_rows == 0): ?>
        <div class="empty">
          <h2>No scheduled meetings</h2>
          <p>There are no scheduled meetings for your department yet.</p>
        </div>
      <?php else: ?>
        <table class="schedule-table">
          <thead>
            <tr>
              <th>Meeting</th>
              <th>Date</th>
              <th>Time</th>
              <th>Location</th>
              <th>Organizer</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($meeting = $result->fetch_assoc()): ?>
              <tr>
                <td>
                  <a href="edit_meeting.php?id=<?php echo $meeting["id"]; ?>" class="schedule-meeting">
                    <?php echo htmlspecialchars($meeting["title"]); ?>
                  </a>
                </td>
                <td class="schedule-time">
                  <?php echo date("d M Y", strtotime($meeting["meeting_date"])); ?>
                </td>
                <td class="schedule-time">
                  <?php echo date("g:i A", strtotime($meeting["start_time"])); ?> - <?php echo date("g:i A", strtotime($meeting["end_time"])); ?>
                </td>
                <td class="schedule-location">
                  <?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?>
                </td>
                <td class="schedule-organizer">
                  <?php echo htmlspecialchars($meeting["organizer_name"]); ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="form-actions">
      <a href="calendar.php" class="btn">Open Calendar</a>
      <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

  </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>