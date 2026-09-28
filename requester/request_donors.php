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

$request_id = isset($_GET["request_id"])
    ? (int) $_GET["request_id"]
    : 0;

if ($request_id <= 0) {
    die("Invalid request.");
}

/* =========================================================
   GET REQUEST
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        blood_group,
        units_required,
        hospital_name,
        hospital_location,
        required_date,
        required_time,
        urgency,
        status,
        latitude,
        longitude
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

$result = $stmt->get_result();
$request = $result->fetch_assoc();

$stmt->close();

if (!$request) {
    die("Request not found.");
}

/* =========================================================
   GET VOLUNTEERS
   ========================================================= */

$donors = [];

$stmt = $conn->prepare("
    SELECT
        dr.id AS response_id,
        dr.donor_id,
        dr.response_status,
        dr.responded_at,

        u.name,
        u.phone,
        u.latitude AS donor_latitude,
        u.longitude AS donor_longitude,

        dp.blood_group,
        dp.city,
        dp.area,
        dp.availability

    FROM donor_responses dr

    INNER JOIN users u
        ON u.id = dr.donor_id

    INNER JOIN donor_profiles dp
        ON dp.user_id = dr.donor_id

    WHERE dr.request_id = ?
      AND dr.response_status = 'interested'

    ORDER BY dr.responded_at ASC
");

$stmt->bind_param("i", $request_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $donors[] = $row;
}

$stmt->close();

/* =========================================================
   DISTANCE FUNCTION
   ========================================================= */

function calculateDistance(
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

    $earthRadius = 6371;

    $lat1 = deg2rad((float)$lat1);
    $lon1 = deg2rad((float)$lon1);
    $lat2 = deg2rad((float)$lat2);
    $lon2 = deg2rad((float)$lon2);

    $dLat = $lat2 - $lat1;
    $dLon = $lon2 - $lon1;

    $a =
        sin($dLat / 2) * sin($dLat / 2)
        +
        cos($lat1)
        * cos($lat2)
        * sin($dLon / 2)
        * sin($dLon / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Donors - BloodConnect</title>

<style>

body {
    font-family: Arial, sans-serif;
    background: #f5f6fa;
    margin: 0;
}

.container {
    width: 92%;
    max-width: 1100px;
    margin: 30px auto;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.donor {
    border-left: 5px solid #c62828;
}

button {
    background: #c62828;
    color: white;
    border: none;
    padding: 11px 18px;
    border-radius: 7px;
    cursor: pointer;
}

.no-donors {
    padding: 20px;
    background: white;
    border-radius: 10px;
}

</style>

</head>

<body>

<div class="container">

<h1>🩸 Donors for Request #<?= $request_id ?></h1>

<div class="card">

    <h2>
        <?= htmlspecialchars($request["hospital_name"]) ?>
    </h2>

    <p>
        <strong>Blood Group:</strong>
        <?= htmlspecialchars($request["blood_group"]) ?>
    </p>

    <p>
        <strong>Units:</strong>
        <?= (int) $request["units_required"] ?>
    </p>

    <p>
        <strong>Location:</strong>
        <?= htmlspecialchars($request["hospital_location"]) ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?= htmlspecialchars($request["status"]) ?>
    </p>

</div>

<h2>
    Donors Who Can Donate
</h2>

<?php if (empty($donors)): ?>

<div class="no-donors">

    <p>
        No donors have volunteered yet.
    </p>

</div>

<?php endif; ?>


<?php foreach ($donors as $donor): ?>

<?php

$distance = calculateDistance(
    $request["latitude"],
    $request["longitude"],
    $donor["donor_latitude"],
    $donor["donor_longitude"]
);

?>

<div class="card donor">

    <h2>
        <?= htmlspecialchars($donor["name"]) ?>
    </h2>

    <p>
        <strong>Blood Group:</strong>
        <?= htmlspecialchars($donor["blood_group"]) ?>
    </p>

    <p>
        <strong>Location:</strong>
        <?= htmlspecialchars($donor["area"] ?? "") ?>,
        <?= htmlspecialchars($donor["city"]) ?>
    </p>

    <p>
        <strong>Availability:</strong>
        <?= htmlspecialchars($donor["availability"]) ?>
    </p>

    <p>

        <strong>Approximate Distance:</strong>

        <?php if ($distance !== null): ?>

            <?= number_format($distance, 2) ?> km

        <?php else: ?>

            Distance unavailable

        <?php endif; ?>

    </p>

    <p>
        <strong>Volunteered At:</strong>
        <?= htmlspecialchars($donor["responded_at"]) ?>
    </p>


    <?php if ($request["status"] === "searching"): ?>

        <form method="POST"
              action="select_donor.php"
              onsubmit="return confirm('Select this donor for the blood request?');">

            <input
                type="hidden"
                name="request_id"
                value="<?= $request_id ?>"
            >

            <input
                type="hidden"
                name="donor_id"
                value="<?= (int) $donor["donor_id"] ?>"
            >

            <button type="submit">
                ✅ Select Donor
            </button>

        </form>

    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

</body>

</html>