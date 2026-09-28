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


/* =========================================================
   BLOOD GROUP COMPATIBILITY
   =========================================================

   BloodConnect project matching rule:

   O-  -> Can donate to all blood groups

   O+  -> Can donate to:
          O+, A+, B+, AB+

   A-  -> A- only
   A+  -> A+ only

   B-  -> B- only
   B+  -> B+ only

   AB- -> AB- only
   AB+ -> AB+ only

   All requests can still be viewed and shared.

   This function is also checked on the server so that
   an incompatible donor cannot bypass the Donate button.
   ========================================================= */

function canDonateBlood($donorGroup, $recipientGroup)
{
    $donorGroup = strtoupper(trim($donorGroup));
    $recipientGroup = strtoupper(trim($recipientGroup));


    /* ---------------------------------------------------------
       O- CAN DONATE TO ALL GROUPS
       --------------------------------------------------------- */

    if ($donorGroup === "O-") {

        return true;
    }


    /* ---------------------------------------------------------
       O+ CAN DONATE TO POSITIVE GROUPS
       --------------------------------------------------------- */

    if ($donorGroup === "O+") {

        return in_array(
            $recipientGroup,
            [
                "O+",
                "A+",
                "B+",
                "AB+"
            ],
            true
        );
    }


    /* ---------------------------------------------------------
       ALL OTHER GROUPS CAN DONATE ONLY TO SAME GROUP
       --------------------------------------------------------- */

    return $donorGroup === $recipientGroup;
}


/* =========================================================
   GET POST DATA
   ========================================================= */

$request_id = isset($_POST["request_id"])
    ? (int) $_POST["request_id"]
    : 0;

$action = isset($_POST["action"])
    ? trim($_POST["action"])
    : "";


/* =========================================================
   VALIDATE REQUEST ID
   ========================================================= */

if ($request_id <= 0) {

    header(
        "Location: requests.php?error=invalid_request"
    );

    exit;
}


/* =========================================================
   VALIDATE ACTION
   ========================================================= */

if (!in_array($action, ["donate", "cancel"], true)) {

    header(
        "Location: requests.php?error=invalid_action"
    );

    exit;
}


/* =========================================================
   GET BLOOD REQUEST
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        requester_id,
        blood_group,
        units_required,
        hospital_name,
        hospital_location,
        required_date,
        required_time,
        urgency,
        status
    FROM blood_requests
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );
}

$stmt->bind_param(
    "i",
    $request_id
);

$stmt->execute();

$result = $stmt->get_result();

$request = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   REQUEST NOT FOUND
   ========================================================= */

if (!$request) {

    header(
        "Location: requests.php?error=request_not_found"
    );

    exit;
}


/* =========================================================
   REQUEST MUST STILL BE SEARCHING
   ========================================================= */

if ($request["status"] !== "searching") {

    header(
        "Location: requests.php?error=request_closed"
    );

    exit;
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

    die(
        "Database error: " .
        $conn->error
    );
}

$stmt->bind_param(
    "i",
    $donor_id
);

$stmt->execute();

$result = $stmt->get_result();

$donor = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   DONOR PROFILE REQUIRED
   ========================================================= */

if (!$donor) {

    header(
        "Location: requests.php?error=profile_not_found"
    );

    exit;
}


/* =========================================================
   DONATE
   ========================================================= */

