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

if ($_SESSION["user_role"] != "requester") {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "User";

/*
|--------------------------------------------------------------------------
| Unread Notifications
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ? AND is_read = 0
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$notification_data = $result->fetch_assoc();

$unread_count = (int)$notification_data["unread_count"];

$stmt->close();

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

<title>BloodConnect | Requester Dashboard</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(185, 28, 28, 0.07),
            transparent 28%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(220, 38, 38, 0.05),
            transparent 30%
        ),
        #faf7f5;
    color: #292322;
    overflow-x: hidden;
    padding-bottom: 40px;
}

/* =========================================================
   BACKGROUND GLOW
========================================================= */

body::before {
    content: "";
    position: fixed;
    width: 500px;
    height: 500px;
    border-radius: 50%;
    background: rgba(165, 29, 45, 0.035);
    filter: blur(80px);
    top: 100px;
    left: -250px;
    pointer-events: none;
    z-index: -2;
}

body::after {
    content: "";
    position: fixed;
    width: 450px;
    height: 450px;
    border-radius: 50%;
    background: rgba(210, 160, 150, 0.05);
    filter: blur(80px);
    bottom: -200px;
    right: -150px;
    pointer-events: none;
    z-index: -2;
}

/* =========================================================
   NAVBAR
========================================================= */

.navbar {
    height: 72px;
    background: rgba(255, 255, 255, 0.90);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(180, 150, 145, 0.22);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 7%;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 5px 25px rgba(50, 25, 20, 0.035);
}

/* =========================================================
   LOGO
========================================================= */

.logo {
    display: flex;
    align-items: center;
    gap: 11px;
    font-size: 23px;
    font-weight: 800;
    color: #8f1726;
}

.logo-drop {
    width: 28px;
    height: 34px;
    background: linear-gradient(
        145deg,
        #c52c40,
        #8f1726
    );
    border-radius: 60% 60% 65% 65%;
    transform: rotate(45deg);
    position: relative;
    box-shadow: 0 5px 15px rgba(143, 23, 38, 0.25);
}

.logo-drop::after {
    content: "";
    position: absolute;
    width: 8px;
    height: 8px;
    background: rgba(255,255,255,0.45);
    border-radius: 50%;
    top: 7px;
    left: 7px;
}

/* =========================================================
   LOGOUT
========================================================= */

.logout-btn {
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 9px;
    background: #8f1726;
    color: white;
    font-size: 13px;
    font-weight: bold;
    transition: 0.3s;
    box-shadow: 0 5px 15px rgba(143, 23, 38, 0.16);
}

.logout-btn:hover {
    background: #a92335;
    transform: translateY(-2px);
}

/* =========================================================
   HERO
========================================================= */

.hero {
    width: 86%;
    max-width: 1250px;
    min-height: 460px;
    margin: 38px auto 50px;
    border-radius: 26px;
    overflow: hidden;
    display: flex;
    align-items: center;
    position: relative;

    background:
        linear-gradient(
            90deg,
            rgba(77, 19, 27, 0.97) 0%,
            rgba(110, 29, 40, 0.85) 50%,
            rgba(110, 29, 40, 0.25) 100%
        ),
        url("https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=1800&q=85");

    background-size: cover;
    background-position: center;

    box-shadow:
        0 22px 55px rgba(60, 20, 20, 0.17);
}

/* Decorative circle */

.hero::after {
    content: "";
    position: absolute;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.10);
    right: -100px;
    bottom: -140px;
}

/* =========================================================
   HERO CONTENT
========================================================= */

.hero-content {
    width: 65%;
    padding: 65px;
    color: white;
    position: relative;
    z-index: 2;
}

.hero-small {
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 17px;
    color: #ffd8d8;
}

.hero h1 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 50px;
    line-height: 1.08;
    margin-bottom: 20px;
}

.hero p {
    font-size: 16px;
    line-height: 1.75;
    color: #f9eaea;
    max-width: 580px;
    margin-bottom: 23px;
}

.welcome {
    margin-bottom: 25px;
    font-size: 15px;
    color: #f9dfe2;
}

.welcome strong {
    color: white;
}

/* =========================================================
   PRIMARY BUTTON
========================================================= */

.primary-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 22px;
    background: white;
    color: #8f1726;
    border-radius: 9px;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    transition: 0.3s;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.primary-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.23);
}

