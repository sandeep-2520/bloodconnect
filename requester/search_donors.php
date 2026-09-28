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

$message = "";
$total_donors = 0;
$available_donors = 0;
$search_done = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $blood_group = trim($_POST["blood_group"] ?? "");
    $city = trim($_POST["city"] ?? "");

    if (empty($blood_group) || empty($city)) {

        $message = "Please select a blood group and enter a city.";

    } else {

        $search_done = true;

        /*
         * Count all registered active donors
         * matching the selected blood group and city.
         */
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM donor_profiles
            INNER JOIN users ON donor_profiles.user_id = users.id
            WHERE donor_profiles.blood_group = ?
            AND donor_profiles.city LIKE ?
            AND users.status = 'active'
            AND users.role = 'donor'
        ");

        $city_search = "%" . $city . "%";

        $stmt->bind_param(
            "ss",
            $blood_group,
            $city_search
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $total_donors = (int)$row["total"];
        }

        $stmt->close();


        /*
         * Count donors who are currently available.
         */
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS available
            FROM donor_profiles
            INNER JOIN users ON donor_profiles.user_id = users.id
            WHERE donor_profiles.blood_group = ?
            AND donor_profiles.city LIKE ?
            AND donor_profiles.availability = 'available'
            AND users.status = 'active'
            AND users.role = 'donor'
        ");

        $stmt->bind_param(
            "ss",
            $blood_group,
            $city_search
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $available_donors = (int)$row["available"];
        }

        $stmt->close();


        if ($available_donors == 0) {

            $message =
                "No available donors were found in " .
                $city .
                " for the selected blood group right now.";

        } else {

            $message =
                "Good news! Matching blood donors are available in " .
                $city .
                ".";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BloodConnect - Search Donors</title>


<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: Arial, Helvetica, sans-serif;

    min-height: 100vh;

    color: #ffffff;

    background:
        radial-gradient(
            circle at 10% 20%,
            rgba(180, 0, 0, 0.30),
            transparent 35%
        ),
        radial-gradient(
            circle at 90% 80%,
            rgba(255, 40, 40, 0.18),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            #090909,
            #180404,
            #0b0b0b
        );

    overflow-x: hidden;
}


/* =========================================================
   BACKGROUND GLOW
========================================================= */

.background-glow {

    position: fixed;

    width: 400px;
    height: 400px;

    border-radius: 50%;

    filter: blur(100px);

    opacity: 0.18;

    pointer-events: none;

    z-index: -1;
}

.glow-one {

    background: #ff0000;

    top: -150px;
    left: -150px;
}

.glow-two {

    background: #8b0000;

    right: -150px;
    bottom: -150px;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    width: 100%;

    padding: 17px 6%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    position: sticky;

    top: 0;

    z-index: 100;

    background: rgba(10, 10, 10, 0.78);

    backdrop-filter: blur(18px);

    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 22px;

    font-weight: bold;
}

.logo-icon {

    width: 40px;

    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    background: linear-gradient(
        135deg,
        #ff3333,
        #9d0000
    );

    box-shadow:
        0 8px 25px rgba(255, 0, 0, 0.25);

    font-size: 20px;
}

.user-badge {

    padding: 7px 14px;

    border-radius: 30px;

    font-size: 12px;

    font-weight: bold;

    background: rgba(255, 255, 255, 0.07);

    border: 1px solid rgba(255, 255, 255, 0.10);

    color: #ffb0b0;
}

.logout-btn {

    text-decoration: none;

    color: #ffffff;

    padding: 9px 17px;

    border-radius: 10px;

    background: rgba(255, 255, 255, 0.07);

    border: 1px solid rgba(255, 255, 255, 0.10);

    font-size: 13px;

    transition: 0.3s;
}

.logout-btn:hover {

    background: #b30000;

    transform: translateY(-2px);
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    width: 92%;

    max-width: 1150px;

    margin: auto;

    padding: 45px 0 70px;
}


/* =========================================================
   HERO
========================================================= */

.hero {

    text-align: center;

    margin-bottom: 35px;
}

.hero-label {

    display: inline-block;

    padding: 7px 14px;

    margin-bottom: 14px;

    border-radius: 20px;

    background: rgba(255, 40, 40, 0.10);

    border: 1px solid rgba(255, 80, 80, 0.18);

    color: #ff9b9b;

    font-size: 12px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.hero h1 {

    font-size: clamp(32px, 6vw, 52px);

    line-height: 1.1;

    margin-bottom: 13px;
}

.hero h1 span {

    color: #ff4a4a;
}

.hero p {

    max-width: 650px;

    margin: auto;

    color: #999;

    font-size: 15px;

    line-height: 1.7;
}


/* =========================================================
   SEARCH CARD
========================================================= */

.search-card {

    padding: 30px;

    border-radius: 24px;

    background: rgba(255, 255, 255, 0.045);

    border: 1px solid rgba(255, 255, 255, 0.09);

    backdrop-filter: blur(18px);

    box-shadow:
        0 25px 70px rgba(0, 0, 0, 0.30);

    margin-bottom: 35px;
}

.search-card-header {

    display: flex;

    align-items: center;

    gap: 14px;

    margin-bottom: 25px;
}

.search-icon {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background: rgba(255, 40, 40, 0.12);

    border: 1px solid rgba(255, 60, 60, 0.18);

    font-size: 22px;
}

.search-title {

    font-size: 19px;

    font-weight: bold;
}

.search-subtitle {

    color: #888;

    font-size: 12px;

    margin-top: 5px;
}


/* =========================================================
   FORM
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}

.form-group {

    display: flex;

    flex-direction: column;

    gap: 8px;
}

.form-group label {

    color: #bbb;

    font-size: 12px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 0.5px;
}

.form-group select,
.form-group input {

    width: 100%;

    padding: 14px 15px;

    border-radius: 12px;

    border: 1px solid rgba(255, 255, 255, 0.10);

    outline: none;

    color: #ffffff;

    background: rgba(0, 0, 0, 0.35);

    font-size: 14px;

    transition: 0.25s;
}

.form-group select:focus,
.form-group input:focus {

    border-color: rgba(255, 70, 70, 0.55);

    box-shadow:
        0 0 0 3px rgba(255, 40, 40, 0.08);
}

.form-group select option {

    background: #1b0909;

    color: white;
}

.form-group input::placeholder {

    color: #666;
}


/* =========================================================
   SEARCH BUTTON
========================================================= */

.search-button-wrapper {

    margin-top: 22px;
}

.search-btn {

    width: 100%;

    padding: 15px;

    border: none;

    border-radius: 12px;

    cursor: pointer;

    color: white;

    background: linear-gradient(
        135deg,
        #c40000,
        #850000
    );

    font-size: 14px;

    font-weight: bold;

    box-shadow:
        0 10px 30px rgba(170, 0, 0, 0.20);

    transition: 0.3s;
}

.search-btn:hover {

    transform: translateY(-2px);

    background: linear-gradient(
        135deg,
        #e00000,
        #a00000
    );

    box-shadow:
        0 15px 35px rgba(170, 0, 0, 0.28);
}

.search-btn:active {

    transform: translateY(0);
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    margin-bottom: 25px;

    padding: 16px 20px;

    border-radius: 14px;

    background: rgba(255, 60, 60, 0.10);

    border: 1px solid rgba(255, 80, 80, 0.22);

    color: #ffaaaa;

    text-align: center;

    font-size: 14px;
}


/* =========================================================
   RESULTS HEADER
========================================================= */

.results-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;

    gap: 15px;
}

.results-title {

    font-size: 22px;

    font-weight: bold;
}

.results-title span {

    color: #ff4a4a;
}

.results-count {

    padding: 7px 13px;

    border-radius: 20px;

    background: rgba(255, 40, 40, 0.10);

    border: 1px solid rgba(255, 60, 60, 0.18);

    color: #ff8c8c;

    font-size: 12px;

    font-weight: bold;
}


/* =========================================================
   DONOR GRID
========================================================= */

.donor-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(270px, 1fr));

    gap: 20px;
}


