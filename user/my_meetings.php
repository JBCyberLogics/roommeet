<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT id, title, description, meeting_date,
            start_time, end_time, location, status
     FROM meetings
     WHERE created_by = ?
     ORDER BY meeting_date ASC, start_time ASC"
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
<title>MeetSync - My Meetings</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --mm-primary:#0D9488;
  --mm-primary-hover:#0F766E;
  --mm-primary-soft:rgb(13 148 136 / 10%);
  --mm-bg:#F0FDFA;
  --mm-surface:#FFFFFF;
  --mm-text:#134E4A;
  --mm-text-secondary:#475569;
  --mm-border:#5EEAD4;
  --mm-radius:10px;
  --mm-shadow:0 1px 3px rgb(0 0 0 / 0.1),0 4px 12px rgb(0 0 0 / 0.08);
  --mm-shadow-lg:0 20px 40px rgb(0 0 0 / 0.1);
  --mm-focus:0 0 0 3px rgb(13 148 136 / 30%);
  --mm-destructive:#DC2626;
  --mm-destructive-soft:rgb(220 38 38 / 10%);
  --mm-success:#16a34a;
  --mm-font:"Fira Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
[data-theme="dark"]{
  --mm-primary:#2DD4BF;
  --mm-primary-hover:#5EEAD4;
  --mm-primary-soft:rgb(45 212 191 / 15%);
  --mm-bg:#0B1120;
  --mm-surface:#111827;
  --mm-text:#F8FAFC;
  --mm-text-secondary:#94A3B8;
  --mm-border:#1F2937;
  --mm-destructive:#FCA5A5;
  --mm-destructive-soft:rgb(239 68 68 / 15%);
  --mm-success:#4ade80;
}
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;min-height:100vh;background:var(--mm-bg);color:var(--mm-text);
  font-family:var(--mm-font);line-height:1.5;overflow-x:hidden;
}
.parallax-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none;}
.parallax-layer{position:absolute;border-radius:50%;filter:blur(60px);opacity:0.35;animation:float 14s ease-in-out infinite;}
.parallax-layer:nth-child(1){width:420px;height:420px;top:-120px;left:-120px;background:var(--mm-primary);animation-delay:0s;}
.parallax-layer:nth-child(2){width:320px;height:320px;bottom:-60px;right:-60px;background:#8b5cf6;animation-delay:-5s;}
.parallax-layer:nth-child(3){width:260px;height:260px;top:35%;left:45%;background:#06b6d4;animation-delay:-10s;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(24px,-36px) scale(1.06);}}

.navbar-top{position:sticky;top:0;z-index:100;border-bottom:1px solid var(--mm-border);background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);}
.navbar-container{max-width:1200px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:10px;}
.navbar-brand .logo-box{width:36px;height:36px;background:var(--mm-primary-soft);color:var(--mm-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;}
.navbar-brand h1{margin:0;font-size:17px;font-weight:700;color:var(--mm-text);}
.navbar-brand p{margin:0;font-size:11px;color:var(--mm-text-secondary);}
.navbar-links{display:flex;gap:6px;align-items:center;}
.navbar-links a{font-size:13px;color:var(--mm-text-secondary);text-decoration:none;padding:8px 14px;border-radius:6px;transition:background 150ms ease,color 150ms ease;}
.navbar-links a:hover{background:var(--mm-surface);color:var(--mm-primary);}
.navbar-links a.active{background:var(--mm-primary);color:#ffffff;}
.navbar-user{display:flex;align-items:center;gap:12px;}
.navbar-user .user-info{display:flex;flex-direction:column;align-items:flex-end;}
.navbar-user .user-name{font-size:13px;font-weight:600;color:var(--mm-text);}
.navbar-user .user-role{font-size:11px;color:var(--mm-text-secondary);}
.navbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--mm-primary-soft);color:var(--mm-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.navbar-logout{font-size:13px;color:var(--mm-destructive);text-decoration:none;padding:6px 12px;border-radius:6px;border:1px solid var(--mm-destructive-soft);transition:all 150ms ease;}
.navbar-logout:hover{background:var(--mm-destructive-soft);color:var(--mm-destructive);}
.mobile-toggle{display:none;background:none;border:none;color:var(--mm-text);font-size:20px;cursor:pointer;}

.page-wrap{padding:40px 24px;max-width:1000px;margin:0 auto;width:100%;}
.page-header{display:flex;flex-direction:column;gap:8px;margin-bottom:28px;}
.page-header h2{margin:0;font-size:24px;font-weight:700;color:var(--mm-text);letter-spacing:-0.25px;}
.page-header p{margin:0;font-size:14px;color:var(--mm-text-secondary);}
.page-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:16px;}

.meeting-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;}
.meeting-card{border:1px solid var(--mm-border);border-radius:var(--mm-radius);background:var(--mm-surface);box-shadow:var(--mm-shadow);overflow:hidden;display:flex;flex-direction:column;transition:all 150ms ease;}
.meeting-card:hover{box-shadow:var(--mm-shadow-lg);transform:translateY(-2px);border-color:var(--mm-primary);}
.meeting-card-body{padding:20px 20px 16px;flex:1;}
.meeting-card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;}
.meeting-card h3{margin:0;font-size:17px;font-weight:700;color:var(--mm-text);}
.meeting-status{font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:0.5px;}
.meeting-status.scheduled{background:rgb(22 163 74 / 10%);color:var(--mm-success);}
.meeting-status.cancelled{background:var(--mm-destructive-soft);color:var(--mm-destructive);}
.meeting-meta{display:flex;flex-direction:column;gap:8px;}
.meeting-item{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--mm-text-secondary);}
.meeting-item .item-icon{width:16px;height:16px;color:var(--mm-primary);flex-shrink:0;}
.meeting-item strong{color:var(--mm-text);font-weight:600;}
.meeting-actions{display:flex;gap:8px;padding:12px 20px 20px;border-top:1px solid var(--mm-border);background:var(--mm-surface);}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:0;border-radius:8px;background:var(--mm-primary);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease,transform 100ms ease;}
.btn:hover{background:var(--mm-primary-hover);}
.btn:active{transform:translateY(1px);}
.btn:focus-visible{outline:2px solid var(--mm-primary);outline-offset:2px;}
.btn-secondary{background:var(--mm-surface);color:var(--mm-text);border:1px solid var(--mm-border);}
.btn-secondary:hover{background:var(--mm-bg);border-color:var(--mm-border);}
.btn-danger{background:var(--mm-destructive);color:#ffffff;}
.btn-danger:hover{background:#b91c1c;}

.empty-state{display:flex;flex-direction:column;align-items:center;text-align:center;padding:60px 24px;color:var(--mm-text-secondary);}
.empty-icon{width:64px;height:64px;color:var(--mm-border);margin-bottom:16px;}
.empty-title{margin:0 0 8px;font-size:18px;font-weight:700;color:var(--mm-text);}
.empty-desc{margin:0 0 20px;font-size:14px;line-height:1.5;max-width:320px;}

.quick-nav{margin-top:28px;}
.quick-nav h4{font-size:15px;font-weight:700;color:var(--mm-text);margin:0 0 16px;}
.quick-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.quick-item{border:1px solid var(--mm-border);border-radius:var(--mm-radius);background:var(--mm-surface);padding:18px;text-decoration:none;color:var(--mm-text);transition:all 150ms ease;display:flex;flex-direction:column;gap:8px;}
.quick-item:hover{border-color:var(--mm-primary);background:var(--mm-bg);}
.quick-item h5{margin:0;font-size:14px;font-weight:700;color:var(--mm-text);}
.quick-item p{margin:0;font-size:12px;color:var(--mm-text-secondary);}

@media (max-width:768px){
  .navbar-links{display:none;position:absolute;top:100%;left:0;right:0;background:var(--mm-surface);border-bottom:1px solid var(--mm-border);padding:16px 24px;flex-direction:column;gap:8px;box-shadow:var(--mm-shadow);}
  .navbar-links.open{display:flex;}
  .mobile-toggle{display:block;}
  .page-wrap{padding:24px 16px;}
  .meeting-grid{grid-template-columns:1fr;}
  .quick-grid{grid-template-columns:1fr;}
  .navbar-user .user-info{display:none;}
}
@media (max-width:560px){
  .page-wrap{padding:20px 16px;}
  .meeting-actions{flex-direction:column;}
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
      <a href="my_meetings.php" class="active">My Meetings</a>
      <a href="my_invitations.php">My Invitations</a>
      <a href="calendar.php">Calendar</a>
      <a href="notifications.php">Notifications</a>
      <a href="reminders.php">Reminders</a>
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
  <h2>My Meetings</h2>
  <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong>. Here are your scheduled meetings.</p>
</div>
<div class="page-actions">
  <a href="create_meeting.php" class="btn">Create New Meeting</a>
</div>

<?php if ($result->num_rows == 0): ?>
  <div class="empty-state">
    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="9" cy="7" r="4"></circle>
      <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    <h2 class="empty-title">No meetings yet</h2>
    <p class="empty-desc">You haven't created any meetings. Start by scheduling your first meeting.</p>
  </div>
<?php else: ?>
  <div class="meeting-grid">
    <?php while ($meeting = $result->fetch_assoc()): ?>
      <div class="meeting-card">
        <div class="meeting-card-body">
          <div class="meeting-card-header">
            <h3><?php echo htmlspecialchars($meeting["title"]); ?></h3>
            <span class="meeting-status status-<?php echo htmlspecialchars($meeting["status"]); ?>">
              <?php echo htmlspecialchars(ucfirst($meeting["status"])); ?>
            </span>
          </div>
          <div class="meeting-meta">
            <div class="meeting-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
              <strong>Date:</strong> <?php echo date("d M Y", strtotime($meeting["meeting_date"])); ?>
            </div>
            <div class="meeting-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <strong>Time:</strong> <?php echo date("g:i A", strtotime($meeting["start_time"])); ?> - <?php echo date("g:i A", strtotime($meeting["end_time"])); ?>
            </div>
            <div class="meeting-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
              <strong>Location:</strong> <?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?>
            </div>
            <div class="meeting-item">
              <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 12a4 4 0 10-8 0 4 4 0 008 0zM12 18a6 6 0 006-6v-4a6 6 0 00-12 0v4a6 6 0 006 6z"></path></svg>
              <strong>Description:</strong> <?php echo htmlspecialchars($meeting["description"] ?: "No description"); ?>
            </div>
          </div>
        </div>
        <?php if ($meeting["status"] != "cancelled"): ?>
          <div class="meeting-actions">
            <a href="edit_meeting.php?id=<?php echo $meeting["id"]; ?>" class="btn btn-secondary">Edit Meeting</a>
            <a href="cancel_meeting.php?id=<?php echo $meeting["id"]; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this meeting?');">Cancel Meeting</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<div class="quick-nav">
  <h4>Quick Navigation</h4>
  <div class="quick-grid">
    <a class="quick-item" href="create_meeting.php">
      <h5>Create Meeting</h5>
      <p>Schedule a new meeting.</p>
    </a>
    <a class="quick-item" href="../dashboard.php">
      <h5>Dashboard</h5>
      <p>Return to your main dashboard.</p>
    </a>
  </div>
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