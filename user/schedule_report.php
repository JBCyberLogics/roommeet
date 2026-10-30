<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        m.title,
        m.meeting_date,
        m.start_time,
        m.end_time,
        m.location,
        m.status,
        u.name AS organizer
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
     ORDER BY m.meeting_date ASC,
              m.start_time ASC"
);

$stmt->bind_param(
    "ii",
    $user_id,
    $user_id
);

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MeetSync - Meeting Schedule Report</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --sr-primary:#0D9488;
  --sr-primary-hover:#0F766E;
  --sr-primary-soft:rgb(13 148 136 / 10%);
  --sr-bg:#F0FDFA;
  --sr-surface:#FFFFFF;
  --sr-text:#134E4A;
  --sr-text-secondary:#475569;
  --sr-border:#5EEAD4;
  --sr-radius:10px;
  --sr-shadow:0 1px 3px rgb(0 0 0 / 0.1),0 4px 12px rgb(0 0 0 / 0.08);
  --sr-shadow-lg:0 20px 40px rgb(0 0 0 / 0.1);
  --sr-focus:0 0 0 3px rgb(13 148 136 / 30%);
  --sr-destructive:#DC2626;
  --sr-destructive-soft:rgb(220 38 38 / 10%);
  --sr-success:#16a34a;
  --sr-font:"Fira Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
