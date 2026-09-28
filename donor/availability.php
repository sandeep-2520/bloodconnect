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
| Update Availability
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $availability = $_POST["availability"] ?? "";

    if ($availability == "available" || $availability == "unavailable") {

        $stmt = $conn->prepare("
            UPDATE donor_profiles
            SET availability = ?
            WHERE user_id = ?
        ");

        $stmt->bind_param("si", $availability, $user_id);

        if ($stmt->execute()) {

            /*
            |--------------------------------------------------------------------------
            | Success Alert
            |--------------------------------------------------------------------------
            */

            $_SESSION["success"] =
                "Your donor availability has been updated successfully.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Error Alert
            |--------------------------------------------------------------------------
            */

            $_SESSION["error"] =
                "We couldn't update your availability. Please try again.";
        }

        $stmt->close();

    } else {

        /*
        |--------------------------------------------------------------------------
        | Invalid Availability
        |--------------------------------------------------------------------------
        */

        $_SESSION["warning"] =
            "Please select a valid availability option.";
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Form Resubmission
    |--------------------------------------------------------------------------
    */

    header("Location: availability.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Current Availability
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT availability
    FROM donor_profiles
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$donor = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Donor Profile
|--------------------------------------------------------------------------
*/

if (!$donor) {
    die("Donor profile not found.");
}


$current_availability = $donor["availability"];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>BloodConnect - Donor Availability</title>


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

            max-width: 1000px;

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
           Main Availability Card
        ========================================================= */

        .availability-card {

            padding: 35px;

            border-radius: 30px;

            background:
                rgba(255,255,255,0.55);

            backdrop-filter: blur(20px);

            -webkit-backdrop-filter: blur(20px);

            border:
                1px solid rgba(255,255,255,0.8);

            box-shadow:
                0 20px 45px rgba(100,20,30,0.12);
        }


        /* =========================================================
           Current Status
        ========================================================= */

        .status-box {

            padding: 30px;

            border-radius: 24px;

            margin-bottom: 30px;

            text-align: center;

            border: 1px solid rgba(255,255,255,0.7);

            transition: 0.3s;
        }


        .status-box.available {

            background:
                linear-gradient(
                    135deg,
                    rgba(220,252,231,0.9),
                    rgba(240,255,245,0.8)
                );

            border-color:
                rgba(34,197,94,0.25);
        }


        .status-box.unavailable {

            background:
                linear-gradient(
                    135deg,
                    rgba(255,235,238,0.95),
                    rgba(255,248,249,0.85)
                );

            border-color:
                rgba(220,38,38,0.2);
        }


        .status-icon {

            width: 70px;
            height: 70px;

            margin: 0 auto 18px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            font-size: 34px;
        }


        .status-box.available .status-icon {

            background:
                rgba(34,197,94,0.12);
        }


        .status-box.unavailable .status-icon {

            background:
                rgba(220,38,38,0.1);
        }


        .status-label {

            font-size: 13px;

            text-transform: uppercase;

            letter-spacing: 1px;

            color: #76525a;

            margin-bottom: 8px;
        }


        .status-value {

            font-size: 27px;

            font-weight: bold;
        }


        .status-box.available .status-value {

            color: #15803d;
        }


        .status-box.unavailable .status-value {

            color: #b42336;
        }


        /* =========================================================
           Update Section
        ========================================================= */

        .update-section {

            padding: 30px;

            border-radius: 24px;

            background:
                rgba(255,255,255,0.48);

            border:
                1px solid rgba(255,255,255,0.75);
        }


        .update-section h2 {

            color: #741225;

            font-size: 23px;

            margin-bottom: 8px;
        }


        .update-section > p {

            color: #76525a;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 24px;
        }


        /* =========================================================
           Availability Options
        ========================================================= */

        .availability-options {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

            margin-bottom: 25px;
        }


        .availability-option {

            position: relative;
        }


        .availability-option input {

            position: absolute;

            opacity: 0;

            pointer-events: none;
        }


        .availability-option label {

            display: block;

            cursor: pointer;

            padding: 22px;

            border-radius: 20px;

            border:
                2px solid rgba(120,70,80,0.12);

            background:
                rgba(255,255,255,0.5);

            transition: 0.3s;
        }


        .availability-option label:hover {

            transform: translateY(-3px);

            border-color:
                rgba(143,16,36,0.3);
        }


        .availability-option input:checked + label {

            border-color: #8f1024;

            background:
                rgba(143,16,36,0.08);

            box-shadow:
                0 10px 25px rgba(143,16,36,0.1);
        }


        .option-content {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .option-icon {

            width: 50px;
            height: 50px;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 15px;

            font-size: 24px;
        }


        .available-option .option-icon {

            background:
                rgba(34,197,94,0.12);
        }


        .unavailable-option .option-icon {

            background:
                rgba(220,38,38,0.1);
        }


        .option-text strong {

            display: block;

            color: #741225;

            font-size: 17px;

            margin-bottom: 5px;
        }


        .option-text span {

            color: #76525a;

            font-size: 13px;

            line-height: 1.4;
        }


        /* =========================================================
           Submit Button
        ========================================================= */

        .update-button {

            width: 100%;

            border: none;

            cursor: pointer;

            padding: 15px 25px;

            border-radius: 28px;

            background:
                linear-gradient(
                    135deg,
                    #8f1024,
                    #c51f3b
                );

            color: white;

            font-size: 15px;

            font-weight: bold;

            box-shadow:
                0 10px 25px rgba(143,16,36,0.22);

            transition: 0.3s;
        }


        .update-button:hover {

            transform: translateY(-3px);

            box-shadow:
                0 14px 30px rgba(143,16,36,0.3);
        }


        /* =========================================================
           Information Cards
        ========================================================= */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

            margin-top: 25px;
        }


        .info-card {

            padding: 22px;

            text-align: center;

            border-radius: 20px;

            background:
                rgba(255,255,255,0.45);

            border:
                1px solid rgba(255,255,255,0.75);

            box-shadow:
                0 10px 25px rgba(100,20,30,0.07);
        }


        .info-card .info-icon {

            font-size: 25px;

            margin-bottom: 10px;
        }


        .info-card h3 {

            color: #741225;

            font-size: 16px;

            margin-bottom: 7px;
        }


        .info-card p {

            color: #76525a;

            font-size: 12px;

            line-height: 1.5;
        }


        /* =========================================================
           Navigation Links
        ========================================================= */

        .navigation {

            display: flex;

            justify-content: center;

            flex-wrap: wrap;

            gap: 12px;

            margin-top: 30px;
        }


        .nav-button {

            text-decoration: none;

            padding: 11px 18px;

            border-radius: 22px;

            background:
                rgba(255,255,255,0.55);

            color: #741225;

            border:
                1px solid rgba(143,16,36,0.12);

            font-size: 13px;

            font-weight: bold;

            transition: 0.3s;
        }


        .nav-button:hover {

            transform: translateY(-3px);

            background: white;

            box-shadow:
                0 8px 18px rgba(100,20,30,0.1);
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

                margin-top: 30px;
            }


            .page-header h1 {

                font-size: 31px;
            }


            .availability-card {

                padding: 20px;

                border-radius: 24px;
            }


            .status-box {

                padding: 25px 15px;
            }


            .update-section {

                padding: 22px 17px;
            }


            .availability-options {

                grid-template-columns: 1fr;
            }


            .info-grid {

                grid-template-columns: 1fr;
            }


            .navigation {

                flex-direction: column;
            }


            .nav-button {

                text-align: center;

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
         Main
    ========================================================= -->

    <main class="container">


        <!-- Page Header -->

        <section class="page-header">

            <span class="page-tag">
                ❤️ Donor Settings
            </span>

            <h1>
                Donor Availability
            </h1>

            <p>
                Keep your availability updated so BloodConnect can
                accurately represent whether you are ready to respond
                to blood donation requests.
            </p>

        </section>


        <!-- Main Availability Card -->

        <section class="availability-card">


            <!-- =================================================
                 Current Status
            ================================================== -->

            <div
                class="status-box
                <?php
                    echo ($current_availability == "available")
                        ? "available"
                        : "unavailable";
                ?>"
            >

                <div class="status-icon">

                    <?php if ($current_availability == "available"): ?>

                        🟢

                    <?php else: ?>

                        🔴

                    <?php endif; ?>

                </div>


                <div class="status-label">
                    Current Status
                </div>


                <div class="status-value">

                    <?php if ($current_availability == "available"): ?>

                        Available to Donate

                    <?php else: ?>

                        Currently Unavailable

                    <?php endif; ?>

                </div>

            </div>


            <!-- =================================================
                 Update Availability
            ================================================== -->

            <div class="update-section">

                <h2>
                    Update Your Availability
                </h2>

                <p>
                    Select your current status below. You can change
                    your availability whenever your situation changes.
                </p>


                <form method="POST">


                    <div class="availability-options">


                        <!-- Available -->

                        <div class="availability-option available-option">

                            <input
                                type="radio"
                                id="available"
                                name="availability"
                                value="available"
                                <?php
                                if ($current_availability == "available") {
                                    echo "checked";
                                }
                                ?>
                                required
                            >

                            <label for="available">

                                <div class="option-content">

                                    <div class="option-icon">
                                        🟢
                                    </div>

                                    <div class="option-text">

                                        <strong>
                                            Available
                                        </strong>

                                        <span>
                                            I am currently able to respond
                                            to blood donation requests.
                                        </span>

                                    </div>

                                </div>

                            </label>

                        </div>


                        <!-- Unavailable -->

                        <div class="availability-option unavailable-option">

                            <input
                                type="radio"
                                id="unavailable"
                                name="availability"
                                value="unavailable"
                                <?php
                                if ($current_availability == "unavailable") {
                                    echo "checked";
                                }
                                ?>
                            >

                            <label for="unavailable">

                                <div class="option-content">

                                    <div class="option-icon">
                                        🔴
                                    </div>

                                    <div class="option-text">

                                        <strong>
                                            Unavailable
                                        </strong>

                                        <span>
                                            I am currently unable to respond
                                            to blood donation requests.
                                        </span>

                                    </div>

                                </div>

                            </label>

                        </div>


                    </div>


                    <button
                        type="submit"
                        class="update-button"
                    >
                        Update My Availability
                    </button>


                </form>

            </div>


            <!-- =================================================
                 Information
            ================================================== -->

            <div class="info-grid">


                <div class="info-card">

                    <div class="info-icon">
                        🔔
                    </div>

                    <h3>
                        Stay Updated
                    </h3>

                    <p>
                        Keep your status accurate so you can
                        receive relevant updates.
                    </p>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        🩸
                    </div>

                    <h3>
                        Help When Ready
                    </h3>

                    <p>
                        Set yourself as available when you are
                        ready to respond to matching requests.
                    </p>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        ❤️
                    </div>

                    <h3>
                        Make an Impact
                    </h3>

                    <p>
                        Your availability helps connect donors
                        with people who need blood.
                    </p>

                </div>


            </div>


            <!-- =================================================
                 Navigation
            ================================================== -->

            <div class="navigation">

                <a
                    href="dashboard.php"
                    class="nav-button"
                >
                    ← Dashboard
                </a>

                <a
                    href="requests.php"
                    class="nav-button"
                >
                    🩸 Blood Requests
                </a>

                <a
                    href="profile.php"
                    class="nav-button"
                >
                    👤 My Profile
                </a>

                <a
                    href="donations.php"
                    class="nav-button"
                >
                    📋 My Donations
                </a>

                <a
                    href="notifications.php"
                    class="nav-button"
                >
                    🔔 Notifications
                </a>

            </div>


        </section>


    </main>


    <!-- =========================================================
         Footer
    ========================================================= -->

    <footer>

        BloodConnect | Blood Donation & Request Platform

    </footer>


</body>

</html>