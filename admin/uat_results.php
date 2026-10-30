<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

$stmt = $conn->prepare(
    "SELECT
        uat_tests.id,
        users.name AS tester,
        uat_tests.feature_name,
        uat_tests.expected_result,
        uat_tests.actual_result,
        uat_tests.status,
        uat_tests.comments,
        uat_tests.test_date
     FROM uat_tests
     INNER JOIN users
     ON uat_tests.tester_id = users.id
     ORDER BY uat_tests.test_date DESC"
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

<title>MeetSync - UAT Results</title>

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

    <a href="meeting_reports.php">
        Meeting Reports
    </a>

    <a href="backup.php">
        Backup
    </a>

    <a href="restore.php">
        Restore
    </a>

    <a href="uat_tests.php">
        Record UAT Test
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
        User Acceptance Testing Results
    </h2>

    <p>
        Review the recorded results of system acceptance testing.
    </p>

</section>


<!-- =========================
     ACTIONS
========================= -->

<div class="form-actions">

    <a href="uat_tests.php"
       class="btn btn-success">

        Record New UAT Test

    </a>


    <a href="index.php"
       class="btn btn-secondary">

        Back to Administration

    </a>

</div>


<br>


<!-- =========================
     RESULTS
========================= -->

<?php if ($result->num_rows > 0): ?>

    <div class="card">

        <h3>
            Recorded UAT Tests
        </h3>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Tester</th>

                        <th>Feature Tested</th>

                        <th>Expected Result</th>

                        <th>Actual Result</th>

                        <th>Status</th>

                        <th>Comments</th>

                    </tr>

                </thead>


                <tbody>

                    <?php while ($test = $result->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $test["test_date"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $test["tester"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $test["feature_name"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $test["expected_result"]
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $test["actual_result"]
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php if ($test["status"] === "Passed"): ?>

                                    <span class="status status-completed">
                                        Passed
                                    </span>

                                <?php else: ?>

                                    <span class="status status-cancelled">
                                        Failed
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $test["comments"] ?? ""
                                    )
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>


<?php else: ?>

    <div class="message message-info">

        No UAT tests have been recorded yet.

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

        Meeting Scheduling and Coordination System.<br>
        

    </p>

</div>


</footer>

</body>

</html>

<?php

$stmt->close();

?>
