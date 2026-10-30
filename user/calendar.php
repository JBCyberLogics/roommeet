<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

$view = $_GET["view"] ?? "daily";
$selected_date = $_GET["date"] ?? date("Y-m-d");

if (!in_array($view, ["daily", "weekly", "monthly"])) {
    $view = "daily";
}

if ($view === "weekly") {
    $timestamp = strtotime($selected_date);
    $day_of_week = date("N", $timestamp);
    $start_date = date("Y-m-d", strtotime("-" . ($day_of_week - 1) . " days", $timestamp));
    $end_date = date("Y-m-d", strtotime("+6 days", strtotime($start_date)));
} elseif ($view === "monthly") {
    $start_date = date("Y-m-01", strtotime($selected_date));
    $end_date = date("Y-m-t", strtotime($selected_date));
} else {
    $start_date = $selected_date;
    $end_date = $selected_date;
}

$stmt = $conn->prepare(
    "SELECT DISTINCT
        m.id,
        m.title,
        m.description,
        m.meeting_date,
        m.start_time,
        m.end_time,
        m.location,
        m.status,
        u.name AS organizer_name
     FROM meetings m
     INNER JOIN users u
        ON m.created_by = u.id
     LEFT JOIN meeting_participants mp
        ON m.id = mp.meeting_id
     WHERE m.status = 'scheduled'
     AND m.meeting_date BETWEEN ? AND ?
     AND (
        m.created_by = ?
        OR mp.user_id = ?
     )
     ORDER BY m.meeting_date ASC, m.start_time ASC"
);

