<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

$today = date("Y-m-d");
$current_time = date("H:i:s");

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
     WHERE m.status = 'scheduled'
     AND (
        m.created_by = ?
        OR mp.user_id = ?
     )
     AND (
        m.meeting_date > ?
        OR (
            m.meeting_date = ?
            AND m.start_time >= ?
        )
     )
     ORDER BY m.meeting_date ASC,
               m.start_time ASC"
);

$stmt->bind_param(
    "iisss",
    $user_id,
    $user_id,
    $today,
    $today,
    $current_time
);

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MeetSync - Meeting Reminders</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --rm-primary:#0D9488;
  --rm-primary-hover:#0F766E;
  --rm-primary-soft:rgb(13 148 136 / 10%);
  --rm-bg:#F0FDFA;
  --rm-surface:#FFFFFF;
  --rm-text:#134E4A;
  --rm-text-secondary:#475569;
  --rm-border:#5EEAD4;
  --rm-radius:10px;
  --rm-shadow:0 1px 3px rgb(0 0 0 / 0.1),0 4px 12px rgb(0 0 0 / 0.08);
  --rm-shadow-lg:0 20px 40px rgb(0 0 0 / 0.1);
  --rm-focus:0 0 0 3px rgb(13 148 136 / 30%);
  --rm-destructive:#DC2626;
  --rm-destructive-soft:rgb(220 38 38 / 10%);
  --rm-success:#16a34a;
  --rm-font:"Fira Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
[data-theme="dark"]{
  --rm-primary:#2DD4BF;
  --rm-primary-hover:#5EEAD4;
  --rm-primary-soft:rgb(45 212 191 / 15%);
  --rm-bg:#0B1120;
  --rm-surface:#111827;
  --rm-text:#F8FAFC;
  --rm-text-secondary:#94A3B8;
  --rm-border:#1F2937;
  --rm-destructive:#FCA5A5;
  --rm-destructive-soft:rgb(239 68 68 / 15%);
  --rm-success:#4ade80;
}
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;min-height:100vh;background:var(--rm-bg);color:var(--rm-text);
  font-family:var(--rm-font);line-height:1.5;overflow-x:hidden;
}
.parallax-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none;}
.parallax-layer{position:absolute;border-radius:50%;filter:blur(60px);opacity:0.35;animation:float 14s ease-in-out infinite;}
.parallax-layer:nth-child(1){width:420px;height:420px;top:-120px;left:-120px;background:var(--rm-primary);animation-delay:0s;}
.parallax-layer:nth-child(2){width:320px;height:320px;bottom:-60px;right:-60px;background:#8b5cf6;animation-delay:-5s;}
.parallax-layer:nth-child(3){width:260px;height:260px;top:35%;left:45%;background:#06b6d4;animation-delay:-10s;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(24px,-36px) scale(1.06);}}

/* ---------- Navbar ---------- */
.navbar-top{position:sticky;top:0;z-index:100;border-bottom:1px solid var(--rm-border);background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);}
.navbar-container{max-width:1200px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:10px;}
.navbar-brand .logo-box{width:36px;height:36px;background:var(--rm-primary-soft);color:var(--rm-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;}
.navbar-brand h1{margin:0;font-size:17px;font-weight:700;color:var(--rm-text);}
.navbar-brand p{margin:0;font-size:11px;color:var(--rm-text-secondary);}
.navbar-links{display:flex;gap:6px;align-items:center;}
.navbar-links a{font-size:13px;color:var(--rm-text-secondary);text-decoration:none;padding:8px 14px;border-radius:6px;transition:background 150ms ease,color 150ms ease;}
.navbar-links a:hover{background:var(--rm-surface);color:var(--rm-primary);}
.navbar-links a.active{background:var(--rm-primary);color:#ffffff;}
.navbar-user{display:flex;align-items:center;gap:12px;}
.navbar-user .user-info{display:flex;flex-direction:column;align-items:flex-end;}
.navbar-user .user-name{font-size:13px;font-weight:600;color:var(--rm-text);}
.navbar-user .user-role{font-size:11px;color:var(--rm-text-secondary);}
.navbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--rm-primary-soft);color:var(--rm-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.navbar-logout{font-size:13px;color:var(--rm-destructive);text-decoration:none;padding:6px 12px;border-radius:6px;border:1px solid var(--rm-destructive-soft);transition:all 150ms ease;}
.navbar-logout:hover{background:var(--rm-destructive-soft);color:var(--rm-destructive);}
.mobile-toggle{display:none;background:none;border:none;color:var(--rm-text);font-size:20px;cursor:pointer;}

/* ---------- Page content ---------- */
.page-wrap{padding:40px 24px;max-width:1000px;margin:0 auto;width:100%;}
.page-header{display:flex;flex-direction:column;gap:8px;margin-bottom:28px;}
.page-header h2{margin:0;font-size:24px;font-weight:700;color:var(--rm-text);letter-spacing:-0.25px;}
.page-header p{margin:0;font-size:14px;color:var(--rm-text-secondary);}
.page-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:16px;}

/* ---------- Reminder cards ---------- */
.reminder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;}
.reminder-card{border:1px solid var(--rm-border);border-radius:var(--rm-radius);background:var(--rm-surface);box-shadow:var(--rm-shadow);overflow:hidden;display:flex;flex-direction:column;transition:all 150ms ease;}
.reminder-card:hover{box-shadow:var(--rm-shadow-lg);transform:translateY(-2px);border-color:var(--rm-primary);}
.reminder-card-body{padding:20px 20px 16px;}
.reminder-card h3{margin:0 0 10px;font-size:17px;font-weight:700;color:var(--rm-text);}
.reminder-meta{display:flex;flex-direction:column;gap:8px;}
.reminder-item{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--rm-text-secondary);}
.reminder-item .item-icon{width:16px;height:16px;color:var(--rm-primary);flex-shrink:0;}
.reminder-item strong{color:var(--rm-text);font-weight:600;}

