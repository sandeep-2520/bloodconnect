
<?php

session_start();
require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: ../auth/login.php");
    exit;
}


if ($_SESSION["user_role"] != "admin") {

    header("Location: ../dashboard.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Default Counts
|--------------------------------------------------------------------------
*/

$users_count = 0;
$donors_count = 0;
$requesters_count = 0;
$requests_count = 0;
$completed_count = 0;


/*
|--------------------------------------------------------------------------
| Total Users
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM users
");

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$users_count = $data["total"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Total Donors
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'donor'
");

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$donors_count = $data["total"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Total Requesters
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'requester'
");

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$requesters_count = $data["total"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Total Blood Requests
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM blood_requests
");

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$requests_count = $data["total"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Completed Donations
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM donations
    WHERE status = 'completed'
");

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$completed_count = $data["total"];

$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    BloodConnect | Admin Dashboard
</title>


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


/* =========================================================
   BODY
========================================================= */

body {

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    color: #ffffff;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(255, 45, 75, 0.16),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 20%,
            rgba(180, 0, 50, 0.18),
            transparent 32%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(120, 0, 35, 0.15),
            transparent 35%
        ),

        linear-gradient(
            135deg,
            #100207,
            #2b060f 45%,
            #0c0205
        );

    overflow-x: hidden;

}


/* =========================================================
   BACKGROUND GLOW
========================================================= */

.glow {

    position: fixed;

    width: 430px;
    height: 430px;

    border-radius: 50%;

    filter: blur(120px);

    pointer-events: none;

    z-index: -2;

}


.glow-one {

    left: -170px;
    top: 220px;

    background:
        rgba(255, 25, 65, 0.12);

}


.glow-two {

    right: -180px;
    bottom: -120px;

    background:
        rgba(170, 0, 50, 0.15);

}


/* =========================================================
   FLOATING PARTICLES
========================================================= */

.particle {

    position: fixed;

    width: 4px;
    height: 4px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.75);

    box-shadow:
        0 0 8px rgba(255,255,255,0.8),
        0 0 18px rgba(255,60,90,0.5);

    animation:
        floating 4s infinite ease-in-out;

    z-index: -1;

}


.particle.one {

    top: 15%;
    left: 12%;

}


.particle.two {

    top: 28%;
    right: 15%;

    animation-delay: 1s;

}


.particle.three {

    bottom: 20%;
    left: 8%;

    animation-delay: 2s;

}


.particle.four {

    bottom: 13%;
    right: 10%;

    animation-delay: 3s;

}


@keyframes floating {

    0%,
    100% {

        opacity: .2;

        transform:
            translateY(0)
            scale(.7);

    }

    50% {

        opacity: 1;

        transform:
            translateY(-14px)
            scale(1.2);

    }

}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    position: sticky;

    top: 0;

    z-index: 100;

    width: 100%;

    padding:
        18px 7%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background:
        rgba(17, 2, 7, 0.78);

    border-bottom:
        1px solid rgba(255,255,255,0.09);

    backdrop-filter:
        blur(20px);

}


.logo {

    display: flex;

    align-items: center;

    gap: 8px;

    font-size: 24px;

    font-weight: 800;

}


.logo span {

    color:
        #ff5069;

}


.admin-badge {

    padding:
        6px 12px;

    border-radius:
        20px;

    margin-left:
        8px;

    color:
        #ff9baa;

    background:
        rgba(255,50,80,.1);

    border:
        1px solid
        rgba(255,80,100,.2);

    font-size:
        10px;

    text-transform:
        uppercase;

    letter-spacing:
        .7px;

}


.logout {

    text-decoration:
        none;

    color:
        #ffffff;

    padding:
        10px 19px;

    border-radius:
        25px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.12);

    transition:
        .3s;

}


.logout:hover {

    background:
        rgba(255,50,80,.15);

    border-color:
        rgba(255,90,110,.4);

    transform:
        translateY(-2px);

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    width: 92%;

    max-width: 1200px;

    margin:
        auto;

    padding:
        55px 0 70px;

}


/* =========================================================
   HERO
========================================================= */

.hero {

    margin-bottom:
        40px;

}


.hero-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        8px 16px;

    border-radius:
        30px;

    margin-bottom:
        17px;

    color:
        #ff9aaa;

    background:
        rgba(255,50,80,.09);

    border:
        1px solid
        rgba(255,90,110,.22);

    font-size:
        12px;

    font-weight:
        700;

}


.hero h1 {

    font-size:
        clamp(35px,5vw,52px);

    line-height:
        1.08;

    margin-bottom:
        13px;

    letter-spacing:
        -1px;

}


.hero h1 span {

    color:
        #ff5068;

}


.hero p {

    color:
        #bba9ae;

    font-size:
        14px;

    line-height:
        1.7;

    max-width:
        700px;

}


/* =========================================================
   STATISTICS GRID
========================================================= */