/* =========================================================
   QUICK STATS
========================================================= */

.stats {
    width: 86%;
    max-width: 1250px;
    margin: -5px auto 55px;

    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.stat-card {
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(210,180,175,0.30);
    border-radius: 18px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 10px 28px rgba(50,25,20,0.045);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    background: #f8e3e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.stat-content strong {
    display: block;
    font-size: 24px;
    color: #8f1726;
    margin-bottom: 4px;
}

.stat-content span {
    color: #756966;
    font-size: 12px;
}

/* =========================================================
   INTRO
========================================================= */

.intro {
    width: 80%;
    max-width: 850px;
    margin: 60px auto 45px;
    text-align: center;
}

.intro h2 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 36px;
    color: #332827;
    margin-bottom: 14px;
}

.intro p {
    color: #756966;
    font-size: 15px;
    line-height: 1.8;
}

/* =========================================================
   ACTION SECTION
========================================================= */

.section {
    width: 86%;
    max-width: 1250px;
    margin: 50px auto 75px;
}

.section-heading {
    margin-bottom: 28px;
}

.section-heading h2 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 34px;
    color: #332827;
    margin-bottom: 8px;
}

.section-heading p {
    color: #766b68;
    font-size: 14px;
}

/* =========================================================
   ACTION GRID
========================================================= */

.action-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}

.action-card {
    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.92),
            rgba(255,250,248,0.72)
        );

    backdrop-filter: blur(14px);
    border: 1px solid rgba(210,180,175,0.35);
    border-radius: 18px;
    padding: 27px;
    min-height: 210px;
    text-decoration: none;
    color: inherit;
    transition: 0.35s;
    box-shadow: 0 10px 30px rgba(50,25,20,0.045);
    position: relative;
    overflow: hidden;
}

.action-card::before {
    content: "";
    position: absolute;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(165,29,45,0.05);
    top: -70px;
    right: -70px;
    transition: 0.5s;
}

.action-card:hover {
    transform: translateY(-7px);
    border-color: rgba(165,29,45,0.30);
    box-shadow: 0 20px 40px rgba(70,30,30,0.11);
}

.action-card:hover::before {
    transform: scale(2);
}

.card-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(
        145deg,
        #f9e9e7,
        #f3dddd
    );
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 20px;
    box-shadow: 0 7px 18px rgba(165,29,45,0.08);
    transition: 0.3s;
    position: relative;
    z-index: 2;
}

.action-card:hover .card-icon {
    transform: scale(1.08) rotate(-5deg);
}

.action-card h3 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 20px;
    color: #3a2927;
    margin-bottom: 9px;
    position: relative;
    z-index: 2;
}

.action-card p {
    color: #756966;
    font-size: 13px;
    line-height: 1.65;
    position: relative;
    z-index: 2;
}

/* =========================================================
   NOTIFICATION BADGE
========================================================= */

.notification-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 24px;
    height: 24px;

    padding: 0 7px;
    margin-left: 6px;

    border-radius: 20px;

    background: #a81531;
    color: white;

    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    font-weight: bold;

    vertical-align: middle;
}

/* =========================================================
   REQUEST INFORMATION
========================================================= */

.request-info {
    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.92),
            rgba(253,246,244,0.92)
        );

    border-top: 1px solid #eee1dd;
    border-bottom: 1px solid #eee1dd;

    padding: 75px 7%;
    position: relative;
    overflow: hidden;
}

.request-info::before {
    content: "";
    position: absolute;
    width: 350px;
    height: 350px;
    border-radius: 50%;
    border: 1px solid rgba(165,29,45,0.07);
    right: -120px;
    top: -120px;
}

.request-inner {
    max-width: 1150px;
    margin: auto;
    position: relative;
    z-index: 2;
}

.request-heading {
    text-align: center;
    margin-bottom: 45px;
}

.request-heading h2 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 37px;
    color: #332827;
    margin-bottom: 12px;
}

.request-heading p {
    max-width: 720px;
    margin: auto;
    color: #756966;
    font-size: 15px;
    line-height: 1.7;
}

.request-grid {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 22px;
}

.request-box {
    padding: 30px;
    background: rgba(255,255,255,0.72);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(215,190,185,0.35);
    border-radius: 17px;
    transition: 0.3s;
}

