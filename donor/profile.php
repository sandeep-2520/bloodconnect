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
| Get Donor Profile
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        users.name,
        users.email,
        users.phone,
        donor_profiles.blood_group,
        donor_profiles.date_of_birth,
        donor_profiles.gender,
        donor_profiles.city,
        donor_profiles.area,
        donor_profiles.pincode,
        donor_profiles.last_donation_date,
        donor_profiles.availability
     FROM users
     INNER JOIN donor_profiles
        ON users.id = donor_profiles.user_id
     WHERE users.id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("Donor profile not found.");
}

$donor = $result->fetch_assoc();

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

    <title>BloodConnect - My Profile</title>


    <style>

        /* =========================================================
           Reset
        ========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* =========================================================
           Body
        ========================================================= */

        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            min-height: 100vh;

            color: #351018;

            background:
                radial-gradient(
                    circle at 15% 20%,
                    rgba(255,255,255,0.85),
                    transparent 25%
                ),

                radial-gradient(
                    circle at 85% 15%,
                    rgba(255,220,225,0.75),
                    transparent 25%
                ),

                linear-gradient(
                    135deg,
                    #fff5f6,
                    #f8d9de,
                    #fffafa
                );

            overflow-x: hidden;
        }


        /* =========================================================
           Background Glitter
        ========================================================= */

        body::before,
        body::after {

            content: "";

            position: fixed;

            width: 5px;
            height: 5px;

            background: white;

            border-radius: 50%;

            box-shadow:
                70px 120px white,
                160px 300px rgba(255,255,255,0.8),
                280px 180px rgba(255,255,255,0.7),
                420px 90px white,
                560px 270px rgba(255,255,255,0.8),
                710px 150px white,
                850px 340px rgba(255,255,255,0.8),
                950px 100px white,
                1080px 260px rgba(255,255,255,0.7),
                1180px 160px white,
                1250px 390px rgba(255,255,255,0.8);

            opacity: 0.65;

            pointer-events: none;

            z-index: 0;
        }


        body::before {
            top: 0;
            left: 0;
        }


        body::after {

            top: 400px;
            left: 80px;

            transform: scale(0.7);

            opacity: 0.45;
        }


        /* =========================================================
           Navbar
        ========================================================= */

        .navbar {

            position: relative;

            z-index: 5;

            width: 100%;

            padding: 22px 7%;

            display: flex;

            justify-content: space-between;

            align-items: center;

            background:
                rgba(255,255,255,0.42);

            backdrop-filter: blur(18px);

            -webkit-backdrop-filter: blur(18px);

            border-bottom:
                1px solid rgba(255,255,255,0.7);
        }


        .logo {

            font-size: 25px;

            font-weight: bold;

            color: #8f1024;

            letter-spacing: 0.5px;
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

            padding: 11px 22px;

            border-radius: 25px;

            font-weight: bold;

            box-shadow:
                0 8px 20px rgba(143,16,36,0.25);

            transition: 0.3s;
        }


        .logout:hover {

            transform: translateY(-3px);

            box-shadow:
                0 12px 25px rgba(143,16,36,0.35);
        }


        /* =========================================================
           Main Container
        ========================================================= */

        .container {

            position: relative;

            z-index: 2;

            width: 90%;

            max-width: 1050px;

            margin: 45px auto 70px;
        }


        /* =========================================================
           Page Header
        ========================================================= */

        .page-header {

            text-align: center;

            margin-bottom: 35px;
        }


        .page-tag {

            display: inline-block;

            padding: 8px 16px;

            border-radius: 20px;

            background:
                rgba(143,16,36,0.08);

            color: #8f1024;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 14px;
        }


        .page-header h1 {

            font-size: 38px;

            color: #65101e;

            margin-bottom: 10px;
        }


        .page-header p {

            color: #76525a;

            line-height: 1.6;

            max-width: 650px;

            margin: auto;
        }


        /* =========================================================
           Profile Hero
        ========================================================= */

        .profile-hero {

            display: flex;

            align-items: center;

            gap: 25px;

            padding: 30px;

            margin-bottom: 25px;

            border-radius: 28px;

            background:
                linear-gradient(
                    135deg,
                    rgba(111,8,27,0.96),
                    rgba(151,18,42,0.9)
                );

            color: white;

            box-shadow:
                0 20px 40px rgba(100,10,25,0.18);
        }


        .profile-avatar {

            width: 85px;
            height: 85px;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.16);

            border:
                1px solid rgba(255,255,255,0.3);

            font-size: 42px;

            backdrop-filter: blur(10px);
        }


        .profile-intro h2 {

            font-size: 28px;

            margin-bottom: 7px;
        }


        .profile-intro p {

            color:
                rgba(255,255,255,0.82);

            font-size: 14px;

            line-height: 1.5;
        }


        /* =========================================================
           Profile Grid
        ========================================================= */

        .profile-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }


        .profile-card {

            padding: 28px;

            border-radius: 24px;

            background:
                rgba(255,255,255,0.52);

            backdrop-filter: blur(18px);

            -webkit-backdrop-filter: blur(18px);

            border:
                1px solid rgba(255,255,255,0.8);

            box-shadow:
                0 15px 35px rgba(100,20,30,0.09);
        }


        .profile-card.full {

            grid-column: 1 / -1;
        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 22px;

            padding-bottom: 15px;

            border-bottom:
                1px solid rgba(120,70,80,0.12);
        }


        .card-title-icon {

            width: 40px;
            height: 40px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background:
                rgba(143,16,36,0.09);

            font-size: 20px;
        }


        .card-title h3 {

            color: #741225;

            font-size: 19px;
        }


        /* =========================================================
           Profile Information
        ========================================================= */

        .info-list {

            display: flex;

            flex-direction: column;

            gap: 16px;
        }


        .info-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            padding: 12px 0;

            border-bottom:
                1px solid rgba(120,70,80,0.08);
        }


        .info-row:last-child {

            border-bottom: none;

            padding-bottom: 0;
        }


        .info-label {

            color: #76525a;

            font-size: 13px;

            font-weight: bold;
        }


        .info-value {

            color: #351018;

            font-size: 14px;

            font-weight: 600;

            text-align: right;

            word-break: break-word;
        }


        /* =========================================================
           Blood Group
        ========================================================= */

        .blood-group {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 58px;

            padding: 8px 13px;

            border-radius: 15px;

            background:
                linear-gradient(
                    135deg,
                    #8f1024,
                    #c51f3b
                );

            color: white;

            font-size: 16px;

            font-weight: bold;

            box-shadow:
                0 6px 15px rgba(143,16,36,0.2);
        }


        /* =========================================================
           Availability
        ========================================================= */

        .availability {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 8px 13px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;
        }


        .availability.available {

            color: #15803d;

            background:
                rgba(34,197,94,0.1);
        }


        .availability.unavailable {

            color: #b42336;

            background:
                rgba(220,38,38,0.09);
        }


        .status-dot {

            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: currentColor;
        }


        /* =========================================================
           Location Card
        ========================================================= */

        .location-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }


        .location-item {

            padding: 18px;

            text-align: center;

            border-radius: 18px;

            background:
                rgba(255,255,255,0.45);

            border:
                1px solid rgba(255,255,255,0.7);
        }


        .location-icon {

            font-size: 24px;

            margin-bottom: 8px;
        }


        .location-item span {

            display: block;

            color: #76525a;

            font-size: 12px;

            margin-bottom: 5px;
        }


        .location-item strong {

            color: #741225;

            font-size: 14px;

            word-break: break-word;
        }


        /* =========================================================
           Action Buttons
        ========================================================= */

        .actions {

            display: flex;

            justify-content: center;

            flex-wrap: wrap;

            gap: 13px;

            margin-top: 30px;
        }


        .action-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            text-decoration: none;

            padding: 12px 20px;

            border-radius: 24px;

            font-size: 13px;

            font-weight: bold;

            transition: 0.3s;
        }


        .action-primary {

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #8f1024,
                    #c51f3b
                );

            box-shadow:
                0 8px 20px rgba(143,16,36,0.2);
        }


        .action-secondary {

            color: #741225;

            background:
                rgba(255,255,255,0.6);

            border:
                1px solid rgba(143,16,36,0.12);
        }


        .action-button:hover {

            transform: translateY(-3px);

            box-shadow:
                0 10px 22px rgba(100,20,30,0.13);
        }


        /* =========================================================
           Footer
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
           Mobile
        ========================================================= */

        @media (max-width: 750px) {

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

                margin-top: 30px;
            }


            .page-header h1 {

                font-size: 31px;
            }


            .profile-hero {

                flex-direction: column;

                text-align: center;

                padding: 28px 20px;
            }


            .profile-intro h2 {

                font-size: 24px;
            }


            .profile-grid {

                grid-template-columns: 1fr;
            }


            .profile-card.full {

                grid-column: auto;
            }


            .location-grid {

                grid-template-columns: 1fr;
            }


            .info-row {

                align-items: flex-start;

                flex-direction: column;

                gap: 5px;
            }


            .info-value {

                text-align: left;
            }


            .actions {

                flex-direction: column;
            }


            .action-button {

                width: 100%;
            }

        }

    </style>