/* ---------- Empty state ---------- */
.empty-state{display:flex;flex-direction:column;align-items:center;text-align:center;padding:60px 24px;color:var(--rm-text-secondary);}
.empty-icon{width:64px;height:64px;color:var(--rm-border);margin-bottom:16px;}
.empty-title{margin:0 0 8px;font-size:18px;font-weight:700;color:var(--rm-text);}
.empty-desc{margin:0 0 20px;font-size:14px;line-height:1.5;max-width:320px;}

/* ---------- Buttons ---------- */
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:0;border-radius:8px;background:var(--rm-primary);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease,transform 100ms ease;}
.btn:hover{background:var(--rm-primary-hover);}
.btn:active{transform:translateY(1px);}
.btn:focus-visible{outline:2px solid var(--rm-primary);outline-offset:2px;}
.btn-secondary{background:var(--rm-surface);color:var(--rm-text);border:1px solid var(--rm-border);}
.btn-secondary:hover{background:var(--rm-bg);border-color:var(--rm-border);}
.btn-success{background:var(--rm-success);color:#ffffff;}
.btn-success:hover{background:#15803d;}

@media (max-width:768px){
  .navbar-links{display:none;position:absolute;top:100%;left:0;right:0;background:var(--rm-surface);border-bottom:1px solid var(--rm-border);padding:16px 24px;flex-direction:column;gap:8px;box-shadow:var(--rm-shadow);}
  .navbar-links.open{display:flex;}
  .mobile-toggle{display:block;}
  .page-wrap{padding:24px 16px;}
  .reminder-grid{grid-template-columns:1fr;}
}
@media (max-width:560px){
  .page-wrap{padding:20px 16px;}
  .btn{width:100%;justify-content:center;}
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
      <a href="calendar.php">Calendar</a>
      <a href="reminders.php" class="active">Reminders</a>
      <a href="notifications.php">Notifications</a>
      <a href="department_schedule.php">Department Schedule</a>
      <a href="schedule_report.php">Schedule Reports</a>
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
<div class="page-header">
  <h2>Meeting Reminders</h2>
  <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong>. Here are your upcoming meetings.</p>
</div>
<div class="page-actions">
  <a href="calendar.php" class="btn btn-success">Open Calendar</a>
  <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($result->num_rows == 0): ?>
  <div class="empty-state">
    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="9" cy="7" r="4"></circle>
      <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    <h2 class="empty-title">No upcoming meetings</h2>
    <p class="empty-desc">You have no scheduled meetings coming up.</p>
  </div>
<?php else: ?>
  <div class="reminder-grid">
    <?php while ($meeting = $result->fetch_assoc()): ?>
      <div class="reminder-card">
        <div class="reminder-card-body">
          <h3><?php echo htmlspecialchars($meeting["title"]); ?></h3>
          <div class="reminder-meta">
            <div class="reminder-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
              <strong>Date:</strong> <?php echo date("d M Y", strtotime($meeting["meeting_date"])); ?>
            </div>
            <div class="reminder-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <strong>Time:</strong> <?php echo date("g:i A", strtotime($meeting["start_time"])); ?> - <?php echo date("g:i A", strtotime($meeting["end_time"])); ?>
            </div>
            <div class="reminder-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
              <strong>Organizer:</strong> <?php echo htmlspecialchars($meeting["organizer_name"]); ?>
            </div>
            <div class="reminder-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
              <strong>Location:</strong> <?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
  </div>
<?php endif; ?>
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