.stats-grid {

    display:
        grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap:
        16px;

    margin-bottom:
        38px;

}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card {

    position:
        relative;

    overflow:
        hidden;

    padding:
        23px;

    min-height:
        155px;

    border-radius:
        22px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.075),
            rgba(255,255,255,.025)
        );

    border:
        1px solid
        rgba(255,255,255,.11);

    backdrop-filter:
        blur(18px);

    box-shadow:
        0 20px 45px
        rgba(0,0,0,.25);

    transition:
        .3s;

}


.stat-card::after {

    content:
        "";

    position:
        absolute;

    width:
        110px;

    height:
        110px;

    right:
        -45px;

    bottom:
        -45px;

    border-radius:
        50%;

    background:
        rgba(255,50,80,.08);

}


.stat-card:hover {

    transform:
        translateY(-6px);

    border-color:
        rgba(255,90,110,.28);

    box-shadow:
        0 25px 55px
        rgba(0,0,0,.34);

}


.stat-icon {

    width:
        43px;

    height:
        43px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        13px;

    margin-bottom:
        18px;

    background:
        rgba(255,50,80,.1);

    border:
        1px solid
        rgba(255,80,100,.18);

    font-size:
        20px;

}


.stat-label {

    color:
        #918086;

    font-size:
        11px;

    text-transform:
        uppercase;

    letter-spacing:
        .7px;

    margin-bottom:
        6px;

}


.stat-number {

    font-size:
        30px;

    font-weight:
        800;

}


.stat-description {

    margin-top:
        5px;

    color:
        #75666b;

    font-size:
        10px;

}


/* Different icon backgrounds */

.stat-card.donors .stat-icon {

    background:
        rgba(255,80,100,.11);

}


.stat-card.requesters .stat-icon {

    background:
        rgba(160,80,255,.1);

}


.stat-card.requests .stat-icon {

    background:
        rgba(255,160,50,.1);

}


.stat-card.completed .stat-icon {

    background:
        rgba(50,200,130,.1);

}


/* =========================================================
   SECTION HEADER
========================================================= */

.section-heading {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        18px;

}


.section-heading h2 {

    font-size:
        21px;

}


.section-heading p {

    color:
        #847378;

    font-size:
        12px;

}


/* =========================================================
   ADMIN OPTIONS
========================================================= */

.options-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        17px;

}


.option-card {

    position:
        relative;

    overflow:
        hidden;

    display:
        flex;

    align-items:
        center;

    gap:
        16px;

    padding:
        23px;

    text-decoration:
        none;

    color:
        white;

    border-radius:
        21px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.065),
            rgba(255,255,255,.025)
        );

    border:
        1px solid
        rgba(255,255,255,.1);

    backdrop-filter:
        blur(18px);

    transition:
        .3s;

}


.option-card:hover {

    transform:
        translateY(-5px);

    border-color:
        rgba(255,80,100,.28);

    background:
        linear-gradient(
            145deg,
            rgba(255,50,80,.09),
            rgba(255,255,255,.035)
        );

}


.option-icon {

    min-width:
        52px;

    height:
        52px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        16px;

    background:
        rgba(255,50,80,.1);

    border:
        1px solid
        rgba(255,80,100,.18);

    font-size:
        23px;

}


.option-content h3 {

    margin-bottom:
        5px;

    font-size:
        15px;

}


.option-content p {

    color:
        #88767c;

    font-size:
        11px;

    line-height:
        1.5;

}


.option-arrow {

    margin-left:
        auto;

    color:
        #806f75;

    font-size:
        18px;

    transition:
        .3s;

}


.option-card:hover
.option-arrow {

    color:
        #ff6479;

    transform:
        translateX(4px);

}


/* =========================================================
   SYSTEM STATUS
========================================================= */

.system-card {

    margin-top:
        25px;

    padding:
        22px 25px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    border-radius:
        20px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.08);

}


.system-left {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

}


.status-dot {

    width:
        10px;

    height:
        10px;

    border-radius:
        50%;

    background:
        #35d18a;

    box-shadow:
        0 0 12px
        rgba(53,209,138,.7);

}


.system-left strong {

    font-size:
        13px;

}


.system-left span {

    display:
        block;

    margin-top:
        3px;

    color:
        #75666b;

    font-size:
        10px;

}


.system-time {

    color:
        #7f7075;

    font-size:
        11px;

}


/* =========================================================
   FOOTER
========================================================= */

footer {

    text-align:
        center;

    padding:
        25px;

    color:
        #6e5e63;

    font-size:
        12px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .stats-grid {

        grid-template-columns:
            repeat(3,1fr);

    }

}


