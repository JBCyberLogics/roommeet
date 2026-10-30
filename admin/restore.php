<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

$message = "";
$message_type = "";


/*
 * Create a backup of the current database before restoring.
 */
function createSafetyBackup($conn)
{
    $tables = [
        "users",
        "meetings",
        "meeting_participants",
        "notifications",
        "activity_logs"
    ];

    $filename = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . "meetsync_safety_backup_"
        . date("Y-m-d_H-i-s")
        . ".sql";

    $file = fopen($filename, "w");

    if (!$file) {
        return false;
    }

    fwrite(
        $file,
        "-- MeetSync Safety Backup\n"
    );

    fwrite(
        $file,
        "-- Created before database restore\n\n"
    );

    fwrite(
        $file,
        "SET FOREIGN_KEY_CHECKS=0;\n\n"
    );

    foreach ($tables as $table) {

        $structure_result = $conn->query(
            "SHOW CREATE TABLE `$table`"
        );

        if (!$structure_result) {
            fclose($file);
            return false;
        }

        $structure = $structure_result->fetch_assoc();

        fwrite(
            $file,
            "DROP TABLE IF EXISTS `$table`;\n"
        );

        fwrite(
            $file,
            $structure["Create Table"] . ";\n\n"
        );

        $data_result = $conn->query(
            "SELECT * FROM `$table`"
        );

        if (!$data_result) {
            fclose($file);
            return false;
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

            $sql = "INSERT INTO `$table` ("
                . implode(", ", $columns)
                . ") VALUES ("
                . implode(", ", $values)
                . ");\n";

            fwrite($file, $sql);
        }

        fwrite($file, "\n");
    }

    fwrite(
        $file,
        "SET FOREIGN_KEY_CHECKS=1;\n"
    );

    fclose($file);

    return $filename;
}


/*
 * Process restore request.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_FILES["backup_file"]) ||
        $_FILES["backup_file"]["error"] !== UPLOAD_ERR_OK
    ) {

        $message = "Please select a valid SQL backup file.";
        $message_type = "error";

    } elseif (
        !isset($_POST["confirm_restore"])
    ) {

        $message = "Please confirm the restore operation.";
        $message_type = "error";

    } else {

        $file_name = $_FILES["backup_file"]["name"];
        $tmp_name = $_FILES["backup_file"]["tmp_name"];

        $extension = strtolower(
            pathinfo($file_name, PATHINFO_EXTENSION)
        );

        if ($extension !== "sql") {

            $message = "Only SQL backup files are allowed.";
            $message_type = "error";

        } else {

            $sql_content = file_get_contents($tmp_name);

            if ($sql_content === false || trim($sql_content) === "") {

                $message = "The selected backup file is empty.";
                $message_type = "error";

            } else {

                /*
                 * Make sure this looks like a MeetSync backup.
                 */
                $required_tables = [
                    "users",
                    "meetings",
                    "meeting_participants",
                    "notifications",
                    "activity_logs"
                ];

                $valid_backup = true;

                foreach ($required_tables as $table) {

                    if (
                        strpos(
                            $sql_content,
                            "Table: " . $table
                        ) === false
                    ) {
                        $valid_backup = false;
                        break;
                    }
                }

                if (!$valid_backup) {

                    $message =
                        "The selected file does not appear to be a valid MeetSync database backup.";

                    $message_type = "error";

                } else {

                    /*
                     * Create a safety backup first.
                     */
                    $safety_backup = createSafetyBackup($conn);

                    if ($safety_backup === false) {

                        $message =
                            "Restore cancelled. The current database could not be backed up safely.";

                        $message_type = "error";

                    } else {

                        /*
                         * Execute the selected SQL backup.
                         */
                        $restore_successful = true;
                        $restore_error = "";

                        if (!$conn->multi_query($sql_content)) {

                            $restore_successful = false;
                            $restore_error = $conn->error;

                        } else {

                            /*
                             * Process all SQL statements.
                             */
                            do {

                                if ($result = $conn->store_result()) {
                                    $result->free();
                                }

                                if ($conn->errno) {

                                    $restore_successful = false;
                                    $restore_error = $conn->error;
                                    break;
                                }

                            } while (
                                $conn->more_results()
                                && $conn->next_result()
                            );
                        }

                        if ($restore_successful) {

                            $message =
                                "Database restored successfully. A safety backup of the previous database was created before the restore.";

                            $message_type = "success";

                        } else {

                            $message =
                                "Database restore failed. Safety backup created at: "
                                . $safety_backup
                                . ". Error: "
                                . $restore_error;

                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>MeetSync - Restore Database</title>

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

    <a href="meeting_reports.php">
        Meeting Reports
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
        Restore MeetSync Database
    </h2>

    <p>
        Restore the system database using a previously created
        MeetSync SQL backup.
    </p>

</section>


<!-- =========================
     WARNING
========================= -->

<div class="message message-error">

    <strong>Warning:</strong>

    Restoring a database will replace the current database
    data with the data contained in the selected backup.

    A safety backup of the current database will be created
    before the restore operation begins.

</div>


<!-- =========================
     RESULT MESSAGE
========================= -->

<?php if (!empty($message)): ?>

    <?php

    $message_class =
        ($message_type === "success")
        ? "message-success"
        : "message-error";

    ?>

    <div class="message <?php echo $message_class; ?>">

        <?php echo htmlspecialchars($message); ?>

    </div>

<?php endif; ?>


<!-- =========================
     RESTORE FORM
========================= -->

<div class="form-container">

    <div class="card">


        <h3>
            Select Backup File
        </h3>


        <form method="POST"
              action=""
              enctype="multipart/form-data">


            <div class="form-group">

                <label for="backup_file">

                    Select SQL Backup File

                </label>

                <input
                    type="file"
                    id="backup_file"
                    name="backup_file"
                    accept=".sql"
                    required>

            </div>


            <div class="form-group">

                <label>

                    <input
                        type="checkbox"
                        name="confirm_restore"
                        value="1"
                        required>

                    I understand that this will replace
                    the current database data.

                </label>

            </div>


            <div class="form-actions">

                <button type="submit"
                        class="btn btn-danger">

                    Restore Database

                </button>


                <a href="index.php"
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
