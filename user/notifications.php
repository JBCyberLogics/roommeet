<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        n.id,
        n.message,
        n.is_read,
        n.created_at,
        m.title AS meeting_title
     FROM notifications n
     LEFT JOIN meetings m
        ON n.meeting_id = m.id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MeetSync - Notifications</title>

<link rel="stylesheet" href="../css/style.css">

<script src="../js/app.js"></script>

<style>
/* ========================================
   MEETSYNC NOTIFICATIONS — editorial layout
   Chronological communication log, not card grid.
======================================== */

:root{
  --notify-ink:#0F172A;
  --notify-text:#334155;
  --notify-surface:#FFFFFF;
  --notify-bg:#F8FAFC;
  --notify-border:#E2E8F0;
  --notify-accent:#0D9488;
  --notify-accent-hover:#0F766E;
  --notify-accent-soft:rgb(13 148 136 / 10%);
  --notify-unread:#FEF3C7;
  --notify-read:#F1F5F9;
  --notify-radius:0;
  --notify-shadow:none;
  --notify-focus:0 0 0 3px rgb(13 148 136 / 30%);
  --notify-font:"Georgia", "Times New Roman", serif;
  --notify-font-sans:"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
[data-theme="dark"]{
  --notify-ink:#F8FAFC;
  --notify-text:#94A3B8;
  --notify-surface:#0F172A;
  --notify-bg:#020617;
  --notify-border:#334155;
  --notify-accent:#2DD4BF;
  --notify-accent-hover:#5EEAD4;
  --notify-accent-soft:rgb(45 212 191 / 15%);
  --notify-unread:#451A03;
  --notify-read:#1E293B;
}
*,
*::before,
*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;min-height:100svh;background:var(--notify-bg);color:var(--notify-ink);
  font-family:var(--notify-font-sans);line-height:1.5;overflow-x:hidden;
}
.parallax-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none;}
.parallax-layer{position:absolute;border-radius:50%;filter:blur(60px);opacity:0.35;animation:float 14s ease-in-out infinite;}
.parallax-layer:nth-child(1){width:420px;height:420px;top:-120px;left:-120px;background:var(--notify-accent);animation-delay:0s;}
.parallax-layer:nth-child(2){width:320px;height:320px;bottom:-60px;right:-60px;background:var(--notify-accent-soft);animation-delay:-5s;}
.parallax-layer:nth-child(3){width:260px;height:260px;top:35%;left:45%;background:#06b6d4;animation-delay:-10s;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(24px,-36px) scale(1.06);}}

/* ---------- Navbar ---------- */
.navbar-top{position:sticky;top:0;z-index:100;background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);}
.navbar-container{max-width:1200px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:10px;}
.navbar-brand .logo-box{width:36px;height:36px;background:var(--notify-accent-soft);color:var(--notify-accent);border-radius: 8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;}
.navbar-brand h1{margin:0;font-size:17px;font-weight:700;color:var(--notify-ink);}
.navbar-brand p{margin:0;font-size:11px;color:var(--notify-text);}
.navbar-links{display:flex;gap:6px;align-items:center;}
.navbar-links a{font-size:13px;color:var(--notify-text);text-decoration:none;padding:8px 14px;border-radius: 8px;transition:background 150ms ease,color 150ms ease;}
.navbar-links a:hover{background:var(--notify-surface);color:var(--notify-accent);}
.navbar-links a.active{background:var(--notify-accent);color:#ffffff;}
.navbar-user{display:flex;align-items:center;gap:12px;}
.navbar-user .user-info{display:flex;flex-direction:column;align-items:flex-end;}
.navbar-user .user-name{font-size:13px;font-weight:600;color:var(--notify-ink);}
.navbar-user .user-role{font-size:11px;color:var(--notify-text);}
.navbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--notify-accent-soft);color:var(--notify-accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.navbar-logout{font-size:13px;color:var(--notify-accent);text-decoration:none;padding:6px 12px;border:0;border-radius: 8px;background:transparent;cursor:pointer;transition:color 150ms ease;}
.navbar-logout:hover{color:var(--notify-accent-hover);}
.mobile-toggle{display:none;background:none;border:none;color:var(--notify-ink);font-size:20px;cursor:pointer;}

/* ---------- Page wrapper ---------- */
.page-wrap{padding:40px 24px;max-width:1200px;margin:0 auto;width:100%;}

/* ---------- Hero: serif headline statement ---------- */
.hero{margin-bottom:48px;}
.hero h1{margin:0;font-size:clamp(32px,6vw,48px);font-weight:700;color:var(--notify-ink);line-height:1.1;max-width:42ch;letter-spacing:-0.02em;}
.hero p{margin-top:16px;font-size:16px;color:var(--notify-text);line-height:1.6;max-width:52ch;}

/* ---------- Empty state ---------- */
.empty-state{text-align:left;padding:48px 0;}
.empty-eyebrow{font-size:11px;font-weight:600;letter-spacing:0.06em;color:var(--notify-accent);text-transform:none;display:block;margin-bottom:8px;}
.empty h2{margin:0 0 16px;font-size:24px;font-weight:600;color:var(--notify-ink);line-height:1.2;}
.empty p{margin:0 0 24px;font-size:16px;color:var(--notify-text);line-height:1.6;max-width:42ch;}
.empty .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border:0;border-radius: 8px;background:var(--notify-accent);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease;}
.empty .btn:hover{background:var(--notify-accent-hover);}