@media (max-width: 800px) {

    .options-grid {

        grid-template-columns:
            1fr;

    }

    .stats-grid {

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media (max-width: 600px) {

    .navbar {

        padding:
            16px 5%;

    }

    .logo {

        font-size:
            20px;

    }

    .admin-badge {

        display:
            none;

    }

    .logout {

        padding:
            8px 15px;

        font-size:
            12px;

    }

    .container {

        width:
            92%;

        padding-top:
            40px;

    }

    .hero {

        margin-bottom:
            30px;

    }

    .hero h1 {

        font-size:
            34px;

    }

    .hero p {

        font-size:
            13px;

    }

    .stats-grid {

        grid-template-columns:
            1fr;

    }

    .stat-card {

        min-height:
            auto;

    }

    .system-card {

        align-items:
            flex-start;

        flex-direction:
            column;

        gap:
            12px;

    }

}

</style>

</head>


<body>


<!-- Background -->

<div class="glow glow-one"></div>
<div class="glow glow-two"></div>

<div class="particle one"></div>
<div class="particle two"></div>
<div class="particle three"></div>
<div class="particle four"></div>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">


    <div class="logo">

        🩸 Blood<span>Connect</span>

        <span class="admin-badge">
            Admin
        </span>

    </div>


    <a
        href="../auth/logout.php"
        class="logout"
    >

        Logout

    </a>


</nav>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


    <!-- HERO -->

    <section class="hero">

        <div class="hero-badge">

            🛡️ Administration Center

        </div>


        <h1>

            Admin <span>Dashboard</span>

        </h1>


        <p>

            Welcome back,
            <strong>
                <?php
                echo htmlspecialchars(
                    $_SESSION["user_name"]
                );
                ?>
            </strong>.
            Monitor BloodConnect users, requests and
            completed donations from one place.

        </p>

    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section>


        <div class="section-heading">

            <h2>
                System Overview
            </h2>

            <p>
                Live platform statistics
            </p>

        </div>


        <div class="stats-grid">


            <!-- USERS -->

            <div class="stat-card">

                <div class="stat-icon">
                    👥
                </div>

                <div class="stat-label">
                    Total Users
                </div>

                <div class="stat-number">
                    <?php
                    echo $users_count;
                    ?>
                </div>

                <div class="stat-description">
                    Registered accounts
                </div>

            </div>


            <!-- DONORS -->

            <div class="stat-card donors">

                <div class="stat-icon">
                    🩸
                </div>

                <div class="stat-label">
                    Donors
                </div>

                <div class="stat-number">
                    <?php
                    echo $donors_count;
                    ?>
                </div>

                <div class="stat-description">
                    Registered donors
                </div>

            </div>


            <!-- REQUESTERS -->

            <div class="stat-card requesters">

                <div class="stat-icon">
                    🙋
                </div>

                <div class="stat-label">
                    Requesters
                </div>

                <div class="stat-number">
                    <?php
                    echo $requesters_count;
                    ?>
                </div>

                <div class="stat-description">
                    People requesting blood
                </div>

            </div>


            <!-- REQUESTS -->

            <div class="stat-card requests">

                <div class="stat-icon">
                    📋
                </div>

                <div class="stat-label">
                    Blood Requests
                </div>

                <div class="stat-number">
                    <?php
                    echo $requests_count;
                    ?>
                </div>

                <div class="stat-description">
                    Total requests created
                </div>

            </div>


            <!-- COMPLETED -->

            <div class="stat-card completed">

                <div class="stat-icon">
                    ✅
                </div>

                <div class="stat-label">
                    Completed
                </div>

                <div class="stat-number">
                    <?php
                    echo $completed_count;
                    ?>
                </div>

                <div class="stat-description">
                    Completed donations
                </div>

            </div>


        </div>

    </section>


    <!-- =====================================================
         ADMIN OPTIONS
    ====================================================== -->

    <section>


        <div class="section-heading">

            <h2>
                Admin Controls
            </h2>

            <p>
                Manage the platform
            </p>

        </div>


        <div class="options-grid">


            <!-- USERS -->

            <a
                href="users.php"
                class="option-card"
            >

                <div class="option-icon">
                    👥
                </div>


                <div class="option-content">

                    <h3>
                        Manage Users
                    </h3>

                    <p>
                        View and manage registered
                        donors and requesters.
                    </p>

                </div>


                <div class="option-arrow">
                    →
                </div>

            </a>


            <!-- REQUESTS -->

            <a
                href="requests.php"
                class="option-card"
            >

                <div class="option-icon">
                    📋
                </div>


                <div class="option-content">

                    <h3>
                        Blood Requests
                    </h3>

                    <p>
                        Monitor and manage blood
                        requests across the platform.
                    </p>

                </div>


                <div class="option-arrow">
                    →
                </div>

            </a>


            <!-- DONATIONS -->

            <a
                href="donations.php"
                class="option-card"
            >

                <div class="option-icon">
                    🩸
                </div>


                <div class="option-content">

                    <h3>
                        View Donations
                    </h3>

                    <p>
                        Track donor responses and
                        completed donations.
                    </p>

                </div>


                <div class="option-arrow">
                    →
                </div>

            </a>


        </div>


    </section>


    <!-- =====================================================
         SYSTEM STATUS
    ====================================================== -->

    <div class="system-card">


        <div class="system-left">

            <div class="status-dot"></div>


            <div>

                <strong>
                    BloodConnect System
                </strong>

                <span>
                    Dashboard and database connection active
                </span>

            </div>

        </div>


        <div class="system-time">

            🕐 Admin Panel

        </div>


    </div>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    BloodConnect
    |
    Admin Control Center

</footer>


</body>

</html>
