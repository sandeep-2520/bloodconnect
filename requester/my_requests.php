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

$requester_id = (int) $_SESSION["user_id"];

$message = "";
$message_type = "";


/* =========================================================
   DISTANCE FUNCTION
   ========================================================= */

function calculateDistanceKm(
    $lat1,
    $lon1,
    $lat2,
    $lon2
) {
    if (
        $lat1 === null ||
        $lon1 === null ||
        $lat2 === null ||
        $lon2 === null ||
        $lat1 === "" ||
        $lon1 === "" ||
        $lat2 === "" ||
        $lon2 === ""
    ) {
        return null;
    }

    $earth_radius = 6371;

    $lat1 = deg2rad((float)$lat1);
    $lon1 = deg2rad((float)$lon1);
    $lat2 = deg2rad((float)$lat2);
    $lon2 = deg2rad((float)$lon2);

    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;

    $a =
        sin($dlat / 2) * sin($dlat / 2) +
        cos($lat1) *
        cos($lat2) *
        sin($dlon / 2) *
        sin($dlon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius * $c;
}


/* =========================================================
   ACCEPT DONOR
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "accept_donor"
) {

    $request_id = isset($_POST["request_id"])
        ? (int)$_POST["request_id"]
        : 0;

    /*
     * IMPORTANT:
     * donor_id received here is USERS.ID because
     * donor_responses.donor_id references users.id.
     */
    $donor_user_id = isset($_POST["donor_id"])
        ? (int)$_POST["donor_id"]
        : 0;

    if ($request_id <= 0 || $donor_user_id <= 0) {

        $message = "Invalid donor or request.";
        $message_type = "error";

    } else {

        try {

            $conn->begin_transaction();

            /* -------------------------------------------------
               Verify request belongs to current requester
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    status,
                    blood_group
                FROM blood_requests
                WHERE id = ?
                AND requester_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $requester_id
            );

            $stmt->execute();

            $request_result = $stmt->get_result();

            if ($request_result->num_rows === 0) {
                throw new Exception(
                    "Blood request not found."
                );
            }

            $request_data = $request_result->fetch_assoc();

            if (
                $request_data["status"] === "completed"
            ) {
                throw new Exception(
                    "This request has already been completed."
                );
            }


            /* -------------------------------------------------
               Find interested donor
               
               donor_responses.donor_id = users.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    donor_id,
                    response_status
                FROM donor_responses
                WHERE request_id = ?
                AND donor_id = ?
                AND response_status = 'interested'
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $donor_user_id
            );

            $stmt->execute();

            $response_result = $stmt->get_result();

            if ($response_result->num_rows === 0) {
                throw new Exception(
                    "This donor is no longer available for this request."
                );
            }

            $response_data = $response_result->fetch_assoc();

            $response_id = (int)$response_data["id"];


            /* -------------------------------------------------
               Get donor profile ID
               
               donor_profiles.id is DIFFERENT from users.id.
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT
                    id
                FROM donor_profiles
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $donor_user_id
            );

            $stmt->execute();

            $profile_result = $stmt->get_result();

            if ($profile_result->num_rows === 0) {
                throw new Exception(
                    "Donor profile was not found."
                );
            }

            $profile_data = $profile_result->fetch_assoc();

            /*
             * This ID must be used for:
             * request_matches.donor_id
             */
            $donor_profile_id = (int)$profile_data["id"];


            /* -------------------------------------------------
               Mark selected donor response as ACCEPTED
               
               donor_responses.donor_id = users.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE donor_responses
                SET response_status = 'accepted',
                    responded_at = NOW()
                WHERE id = ?
                AND donor_id = ?
                AND request_id = ?
            ");

            $stmt->bind_param(
                "iii",
                $response_id,
                $donor_user_id,
                $request_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Update blood request
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE blood_requests
                SET status = 'donor_accepted'
                WHERE id = ?
                AND requester_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $requester_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Update / create request match
               
               request_matches.donor_id = donor_profiles.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT id
                FROM request_matches
                WHERE request_id = ?
                AND donor_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $donor_profile_id
            );

            $stmt->execute();

            $match_result = $stmt->get_result();

            if ($match_result->num_rows > 0) {

                $match_data = $match_result->fetch_assoc();

                $match_id = (int)$match_data["id"];

                $stmt = $conn->prepare("
                    UPDATE request_matches
                    SET match_status = 'accepted',
                        matched_at = NOW()
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $match_id
                );

                $stmt->execute();

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO request_matches
                    (
                        request_id,
                        donor_id,
                        match_status,
                        matched_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'accepted',
                        NOW()
                    )
                ");

                $stmt->bind_param(
                    "ii",
                    $request_id,
                    $donor_profile_id
                );

                $stmt->execute();
            }


            /* -------------------------------------------------
               Decline other interested donors
               
               donor_responses.donor_id = users.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE donor_responses
                SET response_status = 'declined',
                    responded_at = NOW()
                WHERE request_id = ?
                AND donor_id != ?
                AND response_status = 'interested'
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $donor_user_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Reject other request matches
               
               request_matches.donor_id = donor_profiles.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE request_matches
                SET match_status = 'rejected'
                WHERE request_id = ?
                AND donor_id != ?
                AND match_status IN ('matched', 'contacted')
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $donor_profile_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Notify selected donor
               
               notifications.user_id = users.id
            ------------------------------------------------- */

            $title = "Donor Accepted";

            $notification_message =
                "Your donation offer has been accepted for Blood Request #"
                . $request_id
                . ". Please contact the requester.";

            $stmt = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    request_id,
                    title,
                    message,
                    type,
                    is_read,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'blood_request',
                    0,
                    NOW()
                )
            ");

            $stmt->bind_param(
                "iiss",
                $donor_user_id,
                $request_id,
                $title,
                $notification_message
            );

            $stmt->execute();


            $conn->commit();

            $message =
                "Donor accepted successfully. The donor has been notified.";

            $message_type = "success";

        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}


/* =========================================================
   COMPLETE DONATION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "complete_donation"
) {

    $request_id = isset($_POST["request_id"])
        ? (int)$_POST["request_id"]
        : 0;

    if ($request_id <= 0) {

        $message = "Invalid request.";
        $message_type = "error";

    } else {

        try {

            $conn->begin_transaction();


            /* -------------------------------------------------
               Verify request and accepted donor
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT
                    br.id,
                    br.status,
                    br.units_required,
                    br.hospital_name,
                    dr.id AS response_id,
                    dr.donor_id AS donor_user_id
                FROM blood_requests br
                INNER JOIN donor_responses dr
                    ON dr.request_id = br.id
                WHERE br.id = ?
                AND br.requester_id = ?
                AND br.status = 'donor_accepted'
                AND dr.response_status = 'accepted'
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $requester_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception(
                    "No accepted donor was found for this request."
                );
            }

            $data = $result->fetch_assoc();

            /*
             * This is USERS.ID.
             */
            $donor_user_id = (int)$data["donor_user_id"];

            $response_id = (int)$data["response_id"];

            $units_required = (int)$data["units_required"];

            $hospital_name = $data["hospital_name"];


            /* -------------------------------------------------
               Get donor profile ID
               
               donor_profiles.id is required by:
               donations.donor_id
               request_matches.donor_id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                SELECT
                    id
                FROM donor_profiles
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $donor_user_id
            );

            $stmt->execute();

            $profile_result = $stmt->get_result();

            if ($profile_result->num_rows === 0) {
                throw new Exception(
                    "Donor profile was not found."
                );
            }

            $profile_data = $profile_result->fetch_assoc();

            /*
             * THIS IS THE IMPORTANT ID.
             *
             * donations.donor_id references donor_profiles.id
             */
            $donor_profile_id = (int)$profile_data["id"];


            /* -------------------------------------------------
               Update blood request
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE blood_requests
                SET status = 'completed'
                WHERE id = ?
                AND requester_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $requester_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Update donor response
               
               donor_responses.donor_id = users.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE donor_responses
                SET response_status = 'completed',
                    responded_at = NOW()
                WHERE id = ?
                AND donor_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $response_id,
                $donor_user_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Update request match
               
               request_matches.donor_id = donor_profiles.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE request_matches
                SET match_status = 'completed'
                WHERE request_id = ?
                AND donor_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $request_id,
                $donor_profile_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Update donor profile
               
               donor_profiles.user_id = users.id
            ------------------------------------------------- */

            $stmt = $conn->prepare("
                UPDATE donor_profiles
                SET
                    last_donation_date = CURDATE(),
                    availability = 'unavailable'
                WHERE user_id = ?
            ");

            $stmt->bind_param(
                "i",
                $donor_user_id
            );

            $stmt->execute();


            /* -------------------------------------------------
               Insert donation record
               
               IMPORTANT:
               
               donations.donor_id references
               donor_profiles.id
               
               NOT users.id.
            ------------------------------------------------- */

            $today = date("Y-m-d");

            $donation_status = "completed";

            $notes = "Donation completed successfully.";

            $stmt = $conn->prepare("
                INSERT INTO donations
                (
                    request_id,
                    donor_id,
                    units_donated,
                    donation_date,
                    hospital_name,
                    status,
                    notes
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            /*
             * donor_profile_id is intentionally used here.
             */
            $stmt->bind_param(
                "iiissss",
                $request_id,
                $donor_profile_id,
                $units_required,
                $today,
                $hospital_name,
                $donation_status,
                $notes
            );

            $stmt->execute();


            /* -------------------------------------------------
               Notify donor
               
               notifications.user_id = users.id
            ------------------------------------------------- */

            $title = "Donation Completed";

            $notification_message =
                "Your blood donation for Request #"
                . $request_id
                . " has been marked as completed. Thank you for saving a life!";

            $stmt = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    request_id,
                    title,
                    message,
                    type,
                    is_read,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'blood_request',
                    0,
                    NOW()
                )
            ");

            $stmt->bind_param(
                "iiss",
                $donor_user_id,
                $request_id,
                $title,
                $notification_message
            );

            $stmt->execute();


            $conn->commit();

            $message =
                "Donation marked as completed successfully.";

            $message_type = "success";

        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}


/* =========================================================
   FETCH REQUESTS
   ========================================================= */

$stmt = $conn->prepare("
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
        br.contact_phone,
        br.additional_message,
        br.status,
        br.created_at,

        br.latitude AS hospital_latitude,
        br.longitude AS hospital_longitude,

        u.id AS donor_user_id,
        u.name AS donor_name,
        u.phone AS donor_phone,
        u.email AS donor_email,

        dp.id AS donor_profile_id,
        dp.blood_group AS donor_blood_group,
        dp.city AS donor_city,
        dp.area AS donor_area,
        dp.pincode AS donor_pincode,
        dp.latitude AS donor_latitude,
        dp.longitude AS donor_longitude,

        dr.id AS donor_response_id,
        dr.response_status AS donor_response_status,
        dr.responded_at AS donor_responded_at

    FROM blood_requests br

    LEFT JOIN donor_responses dr
        ON dr.id = (
            SELECT dr2.id
            FROM donor_responses dr2
            WHERE dr2.request_id = br.id
            AND dr2.response_status IN
                ('interested', 'accepted', 'completed')
            ORDER BY
                CASE dr2.response_status
                    WHEN 'accepted' THEN 1
                    WHEN 'interested' THEN 2
                    WHEN 'completed' THEN 3
                    ELSE 4
                END,
                dr2.id DESC
            LIMIT 1
        )

    LEFT JOIN users u
        ON dr.donor_id = u.id

    LEFT JOIN donor_profiles dp
        ON dr.donor_id = dp.user_id

    WHERE br.requester_id = ?

    ORDER BY br.created_at DESC
");

$stmt->bind_param(
    "i",
    $requester_id
);

$stmt->execute();

$result = $stmt->get_result();

$requests = [];

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>My Blood Requests - BloodConnect</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #222;
    padding-bottom: 90px;
}


/* =========================================================
   HEADER
========================================================= */

.header {
    background: linear-gradient(
        135deg,
        #c62828,
        #e53935,
        #ff5252
    );

    color: white;

    padding: 25px 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.15);
}

.header-inner {
    max-width: 1100px;
    margin: auto;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;
}

.header h1 {
    font-size: 28px;
    margin-bottom: 5px;
}

.header p {
    font-size: 14px;
    opacity: 0.9;
}

.dashboard-btn {
    text-decoration: none;
    color: #c62828;
    background: white;

    padding: 11px 18px;

    border-radius: 10px;

    font-weight: bold;

    white-space: nowrap;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {
    max-width: 1100px;

    margin: 30px auto;

    padding: 0 18px;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {
    padding: 15px 18px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-weight: 600;
}

.alert.success {
    background: #e8f5e9;
    color: #2e7d32;

    border: 1px solid #a5d6a7;
}

.alert.error {
    background: #ffebee;
    color: #c62828;

    border: 1px solid #ef9a9a;
}


/* =========================================================
   REQUEST CARD
========================================================= */

.request-card {
    background: white;

    border-radius: 18px;

    margin-bottom: 25px;

    padding: 22px;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.08);

    border: 1px solid #eee;
}


/* =========================================================
   REQUEST HEADER
========================================================= */

.request-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 20px;
}

.request-title {
    font-size: 20px;

    font-weight: bold;

    color: #333;
}

.request-id {
    font-size: 13px;

    color: #777;

    margin-top: 5px;
}

.badge {
    padding: 7px 12px;

    border-radius: 30px;

    font-size: 12px;

    font-weight: bold;

    text-transform: capitalize;

    white-space: nowrap;
}

.badge-searching {
    background: #fff3e0;
    color: #ef6c00;
}

.badge-donor_found {
    background: #e3f2fd;
    color: #1565c0;
}

.badge-donor_accepted {
    background: #e8f5e9;
    color: #2e7d32;
}

.badge-completed {
    background: #eeeeee;
    color: #555;
}


/* =========================================================
   BLOOD GROUP
========================================================= */

.blood-section {
    display: flex;

    align-items: center;

    gap: 18px;

    background: #fff5f5;

    padding: 18px;

    border-radius: 14px;

    margin-bottom: 18px;
}

.blood-icon {
    width: 60px;
    height: 60px;

    border-radius: 50%;

    background: #c62828;

    color: white;

    display: flex;

    align-items: center;
    justify-content: center;

    font-weight: bold;

    font-size: 18px;
}

.blood-info strong {
    display: block;

    font-size: 21px;

    color: #c62828;
}

.blood-info span {
    color: #666;

    font-size: 14px;
}


/* =========================================================
   INFO GRID
========================================================= */

.info-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 20px;
}

