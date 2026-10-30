<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=meetsync_meeting_report.csv");

$output = fopen("php://output", "w");

fputcsv(
    $output,
    [
        "Meeting",
        "Date",
        "Start Time",
        "End Time",
        "Location",
        "Organizer",
        "Status"
    ]
);

$stmt = $conn->prepare(
    "SELECT
        m.title,
        m.meeting_date,
        m.start_time,
        m.end_time,
        m.location,
        u.name AS organizer,
        m.status
     FROM meetings m
     INNER JOIN users u
     ON m.created_by = u.id
     ORDER BY m.meeting_date DESC, m.start_time DESC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($meeting = $result->fetch_assoc()) {

    fputcsv(
        $output,
        [
            $meeting["title"],
            $meeting["meeting_date"],
            date("g:i A", strtotime($meeting["start_time"])),
            date("g:i A", strtotime($meeting["end_time"])),
            $meeting["location"],
            $meeting["organizer"],
            $meeting["status"]
        ]
    );
}

$stmt->close();

fclose($output);

exit;

?>