/* ---------- Notification log ---------- */
.notification-log{border-collapse:separate;border-spacing:0;}
.notification-log thead th{
  text-align:left;
  font-size:11px;
  font-weight:700;
  color:var(--notify-text);
  letter-spacing:0.08em;
  padding:16px 24px;
  border-bottom:1px solid var(--notify-border);
}
.notification-log tbody tr{
  border-bottom:1px solid var(--notify-border);
  transition:background 150ms ease;
}
.notification-log tbody tr:last-child{border-bottom:none;}
.notification-log tbody tr.unread{
  background:var(--notify-unread);
  border-left:3px solid var(--notify-accent);
}
.notification-log tbody tr:hover{background:rgb(241 245 249 / 0.6);}
.notification-log td{padding:18px 24px;font-size:15px;color:var(--notify-ink);vertical-align:top;}
.notification-log .meeting-title{font-weight:600;color:var(--notify-accent);font-size:15px;}
.notification-log .message{font-size:14px;color:var(--notify-text);margin-top:4px;line-height:1.5;}
.notification-log .meta{font-size:12px;color:var(--notify-text);margin-top:6px;}
.notification-log .status{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:0.06em;padding:4px 10px;border-radius: 8px;text-transform:none;}
.notification-log .status.unread{background:#FDE68A;color:#92400E;}
.notification-log .status.read{background:#E2E8F0;color:#475569;}
.notification-log .mark-read{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--notify-accent);text-decoration:none;padding:6px 10px;border:1px solid var(--notify-accent);border-radius: 8px;transition:all 150ms ease;}
.notification-log .mark-read:hover{background:var(--notify-accent);color:#ffffff;}

/* ---------- Actions ---------- */
.page-actions{display:flex;gap:12px;margin-top:32px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border:0;border-radius: 8px;background:var(--notify-accent);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease,transform 100ms ease;}
.btn:hover{background:var(--notify-accent-hover);}
.btn:active{transform:translateY(1px);}
.btn:focus-visible{outline:2px solid var(--notify-accent);outline-offset:2px;}
.btn-secondary{background:var(--notify-surface);color:var(--notify-ink);border:1px solid var(--notify-border);}
.btn-secondary:hover{background:var(--notify-bg);border-color:var(--notify-border);}

/* ---------- Responsive ---------- */
@media (max-width:768px){
  .navbar-links{display:none;position:absolute;top:100%;left:0;right:0;background:var(--notify-surface);padding:16px 24px;flex-direction:column;gap:8px;}
  .navbar-links.open{display:flex;}
  .mobile-toggle{display:block;}
  .page-wrap{padding:24px 16px;}
  .notification-log td{padding:16px;}
}
@media (max-width:560px){
  .page-wrap{padding:20px 16px;}
  .notification-log{font-size:13px;}
  .notification-log td{padding:14px;}
  .notification-log .status{font-size:10px;padding:3px 8px;}
}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{transition:none !important;animation:none !important;}
}
</style>
    <link rel="stylesheet" href="../css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="../uploads/profile_pictures/favicon.jpeg">
</head>
<body>

<div class="parallax-bg">
  <div class="parallax-layer"></div>
  <div class="parallax-layer"></div>
  <div class="parallax-layer"></div>
</div>

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
      <a href="notifications.php" class="active">Notifications</a>
      <a href="reminders.php">Reminders</a>
      <a href="calendar.php">Calendar</a>
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

<!-- Hero: serif headline statement -->
<div class="hero">
  <h1>Your activity log</h1>
  <p>Recent updates about meetings you created or were invited to.</p>
</div>

<?php if ($result->num_rows == 0): ?>

  <div class="empty-state">
    <h2>No notifications</h2>
    <p>You haven't received any meeting notifications. Activity here will appear when someone invites you to a meeting or updates one you're part of.</p>
    <a href="dashboard.php" class="btn">Back to Dashboard</a>
  </div>

<?php else: ?>

  <table class="notification-log">
    <thead>
      <tr>
        <th style="width:24%;">Meeting</th>
        <th style="width:28%;">Message</th>
        <th style="width:20%;">Date</th>
        <th style="width:14%;">Status</th>
        <th style="width:14%;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($notification = $result->fetch_assoc()): ?>
        <tr class="<?php echo $notification["is_read"] == 0 ? "unread" : ""; ?>">
          <td>
            <?php if (!empty($notification["meeting_title"])): ?>
              <span class="meeting-title"><?php echo htmlspecialchars($notification["meeting_title"]); ?></span>
            <?php else: ?>
              <span style="color:var(--notify-text);">Meeting update</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="message"><?php echo htmlspecialchars($notification["message"]); ?></div>
          </td>
          <td>
            <div class="meta"><?php echo htmlspecialchars($notification["created_at"]); ?></div>
          </td>
          <td>
            <span class="status <?php echo $notification["is_read"] == 0 ? "unread" : "read"; ?>">
              <?php echo $notification["is_read"] == 0 ? "Unread" : "Read"; ?>
            </span>
          </td>
          <td>
            <?php if ($notification["is_read"] == 0): ?>
              <a
                href="mark_notification_read.php?id=<?php echo (int) $notification["id"]; ?>"
                class="mark-read"
              >
                Mark read
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

<?php endif; ?>

<div class="page-actions">
  <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

</div>

<script>
document.getElementById("mobileToggle").addEventListener("click", function(){
  const links=document.querySelector(".navbar-links");
  const open=links.classList.toggle("open");
  this.setAttribute("aria-expanded", String(open));
});
</script>

</body>
</html>

<?php
$stmt->close();
?>