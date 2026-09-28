<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Prevent Browser Caching
|--------------------------------------------------------------------------
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["user_role"] != "donor") {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Get Donor Notifications
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

/*
|--------------------------------------------------------------------------
| Notification Counts
|--------------------------------------------------------------------------
*/
$total_notifications = $result->num_rows;
$unread_notifications = 0;

$notifications = [];

while ($notification = $result->fetch_assoc()) {

    $notifications[] = $notification;

    if ((int)$notification["is_read"] === 0) {
        $unread_notifications++;
    }
}

/*
|--------------------------------------------------------------------------
| Alert Component
|--------------------------------------------------------------------------
*/
require_once "../components/alert.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BloodConnect - Notifications</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background:
        radial-gradient(circle at top left, rgba(220, 38, 38, 0.10), transparent 30%),
        linear-gradient(135deg, #fff7f7, #f8fafc);
    min-height: 100vh;
    color: #1f2937;
    padding-bottom: 100px;
}

/* ---------------------------------------------------------
   NAVBAR
--------------------------------------------------------- */

.navbar {
    width: 100%;
    background: rgba(255, 255, 255, 0.90);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(220, 38, 38, 0.12);
    padding: 16px 6%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    font-size: 22px;
    font-weight: 800;
    color: #b91c1c;
    text-decoration: none;
}

.logo span {
    color: #374151;
}

.logout-btn {
    text-decoration: none;
    color: #b91c1c;
    font-weight: 700;
    font-size: 14px;
    padding: 9px 15px;
    border-radius: 10px;
    border: 1px solid rgba(185, 28, 28, 0.18);
    background: rgba(254, 242, 242, 0.8);
    transition: 0.2s;
}

.logout-btn:hover {
    background: #fee2e2;
}

/* ---------------------------------------------------------
   MAIN
--------------------------------------------------------- */

.container {
    width: min(1050px, 92%);
    margin: 35px auto;
}

/* ---------------------------------------------------------
   PAGE HEADER
--------------------------------------------------------- */

.page-header {
    background: linear-gradient(
        135deg,
        rgba(127, 29, 29, 0.97),
        rgba(185, 28, 28, 0.94)
    );
    color: white;
    border-radius: 24px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 18px 40px rgba(127, 29, 29, 0.18);
    position: relative;
    overflow: hidden;
}

.page-header::after {
    content: "";
    position: absolute;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
    right: -50px;
    top: -70px;
}

.page-header h1 {
    font-size: 30px;
    margin-bottom: 8px;
    position: relative;
    z-index: 1;
}

.page-header p {
    color: rgba(255,255,255,0.85);
    font-size: 14px;
    position: relative;
    z-index: 1;
}

/* ---------------------------------------------------------
   SUMMARY
--------------------------------------------------------- */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.summary-card {
    background: rgba(255,255,255,0.86);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(185, 28, 28, 0.10);
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 10px 28px rgba(15,23,42,0.06);
}

.summary-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 22px;
    margin-bottom: 12px;
}

.summary-number {
    font-size: 27px;
    font-weight: 800;
    color: #111827;
}

.summary-label {
    color: #6b7280;
    margin-top: 4px;
    font-size: 13px;
}

/* ---------------------------------------------------------
   NOTIFICATION LIST
--------------------------------------------------------- */

.notifications-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 28px 0 15px;
}

.notifications-title h2 {
    font-size: 21px;
    color: #111827;
}

.notification-count {
    font-size: 12px;
    color: #6b7280;
}

/* ---------------------------------------------------------
   NOTIFICATION CARD
--------------------------------------------------------- */

.notification-card {
    position: relative;
    background: rgba(255,255,255,0.92);
    border: 1px solid rgba(148,163,184,0.18);
    border-radius: 20px;
    padding: 22px;
    margin-bottom: 16px;
    box-shadow: 0 10px 26px rgba(15,23,42,0.055);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px rgba(15,23,42,0.09);
}

