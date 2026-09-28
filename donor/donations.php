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
| Get donor profile ID
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
    FROM donor_profiles
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$donor = $result->fetch_assoc();

$stmt->close();

if (!$donor) {
    die("Donor profile not found.");
}

$donor_id = $donor["id"];


/*
|--------------------------------------------------------------------------
| Get donation responses
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        dr.id,
        dr.response_status,
        dr.responded_at,
        br.blood_group,
        br.units_required,
        br.hospital_name,
        br.hospital_location,
        br.required_date,
        br.required_time,
        br.urgency,
        br.patient_name,
        br.status AS request_status
    FROM donor_responses dr
    INNER JOIN blood_requests br
        ON dr.request_id = br.id
    WHERE dr.donor_id = ?
    ORDER BY dr.responded_at DESC
");

$stmt->bind_param("i", $donor_id);
$stmt->execute();

$responses = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Get completed donation records
|--------------------------------------------------------------------------
*/

$stmt2 = $conn->prepare("
    SELECT
        d.donation_date,
        d.hospital_name,
        d.units_donated,
        d.status,
        d.notes
    FROM donations d
    INNER JOIN donor_profiles dp
        ON d.donor_id = dp.id
    WHERE dp.user_id = ?
    ORDER BY d.donation_date DESC
");

$stmt2->bind_param("i", $user_id);
$stmt2->execute();

$donations = $stmt2->get_result();


/*
|--------------------------------------------------------------------------
| Summary counts
|--------------------------------------------------------------------------
*/

$response_count = $responses->num_rows;
$donation_count = $donations->num_rows;

$total_units = 0;

$donation_rows = [];

while ($donation = $donations->fetch_assoc()) {

    $donation_rows[] = $donation;

    $total_units += (int)$donation["units_donated"];
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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>BloodConnect - My Donations</title>


<style>

/* =========================================================
   GLOBAL
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

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    color: #351018;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(255,255,255,0.95),
            transparent 24%
        ),
        radial-gradient(
            circle at 90% 15%,
            rgba(255,220,225,0.75),
            transparent 25%
        ),
        radial-gradient(
            circle at 50% 90%,
            rgba(255,235,238,0.8),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #fff6f7,
            #f8dce1,
            #fffafa
        );

    overflow-x: hidden;
}


/* =========================================================
   BACKGROUND DECORATION
========================================================= */

body::before,
body::after {

    content: "";

    position: fixed;

    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: white;

    box-shadow:
        80px 120px white,
        180px 280px rgba(255,255,255,0.8),
        300px 170px rgba(255,255,255,0.7),
        440px 90px white,
        570px 300px rgba(255,255,255,0.8),
        700px 150px white,
        830px 350px rgba(255,255,255,0.8),
        960px 100px white,
        1090px 260px rgba(255,255,255,0.7),
        1200px 180px white;

    opacity: 0.55;

    pointer-events: none;

    z-index: 0;
}

body::before {
    top: 0;
    left: 0;
}

body::after {

    top: 430px;
    left: 100px;

    transform: scale(0.7);

    opacity: 0.35;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    position: relative;

    z-index: 10;

    width: 100%;

    padding: 20px 7%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    background: rgba(255,255,255,0.48);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    border-bottom:
        1px solid rgba(255,255,255,0.75);

    box-shadow:
        0 8px 25px rgba(100,20,30,0.05);
}

.logo {

    font-size: 25px;

    font-weight: 800;

    color: #8f1024;

    letter-spacing: -0.5px;
}

.logout {

    text-decoration: none;

    color: white;

    background:
        linear-gradient(
            135deg,
            #8f1024,
            #c51f3b
        );

    padding: 11px 23px;

    border-radius: 25px;

    font-weight: bold;

    box-shadow:
        0 8px 20px rgba(143,16,36,0.22);

    transition: 0.3s;
}

.logout:hover {

    transform: translateY(-3px);

    box-shadow:
        0 12px 25px rgba(143,16,36,0.32);
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    position: relative;

    z-index: 2;

    width: 88%;

    max-width: 1250px;

    margin: 38px auto 70px;
}


/* =========================================================
   HERO
========================================================= */

.hero {

    position: relative;

    padding: 50px 55px;

    border-radius: 30px;

    overflow: hidden;

    background:
        linear-gradient(
            105deg,
            rgba(70,3,16,0.97),
            rgba(126,10,32,0.88),
            rgba(126,10,32,0.45)
        ),
        url("https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=1600&q=85");

    background-size: cover;

    background-position: center;

    box-shadow:
        0 25px 55px rgba(100,10,25,0.22);
}

.hero::before {

    content: "";

    position: absolute;

    width: 300px;
    height: 300px;

    right: -90px;
    top: -100px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.08);

    filter: blur(4px);
}

.hero::after {

    content: "";

    position: absolute;

    width: 220px;
    height: 500px;

    right: 18%;
    top: -120px;

    background:
        rgba(255,255,255,0.07);

    transform:
        rotate(25deg);

    filter: blur(10px);
}

.hero-content {

    position: relative;

    z-index: 2;

    max-width: 760px;

    color: white;
}

.hero-tag {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 8px 15px;

    border:
        1px solid rgba(255,255,255,0.3);

    background:
        rgba(255,255,255,0.11);

    backdrop-filter: blur(10px);

    border-radius: 20px;

    font-size: 13px;

    margin-bottom: 18px;
}

.hero h1 {

    font-size: 42px;

    line-height: 1.15;

    margin-bottom: 15px;

    letter-spacing: -0.8px;
}

.hero p {

    font-size: 16px;

    line-height: 1.75;

    color:
        rgba(255,255,255,0.9);

    max-width: 700px;
}


/* =========================================================
   HERO BUTTONS
========================================================= */

.hero-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 12px;

    margin-top: 25px;
}

