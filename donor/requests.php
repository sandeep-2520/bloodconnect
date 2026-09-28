<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../config/database.php";

/* =========================================================
   AUTHENTICATION
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if (
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "donor"
) {
    header("Location: ../dashboard.php");
    exit;
}

$donor_id = (int) $_SESSION["user_id"];

$message = "";
$error = "";


/* =========================================================
   BLOOD GROUP COMPATIBILITY
   ========================================================= */

function canDonateBlood($donorGroup, $recipientGroup)
{
    $donorGroup = strtoupper(trim($donorGroup));
    $recipientGroup = strtoupper(trim($recipientGroup));

    /*
     * O- can donate to all blood groups
     */
    if ($donorGroup === "O-") {
        return true;
    }

    /*
     * O+ can donate to positive blood groups
     */
    if ($donorGroup === "O+") {
        return in_array(
            $recipientGroup,
            ["O+", "A+", "B+", "AB+"],
            true
        );
    }

    /*
     * All other groups can donate only
     * to the same blood group
     */
    return $donorGroup === $recipientGroup;
}


/* =========================================================
   SUCCESS MESSAGES
   ========================================================= */

if (isset($_GET["message"])) {

    $messages = [

        "donation_response_saved" =>
            "Your response has been saved. The requester has been notified.",

        "response_cancelled" =>
            "Your donation response has been cancelled.",

        "already_interested" =>
            "You have already volunteered for this request.",

        "already_selected" =>
            "You have already been selected for this request.",

        "no_active_response" =>
            "You do not have an active response for this request.",

        "request_shared" =>
            "Blood request shared successfully."
    ];

    if (isset($messages[$_GET["message"]])) {
        $message = $messages[$_GET["message"]];
    }
}


/* =========================================================
   ERROR MESSAGES
   ========================================================= */

if (isset($_GET["error"])) {

    $errors = [

        "invalid_request" =>
            "Invalid request.",

        "invalid_action" =>
            "Invalid action.",

        "request_not_found" =>
            "Request not found.",

        "request_closed" =>
            "This blood request is no longer accepting donors.",

        "profile_not_found" =>
            "Please complete your donor profile first.",

        "donor_unavailable" =>
            "You are currently marked as unavailable.",

        "response_failed" =>
            "Unable to save your response. Please try again.",

        "blood_group_not_compatible" =>
            "Your blood group is not compatible with this request. You can still share the request with another donor."
    ];

    if (isset($errors[$_GET["error"]])) {
        $error = $errors[$_GET["error"]];
    }
}


/* =========================================================
   GET DONOR PROFILE
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        blood_group,
        availability
    FROM donor_profiles
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $donor_id);
$stmt->execute();

$result = $stmt->get_result();
$donor = $result->fetch_assoc();

$stmt->close();


if (!$donor) {
    die("Donor profile not found.");
}


/* =========================================================
   GET ALL ACTIVE BLOOD REQUESTS
   ========================================================= */

$requests = [];

$sql = "

    SELECT

        br.id,
        br.blood_group,
        br.units_required,
        br.hospital_name,
        br.hospital_location,
        br.required_date,
        br.required_time,
        br.urgency,
        br.patient_name,
        br.additional_message,
        br.latitude,
        br.longitude,

        COALESCE(
            dr.response_status,
            'none'
        ) AS my_response

    FROM blood_requests br

    LEFT JOIN donor_responses dr

        ON dr.request_id = br.id

        AND dr.donor_id = ?

    WHERE br.status = 'searching'

    ORDER BY

        CASE br.urgency

            WHEN 'emergency' THEN 1

            WHEN 'urgent' THEN 2

            ELSE 3

        END,

        br.required_date ASC,

        br.required_time ASC

";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $donor_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

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
    Blood Requests | BloodConnect
</title>


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


/* =========================================================
   NAVBAR
   ========================================================= */

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


/* =========================================================
   NAV ACTIONS
   ========================================================= */

.nav-actions {

    display: flex;

    align-items: center;

    gap: 10px;
}


