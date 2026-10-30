<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/activity_log.php";

$user_id = $_SESSION["user_id"];


/*
 * Get the participant record ID.
 */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: my_invitations.php");
    exit;
}

$participant_id = (int) $_GET["id"];


/*
 * Get the invitation belonging to the logged-in user.
 */
$stmt = $conn->prepare(
    "SELECT mp.id, mp.status, m.title
     FROM meeting_participants mp
     INNER JOIN meetings m
     ON mp.meeting_id = m.id
     WHERE mp.id = ?
     AND mp.user_id = ?"
);

$stmt->bind_param(
    "ii",
    $participant_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows == 0) {

    $stmt->close();

    header("Location: my_invitations.php");
    exit;
}


$invitation = $result->fetch_assoc();

$stmt->close();


/*
 * Get the requested response.
 */
if (!isset($_GET["response"])) {

    header("Location: my_invitations.php");
    exit;
}

$response = $_GET["response"];


/*
 * Only allow accepted or declined.
 */
if ($response !== "accepted" && $response !== "declined") {

    header("Location: my_invitations.php");
    exit;
}


/*
 * Do not change an already responded invitation.
 */
if ($invitation["status"] !== "invited") {

    header("Location: my_invitations.php");
    exit;
}


/*
 * Update invitation status.
 */
$stmt = $conn->prepare(
    "UPDATE meeting_participants
     SET status = ?
     WHERE id = ?
     AND user_id = ?"
);

$stmt->bind_param(
    "sii",
    $response,
    $participant_id,
    $user_id
);

$stmt->execute();

$update_successful = $stmt->affected_rows > 0;

$stmt->close();


/*
 * Record the response in the activity log.
 */
if ($update_successful) {

    if ($response === "accepted") {

        $action = "Invitation Accepted";
        $description = "Accepted invitation for meeting: " . $invitation["title"];

    } else {

        $action = "Invitation Declined";
        $description = "Declined invitation for meeting: " . $invitation["title"];
    }

    logActivity(
        $conn,
        $user_id,
        $action,
        $description
    );
}


/*
 * Return to invitations page.
 */
header("Location: my_invitations.php");
exit;

?>
