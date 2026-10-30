<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

$stmt = $conn->prepare(
    "SELECT
        m.id,
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
     ORDER BY m.meeting_date DESC,
              m.start_time DESC"
);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>MeetSync - Meeting Reports</title>

<link rel="stylesheet"
      href="../css/style.css">

<script src="../js/app.js"></script>

    <link rel="stylesheet" href="../css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="../uploads/profile_pictures/favicon.jpeg">
</head>

<body>

<!-- =========================
     HEADER
========================= -->

<header class="site-header">

<div class="container">

    <h1>MeetSync Administration</h1>

</div>

</header>

<!-- =========================
     NAVIGATION
========================= -->

<nav class="navbar">

<div class="container">

    <a href="index.php">
        Administration Dashboard
    </a>

    <a href="activity_logs.php">
        Activity Logs
    </a>

    <a href="uat_tests.php">
        UAT Tests
    </a>

    <a href="uat_results.php">
        UAT Results
    </a>

    <a href="../dashboard.php">
        Main Dashboard
    </a>

    <a href="../logout.php">
        Logout
    </a>

</div>

</nav>

<!-- =========================
     MAIN CONTENT
========================= -->

<main class="container">

<section class="section-header">

    <h2>
        Meeting Reports
    </h2>

    <p>
        View meeting records and their current status.
    </p>

</section>


<!-- =========================
     ACTION BUTTONS
========================= -->

<div class="form-actions">

    <a href="download_meeting_report.php"
       class="btn btn-success">

        Download Meeting Report

    </a>


    <a href="index.php"
       class="btn btn-secondary">

        Back to Administration

    </a>

</div>


<br>


<!-- =========================
     REPORT TABLE
========================= -->

<?php if ($result->num_rows > 0): ?>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Meeting</th>

                    <th>Date</th>

                    <th>Start Time</th>

                    <th>End Time</th>

                    <th>Location</th>

                    <th>Organizer</th>

                    <th>Status</th>

                </tr>

            </thead>


            <tbody>


                <?php while ($meeting = $result->fetch_assoc()): ?>


                    <tr>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $meeting["title"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $meeting["meeting_date"]
                                )
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo date(
                                "g:i A",
                                strtotime(
                                    $meeting["start_time"]
                                )
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo date(
                                "g:i A",
                                strtotime(
                                    $meeting["end_time"]
                                )
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $meeting["location"]
                                ?: "Not specified"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $meeting["organizer"]
                            );

                            ?>

                        </td>


                        <td>

                            <span class="status status-<?php
                                echo strtolower(
                                    $meeting["status"]
                                );
                            ?>">

                                <?php

                                echo htmlspecialchars(
                                    ucfirst(
                                        $meeting["status"]
                                    )
                                );

                                ?>

                            </span>

                        </td>


                    </tr>


                <?php endwhile; ?>


            </tbody>

        </table>

    </div>


<?php else: ?>


    <div class="message message-info">

        No meeting records found.

    </div>


<?php endif; ?>

</main>

<!-- =========================
     FOOTER
========================= -->

<footer class="site-footer">

<div class="container">

    <p>

        &copy; <?php echo date("Y"); ?> MeetSync.

        Meeting Scheduling and Coordination System.

    </p>

</div>

</footer>

</body>

</html>

<?php

$stmt->close();

?>
