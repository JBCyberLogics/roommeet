<?php

require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION["user_id"];

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=meetsync_schedule_report.csv");

$output = fopen("php://output", "w");

fputcsv(
    $output,
    [
        "Date",
        "Start Time",
        "End Time",
        "Meeting",
        "Organizer",
        "Location"
    ]
);

$stmt = $conn->prepare(
    "SELECT
        m.title,
        m.meeting_date,
        m.start_time,
        m.end_time,
        m.location,
        u.name AS organizer
     FROM meetings m
     INNER JOIN users u
     ON m.created_by = u.id
     LEFT JOIN meeting_participants mp
     ON m.id = mp.meeting_id
     WHERE m.status = 'scheduled'
     AND (
         m.created_by = ?
         OR mp.user_id = ?
     )
     ORDER BY m.meeting_date ASC, m.start_time ASC"
);

$stmt->bind_param(
    "ii",
    $user_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($meeting = $result->fetch_assoc()) {

    fputcsv(
        $output,
        [
            $meeting["meeting_date"],
            date("g:i A", strtotime($meeting["start_time"])),
            date("g:i A", strtotime($meeting["end_time"])),
            $meeting["title"],
            $meeting["organizer"],
            $meeting["location"]
        ]
    );
}

$stmt->close();

fclose($output);

exit;

?>
