<?php

session_start();

require_once "../config/database.php";

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
| Get all donation records
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        d.id,
        d.request_id,
        d.units_donated,
        d.donation_date,
        d.hospital_name,
        d.status,
        d.notes,
        d.created_at,

        donor.name AS donor_name,
        donor.email AS donor_email,
        donor.phone AS donor_phone,
        dp.blood_group,

        requester.name AS requester_name,
        requester.email AS requester_email

    FROM donations d

    INNER JOIN donor_profiles dp
        ON d.donor_id = dp.id

    INNER JOIN users donor
        ON dp.user_id = donor.id

    INNER JOIN blood_requests br
        ON d.request_id = br.id

    INNER JOIN users requester
        ON br.requester_id = requester.id

    ORDER BY d.donation_date DESC
");

$stmt->execute();

$donations = $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BloodConnect - Donation Records</title>


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

    padding: 18px 6%;

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

    font-size: 23px;

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

.admin-badge {

    padding: 7px 14px;

    border-radius: 30px;

    font-size: 12px;

    font-weight: bold;

    background: rgba(255, 255, 255, 0.08);

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
   MAIN
========================================================= */

.container {

    width: 92%;

    max-width: 1500px;

    margin: auto;

    padding: 45px 0 70px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    gap: 25px;

    margin-bottom: 30px;
}

.header-label {

    display: inline-block;

    padding: 7px 13px;

    margin-bottom: 12px;

    border-radius: 20px;

    background: rgba(255, 40, 40, 0.10);

    border: 1px solid rgba(255, 80, 80, 0.18);

    color: #ff9b9b;

    font-size: 12px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.page-header h1 {

    font-size: clamp(30px, 5vw, 48px);

    line-height: 1.1;

    margin-bottom: 10px;
}

.page-header h1 span {

    color: #ff4a4a;
}

.page-header p {

    color: #aaa;

    font-size: 15px;

    line-height: 1.6;

    max-width: 720px;
}


/* =========================================================
   DONATION COUNT
========================================================= */

.donation-count {

    min-width: 175px;

    padding: 20px;

    border-radius: 18px;

    text-align: center;

    background: rgba(255, 255, 255, 0.055);

    border: 1px solid rgba(255, 255, 255, 0.09);

    backdrop-filter: blur(15px);
}

.donation-count-number {

    font-size: 32px;

    font-weight: bold;

    color: #ff5555;
}

.donation-count-label {

    margin-top: 5px;

    color: #aaa;

    font-size: 12px;
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card {

    background: rgba(255, 255, 255, 0.045);

    border: 1px solid rgba(255, 255, 255, 0.08);

    border-radius: 22px;

    overflow: hidden;

    backdrop-filter: blur(18px);

    box-shadow:
        0 25px 70px rgba(0, 0, 0, 0.30);
}


/* =========================================================
   TABLE HEADER
========================================================= */

.table-top {

    padding: 20px 24px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.table-title {

    font-size: 18px;

    font-weight: bold;
}

.table-subtitle {

    margin-top: 5px;

    color: #888;

    font-size: 12px;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 1500px;

    border-collapse: collapse;
}

thead {

    background: rgba(255, 0, 0, 0.06);
}

th {

    padding: 16px 18px;

    text-align: left;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 0.6px;

    color: #999;

    white-space: nowrap;
}

td {

    padding: 17px 18px;

    border-top: 1px solid rgba(255, 255, 255, 0.055);

    color: #ddd;

    font-size: 13px;

    white-space: nowrap;
}

tbody tr {

    transition: 0.25s;
}

tbody tr:hover {

    background: rgba(255, 255, 255, 0.035);
}


/* =========================================================
   IDs
========================================================= */

.record-id {

    width: 45px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    background: rgba(255, 255, 255, 0.07);

    color: #aaa;

    font-size: 12px;
}

.request-id {

    display: inline-block;

    padding: 7px 10px;

    border-radius: 8px;

    background: rgba(255, 255, 255, 0.055);

    color: #bbb;

    font-size: 12px;
}


/* =========================================================
   DONOR
========================================================= */

.donor-name {

    color: #ffffff;

    font-weight: bold;
}

.donor-email {

    color: #888;

    font-size: 11px;

    margin-top: 4px;
}

.donor-phone {

    color: #888;

    font-size: 11px;

    margin-top: 4px;
}


/* =========================================================
   BLOOD GROUP
========================================================= */

.blood-group {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 60px;

    padding: 9px 12px;

    border-radius: 10px;

    background: rgba(255, 40, 40, 0.12);

    border: 1px solid rgba(255, 70, 70, 0.20);

    color: #ff7777;

    font-weight: bold;

    font-size: 13px;
}


/* =========================================================
   REQUESTER
========================================================= */

.requester-name {

    color: #ffffff;

    font-weight: bold;
}

.requester-email {

    color: #888;

    font-size: 11px;

    margin-top: 4px;
}


/* =========================================================
   UNITS
========================================================= */

.units {

    font-size: 17px;

    font-weight: bold;

    color: #ffffff;
}

.units-label {

    color: #777;

    font-size: 10px;

    margin-left: 3px;
}


/* =========================================================
   DONATION DATE
========================================================= */

.donation-date {

    color: #ffffff;

    font-weight: bold;
}

.donation-day {

    color: #888;

    font-size: 11px;

    margin-top: 4px;
}


/* =========================================================
   HOSPITAL
========================================================= */

.hospital-name {

    color: #ffffff;

    font-weight: bold;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 8px 12px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    white-space: nowrap;
}

.status-completed {

    background: rgba(0, 190, 100, 0.10);

    color: #66e5a4;

    border: 1px solid rgba(0, 190, 100, 0.17);
}

.status-scheduled {

    background: rgba(255, 170, 0, 0.11);

    color: #ffc766;

    border: 1px solid rgba(255, 170, 0, 0.17);
}

.status-other {

    background: rgba(255, 60, 60, 0.10);

    color: #ff7777;

    border: 1px solid rgba(255, 60, 60, 0.17);
}


/* =========================================================
   NOTES
========================================================= */

.notes {

    max-width: 220px;

    white-space: normal;

    line-height: 1.5;

    color: #aaa;

    font-size: 12px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    padding: 80px 20px;

    text-align: center;

    color: #888;
}

.empty-icon {

    font-size: 50px;

    margin-bottom: 15px;
}

.empty-state h3 {

    color: #ddd;

    margin-bottom: 8px;

    font-size: 20px;
}

.empty-state p {

    color: #777;

    font-size: 13px;
}


/* =========================================================
   BOTTOM NAVIGATION
========================================================= */

.bottom-actions {

    display: flex;

    justify-content: center;

    gap: 14px;

    margin-top: 30px;

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

    .admin-badge {

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

    .page-header {

        flex-direction: column;

        align-items: flex-start;
    }

    .donation-count {

        width: 100%;
    }

    .table-card {

        border-radius: 16px;
    }

    .table-top {

        padding: 17px;
    }

    th,
    td {

        padding: 13px 14px;
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

        <span class="admin-badge">
            🛡️ ADMIN
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


    <!-- PAGE HEADER -->

    <section class="page-header">


        <div>

            <span class="header-label">
                🤝 Donation Management
            </span>

            <h1>
                Donation <span>Records</span>
            </h1>

            <p>
                Monitor completed and scheduled donations,
                donor information, blood groups, hospitals and
                connected blood requests.
            </p>

        </div>


        <div class="donation-count">

            <div class="donation-count-number">

                <?php echo $donations->num_rows; ?>

            </div>

            <div class="donation-count-label">

                Total Donations

            </div>

        </div>


    </section>


    <!-- =================================================
         DONATION TABLE
    ================================================= -->

    <section class="table-card">


        <div class="table-top">

            <div>

                <div class="table-title">
                    All Donation Records
                </div>

                <div class="table-subtitle">
                    Donation activity recorded through BloodConnect
                </div>

            </div>

        </div>


        <?php if ($donations->num_rows > 0): ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                    <tr>

                        <th>
                            Donation
                        </th>

                        <th>
                            Request
                        </th>

                        <th>
                            Donor
                        </th>

                        <th>
                            Blood
                        </th>

                        <th>
                            Requester
                        </th>

                        <th>
                            Units
                        </th>

                        <th>
                            Donation Date
                        </th>

                        <th>
                            Hospital
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Notes
                        </th>

                        <th>
                            Created
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php while ($donation = $donations->fetch_assoc()): ?>


                        <tr>


                            <!-- DONATION ID -->

                            <td>

                                <div class="record-id">

                                    #<?php
                                    echo htmlspecialchars(
                                        $donation["id"]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- REQUEST ID -->

                            <td>

                                <span class="request-id">

                                    #<?php
                                    echo htmlspecialchars(
                                        $donation["request_id"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- DONOR -->

                            <td>

                                <div class="donor-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["donor_name"]
                                    );
                                    ?>

                                </div>

                                <div class="donor-email">

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["donor_email"]
                                    );
                                    ?>

                                </div>

                                <div class="donor-phone">

                                    📞
                                    <?php
                                    echo htmlspecialchars(
                                        $donation["donor_phone"]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- BLOOD GROUP -->

                            <td>

                                <span class="blood-group">

                                    🩸
                                    <?php
                                    echo htmlspecialchars(
                                        $donation["blood_group"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- REQUESTER -->

                            <td>

                                <div class="requester-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["requester_name"]
                                    );
                                    ?>

                                </div>

                                <div class="requester-email">

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["requester_email"]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- UNITS -->

                            <td>

                                <span class="units">

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["units_donated"]
                                    );
                                    ?>

                                </span>

                                <span class="units-label">
                                    units
                                </span>

                            </td>


                            <!-- DONATION DATE -->

                            <td>

                                <div class="donation-date">

                                    📅

                                    <?php

                                    echo htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $donation["donation_date"]
                                            )
                                        )
                                    );

                                    ?>

                                </div>


                                <div class="donation-day">

                                    <?php

                                    echo htmlspecialchars(
                                        date(
                                            "l",
                                            strtotime(
                                                $donation["donation_date"]
                                            )
                                        )
                                    );

                                    ?>

                                </div>

                            </td>


                            <!-- HOSPITAL -->

                            <td>

                                <div class="hospital-name">

                                    🏥

                                    <?php
                                    echo htmlspecialchars(
                                        $donation["hospital_name"]
                                    );
                                    ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php

                                if (
                                    $donation["status"]
                                    == "completed"
                                ):

                                ?>

                                    <span
                                        class="status
                                        status-completed"
                                    >

                                        🟢 Completed

                                    </span>


                                <?php
                                elseif (
                                    $donation["status"]
                                    == "scheduled"
                                ):
                                ?>

                                    <span
                                        class="status
                                        status-scheduled"
                                    >

                                        🟡 Scheduled

                                    </span>


                                <?php else: ?>

                                    <span
                                        class="status
                                        status-other"
                                    >

                                        🔴

                                        <?php
                                        echo htmlspecialchars(
                                            $donation["status"]
                                        );
                                        ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- NOTES -->

                            <td>

                                <div class="notes">

                                    <?php

                                    if (
                                        !empty(
                                            $donation["notes"]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $donation["notes"]
                                        );

                                    } else {

                                        echo "No notes";

                                    }

                                    ?>

                                </div>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <div style="color:#ddd;">

                                    <?php

                                    echo htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $donation["created_at"]
                                            )
                                        )
                                    );

                                    ?>

                                </div>

                                <div
                                    style="
                                    color:#777;
                                    font-size:11px;
                                    margin-top:4px;
                                    "
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        date(
                                            "h:i A",
                                            strtotime(
                                                $donation["created_at"]
                                            )
                                        )
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


            <div class="empty-state">

                <div class="empty-icon">
                    🤝
                </div>

                <h3>
                    No Donation Records
                </h3>

                <p>
                    There are currently no donation records in the system.
                </p>

            </div>


        <?php endif; ?>


    </section>


    <!-- =================================================
         BOTTOM NAVIGATION
    ================================================= -->

    <div class="bottom-actions">


        <a
            href="dashboard.php"
            class="bottom-btn dashboard-btn"
        >
            ← Admin Dashboard
        </a>


        <a
            href="users.php"
            class="bottom-btn"
        >
            👥 Manage Users
        </a>


        <a
            href="requests.php"
            class="bottom-btn"
        >
            🩸 Blood Requests
        </a>


    </div>


</main>


</body>

</html>