/* =========================================================
   DONOR CARD
========================================================= */

.donor-card {

    position: relative;

    padding: 23px;

    border-radius: 20px;

    background: rgba(255, 255, 255, 0.045);

    border: 1px solid rgba(255, 255, 255, 0.08);

    backdrop-filter: blur(15px);

    transition: 0.3s;

    overflow: hidden;
}

.donor-card::before {

    content: "";

    position: absolute;

    top: 0;

    left: 0;

    width: 100%;

    height: 3px;

    background: linear-gradient(
        90deg,
        #ff3b3b,
        #970000
    );
}

.donor-card:hover {

    transform: translateY(-5px);

    border-color: rgba(255, 70, 70, 0.22);

    box-shadow:
        0 20px 45px rgba(0, 0, 0, 0.25);
}


/* =========================================================
   DONOR CARD TOP
========================================================= */

.donor-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}

.donor-avatar {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: linear-gradient(
        135deg,
        #b00000,
        #650000
    );

    font-size: 20px;

    box-shadow:
        0 8px 20px rgba(180, 0, 0, 0.20);
}

.available-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 7px 10px;

    border-radius: 20px;

    background: rgba(0, 190, 100, 0.10);

    border: 1px solid rgba(0, 190, 100, 0.17);

    color: #66e5a4;

    font-size: 10px;

    font-weight: bold;
}