.info-box {
    background: #f8f9fb;

    border-radius: 12px;

    padding: 14px;
}

.info-label {
    display: block;

    color: #777;

    font-size: 12px;

    margin-bottom: 5px;
}

.info-value {
    font-weight: 600;

    color: #333;

    word-break: break-word;
}


/* =========================================================
   MESSAGE
========================================================= */

.message-box {
    background: #f8f9fb;

    padding: 15px;

    border-radius: 12px;

    margin-bottom: 20px;
}

.message-box h4 {
    margin-bottom: 8px;

    font-size: 14px;
}

.message-box p {
    color: #555;

    line-height: 1.5;

    font-size: 14px;
}


/* =========================================================
   DONOR SECTION
========================================================= */

.donor-section {
    border-top: 1px solid #eee;

    padding-top: 20px;

    margin-top: 10px;
}

.donor-title {
    font-size: 18px;

    font-weight: bold;

    margin-bottom: 15px;
}

.donor-card {
    background: #f8fbff;

    border: 1px solid #dbeafe;

    border-radius: 15px;

    padding: 18px;
}

.donor-top {
    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 15px;
}

.donor-avatar {
    width: 52px;
    height: 52px;

    border-radius: 50%;

    background: #c62828;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

    font-weight: bold;
}

.donor-name {
    font-size: 18px;

    font-weight: bold;
}

