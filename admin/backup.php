<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

$tables = [
    "users",
    "meetings",
    "meeting_participants",
    "notifications",
    "activity_logs"
];

$filename = "meetsync_backup_" . date("Y-m-d_H-i-s") . ".sql";

header("Content-Type: application/sql");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "-- MeetSync Database Backup\n";
echo "-- Created: " . date("Y-m-d H:i:s") . "\n\n";

echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {

    echo "-- ----------------------------------------\n";
    echo "-- Table: " . $table . "\n";
    echo "-- ----------------------------------------\n\n";

    /*
     * Get the table structure.
     */
    $structure_result = $conn->query(
        "SHOW CREATE TABLE `$table`"
    );

    if (!$structure_result) {
        continue;
    }

    $structure = $structure_result->fetch_assoc();

    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $structure["Create Table"] . ";\n\n";

    /*
     * Get all table records.
     */
    $data_result = $conn->query(
        "SELECT * FROM `$table`"
    );

    if (!$data_result) {
        continue;
    }

    while ($row = $data_result->fetch_assoc()) {

        $columns = [];
        $values = [];

        foreach ($row as $column => $value) {

            $columns[] = "`" . $column . "`";

            if ($value === null) {

                $values[] = "NULL";

            } else {

                $escaped_value = $conn->real_escape_string(
                    $value
                );

                $values[] = "'" . $escaped_value . "'";
            }
        }

        echo "INSERT INTO `$table` (";
        echo implode(", ", $columns);
        echo ") VALUES (";
        echo implode(", ", $values);
        echo ");\n";
    }

    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";

$conn->close();

exit;

?>