/* =========================================================
   DONOR NAME
========================================================= */

.donor-name {

    font-size: 19px;

    font-weight: bold;

    margin-bottom: 5px;
}

.donor-location {

    color: #888;

    font-size: 12px;

    margin-bottom: 18px;
}


/* =========================================================
   BLOOD GROUP
========================================================= */

.blood-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 13px;

    border-radius: 12px;

    background: rgba(255, 40, 40, 0.07);

    border: 1px solid rgba(255, 50, 50, 0.12);

    margin-bottom: 15px;
}

.blood-label {

    color: #888;

    font-size: 11px;
}

.blood-value {

    color: #ff6969;

    font-size: 18px;

    font-weight: bold;
}


/* =========================================================
   DONOR DETAILS
========================================================= */

.details {

    display: grid;

    gap: 10px;
}

.detail {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #aaa;

    font-size: 12px;
}

.detail-icon {

    width: 30px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    background: rgba(255, 255, 255, 0.055);

    font-size: 13px;
}

.detail-text {

    color: #ccc;
}


/* =========================================================
   EMPTY RESULT
========================================================= */

.empty-state {

    padding: 55px 25px;

    text-align: center;

    border-radius: 20px;

    background: rgba(255, 255, 255, 0.035);

    border: 1px solid rgba(255, 255, 255, 0.07);
}

.empty-icon {

    width: 70px;

    height: 70px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 18px;

    border-radius: 50%;

    background: rgba(255, 40, 40, 0.08);

    font-size: 30px;
}

.empty-state h3 {

    font-size: 19px;

    margin-bottom: 8px;
}

.empty-state p {

    color: #777;

    font-size: 13px;

    line-height: 1.5;

}


/* =========================================================
   REQUEST BLOOD BUTTON
========================================================= */

.request-blood-btn {

    display: inline-block;

    margin-top: 22px;

    padding: 13px 24px;

    border-radius: 11px;

    text-decoration: none;

    color: white;

    background: linear-gradient(
        135deg,
        #b00000,
        #7d0000
    );

    border: none;

    font-size: 13px;

    font-weight: bold;

    box-shadow:
        0 10px 25px rgba(170, 0, 0, 0.20);

    transition: 0.3s;
}

.request-blood-btn:hover {

    transform: translateY(-2px);

    background: linear-gradient(
        135deg,
        #d00000,
        #950000
    );

    box-shadow:
        0 15px 30px rgba(170, 0, 0, 0.28);
}


/* =========================================================
   AVAILABILITY RESULT
========================================================= */

.availability-state {

    padding: 35px 25px;

    text-align: center;

    border-radius: 20px;

    background: rgba(255, 255, 255, 0.035);

    border: 1px solid rgba(255, 255, 255, 0.07);

    margin-bottom: 30px;
}

.availability-icon {

    width: 70px;

    height: 70px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 18px;

    border-radius: 50%;

    background: rgba(0, 190, 100, 0.08);

    font-size: 30px;
}

.availability-state h3 {

    font-size: 21px;

    margin-bottom: 10px;
}

