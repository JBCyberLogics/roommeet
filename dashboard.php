<?php

require_once "includes/auth.php";
require_once "includes/db.php";

$user_id = $_SESSION["user_id"];

/*
 * Count unread notifications.
 */
$notification_stmt = $conn->prepare(
    "SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE user_id = ?
     AND is_read = 0"
);

$notification_stmt->bind_param("i", $user_id);
$notification_stmt->execute();

$notification_result = $notification_stmt->get_result();
$notification_data = $notification_result->fetch_assoc();

$unread_count = (int) $notification_data["unread_count"];

$notification_stmt->close();

/*
 * Get upcoming meetings involving the current user.
 * Exclude cancelled meetings.
 */
$meeting_stmt = $conn->prepare(
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
     WHERE m.status = 'scheduled'
     AND (
        m.created_by = ?
        OR mp.user_id = ?
     )
     AND (
        m.meeting_date > CURDATE()
        OR (
            m.meeting_date = CURDATE()
            AND m.start_time >= CURTIME()
        )
     )
     ORDER BY m.meeting_date ASC,
              m.start_time ASC
     LIMIT 5"
);

$meeting_stmt->bind_param("ii", $user_id, $user_id);
$meeting_stmt->execute();

$meeting_result = $meeting_stmt->get_result();
$upcoming_count = $meeting_result->num_rows;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MeetSync Dashboard</title>
<link rel="stylesheet" href="css/style.css">
<script src="js/app.js" defer></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --dash-ink: #0F172A;
  --dash-text: #334155;
  --dash-surface: #FFFFFF;
  --dash-bg: #F8FAFC;
  --dash-border: #E2E8F0;
  --dash-accent: #0D9488;
  --dash-accent-hover: #0F766E;
  --dash-accent-soft: rgb(13 148 136 / 10%);
  --dash-destructive: #DC2626;
  --dash-destructive-soft: rgb(220 38 38 / 10%);
  --dash-success: #16A34A;
  --dash-success-soft: rgb(22 163 74 / 10%);
  --dash-focus: 0 0 0 3px rgb(13 148 136 / 30%);
  --dash-font-display: "Crimson Pro", "Times New Roman", serif;
  --dash-font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
[data-theme="dark"] {
  --dash-ink: #F8FAFC;
  --dash-text: #94A3B8;
  --dash-surface: #0F172A;
  --dash-bg: #020617;
  --dash-border: #334155;
  --dash-accent: #2DD4BF;
  --dash-accent-hover: #5EEAD4;
  --dash-accent-soft: rgb(45 212 191 / 15%);
  --dash-destructive: #FCA5A5;
  --dash-destructive-soft: rgb(239 68 68 / 15%);
  --dash-success: #4ADE80;
  --dash-success-soft: rgb(74 222 128 / 10%);
  --dash-focus: 0 0 0 3px rgb(45 212 191 / 35%);
}
*,
*::before,
*::after {
  box-sizing: border-box;
}
html {
  scroll-behavior: smooth;
}
body.dashboard-page {
  margin: 0;
  min-height: 100svh;
  background: var(--dash-bg);
  color: var(--dash-ink);
  font-family: var(--dash-font-body);
  line-height: 1.6;
  overflow-x: hidden;
}
.parallax-bg {
  position: fixed;
  inset: 0;
  z-index: -1;
  overflow: hidden;
  pointer-events: none;
}
.parallax-layer {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  opacity: 0.35;
  animation: float 14s ease-in-out infinite;
}
.parallax-layer:nth-child(1) {
  width: 420px;
  height: 420px;
  top: -120px;
  left: -120px;
  background: var(--dash-accent);
  animation-delay: 0s;
}
.parallax-layer:nth-child(2) {
  width: 320px;
  height: 320px;
  bottom: -60px;
  right: -60px;
  background: var(--dash-primary-soft);
  animation-delay: -5s;
}
.parallax-layer:nth-child(3) {
  width: 260px;
  height: 260px;
  top: 35%;
  left: 45%;
  background: #06b6d4;
  animation-delay: -10s;
}
@keyframes float {
  0%, 100% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(24px, -36px) scale(1.06); }
}
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
  background: var(--dash-accent-soft);
  color: var(--dash-accent);
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
  color: var(--dash-ink);
  font-family: var(--dash-font-display);
}
.navbar-brand p {
  margin: 0;
  font-size: 11px;
  color: var(--dash-text);
}
.navbar-links {
  display: flex;
  gap: 6px;
  align-items: center;
}
.navbar-links a {
  font-size: 13px;
  color: var(--dash-text);
  text-decoration: none;
  padding: 8px 14px;
  border-radius: 8px;
  transition: background 150ms ease, color 150ms ease;
}
.navbar-links a:hover {
  background: var(--dash-surface);
  color: var(--dash-accent);
}
.navbar-links a.active {
  background: var(--dash-accent);
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
  color: var(--dash-ink);
}
.navbar-user .user-role {
  font-size: 11px;
  color: var(--dash-text);
}
.navbar-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--dash-accent-soft);
  color: var(--dash-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}
