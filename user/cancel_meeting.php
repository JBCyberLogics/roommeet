<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/activity_log.php";

$user_id = $_SESSION["user_id"];

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid meeting ID.");
}

$meeting_id = (int) $_GET["id"];

$stmt = $conn->prepare(
    "SELECT id, title, status
     FROM meetings
     WHERE id = ?
     AND created_by = ?"
);

$stmt->bind_param(
    "ii",
    $meeting_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$meeting = $result->fetch_assoc();

$stmt->close();

if (!$meeting) {
    die("Meeting not found or you are not allowed to cancel it.");
}

if ($meeting["status"] == "cancelled") {
    die("This meeting has already been cancelled.");
}

$update_stmt = $conn->prepare(
    "UPDATE meetings
     SET status = 'cancelled'
     WHERE id = ?
     AND created_by = ?"
);

$update_stmt->bind_param(
    "ii",
    $meeting_id,
    $user_id
);

if ($update_stmt->execute()) {

    logActivity(
        $conn,
        $user_id,
        "Meeting Cancelled",
        "Cancelled meeting: " . $meeting["title"]
    );

    $message = "Meeting cancelled successfully.";

} else {

    $message = "Failed to cancel the meeting.";
}

$update_stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>MeetSync - Cancel Meeting</title>

<link
    rel="stylesheet"
    href="../css/style.css"
>

<script src="../js/app.js"></script>

    <link rel="stylesheet" href="../css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="../uploads/profile_pictures/favicon.jpeg">
</head>

<body>

<header class="site-header">

<div class="container">

    <h1>MeetSync</h1>

    <nav class="navbar">

        <a href="../dashboard.php">
            Dashboard
        </a>

        <a href="my_meetings.php">
            My Meetings
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </nav>

</div>

</header>

<main class="container">

<div class="form-container">

    <h2>Cancel Meeting</h2>

    <div class="message message-success">

        <?php echo htmlspecialchars($message); ?>

    </div>

    <p>
        <strong>Meeting:</strong>
        <?php echo htmlspecialchars($meeting["title"]); ?>
    </p>

    <br>

    <a
        href="my_meetings.php"
        class="btn btn-secondary"
    >
        Back to My Meetings
    </a>

    <a
        href="../dashboard.php"
        class="btn btn-secondary"
    >
        Back to Dashboard
    </a>

</div>

</main>

<footer class="site-footer">

<div class="container">

    <p>
        &copy; <?php echo date("Y"); ?> MeetSync Kenya.
        Meeting Scheduling and Coordination System.
    </p>

</div>


</footer>

</body>

</html>
