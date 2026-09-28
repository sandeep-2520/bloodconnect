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
    $_SESSION["user_role"] !== "requester"
) {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$message = "";
$error = "";


/* =========================================================
   HANDLE FORM SUBMISSION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $blood_group = trim($_POST["blood_group"] ?? "");

    $units_required = (int) ($_POST["units_required"] ?? 0);

    $hospital_name = trim($_POST["hospital_name"] ?? "");

    $hospital_location = trim(
        $_POST["hospital_location"] ?? ""
    );

    $required_date = trim(
        $_POST["required_date"] ?? ""
    );

    $required_time = trim(
        $_POST["required_time"] ?? ""
    );

    $urgency = strtolower(
        trim($_POST["urgency"] ?? "")
    );

    $patient_name = trim(
        $_POST["patient_name"] ?? ""
    );

    $contact_phone = trim(
        $_POST["contact_phone"] ?? ""
    );

    $additional_message = trim(
        $_POST["additional_message"] ?? ""
    );

    $latitude_raw = trim(
        $_POST["latitude"] ?? ""
    );

    $longitude_raw = trim(
        $_POST["longitude"] ?? ""
    );


    /* =====================================================
       BASIC VALIDATION
       ===================================================== */

    if (
        $blood_group === "" ||
        $units_required <= 0 ||
        $hospital_name === "" ||
        $hospital_location === "" ||
        $required_date === "" ||
        $required_time === "" ||
        $urgency === "" ||
        $patient_name === "" ||
        $contact_phone === ""
    ) {

        $_SESSION["error"] =
            "Please fill in all required fields.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       UNITS VALIDATION
       ===================================================== */

    if (
        $units_required < 1 ||
        $units_required > 50
    ) {

        $_SESSION["error"] =
            "Units required must be between 1 and 50.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       BLOOD GROUP VALIDATION
       ===================================================== */

    $allowed_blood_groups = [
        "A+",
        "A-",
        "B+",
        "B-",
        "AB+",
        "AB-",
        "O+",
        "O-"
    ];

    if (
        !in_array(
            $blood_group,
            $allowed_blood_groups,
            true
        )
    ) {

        $_SESSION["error"] =
            "Invalid blood group selected.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       URGENCY VALIDATION
       ===================================================== */

    $allowed_urgencies = [
        "normal",
        "urgent",
        "emergency"
    ];

    if (
        !in_array(
            $urgency,
            $allowed_urgencies,
            true
        )
    ) {

        $_SESSION["error"] =
            "Invalid urgency selected.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       DATE VALIDATION
       ===================================================== */

    $date_object = DateTime::createFromFormat(
        "Y-m-d",
        $required_date
    );

    if (
        !$date_object ||
        $date_object->format("Y-m-d") !== $required_date
    ) {

        $_SESSION["error"] =
            "Invalid required date.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       TIME VALIDATION
       ===================================================== */

    $time_object = DateTime::createFromFormat(
        "H:i",
        $required_time
    );

    if (
        !$time_object ||
        $time_object->format("H:i") !== $required_time
    ) {

        $_SESSION["error"] =
            "Invalid required time.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       PHONE VALIDATION
       ===================================================== */

    $phone_clean = preg_replace(
        "/[^0-9+]/",
        "",
        $contact_phone
    );

    if (
        strlen($phone_clean) < 10 ||
        strlen($phone_clean) > 15
    ) {

        $_SESSION["error"] =
            "Please enter a valid contact phone number.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       MAP COORDINATE VALIDATION
       ===================================================== */

    if (
        $latitude_raw === "" ||
        $longitude_raw === "" ||
        !is_numeric($latitude_raw) ||
        !is_numeric($longitude_raw)
    ) {

        $_SESSION["error"] =
            "Please select the hospital location on the map before submitting the request.";

        header("Location: create_request.php");
        exit;
    }


    $latitude = (float) $latitude_raw;

    $longitude = (float) $longitude_raw;


    /* =====================================================
       CHECK COORDINATE RANGE
       ===================================================== */

    if (
        !is_finite($latitude) ||
        !is_finite($longitude) ||
        $latitude < -90 ||
        $latitude > 90 ||
        $longitude < -180 ||
        $longitude > 180
    ) {

        $_SESSION["error"] =
            "Invalid hospital coordinates. Please select the hospital again.";

        header("Location: create_request.php");
        exit;
    }


    /* =====================================================
       CREATE BLOOD REQUEST
       ===================================================== */

    try {

        $stmt = $conn->prepare(
            "INSERT INTO blood_requests
            (
                requester_id,
                blood_group,
                units_required,
                hospital_name,
                hospital_location,
                required_date,
                required_time,
                urgency,
                patient_name,
                contact_phone,
                additional_message,
                latitude,
                longitude
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );


        if (!$stmt) {

            throw new Exception(
                "Database prepare error: " .
                $conn->error
            );
        }


        /*
         * i = requester_id
         * s = blood_group
         * i = units_required
         * s = hospital_name
         * s = hospital_location
         * s = required_date
         * s = required_time
         * s = urgency
         * s = patient_name
         * s = contact_phone
         * s = additional_message
         * d = latitude
         * d = longitude
         */

        $stmt->bind_param(
            "isissssssssdd",
            $user_id,
            $blood_group,
            $units_required,
            $hospital_name,
            $hospital_location,
            $required_date,
            $required_time,
            $urgency,
            $patient_name,
            $contact_phone,
            $additional_message,
            $latitude,
            $longitude
        );


        if (!$stmt->execute()) {

            $db_error = $stmt->error;

            $stmt->close();

            throw new Exception(
                "Error creating blood request: " .
                $db_error
            );
        }


        /* =================================================
           GET REQUEST ID
           ================================================= */

        $request_id = $stmt->insert_id;

        $stmt->close();


    } catch (Exception $e) {

        die(
            "Database error while creating blood request: " .
            htmlspecialchars(
                $e->getMessage()
            )
        );
    }


    /* =====================================================
       SET REQUEST STATUS TO SEARCHING
       ===================================================== */

    try {

        $status_stmt = $conn->prepare(
            "UPDATE blood_requests
             SET status = 'searching'
             WHERE id = ?"
        );


        if (!$status_stmt) {

            throw new Exception(
                "Status update prepare error: " .
                $conn->error
            );
        }


        $status_stmt->bind_param(
            "i",
            $request_id
        );


        if (!$status_stmt->execute()) {

            $status_error =
                $status_stmt->error;

            $status_stmt->close();

            throw new Exception(
                "Error updating request status: " .
                $status_error
            );
        }


        $status_stmt->close();


    } catch (Exception $e) {

        die(
            "Database error while updating request status: " .
            htmlspecialchars(
                $e->getMessage()
            )
        );
    }


    /* =====================================================
       REQUESTER NOTIFICATION
       
       Your notifications table contains:
       user_id
       request_id
       title
       message
       type
       is_read
       created_at
       ===================================================== */

    try {

        $notification_title =
            "Blood Request Created";

        $notification_message =
            "Your blood request #{$request_id} has been created successfully. Donor search has started.";

        $notification_type =
            "blood_request";


        $notification_stmt = $conn->prepare(
            "INSERT INTO notifications
            (
                user_id,
                request_id,
                title,
                message,
                type,
                is_read,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );


        if ($notification_stmt) {

            $notification_stmt->bind_param(
                "iisss",
                $user_id,
                $request_id,
                $notification_title,
                $notification_message,
                $notification_type
            );


            $notification_stmt->execute();

            $notification_stmt->close();
        }

    } catch (Exception $e) {

        /*
         * Notification failure should not
         * prevent the request from being created.
         */

        error_log(
            "Requester notification error: " .
            $e->getMessage()
        );
    }


    /* =====================================================
       FIND ELIGIBLE DONORS
       =====================================================

       Donor compatibility:

       A+  <- A+, A-, O+, O-
       A-  <- A-, O-
       B+  <- B+, B-, O+, O-
       B-  <- B-, O-
       AB+ <- A+, A-, B+, B-, AB+, AB-, O+, O-
       AB- <- A-, B-, AB-, O-
       O+  <- O+, O-
       O-  <- O-

       We use donor blood group -> requested group.
       ===================================================== */

    $eligible_donor_groups = [];


    switch ($blood_group) {

        case "A+":
            $eligible_donor_groups = [
                "A+",
                "A-",
                "O+",
                "O-"
            ];
            break;


        case "A-":
            $eligible_donor_groups = [
                "A-",
                "O-"
            ];
            break;


        case "B+":
            $eligible_donor_groups = [
                "B+",
                "B-",
                "O+",
                "O-"
            ];
            break;


        case "B-":
            $eligible_donor_groups = [
                "B-",
                "O-"
            ];
            break;


        case "AB+":
            $eligible_donor_groups = [
                "A+",
                "A-",
                "B+",
                "B-",
                "AB+",
                "AB-",
                "O+",
                "O-"
            ];
            break;


        case "AB-":
            $eligible_donor_groups = [
                "A-",
                "B-",
                "AB-",
                "O-"
            ];
            break;


        case "O+":
            $eligible_donor_groups = [
                "O+",
                "O-"
            ];
            break;


        case "O-":
            $eligible_donor_groups = [
                "O-"
            ];
            break;
    }


    /* =====================================================
       BROADCAST REQUEST TO ELIGIBLE DONORS
       ===================================================== */

    if (!empty($eligible_donor_groups)) {

        try {

            /*
             * Create placeholders:
             *
             * ?, ?, ?, ?
             */

            $placeholders = implode(
                ",",
                array_fill(
                    0,
                    count($eligible_donor_groups),
                    "?"
                )
            );


            /*
             * Every blood group is a string.
             */

            $types =
                str_repeat(
                    "s",
                    count($eligible_donor_groups)
                );


            $sql = "
                SELECT
                    u.id

                FROM users u

                INNER JOIN donor_profiles dp
                    ON dp.user_id = u.id

                WHERE u.role = 'donor'
                  AND u.status = 'active'
                  AND dp.availability = 'available'
                  AND dp.blood_group IN ($placeholders)
            ";


            $donor_stmt =
                $conn->prepare(
                    $sql
                );


            if (!$donor_stmt) {

                throw new Exception(
                    "Donor search prepare error: " .
                    $conn->error
                );
            }


            /*
             * mysqli bind_param requires references.
             */

            $bind_values = [];

            $bind_values[] =
                $types;


            foreach (
                $eligible_donor_groups
                as $key => $group
            ) {

                $bind_values[] =
                    &$eligible_donor_groups[$key];
            }


            call_user_func_array(
                [
                    $donor_stmt,
                    "bind_param"
                ],
                $bind_values
            );


            if (!$donor_stmt->execute()) {

                throw new Exception(
                    "Unable to find eligible donors: " .
                    $donor_stmt->error
                );
            }


            $donor_result =
                $donor_stmt->get_result();


            $donor_ids = [];


            while (
                $donor_row =
                $donor_result->fetch_assoc()
            ) {

                $donor_ids[] =
                    (int) $donor_row["id"];
            }


            $donor_stmt->close();


            /* =================================================
               NOTIFY EVERY ELIGIBLE DONOR
               ================================================= */

            if (!empty($donor_ids)) {

                $notification_title =
                    "New Blood Donation Request";


                $notification_message =
                    "A new blood request needs " .
                    $units_required .
                    " unit(s) of " .
                    $blood_group .
                    " blood at " .
                    $hospital_name .
                    ". Open the request to see the details and respond.";


                $notification_type =
                    "blood_request";


                foreach (
                    $donor_ids
                    as $donor_id
                ) {

                    /*
                     * Do not notify the requester
                     * if they somehow have donor role.
                     */

                    if (
                        $donor_id === $user_id
                    ) {
                        continue;
                    }


                    $donor_notification =
                        $conn->prepare(
                            "INSERT INTO notifications
                            (
                                user_id,
                                request_id,
                                title,
                                message,
                                type,
                                is_read,
                                created_at
                            )
                            VALUES (?, ?, ?, ?, ?, 0, NOW())"
                        );


                    if (!$donor_notification) {

                        error_log(
                            "Donor notification prepare error: " .
                            $conn->error
                        );

                        continue;
                    }


                    $donor_notification->bind_param(
                        "iisss",
                        $donor_id,
                        $request_id,
                        $notification_title,
                        $notification_message,
                        $notification_type
                    );


                    if (
                        !$donor_notification->execute()
                    ) {

                        error_log(
                            "Donor notification error: " .
                            $donor_notification->error
                        );
                    }


                    $donor_notification->close();
                }
            }


        } catch (Exception $e) {

            /*
             * The blood request has already been created.
             * Therefore notification failure should not
             * delete the request.
             */

            error_log(
                "Donor broadcast error: " .
                $e->getMessage()
            );
        }
    }


    /* =====================================================
       SUCCESS
       ===================================================== */

    $_SESSION["success"] =
        "Blood request created successfully. Eligible donors have been notified.";

    header(
        "Location: my_requests.php"
    );

    exit;
}


/* =========================================================
   SESSION MESSAGES
   ========================================================= */

if (isset($_SESSION["error"])) {

    $error =
        $_SESSION["error"];

    unset(
        $_SESSION["error"]
    );
}


if (isset($_SESSION["success"])) {

    $message =
        $_SESSION["success"];

    unset(
        $_SESSION["success"]
    );
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

<title>Create Blood Request - BloodConnect</title>


<!-- =====================================================
     LEAFLET CSS
     ===================================================== -->

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
/>


<style>

/* =========================================================
   RESET
   ========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================================================
   BODY
   ========================================================= */

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top left,
            rgba(180, 30, 50, 0.18),
            transparent 35%
        ),
        #0d0b0c;

    color: #f5f5f5;

    min-height: 100vh;
}


/* =========================================================
   PAGE
   ========================================================= */

.page-container {

    width: 100%;

    max-width: 1100px;

    margin: auto;

    padding:
        30px
        20px
        50px;
}


/* =========================================================
   HEADER
   ========================================================= */

.top-header {

    display: flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap: 20px;

    margin-bottom: 30px;
}


.brand {

    display: flex;

    align-items: center;

    gap: 12px;
}


.brand-icon {

    width: 48px;

    height: 48px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(
            220,
            38,
            38,
            0.15
        );

    font-size: 25px;
}


.brand h1 {

    font-size: 23px;

    margin-bottom: 4px;
}


.brand p {

    color: #a99da0;

    font-size: 12px;
}


.back-btn {

    text-decoration: none;

    color: #f5f5f5;

    padding:
        10px
        15px;

    border-radius: 10px;

    background:
        rgba(
            255,
            255,
            255,
            0.06
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.08
        );
}


.back-btn:hover {

    background:
        rgba(
            255,
            255,
            255,
            0.1
        );
}


/* =========================================================
   CARD
   ========================================================= */

.request-card {

    background:
        rgba(
            24,
            20,
            21,
            0.95
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.08
        );

    border-radius: 22px;

    padding: 30px;

    box-shadow:
        0 20px 60px
        rgba(
            0,
            0,
            0,
            0.35
        );
}


.page-title {

    margin-bottom: 8px;

    font-size: 28px;
}


.page-description {

    color: #9f9497;

    font-size: 13px;

    margin-bottom: 28px;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {

    padding:
        13px
        15px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 13px;
}


.alert-error {

    background:
        rgba(
            239,
            68,
            68,
            0.12
        );

    border:
        1px solid
        rgba(
            239,
            68,
            68,
            0.25
        );

    color: #fca5a5;
}


.alert-success {

    background:
        rgba(
            34,
            197,
            94,
            0.12
        );

    border:
        1px solid
        rgba(
            34,
            197,
            94,
            0.25
        );

    color: #86efac;
}


/* =========================================================
   FORM
   ========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 20px;
}


.form-group {

    display: flex;

    flex-direction: column;

    gap: 8px;
}


.form-group.full {

    grid-column:
        1 / -1;
}


label {

    font-size: 12px;

    font-weight: 600;

    color: #ddd4d6;
}


label span {

    color: #ef4444;
}


/* =========================================================
   INPUTS
   ========================================================= */

input,
select,
textarea {

    width: 100%;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.10
        );

    outline: none;

    border-radius: 11px;

    padding:
        13px
        14px;

    background: #151213;

    color: #f5f5f5;

    font-size: 13px;
}


input:focus,
select:focus,
textarea:focus {

    border-color:
        rgba(
            239,
            68,
            68,
            0.65
        );

    box-shadow:
        0 0 0 3px
        rgba(
            239,
            68,
            68,
            0.08
        );
}


textarea {

    resize: vertical;

    min-height: 110px;
}


input::placeholder,
textarea::placeholder {

    color: #6f6669;
}


/* =========================================================
   LOCATION SEARCH
   ========================================================= */

.location-search {

    display: flex;

    gap: 10px;
}


.location-search input {

    flex: 1;
}


.search-btn {

    border: none;

    border-radius: 11px;

    padding:
        0
        18px;

    background: #dc2626;

    color: white;

    cursor: pointer;

    font-weight: 700;

    white-space: nowrap;
}


.search-btn:hover {

    background: #b91c1c;
}


.search-btn:disabled {

    opacity: 0.6;

    cursor: not-allowed;
}


.location-help {

    margin-top: 8px;

    color: #8f7d82;

    font-size: 11px;

    line-height: 1.5;
}


/* =========================================================
   SEARCH RESULTS
   ========================================================= */

.search-results {

    margin-top: 10px;

    border-radius: 12px;

    overflow: hidden;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.08
        );

    display: none;
}


.search-result {

    padding:
        13px
        14px;

    background: #171415;

    border-bottom:
        1px solid
        rgba(
            255,
            255,
            255,
            0.06
        );

    cursor: pointer;

    transition: 0.2s;
}


.search-result:last-child {

    border-bottom: none;
}


.search-result:hover {

    background: #241e20;
}


.result-title {

    color: #f3eeee;

    font-size: 12px;

    font-weight: 600;

    margin-bottom: 5px;
}


.result-address {

    color: #958b8e;

    font-size: 10px;

    line-height: 1.5;
}


/* =========================================================
   MAP
   ========================================================= */

.map-container {

    margin-top: 14px;

    width: 100%;

    height: 380px;

    border-radius: 18px;

    overflow: hidden;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.12
        );

    box-shadow:
        0 15px 40px
        rgba(
            0,
            0,
            0,
            0.3
        );
}


#hospitalMap {

    width: 100%;

    height: 100%;
}


/* =========================================================
   LOCATION STATUS
   ========================================================= */

.location-status {

    margin-top: 9px;

    padding:
        10px
        12px;

    border-radius: 10px;

    background:
        rgba(
            255,
            255,
            255,
            0.04
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.07
        );

    color: #aaa0a3;

    font-size: 11px;

    line-height: 1.5;
}


.location-status.success {

    background:
        rgba(
            34,
            197,
            94,
            0.08
        );

    border-color:
        rgba(
            34,
            197,
            94,
            0.2
        );

    color: #86efac;
}


.location-status.error {

    background:
        rgba(
            239,
            68,
            68,
            0.08
        );

    border-color:
        rgba(
            239,
            68,
            68,
            0.2
        );

    color: #fca5a5;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary-card {

    margin-top: 25px;

    padding: 18px;

    border-radius: 15px;

    background:
        rgba(
            255,
            255,
            255,
            0.025
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.07
        );
}


.summary-title {

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 14px;
}


.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 12px;
}


.summary-item {

    padding: 12px;

    border-radius: 10px;

    background:
        rgba(
            0,
            0,
            0,
            0.16
        );
}


.summary-label {

    font-size: 10px;

    color: #8f8588;

    margin-bottom: 5px;

    text-transform: uppercase;
}


.summary-value {

    font-size: 12px;

    color: #e7e0e2;

    word-break: break-word;
}


/* =========================================================
   SUBMIT
   ========================================================= */

.submit-area {

    margin-top: 25px;

    display: flex;

    justify-content: flex-end;
}


.submit-btn {

    border: none;

    outline: none;

    cursor: pointer;

    border-radius: 12px;

    padding:
        14px
        22px;

    background: #dc2626;

    color: white;

    font-weight: 700;

    font-size: 13px;

    min-width: 220px;
}


.submit-btn:hover {

    background: #b91c1c;
}


.submit-btn:disabled {

    cursor: not-allowed;

    opacity: 0.65;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 700px) {

    .page-container {

        padding:
            20px
            12px
            40px;
    }


    .request-card {

        padding: 20px;
    }


    .top-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }


    .form-grid {

        grid-template-columns:
            1fr;
    }


    .form-group.full {

        grid-column: auto;
    }


    .summary-grid {

        grid-template-columns:
            1fr;
    }


    .map-container {

        height: 320px;
    }


    .location-search {

        flex-direction:
            column;
    }


    .search-btn {

        height: 45px;
    }


    .submit-area {

        justify-content:
            stretch;
    }


    .submit-btn {

        width: 100%;
    }

}

</style>

</head>


<body>


<div class="page-container">


    <!-- =================================================
         HEADER
         ================================================= -->

    <div class="top-header">

        <div class="brand">

            <div class="brand-icon">
                🩸
            </div>

            <div>

                <h1>
                    BloodConnect
                </h1>

                <p>
                    Connecting blood donors with people in need
                </p>

            </div>

        </div>


        <a
            href="../requester/dashboard.php"
            class="back-btn"
        >
            ← Dashboard
        </a>

    </div>


    <!-- =================================================
         REQUEST CARD
         ================================================= -->

    <div class="request-card">


        <h2 class="page-title">
            Create Blood Request
        </h2>


        <p class="page-description">

            Enter the patient and blood requirement details.
            Search for the hospital and select the exact
            location on the map.

        </p>


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-error">

                ⚠️
                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- SUCCESS -->

        <?php if ($message !== ""): ?>

            <div class="alert alert-success">

                ✅
                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FORM
             ================================================= -->

        <form
            method="POST"
            id="requestForm"
        >


            <div class="form-grid">


                <!-- BLOOD GROUP -->

                <div class="form-group">

                    <label for="blood_group">

                        Blood Group

                        <span>*</span>

                    </label>


                    <select
                        name="blood_group"
                        id="blood_group"
                        required
                    >

                        <option value="">
                            Select blood group
                        </option>

                        <option value="A+">
                            A+
                        </option>

                        <option value="A-">
                            A-
                        </option>

                        <option value="B+">
                            B+
                        </option>

                        <option value="B-">
                            B-
                        </option>

                        <option value="AB+">
                            AB+
                        </option>

                        <option value="AB-">
                            AB-
                        </option>

                        <option value="O+">
                            O+
                        </option>

                        <option value="O-">
                            O-
                        </option>

                    </select>

                </div>


                <!-- UNITS -->

                <div class="form-group">

                    <label for="units_required">

                        Units Required

                        <span>*</span>

                    </label>


                    <input
                        type="number"
                        name="units_required"
                        id="units_required"
                        min="1"
                        max="50"
                        placeholder="Example: 2"
                        required
                    >

                </div>


                <!-- HOSPITAL -->

                <div class="form-group full">

                    <label for="hospital_name">

                        Hospital Name

                        <span>*</span>

                    </label>


                    <input
                        type="text"
                        name="hospital_name"
                        id="hospital_name"
                        placeholder="Example: Apollo Hospital"
                        required
                    >

                </div>


                <!-- =================================================
                     HOSPITAL LOCATION
                     ================================================= -->

                <div class="form-group full">


                    <label for="hospital_location">

                        Hospital Location

                        <span>*</span>

                    </label>


                    <div class="location-search">


                        <input
                            type="text"
                            name="hospital_location"
                            id="hospital_location"
                            placeholder="Paste hospital address or Google Maps link"
                            autocomplete="off"
                            required
                        >


                        <button
                            type="button"
                            id="searchLocationBtn"
                            class="search-btn"
                        >

                            🔍 Find Location

                        </button>


                    </div>


                    <div class="location-help">

                        📍 Enter the hospital address
                        or paste a Google Maps link.

                        Then click
                        <b>Find Location</b>.

                        You can also click directly
                        on the map.

                    </div>


                    <!-- SEARCH RESULTS -->

                    <div
                        id="searchResults"
                        class="search-results"
                    ></div>


                    <!-- COORDINATES -->

                    <input
                        type="hidden"
                        name="latitude"
                        id="latitude"
                    >


                    <input
                        type="hidden"
                        name="longitude"
                        id="longitude"
                    >


                    <!-- MAP -->

                    <div class="map-container">

                        <div
                            id="hospitalMap"
                        ></div>

                    </div>


                    <div class="location-help">

                        📌 Click on the map to select
                        the hospital location.

                        You can drag the marker
                        to adjust the exact position.

                    </div>


                    <!-- LOCATION STATUS -->

                    <div
                        id="locationStatus"
                        class="location-status"
                    >

                        🏥 No hospital location selected yet.

                    </div>


                </div>


                <!-- DATE -->

                <div class="form-group">


                    <label for="required_date">

                        Required Date

                        <span>*</span>

                    </label>


                    <input
                        type="date"
                        name="required_date"
                        id="required_date"
                        required
                    >


                </div>


                <!-- TIME -->

                <div class="form-group">


                    <label for="required_time">

                        Required Time

                        <span>*</span>

                    </label>


                    <input
                        type="time"
                        name="required_time"
                        id="required_time"
                        required
                    >


                </div>


                <!-- URGENCY -->

                <div class="form-group">


                    <label for="urgency">

                        Urgency

                        <span>*</span>

                    </label>


                    <select
                        name="urgency"
                        id="urgency"
                        required
                    >

                        <option value="">
                            Select urgency
                        </option>

                        <option value="normal">
                            Normal
                        </option>

                        <option value="urgent">
                            Urgent
                        </option>

                        <option value="emergency">
                            Emergency
                        </option>

                    </select>


                </div>


                <!-- PATIENT -->

                <div class="form-group">


                    <label for="patient_name">

                        Patient Name

                        <span>*</span>

                    </label>


                    <input
                        type="text"
                        name="patient_name"
                        id="patient_name"
                        placeholder="Patient full name"
                        required
                    >


                </div>


                <!-- PHONE -->

                <div class="form-group">


                    <label for="contact_phone">

                        Contact Phone

                        <span>*</span>

                    </label>


                    <input
                        type="tel"
                        name="contact_phone"
                        id="contact_phone"
                        placeholder="Contact number"
                        maxlength="15"
                        required
                    >


                </div>


                <!-- MESSAGE -->

                <div class="form-group full">


                    <label for="additional_message">

                        Additional Message

                    </label>


                    <textarea
                        name="additional_message"
                        id="additional_message"
                        placeholder="Any additional information..."
                    ></textarea>


                </div>


            </div>


            <!-- =================================================
                 SUMMARY
                 ================================================= -->

            <div class="summary-card">


                <div class="summary-title">

                    📋 Request Summary

                </div>


                <div class="summary-grid">


                    <div class="summary-item">

                        <div class="summary-label">
                            Blood Group
                        </div>

                        <div
                            class="summary-value"
                            id="summaryBlood"
                        >
                            Not selected
                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-label">
                            Units
                        </div>

                        <div
                            class="summary-value"
                            id="summaryUnits"
                        >
                            Not selected
                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-label">
                            Hospital
                        </div>

                        <div
                            class="summary-value"
                            id="summaryHospital"
                        >
                            Not selected
                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-label">
                            Location
                        </div>

                        <div
                            class="summary-value"
                            id="summaryLocation"
                        >
                            Not selected
                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-label">
                            Date
                        </div>

                        <div
                            class="summary-value"
                            id="summaryDate"
                        >
                            Not selected
                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-label">
                            Urgency
                        </div>

                        <div
                            class="summary-value"
                            id="summaryUrgency"
                        >
                            Not selected
                        </div>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 SUBMIT
                 ================================================= -->

            <div class="submit-area">


                <button
                    type="submit"
                    id="submitBtn"
                    class="submit-btn"
                >

                    📍 Create Blood Request

                </button>


            </div>


        </form>


    </div>

</div>


<!-- =========================================================
     LEAFLET JS
     ========================================================= -->

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>


<script>

/* =========================================================
   MAP VARIABLES
   ========================================================= */

let hospitalMap;

let hospitalMarker = null;


const defaultLatitude = 17.3850;

const defaultLongitude = 78.4867;


/* =========================================================
   INITIALIZE MAP
   ========================================================= */

hospitalMap =
    L.map(
        "hospitalMap"
    ).setView(
        [
            defaultLatitude,
            defaultLongitude
        ],
        12
    );


L.tileLayer(
    "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
    {

        maxZoom: 19,

        attribution:
            '&copy; OpenStreetMap contributors'

    }
).addTo(
    hospitalMap
);


/* =========================================================
   DOM ELEMENTS
   ========================================================= */

const locationInput =
    document.getElementById(
        "hospital_location"
    );


const latitudeInput =
    document.getElementById(
        "latitude"
    );


const longitudeInput =
    document.getElementById(
        "longitude"
    );


const locationStatus =
    document.getElementById(
        "locationStatus"
    );


const searchResults =
    document.getElementById(
        "searchResults"
    );


const searchButton =
    document.getElementById(
        "searchLocationBtn"
    );


/* =========================================================
   EXTRACT COORDINATES FROM MAP LINKS
   ========================================================= */

function extractGoogleMapsCoordinates(text) {

    let match;


    /*
     * Standard Google Maps URL:
     *
     * https://www.google.com/maps/@17.3850,78.4867,17z
     */

    match =
        text.match(
            /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/i
        );


    if (match) {

        return {

            latitude:
                parseFloat(
                    match[1]
                ),

            longitude:
                parseFloat(
                    match[2]
                )

        };
    }


    /*
     * q / ll / query parameters
     */

    match =
        text.match(
            /[?&](?:q|ll|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/i
        );


    if (match) {

        return {

            latitude:
                parseFloat(
                    match[1]
                ),

            longitude:
                parseFloat(
                    match[2]
                )

        };
    }


    /*
     * Google Maps URL containing:
     *
     * /17.3850,78.4867
     */

    match =
        text.match(
            /\/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)(?:[/?#]|$)/i
        );


    if (match) {

        return {

            latitude:
                parseFloat(
                    match[1]
                ),

            longitude:
                parseFloat(
                    match[2]
                )

        };
    }


    /*
     * Plain coordinates:
     *
     * 17.3850, 78.4867
     */

    match =
        text.match(
            /^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/
        );


    if (match) {

        return {

            latitude:
                parseFloat(
                    match[1]
                ),

            longitude:
                parseFloat(
                    match[2]
                )

        };
    }


    return null;
}


/* =========================================================
   SET HOSPITAL LOCATION
   ========================================================= */

function setHospitalLocation(
    latitude,
    longitude
) {

    latitude =
        parseFloat(latitude);

    longitude =
        parseFloat(longitude);


    if (
        !Number.isFinite(latitude) ||
        !Number.isFinite(longitude)
    ) {

        showLocationError(
            "Invalid location coordinates."
        );

        return;
    }


    if (
        latitude < -90 ||
        latitude > 90 ||
        longitude < -180 ||
        longitude > 180
    ) {

        showLocationError(
            "Invalid map coordinates."
        );

        return;
    }


    /*
     * Remove old marker.
     */

    if (
        hospitalMarker !== null
    ) {

        hospitalMap.removeLayer(
            hospitalMarker
        );
    }


    /*
     * Create draggable marker.
     */

    hospitalMarker =
        L.marker(
            [
                latitude,
                longitude
            ],
            {
                draggable: true
            }
        ).addTo(
            hospitalMap
        );


    /*
     * Move map.
     */

    hospitalMap.setView(
        [
            latitude,
            longitude
        ],
        17
    );


    /*
     * Save coordinates.
     */

    latitudeInput.value =
        latitude.toFixed(8);

    longitudeInput.value =
        longitude.toFixed(8);


    /*
     * Status.
     */

    locationStatus.className =
        "location-status success";


    locationStatus.innerHTML =
        "✅ Hospital location selected.<br>" +
        "Latitude: " +
        latitude.toFixed(6) +
        "<br>" +
        "Longitude: " +
        longitude.toFixed(6);


    /*
     * Marker drag.
     */

    hospitalMarker.on(
        "dragend",
        function(event) {

            const position =
                event.target.getLatLng();


            latitudeInput.value =
                position.lat.toFixed(8);

            longitudeInput.value =
                position.lng.toFixed(8);


            locationStatus.className =
                "location-status success";


            locationStatus.innerHTML =
                "✅ Hospital location updated.<br>" +
                "Latitude: " +
                position.lat.toFixed(6) +
                "<br>" +
                "Longitude: " +
                position.lng.toFixed(6);


            updateSummary();
        }
    );


    updateSummary();
}


/* =========================================================
   MAP CLICK
   ========================================================= */

hospitalMap.on(
    "click",
    function(event) {

        setHospitalLocation(
            event.latlng.lat,
            event.latlng.lng
        );


        /*
         * Do not destroy the address if
         * the user already searched for one.
         */

        if (
            locationInput.value.trim() === ""
        ) {

            locationInput.value =
                "Selected location on map";
        }


        updateSummary();
    }
);


/* =========================================================
   SEARCH BUTTON
   ========================================================= */

searchButton.addEventListener(
    "click",
    searchLocation
);


/* =========================================================
   ENTER KEY SEARCH
   ========================================================= */

locationInput.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Enter"
        ) {

            event.preventDefault();

            searchLocation();
        }
    }
);


/* =========================================================
   SEARCH LOCATION
   ========================================================= */

async function searchLocation() {

    const query =
        locationInput.value.trim();


    if (
        query === ""
    ) {

        showLocationError(
            "Please enter the hospital address or Google Maps location."
        );

        return;
    }


    /*
     * Clear previous coordinates.
     */

    latitudeInput.value = "";

    longitudeInput.value = "";


    /*
     * First try to extract coordinates
     * from the supplied Google Maps URL.
     */

    const googleCoordinates =
        extractGoogleMapsCoordinates(
            query
        );


    if (
        googleCoordinates !== null
    ) {

        setHospitalLocation(
            googleCoordinates.latitude,
            googleCoordinates.longitude
        );


        locationStatus.className =
            "location-status success";


        locationStatus.innerHTML =
            "✅ Google Maps coordinates detected.<br>" +
            "Latitude: " +
            googleCoordinates.latitude.toFixed(6) +
            "<br>" +
            "Longitude: " +
            googleCoordinates.longitude.toFixed(6);


        return;
    }


    /*
     * Otherwise search using geocode.php.
     */

    searchButton.disabled = true;

    searchButton.innerText =
        "Searching...";


    searchResults.style.display =
        "block";


    searchResults.innerHTML =
        '<div class="search-result">' +
        '🔎 Searching for the hospital...' +
        '</div>';


    try {

        const response =
            await fetch(
                "geocode.php?q=" +
                encodeURIComponent(query),
                {
                    method: "GET",
                    headers: {
                        "Accept":
                            "application/json"
                    }
                }
            );


        if (
            !response.ok
        ) {

            throw new Error(
                "Geocoder returned HTTP " +
                response.status
            );
        }


        const data =
            await response.json();


        if (
            !data.success ||
            !Array.isArray(
                data.results
            ) ||
            data.results.length === 0
        ) {

            searchResults.innerHTML =
                '<div class="search-result">' +
                '❌ Location not found.<br>' +
                '<span class="result-address">' +
                'Try the complete hospital address, ' +
                'hospital name + city, or select ' +
                'the location directly on the map.' +
                '</span>' +
                '</div>';


            showLocationError(
                "Location not found. Try a more complete hospital address."
            );


            return;
        }


        /*
         * Show all search results.
         */

        displaySearchResults(
            data.results
        );


        /*
         * Automatically select the
         * first result.
         */

        const firstResult =
            data.results[0];


        setHospitalLocation(
            firstResult.latitude,
            firstResult.longitude
        );


        /*
         * Use returned address.
         */

        if (
            firstResult.address
        ) {

            locationInput.value =
                firstResult.address;
        }


        /*
         * Automatically fill hospital
         * name when empty.
         */

        const hospitalNameInput =
            document.getElementById(
                "hospital_name"
            );


        if (
            hospitalNameInput.value.trim() === "" &&
            firstResult.name
        ) {

            hospitalNameInput.value =
                firstResult.name;
        }


        updateSummary();


    } catch (error) {

        console.error(
            "Location search error:",
            error
        );


        searchResults.style.display =
            "block";


        searchResults.innerHTML =
            '<div class="search-result">' +
            '❌ Unable to search location.<br>' +
            '<span class="result-address">' +
            'Please check your internet connection ' +
            'or select the location directly on the map.' +
            '</span>' +
            '</div>';


        showLocationError(
            "Unable to search location. You can select the hospital directly on the map."
        );


    } finally {

        searchButton.disabled =
            false;

        searchButton.innerText =
            "🔍 Find Location";
    }
}


/* =========================================================
   DISPLAY SEARCH RESULTS
   ========================================================= */

function displaySearchResults(
    results
) {

    searchResults.innerHTML = "";

    searchResults.style.display =
        "block";


    results.forEach(
        function(result) {

            const item =
                document.createElement(
                    "div"
                );


            item.className =
                "search-result";


            const title =
                result.name ||
                "Location";


            const address =
                result.address ||
                "";


            item.innerHTML =
                '<div class="result-title">' +
                escapeHtml(title) +
                '</div>' +
                '<div class="result-address">' +
                escapeHtml(address) +
                '</div>';


            item.addEventListener(
                "click",
                function() {

                    setHospitalLocation(
                        result.latitude,
                        result.longitude
                    );


                    if (
                        result.address
                    ) {

                        locationInput.value =
                            result.address;
                    }


                    const hospitalNameInput =
                        document.getElementById(
                            "hospital_name"
                        );


                    if (
                        hospitalNameInput.value.trim() === "" &&
                        result.name
                    ) {

                        hospitalNameInput.value =
                            result.name;
                    }


                    searchResults.style.display =
                        "none";


                    updateSummary();
                }
            );


            searchResults.appendChild(
                item
            );
        }
    );
}


/* =========================================================
   LOCATION INPUT CHANGE
   ========================================================= */

locationInput.addEventListener(
    "input",
    function() {

        /*
         * If the user changes the location
         * manually, the previous coordinates
         * are no longer trusted.
         */

        latitudeInput.value = "";

        longitudeInput.value = "";


        locationStatus.className =
            "location-status";


        locationStatus.innerHTML =
            "🔎 Search and select the new hospital location.";

    }
);


/* =========================================================
   FORM SUBMISSION
   ========================================================= */

document
    .getElementById(
        "requestForm"
    )
    .addEventListener(
        "submit",
        function(event) {

            const latitude =
                latitudeInput.value.trim();


            const longitude =
                longitudeInput.value.trim();


            const location =
                locationInput.value.trim();


            if (
                location === ""
            ) {

                event.preventDefault();


                showLocationError(
                    "Please enter the hospital location."
                );


                alert(
                    "Please enter the hospital location."
                );


                return;
            }


            if (
                latitude === "" ||
                longitude === ""
            ) {

                event.preventDefault();


                showLocationError(
                    "Please search for the hospital and select the correct location on the map before submitting."
                );


                alert(
                    "Please select the hospital location on the map before creating the request."
                );


                return;
            }


            /*
             * Prevent double submission.
             */

            const submitButton =
                document.getElementById(
                    "submitBtn"
                );


            submitButton.disabled =
                true;


            submitButton.innerHTML =
                "📍 Creating Request...";
        }
    );


/* =========================================================
   SUMMARY
   ========================================================= */

function updateSummary() {

    const blood =
        document.getElementById(
            "blood_group"
        ).value;


    const units =
        document.getElementById(
            "units_required"
        ).value;


    const hospital =
        document.getElementById(
            "hospital_name"
        ).value;


    const location =
        document.getElementById(
            "hospital_location"
        ).value;


    const date =
        document.getElementById(
            "required_date"
        ).value;


    const urgency =
        document.getElementById(
            "urgency"
        ).value;


    document.getElementById(
        "summaryBlood"
    ).textContent =
        blood ||
        "Not selected";


    document.getElementById(
        "summaryUnits"
    ).textContent =
        units
            ? units + " unit(s)"
            : "Not selected";


    document.getElementById(
        "summaryHospital"
    ).textContent =
        hospital ||
        "Not selected";


    document.getElementById(
        "summaryLocation"
    ).textContent =
        location ||
        "Not selected";


    document.getElementById(
        "summaryDate"
    ).textContent =
        date ||
        "Not selected";


    document.getElementById(
        "summaryUrgency"
    ).textContent =
        urgency ||
        "Not selected";
}


/* =========================================================
   SUMMARY EVENTS
   ========================================================= */

[
    "blood_group",
    "units_required",
    "hospital_name",
    "hospital_location",
    "required_date",
    "urgency"
].forEach(
    function(id) {

        const element =
            document.getElementById(
                id
            );


        if (element) {

            element.addEventListener(
                "input",
                updateSummary
            );


            element.addEventListener(
                "change",
                updateSummary
            );
        }
    }
);


/* =========================================================
   ESCAPE HTML
   ========================================================= */

function escapeHtml(text) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        text;


    return div.innerHTML;
}


/* =========================================================
   LOCATION ERROR
   ========================================================= */

function showLocationError(
    message
) {

    locationStatus.className =
        "location-status error";


    locationStatus.innerHTML =
        "❌ " +
        escapeHtml(message);
}


/* =========================================================
   INITIAL SUMMARY
   ========================================================= */

updateSummary();

</script>


</body>

</html>