.donor-status {
    display: inline-block;

    margin-top: 5px;

    padding: 5px 9px;

    border-radius: 20px;

    background: #fff3e0;

    color: #ef6c00;

    font-size: 11px;

    font-weight: bold;

    text-transform: capitalize;
}

.donor-status.accepted {
    background: #e8f5e9;

    color: #2e7d32;
}

.donor-status.completed {
    background: #eeeeee;

    color: #555;
}

.donor-details {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 10px;

    margin-bottom: 15px;
}

.donor-detail {
    font-size: 13px;

    color: #555;
}

.donor-detail strong {
    color: #333;
}


/* =========================================================
   DISTANCE
========================================================= */

.distance {
    background: white;

    padding: 12px 14px;

    border-radius: 10px;

    margin-bottom: 15px;

    font-weight: bold;

    color: #1565c0;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.btn {
    border: none;

    border-radius: 10px;

    padding: 11px 16px;

    cursor: pointer;

    font-size: 14px;

    font-weight: bold;

    text-decoration: none;

    display: inline-block;

    text-align: center;
}

.btn-accept {
    background: #2e7d32;

    color: white;
}

.btn-complete {
    background: #1565c0;

    color: white;
}

.btn-call {
    background: #e8f5e9;

    color: #2e7d32;
}

