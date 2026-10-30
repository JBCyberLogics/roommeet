<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/activity_log.php";

$user_id = $_SESSION["user_id"];

$message = "";
$message_type = "";

/*
 * Check that a meeting ID was provided.
 */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid meeting ID.");
}

$meeting_id = (int) $_GET["id"];

/*
 * Get the meeting.
 *
 * Only the person who created the meeting
 * is allowed to edit it.
 */
$stmt = $conn->prepare(
    "SELECT id, title, description, meeting_date,
            start_time, end_time, location, status
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

/*
 * Stop if the meeting does not exist
 * or does not belong to the logged-in user.
 */
if (!$meeting) {
    die("Meeting not found or you are not allowed to edit it.");
}

/*
 * Cancelled meetings cannot be edited.
 */
if ($meeting["status"] == "cancelled") {
    die("Cancelled meetings cannot be edited.");
}

/*
 * Get participants of this meeting.
 */
$participant_ids = [];

$participant_stmt = $conn->prepare(
    "SELECT user_id
     FROM meeting_participants
     WHERE meeting_id = ?"
);

$participant_stmt->bind_param(
    "i",
    $meeting_id
);

$participant_stmt->execute();

$participant_result = $participant_stmt->get_result();

while ($participant = $participant_result->fetch_assoc()) {
    $participant_ids[] = (int) $participant["user_id"];
}

$participant_stmt->close();

/*
 * Add the meeting creator to the people
 * whose availability must be checked.
 */
$people_to_check = $participant_ids;

$people_to_check[] = $user_id;

$people_to_check = array_unique($people_to_check);

/*
 * Update meeting.
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $meeting_date = $_POST["meeting_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $location = trim($_POST["location"]);

    /*
     * Validate required fields.
     */
    if (
        empty($title) ||
        empty($meeting_date) ||
        empty($start_time) ||
        empty($end_time)
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    } elseif (
        strtotime($end_time) <= strtotime($start_time)
    ) {

        $message = "End time must be later than start time.";
        $message_type = "error";

    } else {

        $conflict_found = false;
        $conflict_person = "";
        $conflict_title = "";
        $conflict_start = "";
        $conflict_end = "";

        /*
         * Check every person involved in the meeting.
         */
        foreach ($people_to_check as $person_id) {

            $person_id = (int) $person_id;

            /*
             * Get person's name.
             */
            $person_stmt = $conn->prepare(
                "SELECT name
                 FROM users
                 WHERE id = ?"
            );

            $person_stmt->bind_param(
                "i",
                $person_id
            );

            $person_stmt->execute();

            $person_result = $person_stmt->get_result();

            $person = $person_result->fetch_assoc();

            $person_stmt->close();

            /*
             * Check for another meeting that overlaps
             * with the new date and time.
             *
             * The current meeting is excluded using:
             * m.id != ?
             *
             * A person can be involved because they:
             * - created the meeting, OR
             * - are listed as a participant.
             */
            $conflict_stmt = $conn->prepare(
                "SELECT m.title, m.start_time, m.end_time
                 FROM meetings m
                 LEFT JOIN meeting_participants mp
                 ON m.id = mp.meeting_id
                 WHERE m.id != ?
                 AND m.meeting_date = ?
                 AND m.status = 'scheduled'
                 AND (m.created_by = ? OR mp.user_id = ?)
                 AND m.start_time < ?
                 AND m.end_time > ?
                 LIMIT 1"
            );

            $conflict_stmt->bind_param(
                "isiiss",
                $meeting_id,
                $meeting_date,
                $person_id,
                $person_id,
                $end_time,
                $start_time
            );

            $conflict_stmt->execute();

            $conflict_result = $conflict_stmt->get_result();

            if ($conflict_result->num_rows > 0) {

                $existing_meeting =
                    $conflict_result->fetch_assoc();

                $person_name =
                    $person["name"]
                    ?? "Meeting participant";

                $conflict_person = $person_name;

                $conflict_title =
                    $existing_meeting["title"];

                $conflict_start =
                    $existing_meeting["start_time"];

                $conflict_end =
                    $existing_meeting["end_time"];

                $conflict_found = true;

                $conflict_stmt->close();

                break;
            }

            $conflict_stmt->close();
        }

        /*
         * Stop the update when a conflict exists.
         */
        if ($conflict_found) {

            $message =
                "Scheduling conflict detected for "
                . $conflict_person
                . ". Existing meeting: "
                . $conflict_title
                . " ("
                . date(
                    "g:i A",
                    strtotime($conflict_start)
                )
                . " - "
                . date(
                    "g:i A",
                    strtotime($conflict_end)
                )
                . ").";

            $message_type = "error";

        } else {

            /*
             * No conflict was found.
             * Update the meeting.
             */
            $update_stmt = $conn->prepare(
                "UPDATE meetings
                 SET title = ?,
                     description = ?,
                     meeting_date = ?,
                     start_time = ?,
                     end_time = ?,
                     location = ?
                 WHERE id = ?
                 AND created_by = ?"
            );

            $update_stmt->bind_param(
                "ssssssii",
                $title,
                $description,
                $meeting_date,
                $start_time,
                $end_time,
                $location,
                $meeting_id,
                $user_id
            );

            if ($update_stmt->execute()) {

                /*
                 * Record successful meeting update
                 * in the activity log.
                 */
                logActivity(
                    $conn,
                    $user_id,
                    "Meeting Updated",
                    "Updated meeting: " . $title
                );

                $message =
                    "Meeting updated successfully.";

                $message_type = "success";

                /*
                 * Update the displayed values.
                 */
                $meeting["title"] = $title;
                $meeting["description"] = $description;
                $meeting["meeting_date"] = $meeting_date;
                $meeting["start_time"] = $start_time;
                $meeting["end_time"] = $end_time;
                $meeting["location"] = $location;

            } else {

                $message =
                    "Failed to update the meeting.";

                $message_type = "error";
            }

            $update_stmt->close();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>MeetSync - Edit Meeting</title>

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

    <div class="section-header">

        <h2>Edit Meeting</h2>

        <p>
            Update the details of your scheduled meeting.
        </p>

    </div>


    <?php if (!empty($message)): ?>

        <div class="message message-<?php echo $message_type; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <form method="POST">

        <div class="form-group">

            <label for="title">
                Meeting Title
            </label>

            <input
                type="text"
                id="title"
                name="title"
                value="<?php echo htmlspecialchars($meeting["title"]); ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="description">
                Agenda / Description
            </label>

            <textarea
                id="description"
                name="description"
                rows="5"
            ><?php echo htmlspecialchars($meeting["description"]); ?></textarea>

        </div>


        <div class="form-group">

            <label for="meeting_date">
                Meeting Date
            </label>

            <input
                type="date"
                id="meeting_date"
                name="meeting_date"
                value="<?php echo htmlspecialchars($meeting["meeting_date"]); ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="start_time">
                Start Time
            </label>

            <input
                type="time"
                id="start_time"
                name="start_time"
                value="<?php echo htmlspecialchars(substr($meeting["start_time"], 0, 5)); ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="end_time">
                End Time
            </label>

            <input
                type="time"
                id="end_time"
                name="end_time"
                value="<?php echo htmlspecialchars(substr($meeting["end_time"], 0, 5)); ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="location">
                Venue / Location
            </label>

            <input
                type="text"
                id="location"
                name="location"
                value="<?php echo htmlspecialchars($meeting["location"]); ?>"
            >

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-success"
            >
                Update Meeting
            </button>

            <a
                href="my_meetings.php"
                class="btn btn-secondary"
            >
                Back to My Meetings
            </a>

        </div>

    </form>

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