.dashboard-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 12px 21px;

    color: #8f1024;

    background: white;

    text-decoration: none;

    border-radius: 25px;

    font-weight: bold;

    transition: 0.3s;

    box-shadow:
        0 8px 20px rgba(0,0,0,0.08);
}

.dashboard-link:hover {

    transform: translateY(-3px);

    box-shadow:
        0 12px 25px rgba(0,0,0,0.12);
}


/* =========================================================
   SUMMARY CARDS
========================================================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-top: 25px;
}

.summary-card {

    position: relative;

    padding: 22px;

    border-radius: 22px;

    background:
        rgba(255,255,255,0.53);

    backdrop-filter: blur(16px);

    -webkit-backdrop-filter: blur(16px);

    border:
        1px solid rgba(255,255,255,0.75);

    box-shadow:
        0 14px 30px rgba(100,20,30,0.08);

    overflow: hidden;

    transition: 0.3s;
}

.summary-card:hover {

    transform: translateY(-4px);

    box-shadow:
        0 20px 38px rgba(100,20,30,0.13);
}

.summary-card::after {

    content: "";

    position: absolute;

    width: 90px;
    height: 90px;

    right: -25px;
    bottom: -30px;

    border-radius: 50%;

    background:
        rgba(173,24,48,0.07);
}

.summary-icon {

    width: 45px;
    height: 45px;

    display: flex;

    justify-content: center;
    align-items: center;

    border-radius: 14px;

    background:
        rgba(143,16,36,0.09);

    font-size: 21px;

    margin-bottom: 14px;
}

.summary-number {

    font-size: 28px;

    font-weight: 800;

    color: #741225;

    margin-bottom: 4px;
}

.summary-label {

    color: #76525a;

    font-size: 13px;

    font-weight: bold;
}


/* =========================================================
   SECTION
========================================================= */

.section {

    margin-top: 48px;
}

.section-heading {

    margin-bottom: 20px;
}

.section-heading h2 {

    font-size: 28px;

    color: #65101e;

    margin-bottom: 7px;

    letter-spacing: -0.3px;
}

.section-heading p {

    color: #76525a;

    line-height: 1.6;

    font-size: 14px;
}


/* =========================================================
   TABLE BOX
========================================================= */

.table-box {

    width: 100%;

    overflow-x: auto;

    border-radius: 24px;

    background:
        rgba(255,255,255,0.52);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    border:
        1px solid rgba(255,255,255,0.75);

    box-shadow:
        0 15px 35px rgba(100,20,30,0.09);
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}

th {

    padding: 17px 15px;

    text-align: left;

    background:
        linear-gradient(
            135deg,
            #6f081b,
            #92142b
        );

    color: white;

    font-size: 12px;

    letter-spacing: 0.3px;

    white-space: nowrap;
}

th:first-child {
    border-top-left-radius: 20px;
}

th:last-child {
    border-top-right-radius: 20px;
}

td {

    padding: 16px 15px;

    border-bottom:
        1px solid rgba(120,30,45,0.08);

    color: #5f4148;

    font-size: 13px;

    vertical-align: middle;
}