[data-theme="dark"]{
  --sr-primary:#2DD4BF;
  --sr-primary-hover:#5EEAD4;
  --sr-primary-soft:rgb(45 212 191 / 15%);
  --sr-bg:#0B1120;
  --sr-surface:#111827;
  --sr-text:#F8FAFC;
  --sr-text-secondary:#94A3B8;
  --sr-border:#1F2937;
  --sr-destructive:#FCA5A5;
  --sr-destructive-soft:rgb(239 68 68 / 15%);
  --sr-success:#4ade80;
}
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;min-height:100vh;background:var(--sr-bg);color:var(--sr-text);
  font-family:var(--sr-font);line-height:1.5;overflow-x:hidden;
}
.parallax-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none;}
.parallax-layer{position:absolute;border-radius:50%;filter:blur(60px);opacity:0.35;animation:float 14s ease-in-out infinite;}
.parallax-layer:nth-child(1){width:420px;height:420px;top:-120px;left:-120px;background:var(--sr-primary);animation-delay:0s;}
.parallax-layer:nth-child(2){width:320px;height:320px;bottom:-60px;right:-60px;background:#8b5cf6;animation-delay:-5s;}
.parallax-layer:nth-child(3){width:260px;height:260px;top:35%;left:45%;background:#06b6d4;animation-delay:-10s;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(24px,-36px) scale(1.06);}}

/* ---------- Navbar ---------- */
.navbar-top{position:sticky;top:0;z-index:100;border-bottom:1px solid var(--sr-border);background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);}
.navbar-container{max-width:1200px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:10px;}
.navbar-brand .logo-box{width:36px;height:36px;background:var(--sr-primary-soft);color:var(--sr-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;}
.navbar-brand h1{margin:0;font-size:17px;font-weight:700;color:var(--sr-text);}
.navbar-brand p{margin:0;font-size:11px;color:var(--sr-text-secondary);}
.navbar-links{display:flex;gap:6px;align-items:center;}
.navbar-links a{font-size:13px;color:var(--sr-text-secondary);text-decoration:none;padding:8px 14px;border-radius:6px;transition:background 150ms ease,color 150ms ease;}
.navbar-links a:hover{background:var(--sr-surface);color:var(--sr-primary);}
.navbar-links a.active{background:var(--sr-primary);color:#ffffff;}
.navbar-user{display:flex;align-items:center;gap:12px;}
.navbar-user .user-info{display:flex;flex-direction:column;align-items:flex-end;}
.navbar-user .user-name{font-size:13px;font-weight:600;color:var(--sr-text);}
.navbar-user .user-role{font-size:11px;color:var(--sr-text-secondary);}
.navbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--sr-primary-soft);color:var(--sr-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.navbar-logout{font-size:13px;color:var(--sr-destructive);text-decoration:none;padding:6px 12px;border-radius:6px;border:1px solid var(--sr-destructive-soft);transition:all 150ms ease;}
.navbar-logout:hover{background:var(--sr-destructive-soft);color:var(--sr-destructive);}
.mobile-toggle{display:none;background:none;border:none;color:var(--sr-text);font-size:20px;cursor:pointer;}

/* ---------- Page content ---------- */
.page-wrap{padding:40px 24px;max-width:1000px;margin:0 auto;width:100%;}
.page-header{display:flex;flex-direction:column;gap:8px;margin-bottom:28px;}
.page-header h2{margin:0;font-size:24px;font-weight:700;color:var(--sr-text);letter-spacing:-0.25px;}
.page-header p{margin:0;font-size:14px;color:var(--sr-text-secondary);}
.page-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:16px;}

/* ---------- Report table ---------- */
.report-card{border:1px solid var(--sr-border);border-radius:var(--sr-radius);background:var(--sr-surface);box-shadow:var(--rm-shadow);overflow:hidden;}
.report-card{border:1px solid var(--sr-border);border-radius:var(--sr-radius);background:var(--sr-surface);box-shadow:var(--sr-shadow);overflow:hidden;}
.report-table{width:100%;border-collapse:collapse;}
.report-table th{text-align:left;padding:12px 16px;font-size:12px;font-weight:700;color:var(--sr-text-secondary);text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--sr-border);background:var(--sr-bg);}
.report-table td{padding:14px 16px;font-size:13px;color:var(--sr-text);border-bottom:1px solid var(--sr-border);}
.report-table tr:last-child td{border-bottom:none;}
.report-table tr:hover{background:var(--sr-bg);}

/* ---------- Empty state ---------- */
.empty-state{display:flex;flex-direction:column;align-items:center;text-align:center;padding:60px 24px;color:var(--sr-text-secondary);}
.empty-icon{width:64px;height:64px;color:var(--sr-border);margin-bottom:16px;}
.empty-title{margin:0 0 8px;font-size:18px;font-weight:700;color:var(--sr-text);}
.empty-desc{margin:0 0 20px;font-size:14px;line-height:1.5;max-width:320px;}

/* ---------- Buttons ---------- */
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:0;border-radius:8px;background:var(--sr-primary);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease,transform 100ms ease;}
.btn:hover{background:var(--sr-primary-hover);}
.btn:active{transform:translateY(1px);}
.btn:focus-visible{outline:2px solid var(--sr-primary);outline-offset:2px;}
.btn-secondary{background:var(--sr-surface);color:var(--sr-text);border:1px solid var(--sr-border);}
.btn-secondary:hover{background:var(--sr-bg);border-color:var(--sr-border);}
.btn-success{background:var(--sr-success);color:#ffffff;}
.btn-success:hover{background:#15803d;}

@media (max-width:768px){
  .navbar-links{display:none;position:absolute;top:100%;left:0;right:0;background:var(--sr-surface);border-bottom:1px solid var(--sr-border);padding:16px 24px;flex-direction:column;gap:8px;box-shadow:var(--sr-shadow);}
  .navbar-links.open{display:flex;}
  .mobile-toggle{display:block;}
  .page-wrap{padding:24px 16px;}
  .report-card{overflow-x:auto;}
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
      <a href="reminders.php">Reminders</a>
      <a href="notifications.php">Notifications</a>
      <a href="schedule_report.php" class="active">Schedule Reports</a>
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
  <h2>Meeting Schedule Report</h2>
  <p>This is a read-only meeting schedule.</p>
</div>
<div class="page-actions">
  <a href="download_schedule_report.php" class="btn btn-success">Download Schedule Report</a>
  <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="report-card">
<?php if ($result->num_rows > 0): ?>
  <table class="report-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Start Time</th>
        <th>End Time</th>
        <th>Meeting</th>
        <th>Organizer</th>
        <th>Location</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($meeting = $result->fetch_assoc()): ?>
        <tr>
          <td><?php echo date("d M Y", strtotime($meeting["meeting_date"])); ?></td>
          <td><?php echo date("g:i A", strtotime($meeting["start_time"])); ?></td>
          <td><?php echo date("g:i A", strtotime($meeting["end_time"])); ?></td>
          <td><?php echo htmlspecialchars($meeting["title"]); ?></td>
          <td><?php echo htmlspecialchars($meeting["organizer"]); ?></td>
          <td><?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <div class="empty-state">
    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="9" cy="7" r="4"></circle>
      <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    <h2 class="empty-title">No scheduled meetings</h2>
    <p class="empty-desc">No scheduled meetings found in the report.</p>
  </div>
<?php endif; ?>
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