.request-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(70,30,30,0.07);
}

.request-box span {
    display: inline-flex;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f5dfdc;
    color: #8f1726;
    font-weight: bold;
    margin-bottom: 18px;
}

.request-box h3 {
    font-family: Georgia, "Times New Roman", serif;
    color: #3a2927;
    font-size: 20px;
    margin-bottom: 10px;
}

.request-box p {
    color: #766b68;
    font-size: 13px;
    line-height: 1.7;
}

/* =========================================================
   HOW IT WORKS
========================================================= */

.info-section {
    padding: 80px 7%;
}

.info-inner {
    max-width: 1150px;
    margin: auto;

    display: grid;
    grid-template-columns: 0.8fr 1.4fr;
    gap: 70px;
}

.info-title h2 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 38px;
    color: #332827;
    line-height: 1.2;
    margin-bottom: 18px;
}

.info-title p {
    color: #756966;
    font-size: 15px;
    line-height: 1.8;
}

.steps {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.step {
    display: flex;
    gap: 15px;
    transition: 0.3s;
}

.step:hover {
    transform: translateX(5px);
}

.step-number {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(
        145deg,
        #b3283b,
        #8f1726
    );
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    box-shadow: 0 5px 15px rgba(143,23,38,0.20);
}

.step h3 {
    font-size: 16px;
    margin-bottom: 7px;
    color: #3b2c29;
}

.step p {
    color: #776b68;
    font-size: 13px;
    line-height: 1.6;
}

/* =========================================================
   FINAL CTA
========================================================= */

.quote-section {
    width: 86%;
    max-width: 1100px;
    margin: 50px auto 80px;
    text-align: center;
    padding: 55px 25px;
    border-radius: 22px;

    background:
        linear-gradient(
            135deg,
            #4f1820,
            #7d2634
        );

    color: white;
    box-shadow: 0 18px 40px rgba(70,25,30,0.14);
    position: relative;
    overflow: hidden;
}

.quote-section::before {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.08);
    right: -60px;
    top: -90px;
}

.quote-section .drop {
    font-size: 32px;
    margin-bottom: 15px;
}

.quote-section h2 {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 35px;
    max-width: 780px;
    margin: auto;
    line-height: 1.3;
    position: relative;
    z-index: 2;
}

.quote-section p {
    color: #f2dfe1;
    margin-top: 15px;
    font-size: 14px;
    position: relative;
    z-index: 2;
}

/* =========================================================
   FOOTER
========================================================= */

footer {
    background:
        linear-gradient(
            135deg,
            #302426,
            #3d292d
        );

    color: #ddd0ce;
    padding: 40px 7%;
    text-align: center;
    font-size: 13px;
    position: relative;
}

footer strong {
    color: white;
}

/* =========================================================
   BOTTOM NAVIGATION
========================================================= */

.bottom-nav {
    display: none;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .action-grid {
        grid-template-columns: repeat(2,1fr);
    }

    .hero-content {
        width: 75%;
    }

}

