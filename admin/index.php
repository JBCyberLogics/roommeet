
<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

/*
 * Get total number of users.
 */

$user_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_users
     FROM users"
);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user_data = $user_result->fetch_assoc();

$total_users = $user_data["total_users"];

$user_stmt->close();

/*
 * Get total number of meetings.
 */

$meeting_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_meetings
     FROM meetings"
);

$meeting_stmt->execute();

$meeting_result = $meeting_stmt->get_result();

$meeting_data = $meeting_result->fetch_assoc();

$total_meetings = $meeting_data["total_meetings"];

$meeting_stmt->close();

/*
 * Get total scheduled meetings.
 */
$scheduled_stmt = $conn->prepare(
    "SELECT COUNT(*) AS scheduled_meetings
     FROM meetings
     WHERE status = 'scheduled'"
);

$scheduled_stmt->execute();

$scheduled_result = $scheduled_stmt->get_result();

$scheduled_data = $scheduled_result->fetch_assoc();

$scheduled_meetings = $scheduled_data["scheduled_meetings"];

$scheduled_stmt->close();

/*
 * Get total cancelled meetings.
 */
$cancelled_stmt = $conn->prepare(
    "SELECT COUNT(*) AS cancelled_meetings
     FROM meetings
     WHERE status = 'cancelled'"
);

$cancelled_stmt->execute();

$cancelled_result = $cancelled_stmt->get_result();

$cancelled_data = $cancelled_result->fetch_assoc();

$cancelled_meetings = $cancelled_data["cancelled_meetings"];

$cancelled_stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MeetSync Admin</title>

<link rel="stylesheet" href="../css/style.css">

<script src="../js/app.js"></script>

    <link rel="stylesheet" href="../css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="../uploads/profile_pictures/favicon.jpeg">
</head>

<body>

<div class="admin-container">

    <div class="admin-header">

        <h2>MeetSync Administration</h2>

        <p>
            Welcome,
            <?php echo htmlspecialchars($_SESSION["user_name"]); ?>.
        </p>

        <p>
            Administrator access confirmed.
        </p>

    </div>


    <h3 class="section-title">System Overview</h3>

    <div class="overview-grid">

        <div class="stat-card">

            <h4>Total Users</h4>

            <div class="stat-number">
                <?php echo htmlspecialchars($total_users); ?>
            </div>

        </div>


        <div class="stat-card">

            <h4>Total Meetings</h4>

            <div class="stat-number">
                <?php echo htmlspecialchars($total_meetings); ?>
            </div>

        </div>


        <div class="stat-card scheduled-card">

            <h4>Scheduled Meetings</h4>

            <div class="stat-number">
                <?php echo htmlspecialchars($scheduled_meetings); ?>
            </div>

        </div>


        <div class="stat-card cancelled-card">

            <h4>Cancelled Meetings</h4>

            <div class="stat-number">
                <?php echo htmlspecialchars($cancelled_meetings); ?>
            </div>

        </div>

    </div>


    <h3 class="section-title">Administration Menu</h3>

    <div class="menu-grid">

        <div class="menu-card">

            <h4>UAT Testing</h4>

            <p>
                <a href="uat_tests.php">
                    Record UAT Test
                </a>
            </p>

        </div>


        <div class="menu-card">

            <h4>UAT Results</h4>

            <p>
                <a href="uat_results.php">
                    View UAT Results
                </a>
            </p>

        </div>


        <div class="menu-card">

            <h4>Activity Logs</h4>

            <p>
                <a href="activity_logs.php">
                    System Activity Logs
                </a>
            </p>

        </div>


        <div class="menu-card">

            <h4>Meeting Reports</h4>

            <p>
                <a href="meeting_reports.php">
                    Meeting Reports
                </a>
            </p>

        </div>


        <div class="menu-card">

            <h4>Database Backup</h4>

            <p>
                <a href="backup.php">
                    Backup Database
                </a>
            </p>

        </div>


        <div class="menu-card">

            <h4>Database Restore</h4>

            <p>
                <a href="restore.php">
                    Restore Database
                </a>
            </p>

        </div>

    </div>

</div>

</body>

</html>
