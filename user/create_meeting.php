<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/availability.php";
require_once "../includes/activity_log.php";

$message = "";
$message_type = "";
$suggestions = [];
$field_errors = [];

$user_id = $_SESSION["user_id"];

$users = [];
$stmt_users = $conn->prepare(
    "SELECT id, name, email
     FROM users
     WHERE id != ?
     ORDER BY name ASC"
);
$stmt_users->bind_param("i", $user_id);
$stmt_users->execute();
$result_users = $stmt_users->get_result();
while ($row = $result_users->fetch_assoc()) {
    $users[] = $row;
}
$stmt_users->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $meeting_date = $_POST["meeting_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $location = trim($_POST["location"]);
    $selected_participants = $_POST["participants"] ?? [];
    $selected_participants = array_map("intval", $selected_participants);
    $selected_participants = array_unique($selected_participants);

    $field_errors = [];

    if (empty($title)) {
        $field_errors["title"] = "Meeting title is required.";
    }
    if (empty($meeting_date)) {
        $field_errors["meeting_date"] = "Meeting date is required.";
    }
    if (empty($start_time)) {
        $field_errors["start_time"] = "Start time is required.";
    }
    if (empty($end_time)) {
        $field_errors["end_time"] = "End time is required.";
    }
    if (!empty($start_time) && !empty($end_time) && strtotime($end_time) <= strtotime($start_time)) {
        $field_errors["end_time"] = "End time must be later than start time.";
    }

    if (empty($field_errors)) {
        $people_to_check = array_unique(array_merge($selected_participants, [$user_id]));
        $conflict_found = false;

        foreach ($people_to_check as $person_id) {
            $person_id = (int) $person_id;
            $person_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $person_stmt->bind_param("i", $person_id);
            $person_stmt->execute();
            $person = $person_stmt->get_result()->fetch_assoc();
            $person_stmt->close();

            $conflict_stmt = $conn->prepare(
                "SELECT m.title, m.start_time, m.end_time
                 FROM meetings m
                 LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
                 WHERE m.meeting_date = ?
                 AND m.status = 'scheduled'
                 AND (m.created_by = ? OR mp.user_id = ?)
                 AND m.start_time < ?
                 AND m.end_time > ?
                 LIMIT 1"
            );
            $conflict_stmt->bind_param("siiss", $meeting_date, $person_id, $person_id, $end_time, $start_time);
            $conflict_stmt->execute();
            $conflict_result = $conflict_stmt->get_result();

            if ($conflict_result->num_rows > 0) {
                $existing = $conflict_result->fetch_assoc();
                $message = "Scheduling conflict detected for " . ($person["name"] ?? "Participant") .
                    ". Existing: " . $existing["title"] . " (" .
                    date("g:i A", strtotime($existing["start_time"])) . " - " .
                    date("g:i A", strtotime($existing["end_time"])) . ").";
                $message_type = "error";
                $conflict_found = true;
                $conflict_stmt->close();
                break;
            }
            $conflict_stmt->close();
        }

        if ($conflict_found) {
            $suggestions = getAlternativeSlots($conn, $people_to_check, $meeting_date, 60);
        }

        if (!$conflict_found) {
            $stmt = $conn->prepare(
                "INSERT INTO meetings (created_by, title, description, meeting_date, start_time, end_time, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issssss", $user_id, $title, $description, $meeting_date, $start_time, $end_time, $location);

            if ($stmt->execute()) {
                $meeting_id = $stmt->insert_id;
                $stmt->close();

                if (!empty($selected_participants)) {
                    $p_stmt = $conn->prepare("INSERT INTO meeting_participants (meeting_id, user_id, status) VALUES (?, ?, 'invited')");
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, meeting_id, message) VALUES (?, ?, ?)");
                    foreach ($selected_participants as $pid) {
                        if ($pid != $user_id) {
                            $p_stmt->bind_param("ii", $meeting_id, $pid);
                            $p_stmt->execute();
                            $n_stmt->bind_param("iis", $pid, $meeting_id, "You have been invited to a meeting: " . $title);
                            $n_stmt->execute();
                        }
                    }
                    $p_stmt->close();
                    $n_stmt->close();
                }

                logActivity($conn, $user_id, "Meeting Created", "Created meeting: " . $title);
                $message = "Meeting created and participants invited successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to create meeting.";
                $message_type = "error";
                $stmt->close();
            }
        }
    } else {
        $message = "Please fix the errors below.";
        $message_type = "error";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MeetSync - Create Meeting</title>
<link rel="stylesheet" href="../css/style.css">
<script src="../js/app.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --cp-primary:#0D9488;
  --cp-primary-hover:#0F766E;
  --cp-primary-soft:rgb(13 148 136 / 10%);
  --cp-bg:#F0FDFA;
  --cp-surface:#FFFFFF;
  --cp-text:#134E4A;
  --cp-text-secondary:#475569;
  --cp-border:#5EEAD4;
  --cp-radius:10px;
  --cp-shadow:0 1px 3px rgb(0 0 0 / 0.1),0 4px 12px rgb(0 0 0 / 0.08);
  --cp-shadow-lg:0 20px 40px rgb(0 0 0 / 0.1);
  --cp-focus:0 0 0 3px rgb(13 148 136 / 30%);
  --cp-destructive:#DC2626;
  --cp-destructive-soft:rgb(220 38 38 / 10%);
  --cp-success:#16a34a;
  --cp-font:"Fira Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
[data-theme="dark"]{
  --cp-primary:#2DD4BF;
  --cp-primary-hover:#5EEAD4;
  --cp-primary-soft:rgb(45 212 191 / 15%);
  --cp-bg:#0B1120;
  --cp-surface:#111827;
  --cp-text:#F8FAFC;
  --cp-text-secondary:#94A3B8;
  --cp-border:#1F2937;
  --cp-destructive:#FCA5A5;
  --cp-destructive-soft:rgb(239 68 68 / 15%);
  --cp-success:#4ade80;
}
*,*::before,*::after{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  margin:0;min-height:100vh;background:var(--cp-bg);color:var(--cp-text);
  font-family:var(--cp-font);line-height:1.5;overflow-x:hidden;
}
.parallax-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;pointer-events:none;}
.parallax-layer{position:absolute;border-radius:50%;filter:blur(60px);opacity:0.35;animation:float 14s ease-in-out infinite;}
.parallax-layer:nth-child(1){width:420px;height:420px;top:-120px;left:-120px;background:var(--cp-primary);animation-delay:0s;}
.parallax-layer:nth-child(2){width:320px;height:320px;bottom:-60px;right:-60px;background:#8b5cf6;animation-delay:-5s;}
.parallax-layer:nth-child(3){width:260px;height:260px;top:35%;left:45%;background:#06b6d4;animation-delay:-10s;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(24px,-36px) scale(1.06);}}

.navbar-top{position:sticky;top:0;z-index:100;border-bottom:1px solid var(--cp-border);background:rgba(255,255,255,0.6);backdrop-filter:blur(8px);}
.navbar-container{max-width:1200px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:10px;}
.navbar-brand .logo-box{width:36px;height:36px;background:var(--cp-primary-soft);color:var(--cp-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;}
.navbar-brand h1{margin:0;font-size:17px;font-weight:700;color:var(--cp-text);}
.navbar-brand p{margin:0;font-size:11px;color:var(--cp-text-secondary);}
.navbar-links{display:flex;gap:6px;align-items:center;}
.navbar-links a{font-size:13px;color:var(--cp-text-secondary);text-decoration:none;padding:8px 14px;border-radius:6px;transition:background 150ms ease,color 150ms ease;}
.navbar-links a:hover{background:var(--cp-surface);color:var(--cp-primary);}
.navbar-links a.active{background:var(--cp-primary);color:#ffffff;}
.navbar-user{display:flex;align-items:center;gap:12px;}
.navbar-user .user-info{display:flex;flex-direction:column;align-items:flex-end;}
.navbar-user .user-name{font-size:13px;font-weight:600;color:var(--cp-text);}
.navbar-user .user-role{font-size:11px;color:var(--cp-text-secondary);}
.navbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--cp-primary-soft);color:var(--cp-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;}
.navbar-logout{font-size:13px;color:var(--cp-destructive);text-decoration:none;padding:6px 12px;border-radius:6px;border:1px solid var(--cp-destructive-soft);transition:all 150ms ease;}
.navbar-logout:hover{background:var(--cp-destructive-soft);color:var(--cp-destructive);}
.mobile-toggle{display:none;background:none;border:none;color:var(--cp-text);font-size:20px;cursor:pointer;}

.page-wrap{padding:40px 24px;max-width:1000px;margin:0 auto;width:100%;}
.page-header{display:flex;flex-direction:column;gap:8px;margin-bottom:28px;}
.page-header h2{margin:0;font-size:24px;font-weight:700;color:var(--cp-text);letter-spacing:-0.25px;}
.page-header p{margin:0;font-size:14px;color:var(--cp-text-secondary);}
.page-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:16px;}

.form-card{border:1px solid var(--cp-border);border-radius:var(--cp-radius);background:var(--cp-surface);box-shadow:var(--cp-shadow-lg);overflow:hidden;display:flex;flex-direction:column;}
.form-header{display:flex;align-items:center;gap:14px;padding:28px 28px 0;}
.header-icon{width:40px;height:40px;border-radius:10px;background:var(--cp-primary-soft);color:var(--cp-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.header-icon svg{width:20px;height:20px;}
.form-header h2{margin:0;font-size:20px;font-weight:700;color:var(--cp-text);}
.form-header p{margin:0;font-size:14px;color:var(--cp-text-secondary);}
.form-body{padding:20px 28px 28px;display:flex;flex-direction:column;gap:20px;}

.message{padding:12px 16px;border-radius:8px;font-size:13px;line-height:1.5;border:1px solid;role:alert;}
.message-error{background:var(--cp-destructive-soft);border-color:var(--cp-destructive);color:var(--cp-destructive);}
.message-success{background:rgb(22 163 74 / 10%);border-color:var(--cp-success);color:var(--cp-success);}
.message-info{background:rgb(13 148 136 / 10%);border-color:var(--cp-primary);color:var(--cp-text);}

.suggestions-card{border:1px solid var(--cp-border);border-radius:var(--cp-radius);background:var(--cp-surface);box-shadow:var(--cp-shadow);padding:20px 24px;}
.suggestions-header{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
.suggestions-icon{width:20px;height:20px;color:var(--cp-primary);flex-shrink:0;}
.suggestions-card h3{margin:0;font-size:16px;font-weight:700;color:var(--cp-text);}
.suggestions-card p{margin:0 0 14px;font-size:13px;color:var(--cp-text-secondary);}
.suggestions-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;}
.suggestions-list li{padding:10px 14px;background:var(--cp-bg);border-radius:8px;border:1px solid var(--cp-border);font-size:13px;}
.suggestions-list strong{color:var(--cp-primary);font-weight:700;}

.field-group{display:flex;flex-direction:column;gap:8px;}
.field-group label{font-size:13px;font-weight:600;color:var(--cp-text-secondary);}
.field-group input,.field-group select,.field-group textarea{width:100%;min-height:48px;padding:0 14px;border:1px solid var(--cp-border);border-radius:8px;background:var(--cp-surface);color:var(--cp-text);font-family:inherit;font-size:14px;outline:none;transition:border-color 150ms ease,box-shadow 150ms ease;}
.field-group textarea{min-height:120px;resize:vertical;padding:12px 14px;line-height:1.5;}
.field-group input:focus,.field-group select:focus,.field-group textarea:focus{border-color:var(--cp-primary);box-shadow:var(--cp-focus);}
.field-group input[aria-invalid="true"],.field-group select[aria-invalid="true"],.field-group textarea[aria-invalid="true"]{border-color:var(--cp-destructive);}
.field-group input[aria-invalid="true"]:focus,.field-group select[aria-invalid="true"]:focus,.field-group textarea[aria-invalid="true"]:focus{box-shadow:0 0 0 3px rgb(220 38 38 / 20%);}
.field-group input::placeholder{color:var(--cp-text-secondary);opacity:0.5;}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.field-group-full{grid-column:1 / -1;}
.field-error{font-size:11.5px;color:var(--cp-destructive);margin-top:4px;}
.field-hint{font-size:11.5px;color:var(--cp-text-secondary);margin-top:4px;}

.section-title{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;color:var(--cp-text);margin-bottom:14px;}
.section-title .badge{width:24px;height:24px;border-radius:6px;background:var(--cp-primary-soft);color:var(--cp-primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;}
.participant-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;}
.participant-card{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--cp-border);border-radius:8px;background:var(--cp-surface);cursor:pointer;transition:all 150ms ease;position:relative;}
.participant-card:hover{border-color:var(--cp-primary);background:var(--cp-primary-soft);}
.participant-card:focus-visible{outline:none;border-color:var(--cp-primary);box-shadow:var(--cp-focus);}
.participant-card input{margin:0;accent-color:var(--cp-primary);opacity:0;position:absolute;inset:0;width:100%;height:100%;z-index:2;cursor:pointer;}
.participant-card input:checked ~ .participant-info .name{font-weight:700;color:var(--cp-primary);}
.participant-card input:checked ~ .check-wrapper{opacity:1;transform:scale(1);}
.participant-card input:not(:checked) ~ .check-wrapper{opacity:0;transform:scale(0.8);}
.check-wrapper{display:flex;align-items:center;justify-content:center;width:20px;height:20px;margin-left:auto;flex-shrink:0;transition:opacity 150ms ease,transform 150ms ease;}
.check-icon{width:16px;height:16px;color:#ffffff;}

.form-actions{display:flex;justify-content:flex-start;gap:12px;margin-top:8px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border:0;border-radius:8px;background:var(--cp-primary);color:#ffffff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background 150ms ease,transform 100ms ease;}
.btn:hover{background:var(--cp-primary-hover);}
.btn:active{transform:translateY(1px);}
.btn:focus-visible{outline:2px solid var(--cp-primary);outline-offset:2px;}
.btn-secondary{background:var(--cp-surface);color:var(--cp-text);border:1px solid var(--cp-border);}
.btn-secondary:hover{background:var(--cp-bg);border-color:var(--cp-border);}
.btn:disabled{opacity:0.6;cursor:not-allowed;}

.empty-state{padding:24px 20px;text-align:center;color:var(--cp-text-secondary);font-size:13px;}

@media (max-width:768px){
  .navbar-links{display:none;position:absolute;top:100%;left:0;right:0;background:var(--cp-surface);border-bottom:1px solid var(--cp-border);padding:16px 24px;flex-direction:column;gap:8px;box-shadow:var(--cp-shadow);}
  .navbar-links.open{display:flex;}
  .mobile-toggle{display:block;}
  .page-wrap{padding:24px 16px;}
}
@media (max-width:560px){
  .page-wrap{padding:20px 16px;}
  .field-row{grid-template-columns:1fr;}
  .form-header{padding:20px 20px 0;}
  .form-body{padding:16px 20px 20px;}
  .navbar-container{padding:10px 16px;}
  .meeting-actions,.form-actions{flex-direction:column;}
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
  <h2>Create New Meeting</h2>
  <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong>. Schedule a new meeting below.</p>
</div>
<div class="page-actions">
  <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if (!empty($message)): ?>
  <div class="message message-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!empty($field_errors)): ?>
  <div class="message message-error" role="alert">
    <strong>Please fix the following:</strong>
    <ul style="margin:6px 0 0;padding-left:18px;font-size:12.5px;">
      <?php foreach ($field_errors as $field => $err): ?>
        <li><?php echo htmlspecialchars($err); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($suggestions)): ?>
  <div class="suggestions-card">
    <div class="suggestions-header">
      <svg class="suggestions-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="13" cy="13" r="8"></circle>
        <line x1="18" y1="18" x2="23" y2="23"></line>
      </svg>
      <h3>Suggested Available Times</h3>
    </div>
    <p>The requested time is not available. The following one-hour slots are available for all selected participants:</p>
    <ul class="suggestions-list">
      <?php foreach ($suggestions as $suggestion): ?>
        <li><strong><?php echo htmlspecialchars($suggestion["start"]); ?></strong> - <?php echo htmlspecialchars($suggestion["end"]); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php elseif ($message_type === "error" && empty($field_errors)): ?>
  <div class="message message-info">No alternative one-hour slots were found between 8:00 AM and 5:00 PM.</div>
<?php endif; ?>

<form method="POST" id="createMeetingForm" novalidate>
  <div class="field-group field-group-full">
    <label for="title">Meeting Title</label>
    <input type="text" id="title" name="title" placeholder="e.g. Weekly Team Sync" required value="<?php echo htmlspecialchars($_POST["title"] ?? ""); ?>" <?php if (isset($field_errors["title"])): ?>aria-invalid="true" aria-describedby="title-error"<?php endif; ?>>
    <?php if (isset($field_errors["title"])): ?><p class="field-error" id="title-error"><?php echo htmlspecialchars($field_errors["title"]); ?></p><?php endif; ?>
  </div>
  <div class="field-group field-group-full">
    <label for="description">Agenda / Description</label>
    <textarea id="description" name="description" placeholder="What is this meeting about?" rows="4"><?php echo htmlspecialchars($_POST["description"] ?? ""); ?></textarea>
    <p class="field-hint">Optional agenda or talking points.</p>
  </div>
  <div class="field-row">
    <div class="field-group field-group-full">
      <label for="meeting_date">Meeting Date</label>
      <input type="date" id="meeting_date" name="meeting_date" required value="<?php echo htmlspecialchars($_POST["meeting_date"] ?? ""); ?>" <?php if (isset($field_errors["meeting_date"])): ?>aria-invalid="true" aria-describedby="date-error"<?php endif; ?>>
      <?php if (isset($field_errors["meeting_date"])): ?><p class="field-error" id="date-error"><?php echo htmlspecialchars($field_errors["meeting_date"]); ?></p><?php endif; ?>
    </div>
  </div>
  <div class="field-row">
    <div class="field-group">
      <label for="start_time">Start Time</label>
      <input type="time" id="start_time" name="start_time" required value="<?php echo htmlspecialchars($_POST["start_time"] ?? ""); ?>" <?php if (isset($field_errors["start_time"])): ?>aria-invalid="true" aria-describedby="start-error"<?php endif; ?>>
      <?php if (isset($field_errors["start_time"])): ?><p class="field-error" id="start-error"><?php echo htmlspecialchars($field_errors["start_time"]); ?></p><?php endif; ?>
    </div>
    <div class="field-group">
      <label for="end_time">End Time</label>
      <input type="time" id="end_time" name="end_time" required value="<?php echo htmlspecialchars($_POST["end_time"] ?? ""); ?>" <?php if (isset($field_errors["end_time"])): ?>aria-invalid="true" aria-describedby="end-error"<?php endif; ?>>
      <?php if (isset($field_errors["end_time"])): ?><p class="field-error" id="end-error"><?php echo htmlspecialchars($field_errors["end_time"]); ?></p><?php endif; ?>
    </div>
  </div>
  <div class="field-group field-group-full">
    <label for="location">Venue / Location</label>
    <input type="text" id="location" name="location" placeholder="Physical room or video call link" value="<?php echo htmlspecialchars($_POST["location"] ?? ""); ?>">
    <p class="field-hint">Where will this meeting take place?</p>
  </div>
  <div class="field-group field-group-full">
    <div class="section-title">
      <span class="badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </span>
      Select Participants
    </div>
    <div class="participant-grid">
      <?php if (empty($users)): ?>
        <div class="empty-state">
          <svg class="empty-icon-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.47"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <div class="empty-text-inline">No other employees are registered yet.</div>
        </div>
      <?php else: ?>
        <?php foreach ($users as $user): ?>
          <label class="participant-card" role="option" aria-selected="<?php echo in_array($user["id"], $selected_participants ?? []) ? "true" : "false"; ?>" tabindex="0">
            <input type="checkbox" name="participants[]" value="<?php echo (int) $user["id"]; ?>" <?php if (in_array($user["id"], $selected_participants ?? [])): ?>checked<?php endif; ?> class="sr-only" tabindex="-1">
            <div class="avatar"><?php echo strtoupper(substr(htmlspecialchars($user["name"]), 0, 1)); ?></div>
            <div class="participant-info">
              <div class="name"><?php echo htmlspecialchars($user["name"]); ?></div>
              <div class="email"><?php echo htmlspecialchars($user["email"]); ?></div>
            </div>
            <div class="check-wrapper">
              <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn" id="submitBtn">Create Meeting</button>
    <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>
</form>

</div>

<script>
document.getElementById("mobileToggle").addEventListener("click", function(){
  const links=document.querySelector(".navbar-links");
  const open=links.classList.toggle("open");
  this.setAttribute("aria-expanded", String(open));
});

(function(){
  const countEl=document.getElementById("panelSelectedCount");
  function updateCount(){
    if(countEl) countEl.textContent=document.querySelectorAll('input[name="participants[]"]:checked').length;
  }
  document.querySelectorAll('input[name="participants[]"]').forEach(function(cb){cb.addEventListener("change",updateCount);});
  updateCount();
})();

(function(){
  const form=document.getElementById("createMeetingForm");
  const submitBtn=document.getElementById("submitBtn");
  form.addEventListener("submit",function(){
    submitBtn.classList.add("is-loading");
    submitBtn.disabled=true;
    submitBtn.textContent="Creating\u2026";
  });
})();
</script>
</body>
</html>

<?php
$stmt->close();
?>