.btn-email {
    background: #e3f2fd;

    color: #1565c0;
}

.btn:hover {
    opacity: 0.9;
}


/* =========================================================
   NO DONOR
========================================================= */

.no-donor {
    background: #fafafa;

    border: 1px dashed #ccc;

    padding: 18px;

    border-radius: 12px;

    text-align: center;

    color: #777;

    font-size: 14px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {
    background: white;

    border-radius: 18px;

    padding: 50px 20px;

    text-align: center;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.06);
}

.empty-icon {
    font-size: 55px;

    margin-bottom: 15px;
}

.empty h2 {
    margin-bottom: 10px;
}

.empty p {
    color: #777;

    margin-bottom: 20px;
}

.create-btn {
    display: inline-block;

    background: #c62828;

    color: white;

    padding: 12px 20px;

    border-radius: 10px;

    text-decoration: none;

    font-weight: bold;
}


/* =========================================================
   BOTTOM NAV
========================================================= */

.bottom-nav {
    position: fixed;

    bottom: 0;

    left: 0;

    right: 0;

    background: white;

    height: 65px;

    box-shadow:
        0 -3px 15px rgba(0,0,0,0.08);

    display: flex;

    align-items: center;

    justify-content: center;

    z-index: 100;
}

.bottom-nav-inner {
    width: 100%;

    max-width: 600px;

    display: flex;

    justify-content: space-around;
}