$stmt->bind_param("ssii", $start_date, $end_date, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$meetings_by_date = [];
while ($meeting = $result->fetch_assoc()) {
    $meeting_date = $meeting["meeting_date"];
    $meetings_by_date[$meeting_date][] = $meeting;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MeetSync - Calendar</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --cal-primary:#0D9488;
  --cal-primary-hover:#0F766E;
  --cal-primary-soft:rgb(13 148 136 / 10%);
  --cal-bg:#F0FDFA;
  --cal-surface:#FFFFFF;
  --cal-text:#134E4A;
  --cal-text-secondary:#475569;
  --cal-border:#5EEAD4;
  --cal-radius:10px;
  --cal-shadow:0 1px 3px rgb(0 0 0 / 0.1),0 4px 12px rgb(0 0 0 / 0.08);
  --cal-shadow-lg:0 20px 40px rgb(0 0 0 / 0.1);
  --cal-focus:0 0 0 3px rgb(13 148 136 / 30%);
  --cal-destructive:#DC2626;
  --cal-destructive-soft:rgb(220 38 38 / 10%);
  --cal-success:#16a34a;
  --cal-font:"Fira Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
[data-theme="dark"]{
  --cal-primary:#2DD4BF;
  --cal-primary-hover:#5EEAD4;
  --cal-primary-soft:rgb(45 212 191 / 15%);
  --cal-bg:#0B1120;
  --cal-surface:#111827;
  --cal-text:#F8FAFC;
  --cal-text-secondary:#94A3B8;
  --cal-border:#1F2937;
  --cal-destructive:#FCA5A5;
  --cal-destructive-soft:rgb(239 68 68 / 15%);
  --cal-success:#4ade80;
}
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;min-height:100vh;background:var(--cal-bg);color:var(--cal-text);
  font-family:var(--cal-font);line-height:1.5;overflow-x:hidden;
}
.parallax-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none;}
.parallax-layer{position:absolute;border-radius:50%;filter:blur(60px);opacity:0.35;animation:float 14s ease-in-out infinite;}
.parallax-layer:nth-child(1){width:420px;height:420px;top:-120px;left:-120px;background:var(--cal-primary);animation-delay:0s;}
.parallax-layer:nth-child(2){width:320px;height:320px;bottom:-60px;right:-60px;background:#8b5cf6;animation-delay:-5s;}
.parallax-layer:nth-child(3){width:260px;height:260px;top:35%;left:45%;background:#06b6d4;animation-delay:-10s;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(24px,-36px) scale(1.06);}}

/* ---------- Navbar ---------- */
.navbar-top{position:sticky;top:0;z-index:100;border-bottom:1px solid var(--cal-border);background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);}
.navbar-container{max-width:1200px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:10px;}
.navbar-brand .logo-box{width:36px;height:36px;background:var(--cal-primary-soft);color:var(--cal-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;}
.navbar-brand h1{margin:0;font-size:17px;font-weight:700;color:var(--cal-text);}
.navbar-brand p{margin:0;font-size:11px;color:var(--cal-text-secondary);}
.navbar-links{display:flex;gap:6px;align-items:center;}
.navbar-links a{font-size:13px;color:var(--cal-text-secondary);text-decoration:none;padding:8px 14px;border-radius:6px;transition:background 150ms ease,color 150ms ease;}
.navbar-links a:hover{background:var(--cal-surface);color:var(--cal-primary);}
.navbar-links a.active{background:var(--cal-primary);color:#ffffff;}
.navbar-user{display:flex;align-items:center;gap:12px;}
.navbar-user .user-info{display:flex;flex-direction:column;align-items:flex-end;}
.navbar-user .user-name{font-size:13px;font-weight:600;color:var(--cal-text);}
.navbar-user .user-role{font-size:11px;color:var(--cal-text-secondary);}
.navbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--cal-primary-soft);color:var(--cal-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.navbar-logout{font-size:13px;color:var(--cal-destructive);text-decoration:none;padding:6px 12px;border-radius:6px;border:1px solid var(--cal-destructive-soft);transition:all 150ms ease;}
.navbar-logout:hover{background:var(--cal-destructive-soft);color:var(--cal-destructive);}
.mobile-toggle{display:none;background:none;border:none;color:var(--cal-text);font-size:20px;cursor:pointer;}

/* ---------- Page content ---------- */
.page-wrap{padding:40px 24px;max-width:1000px;margin:0 auto;width:100%;}
.page-header{display:flex;flex-direction:column;gap:8px;margin-bottom:28px;}
.page-header h2{margin:0;font-size:24px;font-weight:700;color:var(--cal-text);letter-spacing:-0.25px;}
.page-header p{margin:0;font-size:14px;color:var(--cal-text-secondary);}
.page-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:16px;}

/* ---------- View toggle ---------- */
.view-toggle{display:flex;gap:4px;background:var(--cal-surface);border:1px solid var(--cal-border);border-radius:var(--cal-radius);padding:4px;margin-bottom:28px;}
.view-toggle .view-btn{font-size:13px;padding:8px 16px;border:0;background:transparent;color:var(--cal-text-secondary);cursor:pointer;border-radius:6px;transition:all 150ms ease;font-family:inherit;font-weight:600;}
.view-toggle .view-btn:hover{background:var(--cal-bg);color:var(--cal-primary);}
.view-toggle .view-btn.active{background:var(--cal-primary);color:#ffffff;}

/* ---------- Date selector ---------- */
.date-selector{border:1px solid var(--cal-border);border-radius:var(--cal-radius);background:var(--cal-surface);box-shadow:var(--cal-shadow);padding:20px;display:flex;flex-direction:column;gap:16px;align-items:flex-start;}
.date-selector h3{margin:0;font-size:16px;font-weight:700;color:var(--cal-text);}
.date-selector .field-group{display:flex;align-items:center;gap:12px;}
.date-selector label{font-size:13px;font-weight:600;color:var(--cal-text-secondary);margin:0;}
.date-selector input[type="date"]{min-height:44px;padding:0 14px;border:1px solid var(--cal-border);border-radius:8px;background:var(--cal-surface);color:var(--cal-text);font-family:inherit;font-size:14px;outline:none;transition:border-color 150ms ease,box-shadow 150ms ease;}
.date-selector input[type="date"]:focus{border-color:var(--cal-primary);box-shadow:var(--cal-focus);}
.date-selector button{min-height:44px;padding:0 20px;border:0;border-radius:8px;background:var(--cal-primary);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:background 150ms ease;}
.date-selector button:hover{background:var(--cal-primary-hover);}
.date-selector button:focus-visible{outline:2px solid var(--cal-primary);outline-offset:2px;}

/* ---------- Meeting cards ---------- */
.meeting-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}
.day-section{margin-bottom:28px;}
.day-section h3{font-size:17px;font-weight:700;color:var(--cal-text);margin:0 0 16px;padding-bottom:8px;border-bottom:2px solid var(--cal-primary-soft);}

.meeting-card{border:1px solid var(--cal-border);border-radius:var(--cal-radius);background:var(--cal-surface);box-shadow:var(--cal-shadow);overflow:hidden;display:flex;flex-direction:column;transition:all 150ms ease;}
.meeting-card:hover{box-shadow:var(--cal-shadow-lg);transform:translateY(-2px);border-color:var(--cal-primary);}
.meeting-card-body{padding:16px 16px 12px;}
.meeting-card h3{margin:0 0 10px;font-size:16px;font-weight:700;color:var(--cal-text);}
.meeting-time{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--cal-text-secondary);margin-bottom:10px;}
.meeting-time .item-icon{width:14px;height:14px;color:var(--cal-primary);flex-shrink:0;}
.meeting-time strong{color:var(--cal-text);font-weight:600;}
.meeting-item{display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--cal-text-secondary);margin-bottom:8px;}
.meeting-item .item-icon{width:14px;height:14px;color:var(--cal-primary);flex-shrink:0;margin-top:2px;}
.meeting-item strong{color:var(--cal-text);font-weight:600;}

