<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Prevent Browser Cache
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
| Get Unread Notifications
|--------------------------------------------------------------------------
*/

$unread_count = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $unread_count = (int)$row["unread_count"];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get Donor Profile
|--------------------------------------------------------------------------
*/

$last_donation_date = null;
$availability = "available";

/*
|--------------------------------------------------------------------------
| Project Recovery Period
|--------------------------------------------------------------------------
|
| This is a configurable project value.
| Change this value if your project uses a different recovery period.
|
*/

$recovery_period_days = 90;

$stmt = $conn->prepare("
    SELECT
        last_donation_date,
        availability
    FROM donor_profiles
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($donor_profile = $result->fetch_assoc()) {

        $last_donation_date = $donor_profile["last_donation_date"];
        $availability = $donor_profile["availability"];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Calculate Recovery Status
|--------------------------------------------------------------------------
*/

$show_recovery = false;
$recovery_completed = false;

$next_eligible_date = null;
$days_remaining = 0;

if (!empty($last_donation_date)) {

    try {

        $last_donation = new DateTime($last_donation_date);

        $next_eligible = clone $last_donation;

        $next_eligible->modify(
            "+{$recovery_period_days} days"
        );

        $today = new DateTime("today");

        /*
        |--------------------------------------------------------------------------
        | Still in Recovery
        |--------------------------------------------------------------------------
        */

        if ($today < $next_eligible) {

            $show_recovery = true;

            $next_eligible_date =
                $next_eligible->format("F j, Y");

            $days_remaining =
                (int)$today->diff($next_eligible)->days;
        }

        /*
        |--------------------------------------------------------------------------
        | Recovery Completed
        |--------------------------------------------------------------------------
        */

        else {

            $recovery_completed = true;

            $next_eligible_date =
                $next_eligible->format("F j, Y");
        }

    } catch (Exception $e) {

        $show_recovery = false;
        $recovery_completed = false;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Donor Dashboard | BloodConnect</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #fff5f5,
                    #ffe8e8,
                    #fffafa
                );

            min-height: 100vh;

            color: #2b1518;
        }

        /*
        |--------------------------------------------------------------------------
        | Navbar
        |--------------------------------------------------------------------------
        */

        .navbar {

            width: 100%;

            padding: 18px 7%;

            display: flex;

            justify-content: space-between;

            align-items: center;

            background: rgba(255,255,255,0.72);

            backdrop-filter: blur(15px);

            -webkit-backdrop-filter: blur(15px);

            border-bottom:
                1px solid rgba(160, 30, 45, 0.10);

            position: sticky;

            top: 0;

            z-index: 100;
        }

        .logo {

            font-size: 25px;

            font-weight: 800;

            color: #a3162d;

            text-decoration: none;
        }

        .logout {

            text-decoration: none;

            background: #a3162d;

            color: white;

            padding: 10px 18px;

            border-radius: 10px;

            font-weight: 600;

            transition: 0.3s;
        }

        .logout:hover {

            background: #7d0f21;

            transform: translateY(-2px);
        }

        /*
        |--------------------------------------------------------------------------
        | Main Container
        |--------------------------------------------------------------------------
        */

        .container {

            width: 86%;

            max-width: 1250px;

            margin: auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */

        .hero {

            margin-top: 35px;

            min-height: 330px;

            border-radius: 25px;

            overflow: hidden;

            position: relative;

            display: flex;

            align-items: center;

            padding: 55px;

            background:
                linear-gradient(
                    rgba(90, 0, 15, 0.65),
                    rgba(90, 0, 15, 0.65)
                ),
                url("https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=1600&q=80");

            background-size: cover;

            background-position: center;
        }

        .hero-content {

            color: white;

            max-width: 700px;

            position: relative;

            z-index: 2;
        }

        .hero-tag {

            display: inline-block;

            background: rgba(255,255,255,0.18);

            border: 1px solid rgba(255,255,255,0.25);

            padding: 8px 14px;

            border-radius: 50px;

            margin-bottom: 18px;

            font-size: 14px;

            backdrop-filter: blur(10px);
        }

        .hero h1 {

            font-size: 44px;

            line-height: 1.15;

            margin-bottom: 18px;
        }

        .hero p {

            font-size: 17px;

            line-height: 1.7;

            color: #ffe9ed;

            margin-bottom: 25px;
        }

        .hero-btn {

            display: inline-block;

            text-decoration: none;

            background: white;

            color: #9d132b;

            padding: 13px 22px;

            border-radius: 12px;

            font-weight: 700;

            transition: 0.3s;
        }

        .hero-btn:hover {

            transform: translateY(-3px);

            box-shadow:
                0 10px 25px rgba(0,0,0,0.18);
        }

        /*
        |--------------------------------------------------------------------------
        | Welcome Section
        |--------------------------------------------------------------------------
        */

        .welcome {

            text-align: center;

            margin: 50px 0 30px;
        }

        .welcome h2 {

            font-size: 30px;

            color: #8e1528;

            margin-bottom: 10px;
        }

        .welcome p {

            color: #765e62;

            font-size: 16px;
        }

        /*
        |--------------------------------------------------------------------------
        | Recovery Card
        |--------------------------------------------------------------------------
        */

        .recovery-card {

            margin: 30px 0 40px;

            padding: 30px;

            border-radius: 22px;

            background:
                linear-gradient(
                    135deg,
                    rgba(255,255,255,0.90),
                    rgba(255,238,241,0.92)
                );

            border:
                1px solid rgba(170, 30, 50, 0.13);

            box-shadow:
                0 15px 40px rgba(120, 20, 40, 0.08);

            position: relative;

            overflow: hidden;
        }

        .recovery-card::before {

            content: "";

            position: absolute;

            width: 150px;

            height: 150px;

            border-radius: 50%;

            background: rgba(190, 35, 60, 0.06);

            right: -50px;

            top: -50px;
        }

        .recovery-title {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 12px;
        }

        .recovery-title h3 {

            color: #95172c;

            font-size: 23px;
        }

        .recovery-card > p {

            color: #684f54;

            line-height: 1.6;

            margin-bottom: 22px;
        }

        .recovery-info {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-top: 20px;
        }

        .recovery-item {

            background: rgba(255,255,255,0.75);

            padding: 18px;

            border-radius: 15px;

            border:
                1px solid rgba(160, 30, 45, 0.08);
        }

        .recovery-item span {

            display: block;

            color: #80666b;

            font-size: 13px;

            margin-bottom: 7px;
        }

        .recovery-item strong {

            color: #92162b;

            font-size: 17px;
        }

        .recovery-days {

            margin-top: 20px;

            padding: 15px 18px;

            background: rgba(169, 24, 46, 0.08);

            border-radius: 12px;

            color: #7e1729;

            font-weight: 600;
        }

        .recovery-note {

            margin-top: 15px;

            font-size: 13px;

            color: #80666b;
        }

        /*
        |--------------------------------------------------------------------------
        | Recovery Completed
        |--------------------------------------------------------------------------
        */

        .eligible-card {

            margin: 30px 0 40px;

            padding: 28px;

            border-radius: 22px;

            background:
                rgba(255,255,255,0.82);

            border:
                1px solid rgba(60, 130, 80, 0.15);

            box-shadow:
                0 15px 40px rgba(40, 100, 60, 0.07);
        }

        .eligible-card h3 {

            color: #287343;

            margin-bottom: 10px;

            font-size: 23px;
        }

        .eligible-card p {

            color: #5f6962;

            line-height: 1.6;

            margin-bottom: 15px;
        }

        .eligible-date {

            display: inline-block;

            padding: 10px 15px;

            background: rgba(45, 130, 75, 0.08);

            border-radius: 10px;

            color: #287343;

            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Cards
        |--------------------------------------------------------------------------
        */

        .cards {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 22px;

            margin-bottom: 55px;
        }

        .card {

            background:
                rgba(255,255,255,0.70);

            backdrop-filter: blur(15px);

            -webkit-backdrop-filter: blur(15px);

            border:
                1px solid rgba(160,30,45,0.10);

            border-radius: 20px;

            padding: 28px;

            text-decoration: none;

            color: inherit;

            box-shadow:
                0 10px 30px rgba(100,20,30,0.05);

            transition: 0.3s;

            min-height: 180px;

            position: relative;
        }

        .card:hover {

            transform: translateY(-6px);

            box-shadow:
                0 18px 35px rgba(100,20,30,0.12);
        }

        .card-icon {

            width: 52px;

            height: 52px;

            border-radius: 15px;

            background:
                rgba(170, 25, 45, 0.09);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;

            margin-bottom: 17px;
        }

        .card h3 {

            color: #8e1528;

            margin-bottom: 8px;

            font-size: 19px;
        }

        .card p {

            color: #756066;

            line-height: 1.5;

            font-size: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | Notification Badge
        |--------------------------------------------------------------------------
        */

        .notification-badge {

            position: absolute;

            top: 18px;

            right: 18px;

            min-width: 26px;

            height: 26px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #c01835;

            color: white;

            font-size: 12px;

            font-weight: 700;

            padding: 0 7px;
        }

        /*
        |--------------------------------------------------------------------------
        | How It Works
        |--------------------------------------------------------------------------
        */

        .how-section {

            margin: 20px 0 60px;

            text-align: center;
        }

        .how-section h2 {

            font-size: 28px;

            color: #8e1528;

            margin-bottom: 30px;
        }

        .steps {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;
        }

        .step {

            background:
                rgba(255,255,255,0.68);

            padding: 25px 20px;

            border-radius: 18px;

            border:
                1px solid rgba(160,30,45,0.08);
        }

        .step-number {

            width: 42px;

            height: 42px;

            margin: 0 auto 15px;

            border-radius: 50%;

            background: #a3162d;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;
        }

        .step h3 {

            color: #8e1528;

            font-size: 17px;

            margin-bottom: 8px;
        }

        .step p {

            color: #78636a;

            font-size: 14px;

            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Bottom Message
        |--------------------------------------------------------------------------
        */

        .bottom-message {

            text-align: center;

            margin: 45px 0;

            color: #8e1528;

            font-size: 20px;

            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        footer {

            text-align: center;

            padding: 25px;

            color: #80666b;

            font-size: 14px;

            border-top:
                1px solid rgba(160,30,45,0.08);

            background:
                rgba(255,255,255,0.45);
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .cards {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .steps {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .recovery-info {

                grid-template-columns:
                    1fr;
            }

            .hero h1 {

                font-size: 36px;
            }
        }

        @media (max-width: 600px) {

            .container {

                width: 92%;
            }

            .navbar {

                padding: 15px 4%;
            }

            .hero {

                padding: 35px 25px;

                min-height: 400px;
            }

            .hero h1 {

                font-size: 31px;
            }

            .cards {

                grid-template-columns: 1fr;
            }

            .steps {

                grid-template-columns: 1fr;
            }

            .recovery-card {

                padding: 22px;
            }
        }

    </style>

</head>

<body>

<!--
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
-->

<nav class="navbar">

    <a href="dashboard.php" class="logo">
        🩸 BloodConnect
    </a>

    <a href="../auth/logout.php" class="logout">
        Logout
    </a>

</nav>


<div class="container">

    <!--
    |--------------------------------------------------------------------------
    | Hero
    |--------------------------------------------------------------------------
    -->

    <section class="hero">

        <div class="hero-content">

            <div class="hero-tag">
                🩸 BloodConnect Donor
            </div>

            <h1>
                Your blood can be the connection someone needs.
            </h1>

            <p>
                Every donation can help someone during one of the
                most important moments of their life. Find people
                who need your blood and make a difference.
            </p>

            <a
                href="requests.php"
                class="hero-btn"
            >
                View Blood Requests →
            </a>

        </div>

    </section>


    <!--
    |--------------------------------------------------------------------------
    | Welcome
    |--------------------------------------------------------------------------
    -->

    <section class="welcome">

        <h2>
            Welcome, Donor ❤️
        </h2>

        <p>
            Thank you for being part of the BloodConnect community.
        </p>

    </section>


    <?php if ($show_recovery): ?>

        <!--
        |--------------------------------------------------------------------------
        | Recovery Section
        |--------------------------------------------------------------------------
        -->

        <section class="recovery-card">

            <div class="recovery-title">

                <span style="font-size: 28px;">
                    🩸
                </span>

                <h3>
                    Thank You for Donating!
                </h3>

            </div>

            <p>

                Your blood donation has been successfully completed.
                Please allow your body the required recovery period
                before donating again.

            </p>


            <div class="recovery-info">

                <div class="recovery-item">

                    <span>
                        Last Donation
                    </span>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            date(
                                "F j, Y",
                                strtotime($last_donation_date)
                            )
                        );
                        ?>

                    </strong>

                </div>


                <div class="recovery-item">

                    <span>
                        Next Project Eligible Date
                    </span>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $next_eligible_date
                        );
                        ?>

                    </strong>

                </div>


                <div class="recovery-item">

                    <span>
                        Current Availability
                    </span>

                    <strong>
                        Paused
                    </strong>

                </div>

            </div>


            <div class="recovery-days">

                ⏳ Recovery remaining:
                <strong>
                    <?php echo $days_remaining; ?> days
                </strong>

            </div>


            <p class="recovery-note">

                Your donor availability is currently paused during
                this recovery period. Once the recovery period ends,
                you can review and update your availability from
                <strong>My Availability</strong>.

            </p>

        </section>


    <?php elseif ($recovery_completed): ?>

        <!--
        |--------------------------------------------------------------------------
        | Recovery Completed Section
        |--------------------------------------------------------------------------
        -->

        <section class="eligible-card">

            <h3>
                🎉 Your Recovery Period Has Ended
            </h3>

            <p>

                Your project recovery period has ended.
                If you are ready and meet the applicable blood
                donation requirements, you can review your availability
                and choose whether you are available to help another
                person.

            </p>

            <div class="eligible-date">

                Recovery ended:
                <?php echo htmlspecialchars($next_eligible_date); ?>

            </div>

        </section>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | Dashboard Cards
    |--------------------------------------------------------------------------
    -->

    <section class="cards">


        <!-- Blood Requests -->

        <a
            href="requests.php"
            class="card"
        >

            <div class="card-icon">
                🩸
            </div>

            <h3>
                Blood Requests
            </h3>

            <p>
                View nearby blood requests and find
                opportunities to help someone in need.
            </p>

        </a>


        <!-- Availability -->

        <a
            href="availability.php"
            class="card"
        >

            <div class="card-icon">
                🟢
            </div>

            <h3>
                My Availability
            </h3>

            <p>

                Manage your donor availability and let
                requesters know whether you are available.

            </p>

        </a>


        <!-- Donations -->

        <a
            href="donations.php"
            class="card"
        >

            <div class="card-icon">
                ❤️
            </div>

            <h3>
                My Donations
            </h3>

            <p>

                View your previous blood donations and
                donation history.

            </p>

        </a>


        <!-- Donor Profile -->

        <a
            href="profile.php"
            class="card"
        >

            <div class="card-icon">
                👤
            </div>

            <h3>
                Donor Profile
            </h3>

            <p>

                View and update your blood group,
                location and donor information.

            </p>

        </a>


        <!-- Notifications -->

        <a
            href="notifications.php"
            class="card"
        >

            <div class="card-icon">
                🔔
            </div>

            <?php if ($unread_count > 0): ?>

                <span class="notification-badge">

                    <?php
                    echo $unread_count > 99
                        ? "99+"
                        : $unread_count;
                    ?>

                </span>

            <?php endif; ?>

            <h3>
                Notifications
            </h3>

            <p>

                Check blood requests, donation updates
                and important messages.

            </p>

        </a>


        <!-- Help Someone -->

        <a
            href="requests.php"
            class="card"
        >

            <div class="card-icon">
                🤝
            </div>

            <h3>
                Help Someone
            </h3>

            <p>

                Respond to a blood request and become
                a helping hand when someone needs you.

            </p>

        </a>

    </section>


    <!--
    |--------------------------------------------------------------------------
    | How BloodConnect Works
    |--------------------------------------------------------------------------
    -->

    <section class="how-section">

        <h2>
            How donating through BloodConnect works
        </h2>


        <div class="steps">


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <h3>
                    Find a Request
                </h3>

                <p>
                    Browse blood requests that match
                    your blood group.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <h3>
                    Respond
                </h3>

                <p>
                    Let the requester know that you
                    are interested in donating.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <h3>
                    Donate
                </h3>

                <p>
                    Coordinate with the requester and
                    complete the blood donation.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    4
                </div>

                <h3>
                    Recover
                </h3>

                <p>
                    After your donation is completed,
                    your availability is paused during
                    the configured recovery period.
                </p>

            </div>


        </div>

    </section>


    <!--
    |--------------------------------------------------------------------------
    | Bottom Message
    |--------------------------------------------------------------------------
    -->

    <div class="bottom-message">

        One response can make a real difference. 🩸

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
-->

<footer>

    © <?php echo date("Y"); ?> BloodConnect.
    Connecting donors with people who need blood.

</footer>


</body>

</html>