/* =========================================================
   DASHBOARD BUTTON
   ========================================================= */

.dashboard-btn {

    display: inline-block;

    text-decoration: none;

    background:
        rgba(170, 25, 45, 0.09);

    color: #92162b;

    padding: 10px 17px;

    border-radius: 10px;

    font-weight: 700;

    border:
        1px solid rgba(160,30,45,0.10);

    transition: 0.3s;
}

.dashboard-btn:hover {

    background: #a3162d;

    color: white;

    transform: translateY(-2px);
}


/* =========================================================
   LOGOUT
   ========================================================= */

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


/* =========================================================
   MAIN CONTAINER
   ========================================================= */

.container {

    width: 86%;

    max-width: 1250px;

    margin: auto;
}


/* =========================================================
   PAGE HERO
   ========================================================= */

.page-hero {

    margin-top: 35px;

    padding: 40px 45px;

    border-radius: 25px;

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            rgba(90, 0, 15, 0.76),
            rgba(90, 0, 15, 0.76)
        ),
        url("https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=1600&q=80");

    background-size: cover;

    background-position: center;

    color: white;

    box-shadow:
        0 15px 40px rgba(120, 20, 40, 0.12);
}

.page-hero-content {

    position: relative;

    z-index: 2;

    max-width: 850px;
}

.hero-tag {

    display: inline-block;

    background: rgba(255,255,255,0.18);

    border:
        1px solid rgba(255,255,255,0.25);

    padding: 8px 14px;

    border-radius: 50px;

    margin-bottom: 16px;

    font-size: 14px;

    backdrop-filter: blur(10px);
}

.page-hero h1 {

    font-size: 38px;

    line-height: 1.2;

    margin-bottom: 12px;
}

.page-hero p {

    color: #ffe9ed;

    font-size: 16px;

    line-height: 1.7;
}


/* =========================================================
   INFORMATION BOX
   ========================================================= */

.info-box {

    margin: 28px 0;

    padding: 24px 26px;

    border-radius: 20px;

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

    color: #684f54;

    line-height: 1.7;

    position: relative;

    overflow: hidden;
}

.info-box::before {

    content: "";

    position: absolute;

    width: 120px;

    height: 120px;

    border-radius: 50%;

    background:
        rgba(190, 35, 60, 0.06);

    right: -40px;

    top: -40px;
}

.info-title {

    color: #95172c;

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 8px;

    position: relative;

    z-index: 2;
}

.info-content {

    position: relative;

    z-index: 2;
}


/* =========================================================
   MESSAGES
   ========================================================= */

.message {

    background:
        rgba(232, 247, 237, 0.90);

    color: #287343;

    padding: 15px 18px;

    border-radius: 14px;

    margin-bottom: 20px;

    border:
        1px solid rgba(60, 130, 80, 0.15);

    box-shadow:
        0 8px 25px rgba(40, 100, 60, 0.05);

    font-weight: 600;
}

.error {

    background:
        rgba(255, 235, 238, 0.92);

    color: #9b1c31;

    padding: 15px 18px;

    border-radius: 14px;

    margin-bottom: 20px;

    border:
        1px solid rgba(170, 30, 50, 0.14);

    box-shadow:
        0 8px 25px rgba(120, 20, 40, 0.05);

    font-weight: 600;
}


/* =========================================================
   SECTION TITLE
   ========================================================= */

