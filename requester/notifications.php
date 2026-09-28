<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["user_role"] != "requester") {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Mark all notifications as read when notification page is opened
|--------------------------------------------------------------------------
*/

$mark_read = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
    AND is_read = 0
");

$mark_read->bind_param("i", $user_id);
$mark_read->execute();
$mark_read->close();


/*
|--------------------------------------------------------------------------
| Get notifications
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        request_id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BloodConnect - Notifications</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #faf7f5;
    color: #332827;
}

.container {
    width: 90%;
    max-width: 900px;
    margin: 40px auto;
}

.header {
    background: linear-gradient(
        135deg,
        #7d2634,
        #a92335
    );

    color: white;

    padding: 30px;

    border-radius: 18px;

    margin-bottom: 25px;

    box-shadow:
        0 15px 35px rgba(70,25,30,.15);
}

.header h1 {
    margin: 0 0 8px;
}

.header p {
    margin: 0;
    color: #f7dfe2;
}

.notification {
    background: white;

    padding: 22px;

    margin-bottom: 18px;

    border-radius: 15px;

    border: 1px solid #eadbd8;

    box-shadow:
        0 8px 25px rgba(50,25,20,.06);
}

.notification.unread {
    border-left: 5px solid #a81531;
}

.notification.read {
    border-left: 5px solid #bdb5b3;
}

.status {
    display: inline-block;

    padding: 6px 10px;

    border-radius: 15px;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 10px;
}

.new {
    background: #fde5e8;
    color: #a81531;
}

.read {
    background: #eeeeee;
    color: #666;
}

.notification h3 {
    margin: 5px 0 10px;

    color: #6f1d2b;
}

.notification p {
    color: #756966;

    line-height: 1.6;
}

.date {
    font-size: 12px;

    color: #999;
}

.open-btn {
    display: inline-block;

    margin-top: 8px;

    padding: 9px 15px;

    background: #8f1726;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-size: 13px;

    font-weight: bold;
}

.open-btn:hover {
    background: #a92335;
}

.empty {
    background: white;

    padding: 40px;

    text-align: center;

    border-radius: 15px;

    color: #756966;
}

.navigation {
    margin-top: 30px;

    display: flex;

    flex-wrap: wrap;

    gap: 12px;
}

.navigation a {
    text-decoration: none;

    padding: 11px 17px;

    border-radius: 8px;

    background: #8f1726;

    color: white;

    font-size: 13px;

    font-weight: bold;
}

.navigation a:hover {
    background: #a92335;
}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <h1>
            🩸 BloodConnect
        </h1>

        <p>
            🔔 Your Notifications
        </p>

    </div>


    <!-- NOTIFICATIONS -->

    <?php if ($result->num_rows > 0): ?>

        <?php while ($notification = $result->fetch_assoc()): ?>

            <div class="notification read">

                <span class="status read">
                    ✓ Read
                </span>

                <h3>
                    <?php echo htmlspecialchars($notification["title"]); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($notification["message"]); ?>
                </p>

                <p>
                    <strong>Type:</strong>
                    <?php echo htmlspecialchars($notification["type"]); ?>
                </p>

                <p class="date">
                    <?php echo htmlspecialchars($notification["created_at"]); ?>
                </p>


                <?php if (!empty($notification["request_id"])): ?>

                    <a
                        class="open-btn"
                        href="notification_view.php?id=<?php echo $notification["id"]; ?>"
                    >
                        Open Notification →
                    </a>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">

            <h3>
                🔔 No Notifications
            </h3>

            <p>
                You don't have any notifications yet.
            </p>

        </div>

    <?php endif; ?>


    <!-- NAVIGATION -->

    <div class="navigation">

        <a href="dashboard.php">
            ← Dashboard
        </a>

        <a href="create_request.php">
            + Create Blood Request
        </a>

        <a href="my_requests.php">
            My Requests
        </a>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>

</body>

</html>