/* Unread notification */

.notification-card.unread {
    border-left: 5px solid #dc2626;
    background:
        linear-gradient(
            90deg,
            rgba(254,242,242,0.95),
            rgba(255,255,255,0.95)
        );
}

/* Notification top */

.notification-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 12px;
}

.notification-heading {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: #fee2e2;
    color: #b91c1c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    flex-shrink: 0;
}

.notification-title {
    font-size: 17px;
    font-weight: 800;
    color: #111827;
}

/* Status badge */

.status-badge {
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.status-new {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.status-read {
    color: #166534;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
}

/* Message */

.notification-message {
    color: #4b5563;
    line-height: 1.65;
    font-size: 14px;
    margin: 10px 0 17px;
}

/* Meta */

.notification-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 17px;
}

.meta-item {
    font-size: 12px;
    color: #6b7280;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    padding: 7px 10px;
    border-radius: 9px;
}

.meta-item strong {
    color: #374151;
}

/* Type badges */

.type-badge {
    font-size: 11px;
    font-weight: 800;
    text-transform: capitalize;
    padding: 7px 10px;
    border-radius: 9px;
    background: #fef2f2;
    color: #991b1b;
}

/* Open button */

.open-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    background: #b91c1c;
    color: white;
    padding: 10px 15px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    transition: 0.2s;
}

.open-btn:hover {
    background: #991b1b;
    transform: translateY(-1px);
}

/* ---------------------------------------------------------
   EMPTY STATE
--------------------------------------------------------- */

.empty-state {
    text-align: center;
    background: rgba(255,255,255,0.9);
    border: 1px solid #e5e7eb;
    border-radius: 22px;
    padding: 55px 25px;
    box-shadow: 0 10px 25px rgba(15,23,42,0.05);
}

.empty-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 18px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fee2e2;
    font-size: 32px;
}

.empty-state h3 {
    color: #111827;
    margin-bottom: 8px;
}

.empty-state p {
    color: #6b7280;
    font-size: 14px;
}

/* ---------------------------------------------------------
   BOTTOM NAVIGATION
--------------------------------------------------------- */

.bottom-nav {
    position: fixed;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    width: min(650px, 92%);
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(18px);
    border: 1px solid rgba(185,28,28,0.12);
    box-shadow: 0 15px 40px rgba(15,23,42,0.14);
    border-radius: 18px;
    padding: 10px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    z-index: 200;
}

.bottom-nav a {
    text-decoration: none;
    color: #6b7280;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 9px 5px;
    border-radius: 13px;
    font-size: 11px;
    font-weight: 700;
    transition: 0.2s;
}

.bottom-nav a:hover {
    background: #fef2f2;
    color: #b91c1c;
}

.bottom-nav a.active {
    background: #fee2e2;
    color: #b91c1c;
}

.nav-icon {
    font-size: 19px;
}

/* ---------------------------------------------------------
   RESPONSIVE
--------------------------------------------------------- */