.requests-heading {

    margin: 35px 0 20px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.requests-heading h2 {

    color: #8e1528;

    font-size: 28px;
}

.requests-heading p {

    color: #80666b;

    font-size: 14px;
}


/* =========================================================
   REQUEST CARD
   ========================================================= */

.card {

    background:
        rgba(255,255,255,0.72);

    backdrop-filter: blur(15px);

    -webkit-backdrop-filter: blur(15px);

    border:
        1px solid rgba(160,30,45,0.10);

    border-radius: 20px;

    padding: 28px;

    margin-bottom: 22px;

    box-shadow:
        0 10px 30px rgba(100,20,30,0.05);

    transition: 0.3s;

    position: relative;

    overflow: hidden;
}

.card:hover {

    transform: translateY(-4px);

    box-shadow:
        0 18px 35px rgba(100,20,30,0.10);
}

.card::before {

    content: "";

    position: absolute;

    width: 120px;

    height: 120px;

    border-radius: 50%;

    background:
        rgba(170, 25, 45, 0.035);

    right: -40px;

    top: -45px;
}


/* =========================================================
   BADGES
   ========================================================= */

.badges {

    margin-bottom: 13px;

    position: relative;

    z-index: 2;
}

.badge {

    display: inline-block;

    padding: 7px 12px;

    border-radius: 50px;

    margin-right: 6px;

    margin-bottom: 6px;

    font-size: 13px;

    font-weight: 700;
}

.blood-badge {

    background:
        rgba(170, 25, 45, 0.09);

    color: #92162b;

    border:
        1px solid rgba(160,30,45,0.08);
}

.emergency-badge {

    background:
        rgba(192, 24, 53, 0.10);

    color: #a3162d;

    border:
        1px solid rgba(192,24,53,0.10);
}

.urgent-badge {

    background:
        rgba(230, 120, 30, 0.10);

    color: #a85a12;

    border:
        1px solid rgba(230,120,30,0.10);
}


/* =========================================================
   CARD TITLE
   ========================================================= */

.card h2 {

    color: #8e1528;

    font-size: 22px;

    margin: 5px 0 18px;

    position: relative;

    z-index: 2;
}


/* =========================================================
   DETAILS
   ========================================================= */

.details {

    line-height: 1.9;

    color: #5f4b50;

    position: relative;

    z-index: 2;
}

.details strong {

    color: #3f292d;
}


/* =========================================================
   HELP MESSAGE
   ========================================================= */

.help-message {

    background:
        rgba(255,248,225,0.82);

    border:
        1px solid rgba(230,170,50,0.20);

    color: #795548;

    padding: 14px 16px;

    border-radius: 13px;

    margin-top: 18px;

    line-height: 1.6;

    font-size: 14px;

    position: relative;

    z-index: 2;
}


/* =========================================================
   STATUS
   ========================================================= */

.status {

    background:
        rgba(45, 130, 75, 0.08);

    color: #287343;

    padding: 13px 16px;

    border-radius: 12px;

    margin-top: 18px;

    font-weight: 700;

    border:
        1px solid rgba(60, 130, 80, 0.10);

    position: relative;

    z-index: 2;
}


/* =========================================================
   BUTTON AREA
   ========================================================= */

.buttons {

    display: flex;

    gap: 10px;

    margin-top: 20px;

    flex-wrap: wrap;

    position: relative;

    z-index: 3;
}

button {

    border: none;

    padding: 12px 18px;

    border-radius: 11px;

    cursor: pointer;

    font-size: 14px;

    font-weight: 700;

    transition: 0.3s;

    font-family: inherit;
}

button:hover {

    transform: translateY(-2px);
}


/* =========================================================
   DONATE BUTTON
   ========================================================= */

.donate {

    background: #a3162d;

    color: white;

    box-shadow:
        0 6px 15px rgba(163,22,45,0.16);
}

.donate:hover {

    background: #7d0f21;

    box-shadow:
        0 9px 20px rgba(125,15,33,0.20);
}


/* =========================================================
   DISABLED DONATE
   ========================================================= */

.disabled-donate {

    background:
        #d8cfd1;

    color: #75696c;

    cursor: not-allowed;

    opacity: 0.9;

    box-shadow: none;
}

.disabled-donate:hover {

    background:
        #d8cfd1;

    transform: none;

    box-shadow: none;
}


/* =========================================================
   CANCEL BUTTON
   ========================================================= */

.cancel {

    background: #75676a;

    color: white;

    box-shadow:
        0 6px 15px rgba(80,60,65,0.12);
}

.cancel:hover {

    background: #574b4e;
}


/* =========================================================
   SHARE BUTTON
   ========================================================= */

.share {

    background:
        rgba(170, 25, 45, 0.09);

    color: #92162b;

    border:
        1px solid rgba(160,30,45,0.10);
}

.share:hover {

    background:
        rgba(170, 25, 45, 0.15);
}


/* =========================================================
   NO REQUESTS
   ========================================================= */

.empty-card {

    background:
        rgba(255,255,255,0.70);

    backdrop-filter: blur(15px);

    border:
        1px solid rgba(160,30,45,0.10);

    border-radius: 20px;

    padding: 45px 30px;

    margin: 25px 0 60px;

    text-align: center;

    box-shadow:
        0 10px 30px rgba(100,20,30,0.05);
}

.empty-icon {

    font-size: 48px;

    margin-bottom: 15px;
}

.empty-card h2 {

    color: #8e1528;

    margin-bottom: 10px;
}

.empty-card p {

    color: #80666b;

    line-height: 1.6;
}


/* =========================================================
   FOOTER
   ========================================================= */

footer {

    text-align: center;

    padding: 25px;

    margin-top: 40px;

    color: #80666b;

    font-size: 14px;

    border-top:
        1px solid rgba(160,30,45,0.08);

    background:
        rgba(255,255,255,0.45);
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .container {
        width: 90%;
    }

    .page-hero {
        padding: 35px;
    }

    .page-hero h1 {
        font-size: 33px;
    }

    .requests-heading {
        flex-direction: column;
        align-items: flex-start;
    }
}


@media (max-width: 600px) {

    .container {
        width: 92%;
    }

    .navbar {
        padding: 15px 4%;
    }

    .logo {
        font-size: 21px;
    }

    .nav-actions {
        gap: 6px;
    }

    .dashboard-btn,
    .logout {
        padding: 8px 11px;
        font-size: 13px;
    }

    .page-hero {

        margin-top: 22px;

        padding: 30px 24px;

        min-height: 260px;

        border-radius: 20px;
    }

    .page-hero h1 {
        font-size: 29px;
    }

    .page-hero p {
        font-size: 14px;
    }

    .info-box {
        padding: 20px;
    }

    .card {
        padding: 22px;
        border-radius: 18px;
    }

    .card h2 {
        font-size: 20px;
    }

    .buttons {
        flex-direction: column;
    }

    .buttons form {
        width: 100%;
    }

    .buttons button {
        width: 100%;
    }

    .share {
        width: 100%;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
     ========================================================= -->

<nav class="navbar">

    <a
        href="dashboard.php"
        class="logo"
    >
        🩸 BloodConnect
    </a>

    <div class="nav-actions">

        <a
            href="dashboard.php"
            class="dashboard-btn"
        >
            🏠 Dashboard
        </a>

        <a
            href="../auth/logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>


<div class="container">


    <!-- =====================================================
         PAGE HERO
         ===================================================== -->

    <section class="page-hero">

        <div class="page-hero-content">

            <div class="hero-tag">
                🩸 BloodConnect Donor
            </div>

            <h1>
                Blood Requests
            </h1>

            <p>
                Find people who need blood, respond when you
                can help, and share requests with others when
                you cannot donate yourself.
            </p>

        </div>

    </section>


    <!-- =====================================================
         INFORMATION
         ===================================================== -->

    <div class="info-box">

        <div class="info-title">
            🤝 Help Beyond Blood Matching
        </div>

        <div class="info-content">

            You can see <strong>all active blood requests</strong>,
            even when the requested blood group does not match
            your blood group.

            <br>

            You can donate only when your blood group is compatible
            with the requested blood group.

            If you cannot donate yourself, you can still share the
            request with a family member, friend, or another eligible
            donor.

        </div>

    </div>


    <!-- =====================================================
         SUCCESS MESSAGE
         ===================================================== -->

    <?php if ($message): ?>

        <div class="message">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ERROR MESSAGE
         ===================================================== -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         REQUEST HEADING
         ===================================================== -->

    <div class="requests-heading">

        <div>

            <h2>
                🩸 Active Blood Requests
            </h2>

            <p>
                Requests are arranged by urgency and required date.
            </p>

        </div>

    </div>


    <!-- =====================================================
         NO REQUESTS
         ===================================================== -->

    <?php if (empty($requests)): ?>

        <div class="empty-card">

            <div class="empty-icon">
                🩸
            </div>

            <h2>
                No Active Requests
            </h2>

            <p>
                There are currently no active blood requests.
                Please check again later.
            </p>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         REQUEST LIST
         ===================================================== -->

    <?php foreach ($requests as $request): ?>

        <?php

        $isCompatible = canDonateBlood(
            $donor["blood_group"],
            $request["blood_group"]
        );

        ?>


        <div class="card">


            <!-- =================================================
                 BADGES
                 ================================================= -->

            <div class="badges">

                <span class="badge blood-badge">

                    🩸 Blood Group:

                    <?= htmlspecialchars(
                        $request["blood_group"]
                    ) ?>

                </span>


                <?php if (
                    $request["urgency"] === "emergency"
                ): ?>

                    <span class="badge emergency-badge">

                        🚨 Emergency

                    </span>

                <?php elseif (
                    $request["urgency"] === "urgent"
                ): ?>

                    <span class="badge urgent-badge">

                        ⚠️ Urgent

                    </span>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 HOSPITAL
                 ================================================= -->

            <h2>

                <?= htmlspecialchars(
                    $request["hospital_name"]
                ) ?>

            </h2>


            <!-- =================================================
                 REQUEST DETAILS
                 ================================================= -->

            <div class="details">

                <strong>
                    Request #:
                </strong>

                <?= (int) $request["id"] ?>

                <br>

                <strong>
                    Blood Group Required:
                </strong>

                <?= htmlspecialchars(
                    $request["blood_group"]
                ) ?>

                <br>

                <strong>
                    Units Required:
                </strong>

                <?= (int) $request["units_required"] ?>

                <br>

                <strong>
                    Hospital:
                </strong>

                <?= htmlspecialchars(
                    $request["hospital_name"]
                ) ?>

                <br>

                <strong>
                    Location:
                </strong>

                <?= htmlspecialchars(
                    $request["hospital_location"]
                ) ?>

                <br>

                <strong>
                    Required Date:
                </strong>

                <?= htmlspecialchars(
                    $request["required_date"]
                ) ?>


                <?php if (
                    !empty($request["required_time"])
                ): ?>

                    <br>

                    <strong>
                        Required Time:
                    </strong>

                    <?= htmlspecialchars(
                        $request["required_time"]
                    ) ?>

                <?php endif; ?>


                <?php if (
                    !empty($request["patient_name"])
                ): ?>

                    <br>

                    <strong>
                        Patient:
                    </strong>

                    <?= htmlspecialchars(
                        $request["patient_name"]
                    ) ?>

                <?php endif; ?>


                <?php if (
                    !empty($request["additional_message"])
                ): ?>

                    <br>
                    <br>

                    <strong>
                        Message:
                    </strong>

                    <br>

                    <?= nl2br(
                        htmlspecialchars(
                            $request["additional_message"]
                        )
                    ) ?>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 HELP MESSAGE
                 ================================================= -->

            <div class="help-message">

                <?php if ($isCompatible): ?>

                    🩸
                    <strong>
                        You can donate for this request.
                    </strong>

                    Your blood group is compatible with the
                    requested blood group.

                <?php else: ?>

                    💡
                    <strong>
                        You cannot donate for this request.
                    </strong>

                    Your blood group is not compatible with the
                    requested blood group.

                    <br>

                    You can still share this request with another
                    eligible donor.

                <?php endif; ?>

            </div>


            <!-- =================================================
                 DONOR RESPONSE STATUS
                 ================================================= -->

            <?php if (
                $request["my_response"] === "interested"
            ): ?>


                <div class="status">

                    🩸 You have volunteered
                    for this request.

                </div>


                <div class="buttons">


                    <form
                        method="POST"
                        action="respond_request.php"
                        onsubmit="
                            return confirm(
                                'Cancel your donation response?'
                            );
                        "
                    >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?= (int) $request["id"] ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="cancel"
                        >

                        <button
                            type="submit"
                            class="cancel"
                        >

                            ❌ Cancel

                        </button>

                    </form>


                    <button
                        type="button"
                        class="share"
                        onclick="
                            shareRequest(
                                <?= (int) $request["id"] ?>,
                                '<?= htmlspecialchars(
                                    $request["blood_group"],
                                    ENT_QUOTES
                                ) ?>',
                                '<?= htmlspecialchars(
                                    $request["hospital_name"],
                                    ENT_QUOTES
                                ) ?>'
                            )
                        "
                    >

                        📤 Share Request

                    </button>


                </div>


            <?php elseif (
                $request["my_response"] === "accepted"
            ): ?>


                <div class="status">

                    ✅ You have been selected
                    as the donor.

                </div>


                <div class="buttons">

                    <button
                        type="button"
                        class="share"
                        onclick="
                            shareRequest(
                                <?= (int) $request["id"] ?>,
                                '<?= htmlspecialchars(
                                    $request["blood_group"],
                                    ENT_QUOTES
                                ) ?>',
                                '<?= htmlspecialchars(
                                    $request["hospital_name"],
                                    ENT_QUOTES
                                ) ?>'
                            )
                        "
                    >

                        📤 Share Request

                    </button>

                </div>


            <?php else: ?>


                <div class="buttons">


                    <!-- =================================================
                         DONATE
                         ================================================= -->

                    <?php if ($isCompatible): ?>

                        <form
                            method="POST"
                            action="respond_request.php"
                        >

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= (int) $request["id"] ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="donate"
                            >

                            <button
                                type="submit"
                                class="donate"
                            >

                                🩸 I Can Donate

                            </button>

                        </form>

                    <?php else: ?>

                        <button
                            type="button"
                            class="donate disabled-donate"
                            disabled
                        >

                            🚫 Blood Group Not Compatible

                        </button>

                    <?php endif; ?>


                    <!-- =================================================
                         SHARE
                         ================================================= -->

                    <button
                        type="button"
                        class="share"
                        onclick="
                            shareRequest(
                                <?= (int) $request["id"] ?>,
                                '<?= htmlspecialchars(
                                    $request["blood_group"],
                                    ENT_QUOTES
                                ) ?>',
                                '<?= htmlspecialchars(
                                    $request["hospital_name"],
                                    ENT_QUOTES
                                ) ?>'
                            )
                        "
                    >

                        📤 Share Request

                    </button>


                </div>


            <?php endif; ?>


        </div>

    <?php endforeach; ?>


</div>


<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer>

    © <?php echo date("Y"); ?> BloodConnect.
    Connecting donors with people who need blood.

</footer>


<script>

/* =========================================================
   SHARE REQUEST
   ========================================================= */

function shareRequest(
    requestId,
    bloodGroup,
    hospital
) {

    const shareText =
        "🩸 BloodConnect Blood Request\n\n" +

        "Blood Group: " +
        bloodGroup +

        "\nHospital: " +
        hospital +

        "\nRequest ID: #" +
        requestId +

        "\n\nA blood donor may be needed urgently. " +
        "Please check the BloodConnect request.";


    /*
     * Use native phone/browser sharing if available.
     */

    if (navigator.share) {

        navigator.share({

            title:
                "BloodConnect Blood Request",

            text:
                shareText

        }).catch(function () {

            /*
             * User cancelled sharing.
             */

        });

        return;
    }


    /*
     * Fallback:
     * Copy the request information.
     */

    if (navigator.clipboard) {

        navigator.clipboard
            .writeText(shareText)
            .then(function () {

                alert(
                    "Request details copied. " +
                    "You can now share them with your family or friends."
                );

            })
            .catch(function () {

                alert(shareText);

            });

    } else {

        alert(shareText);

    }

}

</script>


</body>

</html>