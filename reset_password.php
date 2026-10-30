<?php

require_once "includes/db.php";

$message = "";
$message_type = "";

$token = trim($_GET["token"] ?? "");

if (empty($token)) {

    $message = "Invalid or missing password reset token.";
    $message_type = "error";

} else {

    $stmt = $conn->prepare(
        "SELECT id
         FROM users
         WHERE reset_token = ?
         AND reset_token_expiry > NOW()"
    );

    $stmt->bind_param(
        "s",
        $token
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {

        $message =
            "This password reset link is invalid or has expired.";

        $message_type = "error";

    } else {

        $user = $result->fetch_assoc();

        if ($_SERVER["REQUEST_METHOD"] === "POST") {

            $new_password =
                $_POST["new_password"] ?? "";

            $confirm_password =
                $_POST["confirm_password"] ?? "";

            if (
                empty($new_password) ||
                empty($confirm_password)
            ) {

                $message =
                    "Please enter and confirm your new password.";

                $message_type = "error";

            } elseif (strlen($new_password) < 8) {

                $message =
                    "Password must contain at least 8 characters.";

                $message_type = "error";

            } elseif (
                $new_password !== $confirm_password
            ) {

                $message =
                    "The passwords do not match.";

                $message_type = "error";

            } else {

                /*
                 * Hash the new password securely.
                 */
                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $update_stmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?,
                         reset_token = NULL,
                         reset_token_expiry = NULL
                     WHERE id = ?"
                );

                $update_stmt->bind_param(
                    "si",
                    $hashed_password,
                    $user["id"]
                );

                if ($update_stmt->execute()) {

                    $message =
                        "Your password has been reset successfully.";

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to reset your password.";

                    $message_type = "error";
                }

                $update_stmt->close();
            }
        }
    }

    $stmt->close();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>MeetSync - Reset Password</title>

<link rel="stylesheet"
      href="css/style.css">
<script src="js/app.js"></script>


    <link rel="stylesheet" href="css/professional-ui.css">
<link rel="icon" type="image/jpeg" href="uploads/profile_pictures/favicon.jpeg">
</head>

<body>

<header class="site-header">


<div class="container">

    <div class="navbar">

        <div>

            <h1>MeetSync</h1>

            <p>
                Meeting Scheduling and Coordination System
            </p>

        </div>

    </div>

</div>

</header>

<main class="container">

<div class="form-container">

    <div class="card">

        <h2>
            Reset Password
        </h2>


        <?php if (!empty($message)): ?>

            <div class="message
                <?php
                echo $message_type === "success"
                    ? "message-success"
                    : "message-error";
                ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <?php if (
            $message_type !== "success"
            && !empty($token)
            && isset($user)
        ): ?>

            <form method="POST"
                  action="?token=<?php echo urlencode($token); ?>">


                <div class="form-group">

                    <label for="new_password">

                        New Password

                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <small>
                        Password must contain at least 8 characters.
                    </small>

                </div>


                <div class="form-group">

                    <label for="confirm_password">

                        Confirm New Password

                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-success">

                        Reset Password

                    </button>


                    <a
                        href="login.php"
                        class="btn btn-secondary">

                        Back to Login

                    </a>

                </div>


            </form>

        <?php elseif ($message_type === "success"): ?>

            <div class="form-actions">

                <a
                    href="login.php"
                    class="btn">

                    Go to Login

                </a>

            </div>

        <?php endif; ?>


        <?php if (
            $message_type === "error"
            && !isset($user)
        ): ?>

            <div class="form-actions">

                <a
                    href="forgot_password.php"
                    class="btn btn-secondary">

                    Request New Reset Link

                </a>

                <a
                    href="login.php"
                    class="btn btn-secondary">

                    Back to Login

                </a>

            </div>

        <?php endif; ?>


    </div>

</div>

</main>

<footer class="site-footer">

<div class="container">

    <p>

        MeetSync &copy; <?php echo date("Y"); ?>

        — Meeting Scheduling and Coordination System

    </p>

</div>

</footer>

</body>

</html>
