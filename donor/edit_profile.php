<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["user_role"] != "donor") {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $city = trim($_POST["city"]);
    $area = trim($_POST["area"]);
    $pincode = trim($_POST["pincode"]);
    $last_donation_date = $_POST["last_donation_date"];
    $availability = $_POST["availability"];

    if (empty($city)) {

        $message = "City is required.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE donor_profiles
             SET city = ?,
                 area = ?,
                 pincode = ?,
                 last_donation_date = NULLIF(?, ''),
                 availability = ?
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "sssssi",
            $city,
            $area,
            $pincode,
            $last_donation_date,
            $availability,
            $user_id
        );

        if ($stmt->execute()) {

            $message = "Profile updated successfully.";

        } else {

            $message = "Failed to update profile.";
        }

        $stmt->close();
    }
}

$stmt = $conn->prepare(
    "SELECT city, area, pincode, last_donation_date, availability
     FROM donor_profiles
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$donor = $result->fetch_assoc();

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

    <title>BloodConnect - Edit Profile</title>

</head>

<body>

    <h1>🩸 BloodConnect</h1>

    <h2>Edit Donor Profile</h2>

    <?php if (!empty($message)): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>

    <hr>

    <form method="POST">

        <label>City:</label><br>

        <input
            type="text"
            name="city"
            value="<?php echo htmlspecialchars($donor["city"]); ?>"
            required
        >

        <br><br>


        <label>Area:</label><br>

        <input
            type="text"
            name="area"
            value="<?php echo htmlspecialchars($donor["area"]); ?>"
        >

        <br><br>


        <label>Pincode:</label><br>

        <input
            type="text"
            name="pincode"
            value="<?php echo htmlspecialchars($donor["pincode"]); ?>"
        >

        <br><br>


        <label>Last Donation Date:</label><br>

        <input
            type="date"
            name="last_donation_date"
            value="<?php echo htmlspecialchars($donor["last_donation_date"] ?? ""); ?>"
        >

        <br><br>


        <label>Availability:</label><br>

        <select name="availability">

            <option
                value="available"
                <?php
                if ($donor["availability"] == "available") {
                    echo "selected";
                }
                ?>
            >
                Available
            </option>

            <option
                value="unavailable"
                <?php
                if ($donor["availability"] == "unavailable") {
                    echo "selected";
                }
                ?>
            >
                Unavailable
            </option>

        </select>

        <br><br>

        <button type="submit">
            Update Profile
        </button>

    </form>

    <hr>

    <a href="profile.php">View My Profile</a>

    <br><br>

    <a href="dashboard.php">Back to Dashboard</a>

    <br><br>

    <a href="../auth/logout.php">Logout</a>

</body>

</html>