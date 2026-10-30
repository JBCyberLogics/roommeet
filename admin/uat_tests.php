<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

$message = "";

$users_stmt = $conn->prepare(
    "SELECT id, name
     FROM users
     ORDER BY name ASC"
);

$users_stmt->execute();

$users_result = $users_stmt->get_result();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tester_id = isset($_POST["tester_id"])
        ? (int) $_POST["tester_id"]
        : 0;

    $feature_name = trim($_POST["feature_name"] ?? "");
    $expected_result = trim($_POST["expected_result"] ?? "");
    $actual_result = trim($_POST["actual_result"] ?? "");
    $status = $_POST["status"] ?? "";
    $comments = trim($_POST["comments"] ?? "");

    if (
        $tester_id <= 0 ||
        empty($feature_name) ||
        empty($expected_result) ||
        empty($actual_result) ||
        ($status !== "Passed" && $status !== "Failed")
    ) {

        $message = "Please complete all required fields.";

    } else {

        /*
         * Confirm that the selected tester exists.
         */
        $tester_stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ?"
        );

        $tester_stmt->bind_param(
            "i",
            $tester_id
        );

        $tester_stmt->execute();

        $tester_result = $tester_stmt->get_result();

        if ($tester_result->num_rows === 0) {

            $message = "Selected tester was not found.";

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO uat_tests
                (
                    tester_id,
                    feature_name,
                    expected_result,
                    actual_result,
                    status,
                    comments
                )
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isssss",
                $tester_id,
                $feature_name,
                $expected_result,
                $actual_result,
                $status,
                $comments
            );

            if ($stmt->execute()) {

                $message = "UAT test recorded successfully.";

            } else {

                $message = "Failed to record the UAT test.";
            }

            $stmt->close();
        }

        $tester_stmt->close();
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>MeetSync - UAT Testing</title>

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
        User Acceptance Testing
    </h2>

    <p>
        Record the results of testing MeetSync system features.
    </p>

</section>


<!-- =========================
     MESSAGE
========================= -->

<?php if (!empty($message)): ?>

    <div class="message
        <?php
        echo (
            $message === "UAT test recorded successfully."
            ? "message-success"
            : "message-error"
        );
        ?>">

        <?php echo htmlspecialchars($message); ?>

    </div>

<?php endif; ?>


<!-- =========================
     UAT FORM
========================= -->

<div class="form-container">

    <div class="card">


        <h3>
            Record UAT Test
        </h3>


        <form method="POST"
              action="">


            <!-- Tester -->

            <div class="form-group">

                <label for="tester_id">
                    Tester
                </label>

                <select
                    id="tester_id"
                    name="tester_id"
                    required>

                    <option value="">
                        Select Tester
                    </option>

                    <?php while ($user = $users_result->fetch_assoc()): ?>

                        <option value="<?php echo $user["id"]; ?>">

                            <?php
                            echo htmlspecialchars(
                                $user["name"]
                            );
                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- Feature -->

            <div class="form-group">

                <label for="feature_name">
                    Feature Tested
                </label>

                <input
                    type="text"
                    id="feature_name"
                    name="feature_name"
                    placeholder="Example: Meeting Scheduling"
                    required>

            </div>


            <!-- Expected Result -->

            <div class="form-group">

                <label for="expected_result">
                    Expected Result
                </label>

                <textarea
                    id="expected_result"
                    name="expected_result"
                    rows="4"
                    placeholder="Describe what should happen."
                    required></textarea>

            </div>


            <!-- Actual Result -->

            <div class="form-group">

                <label for="actual_result">
                    Actual Result
                </label>

                <textarea
                    id="actual_result"
                    name="actual_result"
                    rows="4"
                    placeholder="Describe what actually happened."
                    required></textarea>

            </div>


            <!-- Status -->

            <div class="form-group">

                <label for="status">
                    Test Status
                </label>

                <select
                    id="status"
                    name="status"
                    required>

                    <option value="">
                        Select Status
                    </option>

                    <option value="Passed">
                        Passed
                    </option>

                    <option value="Failed">
                        Failed
                    </option>

                </select>

            </div>


            <!-- Comments -->

            <div class="form-group">

                <label for="comments">
                    Comments
                </label>

                <textarea
                    id="comments"
                    name="comments"
                    rows="4"
                    placeholder="Optional comments about the test."></textarea>

            </div>


            <!-- Actions -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-success">

                    Record UAT Test

                </button>


                <a
                    href="uat_results.php"
                    class="btn btn-secondary">

                    View UAT Results

                </a>


                <a
                    href="index.php"
                    class="btn btn-secondary">

                    Back to Administration

                </a>

            </div>


        </form>


    </div>

</div>


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

$users_stmt->close();

?>
