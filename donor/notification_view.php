<?php

session_start();
require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Donor Role Check
|--------------------------------------------------------------------------
*/

if ($_SESSION["user_role"] != "donor") {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Notification ID Check
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"])) {
    header("Location: notifications.php");
    exit;
}

$notification_id = intval($_GET["id"]);

/*
|--------------------------------------------------------------------------
| Get Notification
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        request_id,
        title,
        message,
        type,
        created_at
    FROM notifications
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

$notification = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Notification Not Found
|--------------------------------------------------------------------------
*/

if (!$notification) {
    die("Notification not found.");
}

/*
|--------------------------------------------------------------------------
| Mark Notification as Read
|--------------------------------------------------------------------------
*/

$update = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
    AND user_id = ?
");

$update->bind_param(
    "ii",
    $notification_id,
    $user_id
);

$update->execute();

$update->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>BloodConnect - Notification</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background: #f5f6fa;
    color: #333;
}

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.header {
    background: #a81531;
    color: white;
    text-align: center;
    padding: 20px;
}

.header h1 {
    margin: 0;
    font-size: 26px;
}

.header p {
    margin: 6px 0 0;
    font-size: 14px;
}

/*
|--------------------------------------------------------------------------
| Main Container
|--------------------------------------------------------------------------
*/

.container {
    max-width: 700px;
    margin: 30px auto;
    padding: 0 20px;
}

/*
|--------------------------------------------------------------------------
| Notification Card
|--------------------------------------------------------------------------
*/

.notification-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.notification-title {
    color: #a81531;
    margin-top: 0;
    font-size: 24px;
}

/*
|--------------------------------------------------------------------------
| Message
|--------------------------------------------------------------------------
*/

.message-box {
    background: #f8f8f8;
    border-left: 4px solid #a81531;
    padding: 15px;
    border-radius: 6px;
    margin: 20px 0;
}

.message-box p {
    margin: 0;
    line-height: 1.6;
}

/*
|--------------------------------------------------------------------------
| Information
|--------------------------------------------------------------------------
*/

.info-row {
    margin: 12px 0;
    font-size: 15px;
}

.info-label {
    font-weight: bold;
}

/*
|--------------------------------------------------------------------------
| Read Status
|--------------------------------------------------------------------------
*/

.read-status {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

/*
|--------------------------------------------------------------------------
| Related Request
|--------------------------------------------------------------------------
*/

.related-request {
    margin-top: 25px;
    padding: 18px;
    background: #fff5f6;
    border: 1px solid #f1c5cc;
    border-radius: 10px;
}

.related-request h3 {
    margin-top: 0;
    color: #a81531;
}

.related-request p {
    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

.button {
    display: inline-block;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 7px;
    margin: 5px 5px 5px 0;
    font-size: 14px;
    font-weight: bold;
}

.primary-button {
    background: #a81531;
    color: white;
}

.secondary-button {
    background: #eeeeee;
    color: #333;
}

.logout-button {
    background: #333;
    color: white;
}

/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 600px) {

    .container {
        margin-top: 20px;
        padding: 0 12px;
    }

    .notification-card {
        padding: 18px;
    }

    .notification-title {
        font-size: 21px;
    }

    .button {
        display: block;
        text-align: center;
        margin: 8px 0;
    }

}

</style>

</head>

<body>

<!-- Header -->

<div class="header">

    <h1>🩸 BloodConnect</h1>

    <p>Notification Details</p>

</div>


<!-- Main Content -->

<div class="container">

    <div class="notification-card">

        <!-- Notification Title -->

        <h2 class="notification-title">

            <?php
            echo htmlspecialchars($notification["title"]);
            ?>

        </h2>


        <!-- Message -->

        <div class="message-box">

            <p>

                <?php
                echo htmlspecialchars($notification["message"]);
                ?>

            </p>

        </div>


        <!-- Type -->

        <div class="info-row">

            <span class="info-label">
                Type:
            </span>

            <?php
            echo htmlspecialchars($notification["type"]);
            ?>

        </div>


        <!-- Date -->

        <?php if (!empty($notification["created_at"])): ?>

            <div class="info-row">

                <span class="info-label">
                    Received:
                </span>

                <?php
                echo htmlspecialchars($notification["created_at"]);
                ?>

            </div>

        <?php endif; ?>


        <!-- Read Status -->

        <div class="info-row">

            <span class="read-status">
                ✓ Read
            </span>

        </div>


        <!-- Related Blood Request -->

        <?php if (!empty($notification["request_id"])): ?>

            <div class="related-request">

                <h3>🩸 Related Blood Request</h3>

                <p>
                    This notification is related to a blood
                    donation request that matches your blood group.
                </p>

                <a
                    href="requests.php"
                    class="button primary-button"
                >
                    View Blood Requests
                </a>

            </div>

        <?php endif; ?>


        <!-- Navigation -->

        <br>

        <a
            href="notifications.php"
            class="button secondary-button"
        >
            ← Back to Notifications
        </a>

        <a
            href="dashboard.php"
            class="button secondary-button"
        >
            🏠 Dashboard
        </a>

        <a
            href="../auth/logout.php"
            class="button logout-button"
        >
            Logout
        </a>

    </div>

</div>

</body>

</html>