td strong {
    color: #4d2831;
}

tr:last-child td {
    border-bottom: none;
}

tbody tr {
    transition: 0.25s;
}

tbody tr:hover {
    background:
        rgba(255,255,255,0.58);
}


/* =========================================================
   BLOOD GROUP
========================================================= */

.blood-group {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 50px;

    padding: 7px 11px;

    border-radius: 12px;

    background:
        rgba(151,18,42,0.1);

    color: #8f1024;

    font-weight: bold;

    box-shadow:
        inset 0 0 0 1px rgba(151,18,42,0.04);
}


/* =========================================================
   URGENCY
========================================================= */

.urgency {

    display: inline-flex;

    align-items: center;

    padding: 6px 11px;

    border-radius: 15px;

    font-size: 11px;

    font-weight: bold;

    text-transform: capitalize;

    white-space: nowrap;
}

.urgency.normal {

    background:
        rgba(60,120,80,0.12);

    color: #386b4b;
}

.urgency.urgent {

    background:
        rgba(210,130,20,0.14);

    color: #9a5e0d;
}

.urgency.emergency {

    background:
        rgba(180,20,45,0.13);

    color: #a31430;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 7px 12px;

    border-radius: 16px;

    font-size: 11px;

    font-weight: bold;

    text-transform: capitalize;

    white-space: nowrap;
}

.status.interested {

    background:
        rgba(35,100,180,0.12);

    color: #275e99;
}

.status.accepted {

    background:
        rgba(50,130,80,0.13);

    color: #357149;
}

.status.completed {

    background:
        rgba(45,135,80,0.14);

    color: #2f7446;
}

.status.cancelled {

    background:
        rgba(170,30,50,0.12);

    color: #a51b35;
}


/* =========================================================
   DATE / TIME
========================================================= */

.date-cell {

    line-height: 1.5;

    color: #573840;
}

.date-cell small {

    color: #947078;

    font-size: 11px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    padding: 50px 25px;

    text-align: center;

    color: #76525a;

    line-height: 1.7;
}

.empty-icon {

    width: 68px;
    height: 68px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin: 0 auto 15px;

    border-radius: 22px;

    background:
        rgba(143,16,36,0.08);

    font-size: 31px;
}

.empty strong {

    display: block;

    color: #65101e;

    font-size: 17px;

    margin-bottom: 7px;
}

.empty p {

    max-width: 500px;

    margin: auto;

    font-size: 13px;
}


/* =========================================================
   DONATION CARDS
========================================================= */

.donation-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}

.donation-card {

    position: relative;

    padding: 25px;

    border-radius: 23px;

    background:
        rgba(255,255,255,0.5);

    backdrop-filter: blur(16px);

    -webkit-backdrop-filter: blur(16px);

    border:
        1px solid rgba(255,255,255,0.75);

    box-shadow:
        0 15px 35px rgba(100,20,30,0.09);

    overflow: hidden;

    transition: 0.3s;
}

.donation-card:hover {

    transform: translateY(-6px);

    box-shadow:
        0 22px 42px rgba(100,20,30,0.16);
}

.donation-card::before {

    content: "";

    position: absolute;

    width: 4px;

    height: 70px;

    left: 0;

    top: 28px;

    border-radius:
        0 5px 5px 0;

    background:
        linear-gradient(
            #8f1024,
            #c51f3b
        );
}

.donation-card::after {

    content: "";

    position: absolute;

    width: 100px;
    height: 100px;

    right: -30px;
    bottom: -30px;

    border-radius: 50%;

    background:
        rgba(173,24,48,0.08);
}

.donation-icon {

    width: 54px;
    height: 54px;

    display: flex;

    justify-content: center;
    align-items: center;

    border-radius: 17px;

    background:
        rgba(151,18,42,0.1);

    font-size: 25px;

    margin-bottom: 17px;
}

.donation-card h3 {

    color: #741225;

    font-size: 18px;

    margin-bottom: 18px;
}

.info {

    margin-bottom: 13px;

    position: relative;

    z-index: 2;
}

.info-label {

    display: block;

    font-size: 10px;

    color: #947078;

    text-transform: uppercase;

    letter-spacing: 0.7px;

    margin-bottom: 5px;
}

.info-value {

    color: #4f3038;

    font-size: 14px;

    font-weight: bold;

    line-height: 1.5;
}

.units {

    display: inline-flex;

    align-items: center;

    margin-top: 4px;

    padding: 7px 11px;

    border-radius: 12px;

    background:
        rgba(143,16,36,0.1);

    color: #8f1024;

    font-size: 13px;

    font-weight: bold;
}

