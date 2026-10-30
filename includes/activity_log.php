<?php

function logActivity($conn, $user_id, $action, $description)
{
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs
        (user_id, action, description)
        VALUES (?, ?, ?)"
    );

    $stmt->bind_param(
        "iss",
        $user_id,
        $action,
        $description
    );

    $stmt->execute();

    $stmt->close();
}

?>