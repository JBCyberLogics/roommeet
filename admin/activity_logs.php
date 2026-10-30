
<?php

require_once "../includes/admin_auth.php";
require_once "../includes/db.php";

$stmt = $conn->prepare(
    "SELECT
        activity_logs.id,
        users.name,
        activity_logs.action,
        activity_logs.description,
        activity_logs.created_at
     FROM activity_logs
     LEFT JOIN users
     ON activity_logs.user_id = users.id
     ORDER BY activity_logs.created_at DESC"
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

<title>MeetSync - Activity Logs</title>

<link rel="stylesheet" href="../css/style.css">

<script src="../js/app.js"></script>

    <link rel="stylesheet" href="../css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="../uploads/profile_pictures/favicon.jpeg">
</head>

<body>

<div class="container">

    <div class="page-header">

        <h2>System Activity Logs</h2>

        <p>
            View recorded activities performed within the MeetSync system.
        </p>

    </div>


    <a href="index.php" class="back-link">
        ← Back to Administration
    </a>


    <?php if ($result->num_rows > 0): ?>

        <div class="table-container">

            <table class="activity-table">

                <thead>

                    <tr>

                        <th>Date and Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>

                    </tr>

                </thead>

                <tbody>

                    <?php while ($log = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars($log["created_at"]);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $log["name"] ?? "Unknown User"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars($log["action"]);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars($log["description"]);
                                ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="empty-message">

            No activity has been recorded yet.

        </div>

    <?php endif; ?>

</div>

</body>

</html>

<?php

$stmt->close();

?>