.notes {

    position: relative;

    z-index: 2;

    margin-top: 17px;

    padding-top: 14px;

    border-top:
        1px solid rgba(120,30,45,0.08);

    color: #76525a;

    font-size: 13px;

    line-height: 1.6;
}

.notes strong {
    color: #65101e;
}


/* =========================================================
   BOTTOM NAVIGATION
========================================================= */

.bottom-nav {

    display: flex;

    justify-content: center;

    flex-wrap: wrap;

    gap: 12px;

    margin-top: 45px;
}

.bottom-nav a {

    text-decoration: none;

    padding: 12px 19px;

    border-radius: 22px;

    background:
        rgba(255,255,255,0.58);

    border:
        1px solid rgba(255,255,255,0.72);

    color: #741225;

    font-size: 13px;

    font-weight: bold;

    backdrop-filter: blur(12px);

    transition: 0.3s;
}

.bottom-nav a:hover {

    transform: translateY(-3px);

    background: white;

    box-shadow:
        0 8px 20px rgba(100,20,30,0.08);
}


/* =========================================================
   FOOTER
========================================================= */

footer {

    position: relative;

    z-index: 2;

    text-align: center;

    padding: 25px;

    color: #79515a;

    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .donation-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .summary-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .hero h1 {
        font-size: 37px;
    }
}