if ($action === "donate") {


    /* =====================================================
       DONOR AVAILABILITY
       ===================================================== */

    if ($donor["availability"] !== "available") {

        header(
            "Location: requests.php?error=donor_unavailable"
        );

        exit;
    }


    /* =====================================================
       BLOOD GROUP COMPATIBILITY CHECK
       =====================================================

       This is the important server-side protection.

       Even if somebody manually sends:

       action=donate

       through a browser or another request,

       the system checks compatibility here before
       creating the donor response.
       ===================================================== */

    if (
        !canDonateBlood(
            $donor["blood_group"],
            $request["blood_group"]
        )
    ) {

        header(
            "Location: requests.php?error=blood_group_not_compatible"
        );

        exit;
    }


    /* =====================================================
       CHECK EXISTING RESPONSE
       ===================================================== */

    $stmt = $conn->prepare("
        SELECT
            id,
            response_status
        FROM donor_responses
        WHERE request_id = ?
          AND donor_id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        die(
            "Database error: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $existing = $result->fetch_assoc();

    $stmt->close();


    /* =====================================================
       EXISTING RESPONSE
       ===================================================== */

    if ($existing) {


        /* -------------------------------------------------
           ALREADY INTERESTED
           ------------------------------------------------- */

        if (
            $existing["response_status"] === "interested"
        ) {

            header(
                "Location: requests.php?message=already_interested"
            );

            exit;
        }


        /* -------------------------------------------------
           ALREADY SELECTED
           ------------------------------------------------- */

        if (
            $existing["response_status"] === "accepted"
        ) {

            header(
                "Location: requests.php?message=already_selected"
            );

            exit;
        }


        /* -------------------------------------------------
           PREVIOUSLY CANCELLED

           Allow donor to volunteer again.
           ------------------------------------------------- */

        $stmt = $conn->prepare("
            UPDATE donor_responses

            SET
                response_status = 'interested',
                responded_at = NOW()

            WHERE id = ?
        ");

        if (!$stmt) {

            die(
                "Database error: " .
                $conn->error
            );
        }

        $stmt->bind_param(
            "i",
            $existing["id"]
        );

        $stmt->execute();

        $stmt->close();

    } else {


        /* =================================================
           CREATE NEW RESPONSE
           ================================================= */

        $stmt = $conn->prepare("
            INSERT INTO donor_responses
            (
                request_id,
                donor_id,
                response_status,
                responded_at
            )

            VALUES
            (
                ?,
                ?,
                'interested',
                NOW()
            )
        ");

        if (!$stmt) {

            die(
                "Database error: " .
                $conn->error
            );
        }

        $stmt->bind_param(
            "ii",
            $request_id,
            $donor_id
        );

        $stmt->execute();

        $stmt->close();
    }


    /* =====================================================
       NOTIFY REQUESTER
       ===================================================== */

    $title =
        "New Donor Volunteer";

    $message =
        "A donor has volunteered for Blood Request #" .
        $request_id .
        ".";

    $type =
        "match";


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
            ?,
            0,
            NOW()
        )
    ");

    if (!$stmt) {

        die(
            "Database error: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "iisss",
        $request["requester_id"],
        $request_id,
        $title,
        $message,
        $type
    );

    $stmt->execute();

    $stmt->close();


    /* =====================================================
       SUCCESS
       ===================================================== */

    header(
        "Location: requests.php?message=donation_response_saved"
    );

    exit;
}


/* =========================================================
   CANCEL DONATION RESPONSE
   ========================================================= */

if ($action === "cancel") {


    $stmt = $conn->prepare("
        UPDATE donor_responses

        SET
            response_status = 'cancelled',
            responded_at = NOW()

        WHERE request_id = ?
          AND donor_id = ?
          AND response_status = 'interested'
    ");

    if (!$stmt) {

        die(
            "Database error: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();

    $changed =
        $stmt->affected_rows;

    $stmt->close();


    /* =====================================================
       RESPONSE WAS CANCELLED
       ===================================================== */

    if ($changed > 0) {


        /* -------------------------------------------------
           NOTIFY REQUESTER
           ------------------------------------------------- */

        $title =
            "Donor Response Cancelled";

        $message =
            "A donor cancelled their response for Blood Request #" .
            $request_id .
            ".";

        $type =
            "match";


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
                ?,
                0,
                NOW()
            )
        ");

        if (!$stmt) {

            die(
                "Database error: " .
                $conn->error
            );
        }

        $stmt->bind_param(
            "iisss",
            $request["requester_id"],
            $request_id,
            $title,
            $message,
            $type
        );

        $stmt->execute();

        $stmt->close();


        /* -------------------------------------------------
           REDIRECT
           ------------------------------------------------- */

        header(
            "Location: requests.php?message=response_cancelled"
        );

        exit;
    }


    /* =====================================================
       NO ACTIVE RESPONSE
       ===================================================== */

    header(
        "Location: requests.php?message=no_active_response"
    );

    exit;
}


/* =========================================================
   FALLBACK
   ========================================================= */

header(
    "Location: requests.php?error=invalid_action"
);

exit;

?>