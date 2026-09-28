<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../config/database.php";

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

$request_id = isset($_POST["request_id"])
    ? (int) $_POST["request_id"]
    : 0;

$donor_id = isset($_POST["donor_id"])
    ? (int) $_POST["donor_id"]
    : 0;

if ($request_id <= 0 || $donor_id <= 0) {
    die("Invalid selection.");
}

/* =========================================================
   START TRANSACTION
   ========================================================= */

$conn->begin_transaction();

try {

    /* =====================================================
       LOCK REQUEST
       ===================================================== */

    $stmt = $conn->prepare("
        SELECT
            id,
            requester_id,
            blood_group,
            units_required,
            hospital_name,
            required_date,
            status
        FROM blood_requests
        WHERE id = ?
          AND requester_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $requester_id
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    $stmt->close();

    if (!$request) {
        throw new Exception("Request not found.");
    }

    /* =====================================================
       REQUEST MUST STILL BE SEARCHING
       ===================================================== */

    if ($request["status"] !== "searching") {
        throw new Exception(
            "A donor has already been selected or this request is closed."
        );
    }

    /* =====================================================
       CHECK DONOR RESPONSE
       ===================================================== */

    $stmt = $conn->prepare("
        SELECT
            id
        FROM donor_responses
        WHERE request_id = ?
          AND donor_id = ?
          AND response_status = 'interested'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $response = $result->fetch_assoc();

    $stmt->close();

    if (!$response) {
        throw new Exception(
            "This donor is no longer volunteering for the request."
        );
    }

    /* =====================================================
       MARK SELECTED DONOR
       ===================================================== */

    $stmt = $conn->prepare("
        UPDATE donor_responses
        SET response_status = 'accepted',
            responded_at = NOW()
        WHERE request_id = ?
          AND donor_id = ?
          AND response_status = 'interested'
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();
    $stmt->close();

    /* =====================================================
       MARK OTHER VOLUNTEERS AS DECLINED
       ===================================================== */

    $stmt = $conn->prepare("
        UPDATE donor_responses
        SET response_status = 'declined',
            responded_at = NOW()
        WHERE request_id = ?
          AND donor_id <> ?
          AND response_status = 'interested'
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();
    $stmt->close();

    /* =====================================================
       REQUEST MATCH
       ===================================================== */

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
        $donor_id
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $match = $result->fetch_assoc();

    $stmt->close();

    if ($match) {

        $stmt = $conn->prepare("
            UPDATE request_matches
            SET match_status = 'accepted',
                matched_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param(
            "i",
            $match["id"]
        );

        $stmt->execute();
        $stmt->close();

    } else {

        $stmt = $conn->prepare("
            INSERT INTO request_matches
            (
                request_id,
                donor_id,
                match_status,
                matched_at
            )
            VALUES (?, ?, 'accepted', NOW())
        ");

        $stmt->bind_param(
            "ii",
            $request_id,
            $donor_id
        );

        $stmt->execute();
        $stmt->close();
    }

    /* =====================================================
       REJECT OTHER MATCHES
       ===================================================== */

    $stmt = $conn->prepare("
        UPDATE request_matches
        SET match_status = 'rejected'
        WHERE request_id = ?
          AND donor_id <> ?
          AND match_status IN ('matched', 'contacted')
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();
    $stmt->close();

    /* =====================================================
       UPDATE REQUEST
       ===================================================== */

    $stmt = $conn->prepare("
        UPDATE blood_requests
        SET status = 'donor_found'
        WHERE id = ?
          AND requester_id = ?
          AND status = 'searching'
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $requester_id
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception(
            "The request could not be updated."
        );
    }

    $stmt->close();

    /* =====================================================
       GET DONOR NAME
       ===================================================== */

    $stmt = $conn->prepare("
        SELECT name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $donor_id
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $donor = $result->fetch_assoc();

    $stmt->close();

    $donor_name = $donor["name"] ?? "Selected donor";

    /* =====================================================
       NOTIFY SELECTED DONOR
       ===================================================== */

    $title = "You Have Been Selected";

    $message =
        "You have been selected as the donor for Blood Request #"
        . $request_id
        . " at "
        . $request["hospital_name"]
        . ".";

    $type = "match";

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
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");

    $stmt->bind_param(
        "iisss",
        $donor_id,
        $request_id,
        $title,
        $message,
        $type
    );

    $stmt->execute();
    $stmt->close();

    /* =====================================================
       NOTIFY OTHER VOLUNTEERS
       ===================================================== */

    $stmt = $conn->prepare("
        SELECT donor_id
        FROM donor_responses
        WHERE request_id = ?
          AND response_status = 'declined'
          AND donor_id <> ?
    ");

    $stmt->bind_param(
        "ii",
        $request_id,
        $donor_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $other_donors = [];

    while ($row = $result->fetch_assoc()) {
        $other_donors[] = (int) $row["donor_id"];
    }

    $stmt->close();

    $title = "Blood Request Updated";

    $message =
        "Another donor has been selected for Blood Request #"
        . $request_id
        . ".";

    foreach ($other_donors as $other_donor_id) {

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
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");

        $stmt->bind_param(
            "iisss",
            $other_donor_id,
            $request_id,
            $title,
            $message,
            $type
        );

        $stmt->execute();
        $stmt->close();
    }

    /* =====================================================
       COMMIT
       ===================================================== */

    $conn->commit();

    header(
        "Location: request_donors.php?request_id="
        . $request_id
        . "&message=donor_selected"
    );

    exit;

} catch (Throwable $e) {

    $conn->rollback();

    die(
        "Unable to select donor: "
        . htmlspecialchars($e->getMessage())
    );
}