.navbar-logout {
  font-size: 13px;
  color: var(--dash-accent);
  text-decoration: none;
  padding: 6px 12px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  transition: color 150ms ease;
}
.navbar-logout:hover {
  color: var(--dash-accent-hover);
}
.mobile-toggle {
  display: none;
  background: none;
  border: none;
  color: var(--dash-ink);
  font-size: 20px;
  cursor: pointer;
}
.page-wrap {
  min-height: calc(100svh - 68px);
  padding: 48px 24px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}
.hero {
  max-width: 46ch;
  text-align: center;
  margin-bottom: 48px;
}
.hero h1 {
  margin: 0 0 12px;
  font-size: clamp(34px, 6vw, 52px);
  font-weight: 700;
  color: var(--dash-ink);
  line-height: 1.1;
  font-family: var(--dash-font-display);
  letter-spacing: -0.02em;
}
.hero p {
  margin: 0;
  font-size: 18px;
  color: var(--dash-text);
  line-height: 1.7;
}
.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}
.dashboard-stat-card {
  padding: 28px 24px;
  border-radius: var(--dash-radius);
  background: var(--dash-surface);
  border: 1px solid var(--dash-border);
}
.stat-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.stat-card-top h3 {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--dash-text);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.stat-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--dash-accent-soft);
  color: var(--dash-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
}
.dashboard-stat-card .stat-number {
  margin: 16px 0 5px;
  color: var(--dash-ink);
  font-size: 40px;
  font-weight: 800;
  font-family: var(--dash-font-display);
}
.stat-description {
  margin: 0;
  color: var(--dash-muted);
  font-size: 14px;
}
.dashboard-panel {
  margin-bottom: 32px;
  background: var(--dash-surface);
  border: 1px solid var(--dash-border);
  border-radius: var(--dash-radius);
  overflow: hidden;
}
.dashboard-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 24px 24px 0;
}
.dashboard-panel-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: var(--dash-ink);
  font-family: var(--dash-font-display);
  letter-spacing: -0.02em;
}
.dashboard-panel-header p {
  margin: 0;
  color: var(--dash-muted);
  font-size: 13px;
}
.dashboard-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: 1px solid var(--dash-border);
  border-radius: 8px;
  background: transparent;
  color: var(--dash-ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: background 150ms ease, border-color 150ms ease, color 150ms ease;
}
.dashboard-action:hover {
  background: var(--dash-accent);
  color: #ffffff;
  border-color: var(--dash-accent);
}
.dashboard-panel table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.dashboard-panel th {
  text-align: left;
  padding: 14px 16px;
  font-size: 11px;
  font-weight: 700;
  color: var(--dash-text);
  letter-spacing: 0.08em;
  border-bottom: 1px solid var(--dash-border);
  border-top: 1px solid var(--dash-border);
}
.dashboard-panel td {
  padding: 14px 16px;
  color: var(--dash-ink);
  border-bottom: 1px solid var(--dash-border);
  vertical-align: top;
}
.dashboard-panel tbody tr:last-child td {
  border-bottom: none;
}
.dashboard-panel tbody tr:hover {
  background: rgb(248 250 252 / 0.5);
}
.schedule-meeting {
  font-weight: 700;
  color: var(--dash-ink);
  text-decoration: none;
}
.schedule-meeting:hover {
  color: var(--dash-accent);
}
.dashboard-quick-links {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.quick-link-card {
  display: block;
  padding: 24px;
  border: 1px solid var(--dash-border);
  border-radius: var(--dash-radius);
  color: var(--dash-ink);
  text-decoration: none;
  transition: border-color 150ms ease, background 150ms ease;
}
.quick-link-card:hover {
  border-color: var(--dash-accent);
  background: rgb(248 250 252);
}
.quick-link-card strong {
  display: block;
  margin-bottom: 8px;
  color: var(--dash-accent);
  font-size: 16px;
  font-weight: 700;
}
.quick-link-card span {
  display: block;
  color: var(--dash-muted);
  font-size: 13px;
  line-height: 1.6;
}
.message {
  padding: 14px 18px;
  margin-bottom: 24px;
  border-radius: 8px;
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
  background: rgb(220 38 38 / 10%);
  border-color: #b91c1c;
  color: #b91c1c;
}
.message-info {
  background: rgb(13 148 136 / 10%);
  border-color: var(--dash-accent);
  color: var(--dash-ink);
}
@media (max-width: 768px) {
  .navbar-links {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--dash-surface);
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
  .dashboard-stats {
    grid-template-columns: 1fr;
  }
  .dashboard-quick-links {
    grid-template-columns: 1fr;
  }
  .dashboard-topbar {
    flex-wrap: wrap;
    gap: 12px;
  }
}
@media (max-width: 560px) {
  .dashboard-main {
    padding: 14px 16px;
  }
  .hero h1 {
    font-size: clamp(24px, 10vw, 32px);
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
<body class="dashboard-page">
<div class="parallax-bg">
  <div class="parallax-layer"></div>
  <div class="parallax-layer"></div>
  <div class="parallax-layer"></div>
</div>
<nav class="navbar-top">
  <div class="navbar-container">
    <div class="navbar-brand">
      <div class="logo-box">MS</div>
      <div class="navbar-brand-copy">
        <strong>MeetSync</strong>
        <span>Meeting workspace</span>
      </div>
    </div>
    <div class="navbar-links" id="dashboardNavigation">
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="user/create_meeting.php" class="nav-create">Create meeting</a>
      <details class="nav-group">
        <summary>Meetings</summary>
        <div class="nav-dropdown">
          <a href="user/my_meetings.php">My meetings</a>
          <a href="user/my_invitations.php">Invitations</a>
        </div>
      </details>
      <details class="nav-group">
        <summary>Planning</summary>
        <div class="nav-dropdown">
          <a href="user/calendar.php">Calendar</a>
          <a href="user/reminders.php">Meeting reminders</a>
          <a href="user/department_schedule.php">Department schedule</a>
          <a href="user/schedule_report.php">Schedule reports</a>
        </div>
      </details>
      <details class="nav-group">
        <summary>Account</summary>
        <div class="nav-dropdown">
          <a href="profile.php">My profile</a>
          <a href="change_password.php">Change password</a>
          <a href="two_factor_settings.php">Two-factor authentication</a>
          <a href="user/notifications.php">Notifications</a>
        </div>
      </details>
      <?php if ($_SESSION["user_role"] === "admin"): ?>
        <a href="admin/index.php">Administration</a>
      <?php endif; ?>
    </div>
    <div class="navbar-user">
      <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION["user_role"]); ?></div>
      </div>
      <div class="navbar-avatar"><?php echo strtoupper(substr(htmlspecialchars($_SESSION["user_name"]), 0, 1)); ?></div>
      <a href="logout.php" class="navbar-logout">Logout</a>
    </div>
    <button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Toggle navigation" aria-controls="dashboardNavigation" aria-expanded="false">
      <span class="mobile-toggle-icon" aria-hidden="true">&#9776;</span>
      <span>Menu</span>
    </button>
  </div>
</nav>
<main class="page-wrap dashboard-home">
  <header class="dashboard-welcome">
    <div class="dashboard-welcome-copy">
      <p class="dashboard-eyebrow">MEETING OVERVIEW</p>
      <h1>Your workspace</h1>
      <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong>. Here’s what’s happening with your meetings.</p>
    </div>
    <div class="dashboard-welcome-actions">
      <a class="dashboard-action dashboard-action-primary" href="user/create_meeting.php"><span aria-hidden="true">+</span> Create meeting</a>
      <a class="dashboard-action dashboard-action-secondary" href="user/calendar.php">Open calendar <span aria-hidden="true">&#8599;</span></a>
    </div>
  </header>

  <section class="dashboard-stats" aria-label="Meeting summary">
    <article class="dashboard-stat-card">
      <div class="stat-card-top">
        <h2>Unread notifications</h2>
        <span class="stat-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        </span>
      </div>
      <p class="stat-number"><?php echo $unread_count; ?></p>
      <div class="stat-card-bottom">
        <p class="stat-description">Updates that need your attention</p>
        <a href="user/notifications.php">View notifications <span aria-hidden="true">&#8594;</span></a>
      </div>
    </article>
    <article class="dashboard-stat-card">
      <div class="stat-card-top">
        <h2>Upcoming meetings</h2>
        <span class="stat-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
        </span>
      </div>
      <p class="stat-number"><?php echo $upcoming_count; ?></p>
      <div class="stat-card-bottom">
        <p class="stat-description">Scheduled meetings on your calendar</p>
        <a href="user/calendar.php">View your calendar <span aria-hidden="true">&#8594;</span></a>
      </div>
    </article>
  </section>

  <div class="dashboard-content-grid">
    <section class="dashboard-panel dashboard-meetings" aria-labelledby="upcoming-meetings-title">
      <div class="dashboard-panel-header">
        <div>
          <p class="dashboard-section-label">YOUR SCHEDULE</p>
          <h2 id="upcoming-meetings-title">Upcoming meetings</h2>
          <p>Your next scheduled meetings at a glance.</p>
        </div>
        <span class="meeting-count"><?php echo $upcoming_count; ?> upcoming</span>
      </div>
      <?php if ($upcoming_count === 0): ?>
        <div class="dashboard-empty">
          <span class="dashboard-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M9 15h2"/></svg>
          </span>
          <h3>Your calendar is clear</h3>
          <p>When you’re invited to a meeting or schedule one, it will appear here.</p>
          <a class="dashboard-action dashboard-action-primary" href="user/create_meeting.php">Schedule a meeting</a>
        </div>
      <?php else: ?>
        <div class="table-container dashboard-meeting-table">
          <table>
            <caption class="visually-hidden">Upcoming meetings including date, time, organizer, and location</caption>
            <thead>
              <tr>
                <th scope="col">Meeting</th>
                <th scope="col">Date</th>
                <th scope="col">Time</th>
                <th scope="col">Organizer</th>
                <th scope="col">Location</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($meeting = $meeting_result->fetch_assoc()): ?>
                <tr>
                  <td><a href="edit_meeting.php?id=<?php echo $meeting["id"]; ?>" class="schedule-meeting"><?php echo htmlspecialchars($meeting["title"]); ?></a></td>
                  <td class="schedule-time"><?php echo date("d M Y", strtotime($meeting["meeting_date"])); ?></td>
                  <td class="schedule-time"><?php echo date("g:i A", strtotime($meeting["start_time"])); ?> - <?php echo date("g:i A", strtotime($meeting["end_time"])); ?></td>
                  <td class="schedule-organizer"><?php echo htmlspecialchars($meeting["organizer_name"]); ?></td>
                  <td class="schedule-location"><?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <p class="table-scroll-hint">Swipe to view all meeting details</p>
      <?php endif; ?>
    </section>

    <aside class="dashboard-side-column">
      <section class="dashboard-panel dashboard-shortcuts" aria-labelledby="quick-access-title">
        <div class="dashboard-panel-header">
          <div>
            <p class="dashboard-section-label">GET THINGS DONE</p>
            <h2 id="quick-access-title">Quick access</h2>
            <p>Common meeting tasks.</p>
          </div>
        </div>
        <nav class="dashboard-quick-links" aria-label="Quick access">
          <a href="user/create_meeting.php" class="quick-link-card">
            <span class="quick-link-icon" aria-hidden="true">+</span>
            <span class="quick-link-copy"><strong>Create a meeting</strong><span>Set up a new meeting</span></span>
            <span class="quick-link-arrow" aria-hidden="true">&#8599;</span>
          </a>
          <a href="user/my_meetings.php" class="quick-link-card">
            <span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h2M14 14h2"/></svg></span>
            <span class="quick-link-copy"><strong>Manage meetings</strong><span>Review and update plans</span></span>
            <span class="quick-link-arrow" aria-hidden="true">&#8599;</span>
          </a>
          <a href="user/my_invitations.php" class="quick-link-card">
            <span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span>
            <span class="quick-link-copy"><strong>View invitations</strong><span>Respond to your invites</span></span>
            <span class="quick-link-arrow" aria-hidden="true">&#8599;</span>
          </a>
        </nav>
      </section>

      <section class="dashboard-calendar-card" aria-label="Calendar shortcut">
        <span class="calendar-card-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h2M14 14h2M8 18h2"/></svg>
        </span>
        <div>
          <h2>Plan your week</h2>
          <p>See your team's schedule and find a time that works.</p>
          <a href="user/department_schedule.php">View department schedule <span aria-hidden="true">&#8594;</span></a>
        </div>
      </section>
    </aside>
  </div>

  <footer class="dashboard-footer">
    MeetSync &copy; <?php echo date("Y"); ?> <span aria-hidden="true">&middot;</span> Meeting Scheduling and Coordination System
  </footer>
</main>
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
$meeting_stmt->close();
?>