@media (max-width: 950px) {

    .request-grid {
        grid-template-columns: 1fr;
    }

    .info-inner {
        grid-template-columns: 1fr;
        gap: 40px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 700px) {

    body {
        padding-bottom: 85px;
    }

    .navbar {
        height: 65px;
        padding: 0 5%;
    }

    .logo {
        font-size: 20px;
    }

    .logo-drop {
        width: 23px;
        height: 29px;
    }

    .logout-btn {
        padding: 8px 13px;
        font-size: 12px;
    }

    .hero {
        width: 92%;
        min-height: 520px;
        margin-top: 25px;
        border-radius: 20px;
    }

    .hero-content {
        width: 100%;
        padding: 40px 25px;
    }

    .hero h1 {
        font-size: 36px;
    }

    .hero p {
        font-size: 14px;
    }

    .intro {
        width: 90%;
        margin-top: 50px;
    }

    .intro h2 {
        font-size: 30px;
    }

    .section {
        width: 90%;
    }

    .section-heading h2 {
        font-size: 29px;
    }

    .action-grid {
        grid-template-columns: 1fr;
    }

    .request-info {
        padding: 60px 5%;
    }

    .request-heading h2 {
        font-size: 30px;
    }

    .steps {
        grid-template-columns: 1fr;
    }

    .info-section {
        padding: 65px 7%;
    }

    .info-title h2 {
        font-size: 31px;
    }

    .quote-section {
        width: 90%;
        margin-bottom: 45px;
    }

    .quote-section h2 {
        font-size: 29px;
    }

    footer {
        display: none;
    }

    /* Mobile bottom navigation */

    .bottom-nav {
        display: grid;
        position: fixed;
        bottom: 10px;
        left: 50%;
        transform: translateX(-50%);

        width: 92%;

        grid-template-columns: repeat(4,1fr);
        gap: 5px;

        padding: 9px;

        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(18px);

        border: 1px solid rgba(185,28,28,0.12);
        border-radius: 18px;

        box-shadow:
            0 15px 40px rgba(15,23,42,0.15);

        z-index: 200;
    }

    .bottom-nav a {
        text-decoration: none;
        color: #6b7280;

        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;

        gap: 3px;

        padding: 8px 4px;

        border-radius: 12px;

        font-size: 10px;
        font-weight: bold;

        transition: 0.2s;
    }

    .bottom-nav a:hover,
    .bottom-nav a.active {
        background: #fee2e2;
        color: #b91c1c;
    }

    .nav-icon {
        font-size: 18px;
    }

}

@media (max-width: 450px) {

    .hero h1 {
        font-size: 32px;
    }

    .hero-small {
        font-size: 10px;
    }

    .primary-btn {
        width: 100%;
        justify-content: center;
    }

    .stat-card {
        padding: 18px;
    }

    .stat-content strong {
        font-size: 21px;
    }

    .bottom-nav a {
        font-size: 9px;
    }

    .nav-icon {
        font-size: 16px;
    }

}

</style>

</head>

<body>

<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="logo">

        <span class="logo-drop"></span>

        BloodConnect

    </div>

    <a
        href="../auth/logout.php"
        class="logout-btn"
    >
        Logout
    </a>

</nav>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="hero-content">

        <div class="hero-small">
            BloodConnect • Blood Request Platform
        </div>

        <h1>
            Find the blood you need when it matters most.
        </h1>

        <p>
            Create a blood request and connect with available
            donors who may be able to help with the required
            blood group and location.
        </p>

        <div class="welcome">

            Welcome,
            <strong>
                <?php echo htmlspecialchars($user_name); ?>
            </strong>

        </div>

        <a
            href="create_request.php"
            class="primary-btn"
        >
            🩸 Create Blood Request
        </a>

    </div>

</section>


<!-- =========================================================
     QUICK STATS
========================================================= -->

<section class="stats">

    <div class="stat-card">

        <div class="stat-icon">
            🩸
        </div>

        <div class="stat-content">

            <strong>Blood</strong>

            <span>
                Request support when you need it
            </span>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            🔎
        </div>

        <div class="stat-content">

            <strong>Donors</strong>

            <span>
                Search for available donors
            </span>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">
            🔔
        </div>

        <div class="stat-content">

            <strong>
                <?php echo $unread_count; ?>
            </strong>

            <span>
                Unread notification<?php echo $unread_count == 1 ? "" : "s"; ?>
            </span>

        </div>

    </div>

</section>


<!-- =========================================================
     INTRO
========================================================= -->

<section class="intro">

    <h2>
        Need blood for someone?
    </h2>

    <p>
        BloodConnect makes it easier to organize a blood request,
        search for available donors and keep track of responses.
        Instead of searching through multiple places, you can manage
        your request from one platform.
    </p>

</section>


<!-- =========================================================
     ACTIONS
========================================================= -->

<section class="section">

    <div class="section-heading">

        <h2>
            Manage your blood request
        </h2>

        <p>
            Choose what you need to do.
        </p>

    </div>


    <div class="action-grid">


        <!-- FIND DONOR -->

        <a
            href="search_donors.php"
            class="action-card"
        >

            <div class="card-icon">
                🔎
            </div>

            <h3>
                Find a Donor
            </h3>

            <p>
                Search for available donors based on
                blood group and location.
            </p>

        </a>


        <!-- CREATE REQUEST -->

        <a
            href="create_request.php"
            class="action-card"
        >

            <div class="card-icon">
                +
            </div>

            <h3>
                Request Blood
            </h3>

            <p>
                Create a new request with the patient's
                and hospital's information.
            </p>

        </a>


        <!-- MY REQUESTS -->

        <a
            href="my_requests.php"
            class="action-card"
        >

            <div class="card-icon">
                ✓
            </div>

            <h3>
                My Requests
            </h3>

            <p>
                View your active requests and check
                whether a donor has responded.
            </p>

        </a>


        <!-- NOTIFICATIONS -->

        <a
            href="notifications.php"
            class="action-card"
        >

            <div class="card-icon">
                🔔
            </div>

            <h3>

                Notifications

                <?php if ($unread_count > 0): ?>

                    <span class="notification-number">
                        <?php echo $unread_count; ?>
                    </span>

                <?php endif; ?>

            </h3>

            <p>

                <?php if ($unread_count > 0): ?>

                    You have
                    <strong>
                        <?php echo $unread_count; ?>
                    </strong>
                    unread notification<?php echo $unread_count == 1 ? "" : "s"; ?>.

                <?php else: ?>

                    Check updates and responses
                    related to your requests.

                <?php endif; ?>

            </p>

        </a>


    </div>

</section>


<!-- =========================================================
     REQUEST INFORMATION
========================================================= -->

<section class="request-info">

    <div class="request-inner">

        <div class="request-heading">

            <h2>
                What makes a good blood request?
            </h2>

            <p>
                Providing complete and accurate information helps
                potential donors understand the requirement and
                makes it easier to coordinate with the hospital.
            </p>

        </div>


        <div class="request-grid">


            <div class="request-box">

                <span>
                    01
                </span>

                <h3>
                    Blood Requirement
                </h3>

                <p>
                    Select the required blood group and mention
                    the number of units needed for the patient.
                </p>

            </div>


            <div class="request-box">

                <span>
                    02
                </span>

                <h3>
                    Hospital Details
                </h3>

                <p>
                    Provide the hospital name and location so
                    the donor knows where the blood is required.
                </p>

            </div>


            <div class="request-box">

                <span>
                    03
                </span>

                <h3>
                    Patient Information
                </h3>

                <p>
                    Enter the patient's name and contact details
                    needed for coordinating the request.
                </p>

            </div>


        </div>

    </div>

</section>


<!-- =========================================================
     HOW REQUEST WORKS
========================================================= -->

<section class="info-section">

    <div class="info-inner">


        <div class="info-title">

            <h2>
                How your blood request works.
            </h2>

            <p>
                Once you create a request, BloodConnect helps you
                manage the connection with available donors.
            </p>

        </div>


        <div class="steps">


            <div class="step">

                <div class="step-number">
                    01
                </div>

                <div>

                    <h3>
                        Create your request
                    </h3>

                    <p>
                        Enter the blood group, units required,
                        hospital and patient information.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    02
                </div>

                <div>

                    <h3>
                        Search for donors
                    </h3>

                    <p>
                        Find available donors matching the
                        required blood group.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    03
                </div>

                <div>

                    <h3>
                        Receive a response
                    </h3>

                    <p>
                        When a donor is willing to help,
                        you receive a notification.
                    </p>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    04
                </div>

                <div>

                    <h3>
                        Track the request
                    </h3>

                    <p>
                        Monitor your request until the
                        required blood support is completed.
                    </p>

                </div>

            </div>


        </div>

    </div>

</section>


<!-- =========================================================
     FINAL CTA
========================================================= -->

<section class="quote-section">

    <div class="drop">
        🩸
    </div>

    <h2>
        When blood is needed, finding the right connection matters.
    </h2>

    <p>
        Create your request and start looking for available donors.
    </p>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <strong>
        BloodConnect
    </strong>

    &nbsp; | &nbsp;

    Blood Request Platform

    <br><br>

    Helping people connect with available blood donors.

</footer>


<!-- =========================================================
     MOBILE BOTTOM NAVIGATION
========================================================= -->

<nav class="bottom-nav">

    <a
        href="dashboard.php"
        class="active"
    >

        <span class="nav-icon">
            🏠
        </span>

        Home

    </a>


    <a href="search_donors.php">

        <span class="nav-icon">
            🔎
        </span>

        Donors

    </a>


    <a href="my_requests.php">

        <span class="nav-icon">
            🩸
        </span>

        Requests

    </a>


    <a href="notifications.php">

        <span class="nav-icon">
            🔔
        </span>

        Notifications

    </a>

</nav>

</body>

</html>