.availability-state p {

    max-width: 650px;

    margin: 0 auto;

    color: #888;

    font-size: 13px;

    line-height: 1.7;
}


/* =========================================================
   COUNT BOXES
========================================================= */

.count-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 18px;

    max-width: 650px;

    margin: 25px auto 0;
}

.count-box {

    padding: 22px;

    border-radius: 16px;

    background: rgba(255, 40, 40, 0.06);

    border: 1px solid rgba(255, 60, 60, 0.12);

    text-align: center;
}

.count-number {

    display: block;

    color: #ff6969;

    font-size: 30px;

    font-weight: bold;

    margin-bottom: 7px;
}

.count-label {

    color: #999;

    font-size: 12px;
}


/* =========================================================
   BOTTOM NAVIGATION
========================================================= */

.bottom-actions {

    display: flex;

    justify-content: center;

    gap: 14px;

    margin-top: 35px;

    flex-wrap: wrap;
}

.bottom-btn {

    text-decoration: none;

    padding: 12px 20px;

    border-radius: 11px;

    color: #ddd;

    background: rgba(255, 255, 255, 0.055);

    border: 1px solid rgba(255, 255, 255, 0.09);

    font-size: 13px;

    transition: 0.3s;
}

.bottom-btn:hover {

    background: rgba(255, 255, 255, 0.10);

    transform: translateY(-2px);
}

.dashboard-btn {

    background: linear-gradient(
        135deg,
        #b00000,
        #7d0000
    );

    border: none;

    color: white;
}