</head>


<body>


    <!-- =========================================================
         Reusable Alert Component
    ========================================================= -->

    <?php require_once "../components/alert.php"; ?>


    <!-- =========================================================
         Navbar
    ========================================================= -->

    <nav class="navbar">

        <div class="logo">
            🩸 BloodConnect
        </div>

        <a
            href="../auth/logout.php"
            class="logout"
        >
            Logout
        </a>

    </nav>


    <!-- =========================================================
         Main Content
    ========================================================= -->

    <main class="container">


        <!-- =====================================================
             Page Header
        ====================================================== -->

        <section class="page-header">

            <span class="page-tag">
                👤 Donor Account
            </span>

            <h1>
                My Donor Profile
            </h1>

            <p>
                View your registered information, blood group,
                location and current donor availability.
            </p>

        </section>


        <!-- =====================================================
             Profile Hero
        ====================================================== -->

        <section class="profile-hero">

            <div class="profile-avatar">
                👤
            </div>


            <div class="profile-intro">

                <h2>
                    <?php echo htmlspecialchars($donor["name"]); ?>
                </h2>

                <p>
                    BloodConnect Donor
                    <?php echo " • " . htmlspecialchars($donor["blood_group"]); ?>
                </p>

            </div>

        </section>


        <!-- =====================================================
             Profile Information
        ====================================================== -->

        <section class="profile-grid">


            <!-- =================================================
                 Personal Information
            ================================================== -->

            <div class="profile-card">

                <div class="card-title">

                    <div class="card-title-icon">
                        👤
                    </div>

                    <h3>
                        Personal Information
                    </h3>

                </div>


                <div class="info-list">


                    <div class="info-row">

                        <span class="info-label">
                            Full Name
                        </span>

                        <span class="info-value">
                            <?php
                            echo htmlspecialchars($donor["name"]);
                            ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Email
                        </span>

                        <span class="info-value">
                            <?php
                            echo htmlspecialchars($donor["email"]);
                            ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Phone
                        </span>

                        <span class="info-value">
                            <?php
                            echo htmlspecialchars($donor["phone"]);
                            ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Date of Birth
                        </span>

                        <span class="info-value">

                            <?php

                            if (!empty($donor["date_of_birth"])) {

                                echo htmlspecialchars(
                                    $donor["date_of_birth"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Gender
                        </span>

                        <span class="info-value">

                            <?php

                            if (!empty($donor["gender"])) {

                                echo htmlspecialchars(
                                    $donor["gender"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>

                        </span>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 Donor Information
            ================================================== -->

            <div class="profile-card">

                <div class="card-title">

                    <div class="card-title-icon">
                        🩸
                    </div>

                    <h3>
                        Donor Information
                    </h3>

                </div>


                <div class="info-list">


                    <div class="info-row">

                        <span class="info-label">
                            Blood Group
                        </span>

                        <span class="info-value">

                            <span class="blood-group">
                                <?php
                                echo htmlspecialchars(
                                    $donor["blood_group"]
                                );
                                ?>
                            </span>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Availability
                        </span>

                        <span class="info-value">

                            <?php if ($donor["availability"] == "available"): ?>

                                <span class="availability available">

                                    <span class="status-dot"></span>

                                    Available

                                </span>

                            <?php else: ?>

                                <span class="availability unavailable">

                                    <span class="status-dot"></span>

                                    Unavailable

                                </span>

                            <?php endif; ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Last Donation
                        </span>

                        <span class="info-value">

                            <?php

                            if (!empty($donor["last_donation_date"])) {

                                echo htmlspecialchars(
                                    $donor["last_donation_date"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Donor Status
                        </span>

                        <span class="info-value">
                            Registered Donor
                        </span>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 Location Information
            ================================================== -->

            <div class="profile-card full">

                <div class="card-title">

                    <div class="card-title-icon">
                        📍
                    </div>

                    <h3>
                        Location Information
                    </h3>

                </div>


                <div class="location-grid">


                    <div class="location-item">

                        <div class="location-icon">
                            🏙️
                        </div>

                        <span>
                            City
                        </span>

                        <strong>

                            <?php

                            if (!empty($donor["city"])) {

                                echo htmlspecialchars(
                                    $donor["city"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>

                        </strong>

                    </div>


                    <div class="location-item">

                        <div class="location-icon">
                            📍
                        </div>

                        <span>
                            Area
                        </span>

                        <strong>

                            <?php

                            if (!empty($donor["area"])) {

                                echo htmlspecialchars(
                                    $donor["area"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>

                        </strong>

                    </div>


                    <div class="location-item">

                        <div class="location-icon">
                            📮
                        </div>

                        <span>
                            Pincode
                        </span>

                        <strong>

                            <?php

                            if (!empty($donor["pincode"])) {

                                echo htmlspecialchars(
                                    $donor["pincode"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>

                        </strong>

                    </div>


                </div>

            </div>


        </section>


        <!-- =====================================================
             Action Buttons
        ====================================================== -->

        <div class="actions">

            <a
                href="edit_profile.php"
                class="action-button action-primary"
            >
                ✏️ Edit Profile
            </a>


            <a
                href="availability.php"
                class="action-button action-secondary"
            >
                ❤️ Availability
            </a>


            <a
                href="requests.php"
                class="action-button action-secondary"
            >
                🩸 Blood Requests
            </a>


            <a
                href="donations.php"
                class="action-button action-secondary"
            >
                📋 My Donations
            </a>


            <a
                href="dashboard.php"
                class="action-button action-secondary"
            >
                ← Dashboard
            </a>


        </div>


    </main>


    <!-- =========================================================
         Footer
    ========================================================= -->

    <footer>

        BloodConnect | Blood Donation & Request Platform

    </footer>


</body>

</html>