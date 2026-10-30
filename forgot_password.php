<?php

require_once "includes/db.php";
date_default_timezone_set("Africa/Nairobi");

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (empty($email)) {

        $message = "Please enter your email address.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            /*
             * Generate a secure reset token.
             */
            $reset_token = bin2hex(
                random_bytes(32)
            );

            /*
             * Token expires after 30 minutes.
             */
           $reset_token_expiry = date(
    "Y-m-d H:i:s",
    time() + (30 * 60)
);

            $update_stmt = $conn->prepare(
                "UPDATE users
                 SET reset_token = ?,
                     reset_token_expiry = ?
                 WHERE id = ?"
            );

            $update_stmt->bind_param(
                "ssi",
                $reset_token,
                $reset_token_expiry,
                $user["id"]
            );

            if ($update_stmt->execute()) {

                $reset_link =
                    "http://localhost/meetsync/reset_password.php?token="
                    . urlencode($reset_token);

                $message =
                    "Password reset request created successfully.";

                $message_type = "success";

            } else {

                $message =
                    "Unable to create the password reset request.";

                $message_type = "error";
            }

            $update_stmt->close();

        } else {

            /*
             * Use a general message instead of revealing
             * whether an email exists in the system.
             */
            $message =
                "If the email address is registered, a password reset request has been created.";

            $message_type = "success";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>MeetSync - Forgot Password</title>

      <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>


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
            Forgot Password
        </h2>

        <p>
            Enter your registered email address to request
            a password reset.
        </p>


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
            isset($reset_link)
            && $message_type === "success"
        ): ?>

            <div class="card">

                <h3>
                    Local Testing Reset Link
                </h3>

                <p>
                    Because MeetSync is currently running on
                    your local XAMPP server, use the link below
                    to continue the password reset process.
                </p>

                <p>

                    <a
                        href="<?php echo htmlspecialchars($reset_link); ?>">

                        Reset My Password

                    </a>

                </p>

                <p>

                    <small>
                        This reset link expires after 30 minutes.
                    </small>

                </p>

            </div>

        <?php endif; ?>


        <form method="POST"
              action="">


            <div class="form-group">

                <label for="email">

                    Email Address

                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="email"
                    required
                >

            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="btn">

                    Request Password Reset

                </button>


                <a
                    href="login.php"
                    class="btn btn-secondary">

                    Back to Login

                </a>

            </div>


        </form>

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