/* ---------- Empty state ---------- */
.empty-state{display:flex;flex-direction:column;align-items:center;text-align:center;padding:60px 24px;color:var(--cal-text-secondary);}
.empty-icon{width:64px;height:64px;color:var(--cal-border);margin-bottom:16px;}
.empty-title{margin:0 0 8px;font-size:18px;font-weight:700;color:var(--cal-text);}
.empty-desc{margin:0 0 20px;font-size:14px;line-height:1.5;max-width:320px;}

/* ---------- Buttons ---------- */
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:0;border-radius:8px;background:var(--cal-primary);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease,transform 100ms ease;}
.btn:hover{background:var(--cal-primary-hover);}
.btn:active{transform:translateY(1px);}
.btn:focus-visible{outline:2px solid var(--cal-primary);outline-offset:2px;}
.btn-secondary{background:var(--cal-surface);color:var(--cal-text);border:1px solid var(--cal-border);}
.btn-secondary:hover{background:var(--cal-bg);border-color:var(--cal-border);}
.btn-success{background:var(--cal-success);color:#ffffff;}
.btn-success:hover{background:#15803d;}

@media (max-width:768px){
  .navbar-links{display:none;position:absolute;top:100%;left:0;right:0;background:var(--cal-surface);border-bottom:1px solid var(--cal-border);padding:16px 24px;flex-direction:column;gap:8px;box-shadow:var(--cal-shadow);}
  .navbar-links.open{display:flex;}
  .mobile-toggle{display:block;}
  .page-wrap{padding:24px 16px;}
  .meeting-grid{grid-template-columns:1fr;}
  .view-toggle{flex-wrap:wrap;}
}
@media (max-width:560px){
  .page-wrap{padding:20px 16px;}
  .date-selector .field-group{flex-direction:column;align-items:flex-start;width:100%;}
  .date-selector input[type="date"]{width:100%;}
  .date-selector button{width:100%;}
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
      <a href="calendar.php" class="active">Calendar</a>
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
  <h2>Meeting Calendar</h2>
  <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong>. View and manage your scheduled meetings.</p>
</div>

<div class="view-toggle">
  <button class="view-btn <?php echo $view === "daily" ? "active" : ""; ?>" onclick="location.href='calendar.php?view=daily&date=<?php echo htmlspecialchars($selected_date); ?>'">Daily</button>
  <button class="view-btn <?php echo $view === "weekly" ? "active" : ""; ?>" onclick="location.href='calendar.php?view=weekly&date=<?php echo htmlspecialchars($selected_date); ?>'">Weekly</button>
  <button class="view-btn <?php echo $view === "monthly" ? "active" : ""; ?>" onclick="location.href='calendar.php?view=monthly&date=<?php echo htmlspecialchars($selected_date); ?>'">Monthly</button>
</div>

<div class="date-selector">
  <h3>Select Date</h3>
  <form method="GET">
    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
    <div class="field-group">
      <label for="date">Date</label>
      <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" required>
    </div>
    <button type="submit">View Calendar</button>
  </form>
</div>

<?php if ($view === "monthly"): ?>
  <div class="day-section">
    <h3>Monthly View - <?php echo date("F Y", strtotime($selected_date)); ?></h3>
    <?php if (empty($meetings_by_date)): ?>
      <div class="empty-state">
        <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <h2 class="empty-title">No meetings this month</h2>
        <p class="empty-desc">You have no scheduled meetings in this month.</p>
      </div>
    <?php else: ?>
      <div class="meeting-grid">
        <?php foreach ($meetings_by_date as $meeting_date => $day_meetings): ?>
          <div class="day-section" style="margin-bottom:24px;">
            <h3><?php echo date("l, d M Y", strtotime($meeting_date)); ?></h3>
            <?php foreach ($day_meetings as $meeting): ?>
              <div class="meeting-card">
                <div class="meeting-card-body">
                  <h3><?php echo htmlspecialchars($meeting["title"]); ?></h3>
                  <div class="meeting-time">
                    <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <strong>Time:</strong> <?php echo date("g:i A", strtotime($meeting["start_time"])); ?> - <?php echo date("g:i A", strtotime($meeting["end_time"])); ?>
                  </div>
                  <div class="meeting-item">
                    <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
                    <strong>Organizer:</strong> <?php echo htmlspecialchars($meeting["organizer_name"]); ?>
                  </div>
                  <div class="meeting-item">
                    <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
                    <strong>Location:</strong> <?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php if (empty($meetings_by_date)): ?>
    <div class="empty-state">
      <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <h2 class="empty-title">No meetings</h2>
      <p class="empty-desc">You have no scheduled meetings for the selected period.</p>
    </div>
  <?php else: ?>
    <div class="meeting-grid">
      <?php foreach ($meetings_by_date as $meeting_date => $day_meetings): ?>
        <div class="day-section">
          <h3><?php echo date("l, d M Y", strtotime($meeting_date)); ?></h3>
          <?php foreach ($day_meetings as $meeting): ?>
            <div class="meeting-card">
              <div class="meeting-card-body">
                <h3><?php echo htmlspecialchars($meeting["title"]); ?></h3>
                <?php if ($view === "daily"): ?>
                  <div class="meeting-item">
                    <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <strong>Date:</strong> <?php echo date("l, d M Y", strtotime($meeting_date)); ?>
                  </div>
                <?php endif; ?>
                <div class="meeting-time">
                  <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  <strong>Time:</strong> <?php echo date("g:i A", strtotime($meeting["start_time"])); ?> - <?php echo date("g:i A", strtotime($meeting["end_time"])); ?>
                </div>
                <div class="meeting-item">
                  <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
                  <strong>Organizer:</strong> <?php echo htmlspecialchars($meeting["organizer_name"]); ?>
                </div>
                <div class="meeting-item">
                  <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-3 9-3 9h-6s-3-2-3-9"></path><path d="M18 5c2-3 6-3 6 0s3 3 6 3m-9 9V7m0 10v4m0-4h4m-4 4h-4m-12 12h4m4-4h-4m4-4V7m4 10v4m0-10v-4a6 6 0 00-6-6 6 6 0 00-6 6v4a6 6 0 006 6z"></path></svg>
                  <strong>Location:</strong> <?php echo htmlspecialchars($meeting["location"] ?: "Not specified"); ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="page-actions" style="margin-top:28px;">
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