@media (max-width: 700px) {

    .navbar {
        padding: 18px 5%;
    }

    .logo {
        font-size: 21px;
    }

    .logout {
        padding: 9px 17px;
        font-size: 13px;
    }

    .container {
        width: 92%;
        margin-top: 25px;
    }

    .hero {
        padding: 35px 25px;
        border-radius: 25px;
    }

    .hero h1 {
        font-size: 30px;
    }

    .hero p {
        font-size: 14px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .section {
        margin-top: 38px;
    }

    .section-heading h2 {
        font-size: 24px;
    }

    .donation-grid {
        grid-template-columns: 1fr;
    }

    .bottom-nav {
        gap: 8px;
    }

    .bottom-nav a {
        padding: 10px 14px;
        font-size: 12px;
    }
}


@media (max-width: 450px) {

    .hero-actions {
        flex-direction: column;
    }

    .dashboard-link {
        justify-content: center;
        width: 100%;
    }

    .summary-card {
        padding: 18px;
    }

    .donation-card {
        padding: 22px;
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
        🩸 BloodConnect
    </div>

    <a class="logout" href="../auth/logout.php">
        Logout
    </a>

</nav>


<main class="container">


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="hero-content">

        <span class="hero-tag">
            ❤️ Your Donation Journey
        </span>

        <h1>
            My Blood Donation Activity
        </h1>

        <p>
            Track the blood requests you have responded to
            and the donations you have completed through
            BloodConnect. Every contribution can help connect
            a donor with someone who needs blood.
        </p>

        <div class="hero-actions">

            <a class="dashboard-link" href="dashboard.php">
                ← Donor Dashboard
            </a>

        </div>

    </div>

</section>


<!-- =========================================================
     SUMMARY
========================================================= -->

<div class="summary-grid">


    <div class="summary-card">

        <div class="summary-icon">
            🩸
        </div>

        <div class="summary-number">
            <?php echo $response_count; ?>
        </div>

        <div class="summary-label">
            Requests Responded
        </div>

    </div>


    <div class="summary-card">

        <div class="summary-icon">
            ❤️
        </div>

        <div class="summary-number">
            <?php echo $donation_count; ?>
        </div>

        <div class="summary-label">
            Completed Donations
        </div>

    </div>


    <div class="summary-card">

        <div class="summary-icon">
            🩸
        </div>

        <div class="summary-number">
            <?php echo $total_units; ?>
        </div>

        <div class="summary-label">
            Total Units Donated
        </div>

    </div>


</div>


<!-- =========================================================
     RESPONDED REQUESTS
========================================================= -->

<section class="section">

    <div class="section-heading">

        <h2>
            🩸 Blood Requests I Responded To
        </h2>

        <p>
            Requests where you have indicated that you are willing to donate.
        </p>

    </div>


    <?php if ($responses->num_rows > 0): ?>

        <div class="table-box">

            <table>

                <thead>

                    <tr>

                        <th>Patient</th>
                        <th>Blood Group</th>
                        <th>Units</th>
                        <th>Hospital</th>
                        <th>Location</th>
                        <th>Required Date</th>
                        <th>Urgency</th>
                        <th>My Response</th>
                        <th>Responded On</th>

                    </tr>

                </thead>


                <tbody>

                <?php while ($response = $responses->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $response["patient_name"]
                                );
                                ?>
                            </strong>
                        </td>


                        <td>

                            <span class="blood-group">

                                <?php
                                echo htmlspecialchars(
                                    $response["blood_group"]
                                );
                                ?>

                            </span>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $response["units_required"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $response["hospital_name"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $response["hospital_location"]
                            );
                            ?>

                        </td>


                        <td>

                            <div class="date-cell">

                                <?php
                                echo htmlspecialchars(
                                    $response["required_date"]
                                );
                                ?>

                                <?php if (!empty($response["required_time"])): ?>

                                    <br>

                                    <small>
                                        🕒
                                        <?php
                                        echo htmlspecialchars(
                                            $response["required_time"]
                                        );
                                        ?>
                                    </small>

                                <?php endif; ?>

                            </div>

                        </td>


                        <td>

                            <span
                                class="urgency <?php
                                echo strtolower(
                                    htmlspecialchars(
                                        $response["urgency"]
                                    )
                                );
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $response["urgency"]
                                );
                                ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="status <?php
                                echo strtolower(
                                    htmlspecialchars(
                                        $response["response_status"]
                                    )
                                );
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $response["response_status"]
                                );
                                ?>

                            </span>

                        </td>


                        <td>

                            <div class="date-cell">

                                <?php
                                echo htmlspecialchars(
                                    $response["responded_at"]
                                );
                                ?>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>


    <?php else: ?>

        <div class="table-box">

            <div class="empty">

                <div class="empty-icon">
                    🩸
                </div>

                <strong>
                    No blood requests yet
                </strong>

                <p>
                    You have not responded to any blood requests.
                    When a matching request appears, you can respond
                    from the Blood Requests page.
                </p>

            </div>

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     COMPLETED DONATIONS
========================================================= -->

<section class="section">

    <div class="section-heading">

        <h2>
            ❤️ Completed Donation Records
        </h2>

        <p>
            Your completed blood donations recorded through BloodConnect.
        </p>

    </div>


    <?php if ($donation_count > 0): ?>

        <div class="donation-grid">

            <?php foreach ($donation_rows as $donation): ?>

                <div class="donation-card">

                    <div class="donation-icon">
                        ❤️
                    </div>


                    <h3>
                        Donation Completed
                    </h3>


                    <div class="info">

                        <span class="info-label">
                            Donation Date
                        </span>

                        <span class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $donation["donation_date"]
                            );
                            ?>

                        </span>

                    </div>


                    <div class="info">

                        <span class="info-label">
                            Hospital
                        </span>

                        <span class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $donation["hospital_name"]
                            );
                            ?>

                        </span>

                    </div>


                    <div class="info">

                        <span class="info-label">
                            Units Donated
                        </span>

                        <span class="units">

                            🩸

                            <?php
                            echo htmlspecialchars(
                                $donation["units_donated"]
                            );
                            ?>

                            unit(s)

                        </span>

                    </div>


                    <div class="info">

                        <span class="info-label">
                            Status
                        </span>

                        <span
                            class="status <?php
                            echo strtolower(
                                htmlspecialchars(
                                    $donation["status"]
                                )
                            );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $donation["status"]
                            );
                            ?>

                        </span>

                    </div>


                    <?php if (!empty($donation["notes"])): ?>

                        <div class="notes">

                            <strong>
                                Notes
                            </strong>

                            <br>

                            <?php
                            echo htmlspecialchars(
                                $donation["notes"]
                            );
                            ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="table-box">

            <div class="empty">

                <div class="empty-icon">
                    ❤️
                </div>

                <strong>
                    No completed donations yet
                </strong>

                <p>
                    Your completed donation records will appear here
                    after a donation is confirmed.
                </p>

            </div>

        </div>

    <?php endif; ?>

</section>


<!-- =========================================================
     BOTTOM NAVIGATION
========================================================= -->

<div class="bottom-nav">

    <a href="dashboard.php">
        🏠 Dashboard
    </a>

    <a href="requests.php">
        🩸 Blood Requests
    </a>

    <a href="availability.php">
        ❤️ Availability
    </a>

    <a href="profile.php">
        👤 My Profile
    </a>

</div>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    BloodConnect | Blood Donation & Request Platform

</footer>


</body>

</html>