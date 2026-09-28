<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once "../config/database.php";

echo "<h2>BloodConnect Database Location Insert Test</h2>";

if (!$conn) {
    die("❌ Database connection failed.");
}

echo "<p style='color:green'>✓ Database connection successful.</p>";

$requester_id = 9;
$blood_group = "A+";
$units_required = 1;
$hospital_name = "Apollo Hospitals";
$hospital_location = "Jubilee Hills, Hyderabad, Telangana";
$required_date = date("Y-m-d");
$required_time = null;
$urgency = "normal";
$patient_name = "Location Test";
$contact_phone = "9999999999";
$additional_message = "Temporary location test";
$latitude = 17.4149358;
$longitude = 78.4131784;

$sql = "
    INSERT INTO blood_requests
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
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
";

echo "<h3>Preparing INSERT...</h3>";

$stmt = $conn->prepare($sql);

if ($stmt === false) {

    echo "<p style='color:red'>";
    echo "<b>❌ PREPARE FAILED</b><br><br>";
    echo "Error Number: " . $conn->errno . "<br>";
    echo "MySQL Error: " . htmlspecialchars($conn->error);
    echo "</p>";

    exit;
}

echo "<p style='color:green'>✓ INSERT statement prepared.</p>";

echo "<h3>Binding parameters...</h3>";

$bind_result = $stmt->bind_param(
    "isissssssssdd",
    $requester_id,
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

if ($bind_result === false) {

    echo "<p style='color:red'>";
    echo "<b>❌ BIND FAILED</b><br><br>";
    echo "Error: " . htmlspecialchars($stmt->error);
    echo "</p>";

    exit;
}

echo "<p style='color:green'>✓ Parameters bound successfully.</p>";

echo "<h3>Executing INSERT...</h3>";

try {

    $execute_result = $stmt->execute();

    if ($execute_result === false) {

        echo "<p style='color:red'>";
        echo "<b>❌ EXECUTE FAILED</b><br><br>";
        echo "<b>MySQL Error Number:</b> ";
        echo htmlspecialchars((string)$stmt->errno);
        echo "<br><br>";
        echo "<b>MySQL Error:</b><br>";
        echo htmlspecialchars($stmt->error);
        echo "</p>";

        $stmt->close();
        exit;
    }

    $test_id = $stmt->insert_id;

    echo "<p style='color:green;font-size:20px'>";
    echo "✅ INSERT WORKED!";
    echo "</p>";

    echo "<p>Test Request ID: <b>" . $test_id . "</b></p>";
    echo "<p>Latitude: <b>" . $latitude . "</b></p>";
    echo "<p>Longitude: <b>" . $longitude . "</b></p>";

    // Delete temporary test record
    $delete = $conn->prepare("
        DELETE FROM blood_requests
        WHERE id = ?
    ");

    if ($delete) {

        $delete->bind_param("i", $test_id);
        $delete->execute();
        $delete->close();

        echo "<p style='color:green'>";
        echo "✓ Temporary test record deleted.";
        echo "</p>";
    }

} catch (Throwable $e) {

    echo "<p style='color:red'>";
    echo "<b>❌ PHP/MYSQL EXCEPTION</b><br><br>";

    echo "<b>Exception message:</b><br>";
    echo htmlspecialchars($e->getMessage());

    echo "<br><br>";

    echo "<b>Exception type:</b><br>";
    echo htmlspecialchars(get_class($e));

    echo "</p>";
}

$stmt->close();

?>