.dashboard-btn:hover {

    background: linear-gradient(
        135deg,
        #d00000,
        #950000
    );
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .navbar {

        padding: 14px 5%;
    }

    .logo {

        font-size: 18px;
    }

    .logo-icon {

        width: 35px;

        height: 35px;
    }

    .user-badge {

        display: none;
    }

    .logout-btn {

        padding: 8px 12px;

        font-size: 12px;
    }

    .container {

        width: 94%;

        padding-top: 30px;
    }

    .search-card {

        padding: 20px;

        border-radius: 18px;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .count-grid {

        grid-template-columns: 1fr;
    }

    .results-header {

        align-items: flex-start;

        flex-direction: column;
    }

}

</style>

</head>


<body>


<!-- BACKGROUND -->

<div class="background-glow glow-one"></div>

<div class="background-glow glow-two"></div>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">


    <div class="logo">

        <div class="logo-icon">
            🩸
        </div>

        <span>
            BloodConnect
        </span>

    </div>


    <div style="display:flex; align-items:center; gap:12px;">

        <span class="user-badge">
            🙋 REQUESTER
        </span>

        <a
            href="../auth/logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>


</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="container">


    <!-- HERO -->

    <section class="hero">

        <span class="hero-label">
            🔎 Donor Search
        </span>

        <h1>
            Check <span>Blood Availability</span>
        </h1>

        <p>
            Check whether matching blood donors are currently
            available in your selected city.
        </p>

    </section>


    <!-- =================================================
         SEARCH FORM
    ================================================= -->

    <section class="search-card">


        <div class="search-card-header">

            <div class="search-icon">
                🔍
            </div>

            <div>

                <div class="search-title">
                    Check Donor Availability
                </div>

                <div class="search-subtitle">
                    Enter the required blood group and city
                </div>

            </div>

        </div>


        <form method="POST">


            <div class="form-grid">


                <!-- BLOOD GROUP -->

                <div class="form-group">

                    <label>
                        🩸 Blood Group
                    </label>

                    <select
                        name="blood_group"
                        required
                    >

                        <option value="">
                            Select Blood Group
                        </option>

                        <option value="A+"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "A+"
                            ) echo "selected";
                            ?>
                        >
                            A+
                        </option>

                        <option value="A-"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "A-"
                            ) echo "selected";
                            ?>
                        >
                            A-
                        </option>

                        <option value="B+"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "B+"
                            ) echo "selected";
                            ?>
                        >
                            B+
                        </option>

                        <option value="B-"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "B-"
                            ) echo "selected";
                            ?>
                        >
                            B-
                        </option>

                        <option value="AB+"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "AB+"
                            ) echo "selected";
                            ?>
                        >
                            AB+
                        </option>

                        <option value="AB-"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "AB-"
                            ) echo "selected";
                            ?>
                        >
                            AB-
                        </option>

                        <option value="O+"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "O+"
                            ) echo "selected";
                            ?>
                        >
                            O+
                        </option>

                        <option value="O-"
                            <?php
                            if (
                                isset($_POST["blood_group"])
                                &&
                                $_POST["blood_group"] == "O-"
                            ) echo "selected";
                            ?>
                        >
                            O-
                        </option>

                    </select>

                </div>


                <!-- CITY -->

                <div class="form-group">

                    <label>
                        📍 City
                    </label>

                    <input
                        type="text"
                        name="city"
                        placeholder="Enter city"
                        value="<?php
                            echo isset($_POST["city"])
                                ? htmlspecialchars($_POST["city"])
                                : "";
                        ?>"
                        required
                    >

                </div>


            </div>


            <div class="search-button-wrapper">

                <button
                    type="submit"
                    class="search-btn"
                >
                    🔍 Check Blood Availability
                </button>

            </div>


        </form>


    </section>


    <?php if ($search_done): ?>


        <!-- =================================================
             AVAILABILITY RESULT
        ================================================= -->

        <?php if ($available_donors > 0): ?>


            <section class="availability-state">

                <div class="availability-icon">
                    🩸
                </div>

                <h3>
                    Blood Donors Are Available
                </h3>

                <p>

                    Good news! We found matching donors
                    for

                    <strong>
                        <?php
                        echo htmlspecialchars($blood_group);
                        ?>
                    </strong>

                    blood in

                    <strong>
                        <?php
                        echo htmlspecialchars($city);
                        ?>
                    </strong>.

                    You can now create a blood request with
                    the patient's and hospital's details.
                    Your request can then be processed for
                    suitable donors.

                </p>


                <div class="count-grid">


                    <div class="count-box">

                        <span class="count-number">
                            <?php
                            echo $total_donors;
                            ?>
                        </span>

                        <span class="count-label">
                            Matching Donors
                        </span>

                    </div>


                    <div class="count-box">

                        <span class="count-number">
                            <?php
                            echo $available_donors;
                            ?>
                        </span>

                        <span class="count-label">
                            Currently Available
                        </span>

                    </div>


                </div>


                <a
                    href="create_request.php"
                    class="request-blood-btn"
                >
                    🩸 Request Blood
                </a>


            </section>


        <?php else: ?>


            <!-- =================================================
                 NO DONOR RESULT
            ================================================= -->

            <section class="empty-state">


                <div class="empty-icon">
                    🩸
                </div>


                <h3>
                    No Donors Available in This Location
                </h3>


                <p>

                    We couldn't find any currently available
                    donors matching

                    <strong>
                        <?php
                        echo htmlspecialchars($blood_group);
                        ?>
                    </strong>

                    blood group in

                    <strong>
                        <?php
                        echo htmlspecialchars($city);
                        ?>
                    </strong>

                    right now.

                    <br><br>

                    Don't worry. Donor availability can change
                    as new donors register and existing donors
                    become available.

                    If you need blood, you can still create a
                    blood request with the patient's and
                    hospital's details. Your request will help
                    the system identify suitable donors when
                    they are available.

                    <br><br>

                    <strong>
                        💡 If your requirement is urgent,
                        please create a blood request with
                        accurate hospital and contact details.
                    </strong>

                </p>


                <a
                    href="create_request.php"
                    class="request-blood-btn"
                >
                    🩸 Request Blood
                </a>


            </section>


        <?php endif; ?>


    <?php endif; ?>


    <!-- =================================================
         BOTTOM NAVIGATION
    ================================================= -->

    <div class="bottom-actions">


        <a
            href="dashboard.php"
            class="bottom-btn dashboard-btn"
        >
            ← Dashboard
        </a>


        <a
            href="my_requests.php"
            class="bottom-btn"
        >
            🩸 My Requests
        </a>


        <a
            href="notifications.php"
            class="bottom-btn"
        >
            🔔 Notifications
        </a>


    </div>


</main>


</body>

</html>