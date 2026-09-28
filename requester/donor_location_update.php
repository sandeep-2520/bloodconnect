<?php

require_once "../config/database.php";

$donor_user_id = 13;

$stmt = $conn->prepare("
    SELECT city, area, pincode
    FROM donor_profiles
    WHERE user_id = ?
");

$stmt->bind_param("i", $donor_user_id);
$stmt->execute();

$result = $stmt->get_result();
$donor = $result->fetch_assoc();
$stmt->close();

if (!$donor) {
    die("Donor profile not found.");
}

$address = trim(
    $donor["area"] . ", " .
    $donor["city"] . ", " .
    $donor["pincode"] . ", Andhra Pradesh, India"
);

$url = "https://nominatim.openstreetmap.org/search?" .
       http_build_query([
           "q" => $address,
           "format" => "json",
           "limit" => 1,
           "countrycodes" => "in"
       ]);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        "User-Agent: BloodConnect Student Project"
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    die("Geocoding failed: " . curl_error($ch));
}

curl_close($ch);

if ($http_code !== 200) {
    die("Nominatim returned HTTP status: " . $http_code);
}

$data = json_decode($response, true);

if (
    !is_array($data) ||
    empty($data) ||
    !isset($data[0]["lat"]) ||
    !isset($data[0]["lon"])
) {
    die("Could not find coordinates for: " . htmlspecialchars($address));
}

$latitude = (float)$data[0]["lat"];
$longitude = (float)$data[0]["lon"];

$update = $conn->prepare("
    UPDATE donor_profiles
    SET latitude = ?, longitude = ?
    WHERE user_id = ?
");

$update->bind_param(
    "ddi",
    $latitude,
    $longitude,
    $donor_user_id
);

if (!$update->execute()) {
    die("Database update failed: " . $update->error);
}

$update->close();

echo "<h2>Donor Location Updated</h2>";
echo "<p>Address: " . htmlspecialchars($address) . "</p>";
echo "<p>Latitude: <b>" . $latitude . "</b></p>";
echo "<p>Longitude: <b>" . $longitude . "</b></p>";
echo "<p style='color:green'>✓ Donor coordinates saved successfully.</p>";

?>