.bottom-nav a {
    text-decoration: none;

    color: #777;

    font-size: 12px;

    text-align: center;
}

.bottom-nav a.active {
    color: #c62828;

    font-weight: bold;
}

.bottom-icon {
    display: block;

    font-size: 21px;

    margin-bottom: 2px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .header-inner {
        flex-direction: column;

        align-items: flex-start;
    }

    .dashboard-btn {
        width: 100%;

        text-align: center;
    }

    .request-header {
        align-items: flex-start;

        flex-direction: column;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .donor-details {
        grid-template-columns: 1fr;
    }

    .actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}

</style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <div class="header-inner">

        <div>
            <h1>🩸 My Blood Requests</h1>

            <p>
                Track your blood requests and donor responses
            </p>
        </div>

        <a
            href="../requester/dashboard.php"
            class="dashboard-btn"
        >
            ← Dashboard
        </a>

    </div>

</div>


<!-- =========================================================
     MAIN CONTAINER
========================================================= -->

<div class="container">


<?php if ($message !== ""): ?>

    <div class="alert <?= htmlspecialchars($message_type) ?>">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>


<?php if (count($requests) === 0): ?>

    <div class="empty">

        <div class="empty-icon">
            🩸
        </div>

        <h2>No Blood Requests Yet</h2>

        <p>
            You haven't created any blood requests.
        </p>

        <a
            href="request.php"
            class="create-btn"
        >
            + Create Blood Request
        </a>

    </div>

<?php else: ?>


<?php foreach ($requests as $request): ?>

<?php

    $status = $request["status"];

    $status_class = "badge-" . $status;

    $donor_status =
        $request["donor_response_status"] ?? "";

    $distance = null;

    if (
        $request["hospital_latitude"] !== null &&
        $request["hospital_longitude"] !== null &&
        $request["donor_latitude"] !== null &&
        $request["donor_longitude"] !== null
    ) {

        $distance = calculateDistanceKm(
            $request["hospital_latitude"],
            $request["hospital_longitude"],
            $request["donor_latitude"],
            $request["donor_longitude"]
        );
    }

    $initial = "";

    if (!empty($request["donor_name"])) {
        $initial = strtoupper(
            substr(
                $request["donor_name"],
                0,
                1
            )
        );
    }

?>


<div class="request-card">


    <!-- =====================================================
         REQUEST HEADER
    ====================================================== -->

    <div class="request-header">

        <div>

            <div class="request-title">

                Blood Request #<?= (int)$request["id"] ?>

            </div>

            <div class="request-id">

                Created:
                <?= htmlspecialchars($request["created_at"]) ?>

            </div>

        </div>


        <div class="badge <?= htmlspecialchars($status_class) ?>">

            <?= htmlspecialchars(
                str_replace("_", " ", $status)
            ) ?>

        </div>

    </div>


    <!-- =====================================================
         BLOOD INFORMATION
    ====================================================== -->

    <div class="blood-section">

        <div class="blood-icon">

            <?= htmlspecialchars(
                $request["blood_group"]
            ) ?>

        </div>

        <div class="blood-info">

            <strong>
                <?= htmlspecialchars(
                    $request["blood_group"]
                ) ?>
            </strong>

            <span>
                <?= (int)$request["units_required"] ?>
                unit(s) required
            </span>

        </div>

    </div>


    <!-- =====================================================
         REQUEST INFORMATION
    ====================================================== -->

    <div class="info-grid">


        <div class="info-box">

            <span class="info-label">
                Patient
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $request["patient_name"]
                ) ?>

            </span>

        </div>


        <div class="info-box">

            <span class="info-label">
                Hospital
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $request["hospital_name"]
                ) ?>

            </span>

        </div>


        <div class="info-box">

            <span class="info-label">
                Required Date
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $request["required_date"]
                ) ?>

            </span>

        </div>


        <div class="info-box">

            <span class="info-label">
                Required Time
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $request["required_time"]
                ) ?>

            </span>

        </div>


        <div class="info-box">

            <span class="info-label">
                Urgency
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    ucfirst($request["urgency"])
                ) ?>

            </span>

        </div>


        <div class="info-box">

            <span class="info-label">
                Contact
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $request["contact_phone"]
                ) ?>

            </span>

        </div>


        <div class="info-box"
             style="grid-column: 1 / -1;">

            <span class="info-label">
                Hospital Location
            </span>

            <span class="info-value">

                <?= htmlspecialchars(
                    $request["hospital_location"]
                ) ?>

            </span>

        </div>


    </div>


    <!-- =====================================================
         ADDITIONAL MESSAGE
    ====================================================== -->

    <?php if (!empty($request["additional_message"])): ?>

        <div class="message-box">

            <h4>
                Additional Message
            </h4>

            <p>

                <?= nl2br(
                    htmlspecialchars(
                        $request["additional_message"]
                    )
                ) ?>

            </p>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         DONOR SECTION
    ====================================================== -->

    <div class="donor-section">

        <div class="donor-title">

            🤝 Donor

        </div>


        <?php if (!empty($request["donor_user_id"])): ?>


            <div class="donor-card">


                <div class="donor-top">


                    <div class="donor-avatar">

                        <?= htmlspecialchars(
                            $initial
                        ) ?>

                    </div>


                    <div>

                        <div class="donor-name">

                            <?= htmlspecialchars(
                                $request["donor_name"]
                            ) ?>

                        </div>


                        <span
                            class="donor-status
                            <?=
                            $donor_status === "accepted"
                                ? "accepted"
                                : (
                                    $donor_status === "completed"
                                    ? "completed"
                                    : ""
                                )
                            ?>"
                        >

                            <?= htmlspecialchars(
                                str_replace(
                                    "_",
                                    " ",
                                    $donor_status
                                )
                            ) ?>

                        </span>

                    </div>

                </div>


                <!-- =========================================
                     DONOR DETAILS
                ========================================== -->

                <div class="donor-details">


                    <div class="donor-detail">

                        <strong>
                            Blood Group:
                        </strong>

                        <?= htmlspecialchars(
                            $request["donor_blood_group"]
                        ) ?>

                    </div>


                    <div class="donor-detail">

                        <strong>
                            Phone:
                        </strong>

                        <?= htmlspecialchars(
                            $request["donor_phone"]
                        ) ?>

                    </div>


                    <div class="donor-detail">

                        <strong>
                            Email:
                        </strong>

                        <?= htmlspecialchars(
                            $request["donor_email"]
                        ) ?>

                    </div>


                    <div class="donor-detail">

                        <strong>
                            Area:
                        </strong>

                        <?= htmlspecialchars(
                            $request["donor_area"]
                        ) ?>

                    </div>


                    <div class="donor-detail">

                        <strong>
                            City:
                        </strong>

                        <?= htmlspecialchars(
                            $request["donor_city"]
                        ) ?>

                    </div>


                    <div class="donor-detail">

                        <strong>
                            Pincode:
                        </strong>

                        <?= htmlspecialchars(
                            $request["donor_pincode"]
                        ) ?>

                    </div>


                </div>


                <!-- =========================================
                     DISTANCE
                ========================================== -->

                <div class="distance">

                    📍

                    <?php if ($distance !== null): ?>

                        Approximate Distance:
                        <?= number_format(
                            $distance,
                            2
                        ) ?>
                        km

                    <?php else: ?>

                        Distance unavailable

                    <?php endif; ?>

                </div>


                <!-- =========================================
                     DONOR ACTIONS
                ========================================== -->

                <div class="actions">


                    <?php if (
                        $donor_status === "interested" &&
                        $status !== "donor_accepted" &&
                        $status !== "completed"
                    ): ?>


                        <form
                            method="POST"
                            style="flex: 1;"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="accept_donor"
                            >

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= (int)$request["id"] ?>"
                            >

                            <!--
                                IMPORTANT:
                                This is users.id because
                                donor_responses.donor_id
                                references users.id.
                            -->

                            <input
                                type="hidden"
                                name="donor_id"
                                value="<?= (int)$request["donor_user_id"] ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-accept"
                                style="width: 100%;"
                            >
                                ✓ Accept Donor
                            </button>

                        </form>


                    <?php endif; ?>


                    <?php if (
                        $donor_status === "accepted" &&
                        $status === "donor_accepted"
                    ): ?>


                        <form
                            method="POST"
                            style="flex: 1;"
                            onsubmit="
                                return confirm(
                                    'Confirm that the blood donation has been completed?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="complete_donation"
                            >

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= (int)$request["id"] ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-complete"
                                style="width: 100%;"
                            >
                                ✓ Confirm Donation Completed
                            </button>

                        </form>


                    <?php endif; ?>


                    <?php if (
                        !empty($request["donor_phone"])
                    ): ?>

                        <a
                            href="tel:<?= htmlspecialchars(
                                $request["donor_phone"]
                            ) ?>"
                            class="btn btn-call"
                        >
                            📞 Call
                        </a>

                    <?php endif; ?>


                    <?php if (
                        !empty($request["donor_email"])
                    ): ?>

                        <a
                            href="mailto:<?= htmlspecialchars(
                                $request["donor_email"]
                            ) ?>"
                            class="btn btn-email"
                        >
                            ✉ Email
                        </a>

                    <?php endif; ?>


                </div>


            </div>


        <?php else: ?>


            <div class="no-donor">

                🔎

                <?php if ($status === "searching"): ?>

                    Waiting for a compatible donor
                    to respond...

                <?php elseif ($status === "completed"): ?>

                    This blood request has been completed.

                <?php else: ?>

                    No donor has responded yet.

                <?php endif; ?>

            </div>


        <?php endif; ?>


    </div>


</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


<!-- =========================================================
     BOTTOM NAVIGATION
========================================================= -->

<div class="bottom-nav">

    <div class="bottom-nav-inner">

        <a
            href="../requester/dashboard.php"
        >

            <span class="bottom-icon">
                🏠
            </span>

            Dashboard

        </a>


        <a
            href="requester/request.php"
            class="active"
        >

            <span class="bottom-icon">
                🩸
            </span>

            Requests

        </a>


        <a
            href="../notifications.php"
        >

            <span class="bottom-icon">
                🔔
            </span>

            Notifications

        </a>

    </div>

</div>


</body>

</html>