@media (max-width: 700px) {

    .container {
        width: 94%;
        margin: 22px auto;
    }

    .navbar {
        padding: 14px 4%;
    }

    .page-header {
        padding: 24px;
        border-radius: 20px;
    }

    .page-header h1 {
        font-size: 25px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .notification-card {
        padding: 18px;
    }

    .notification-top {
        flex-direction: column;
    }

    .status-badge {
        align-self: flex-start;
    }

    .bottom-nav {
        bottom: 10px;
    }

}

@media (max-width: 450px) {

    .logo {
        font-size: 19px;
    }

    .logout-btn {
        font-size: 12px;
        padding: 8px 11px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .notification-title {
        font-size: 15px;
    }

    .notification-message {
        font-size: 13px;
    }

    .bottom-nav a {
        font-size: 10px;
    }

    .nav-icon {
        font-size: 17px;
    }

}

</style>

</head>

<body>

<!-- =====================================================
     NAVBAR
====================================================== -->

<nav class="navbar">

    <a href="dashboard.php" class="logo">
        🩸 Blood<span>Connect</span>
    </a>

    <a href="../auth/logout.php" class="logout-btn">
        Logout
    </a>

</nav>


<!-- =====================================================
     MAIN CONTAINER
====================================================== -->

<main class="container">

    <!-- PAGE HEADER -->

    <section class="page-header">

        <h1>🔔 Notifications</h1>

        <p>
            Stay updated about your blood donation requests and activities.
        </p>

    </section>


    <!-- SUMMARY -->

    <section class="summary-grid">

        <div class="summary-card">

            <div class="summary-icon">
                🔔
            </div>

            <div class="summary-number">
                <?php echo $total_notifications; ?>
            </div>

            <div class="summary-label">
                Total Notifications
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                🔴
            </div>

            <div class="summary-number">
                <?php echo $unread_notifications; ?>
            </div>

            <div class="summary-label">
                Unread Notifications
            </div>

        </div>

    </section>


    <!-- NOTIFICATION TITLE -->

    <div class="notifications-title">

        <h2>Your Notifications</h2>

        <span class="notification-count">
            <?php echo $total_notifications; ?>
            notification<?php echo $total_notifications == 1 ? "" : "s"; ?>
        </span>

    </div>


    <!-- NOTIFICATIONS -->

    <?php if (!empty($notifications)): ?>

        <?php foreach ($notifications as $notification): ?>

            <?php
                $is_unread = ((int)$notification["is_read"] === 0);

                $type = strtolower(trim($notification["type"]));

                switch ($type) {

                    case "success":
                        $type_icon = "✓";
                        break;

                    case "warning":
                        $type_icon = "⚠";
                        break;

                    case "error":
                        $type_icon = "!";
                        break;

                    default:
                        $type_icon = "🔔";
                        break;
                }
            ?>

            <article class="notification-card <?php echo $is_unread ? "unread" : ""; ?>">

                <div class="notification-top">

                    <div class="notification-heading">

                        <div class="notification-icon">
                            <?php echo $type_icon; ?>
                        </div>

                        <div class="notification-title">
                            <?php echo htmlspecialchars($notification["title"]); ?>
                        </div>

                    </div>


                    <?php if ($is_unread): ?>

                        <span class="status-badge status-new">
                            🔴 NEW
                        </span>

                    <?php else: ?>

                        <span class="status-badge status-read">
                            ✓ READ
                        </span>

                    <?php endif; ?>

                </div>


                <div class="notification-message">

                    <?php echo nl2br(htmlspecialchars($notification["message"])); ?>

                </div>


                <div class="notification-meta">

                    <span class="type-badge">

                        <?php echo htmlspecialchars($notification["type"]); ?>

                    </span>


                    <span class="meta-item">

                        <strong>📅</strong>

                        <?php echo htmlspecialchars($notification["created_at"]); ?>

                    </span>

                </div>


                <?php if (!empty($notification["request_id"])): ?>

                    <a
                        href="notification_view.php?id=<?php echo $notification["id"]; ?>"
                        class="open-btn"
                    >
                        View Notification →
                    </a>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-state">

            <div class="empty-icon">
                🔔
            </div>

            <h3>No Notifications Yet</h3>

            <p>
                You don't have any notifications at the moment.
                New updates will appear here.
            </p>

        </div>

    <?php endif; ?>

</main>


<!-- =====================================================
     BOTTOM NAVIGATION
====================================================== -->

<nav class="bottom-nav">

    <a href="dashboard.php">

        <span class="nav-icon">🏠</span>

        Dashboard

    </a>


    <a href="requests.php">

        <span class="nav-icon">🩸</span>

        Requests

    </a>


    <a href="notifications.php" class="active">

        <span class="nav-icon">🔔</span>

        Notifications

    </a>


    <a href="donations.php">

        <span class="nav-icon">❤️</span>

        Donations

    </a>

</nav>

</body>

</html>