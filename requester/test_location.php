<?php

echo "<h2>BloodConnect Location Test</h2>";

echo "<p><b>PHP Version:</b> " . PHP_VERSION . "</p>";

/* Check cURL */
if (!function_exists("curl_init")) {
    die("<p style='color:red'><b>ERROR:</b> cURL is NOT enabled in PHP.</p>");
}

echo "<p style='color:green'>✓ cURL is enabled.</p>";

/* Test address */
$address = "Apollo Hospitals, Jubilee Hills, Hyderabad, Telangana, India";

$url = "https://nominatim.openstreetmap.org/search?" .
    http_build_query([
        "q" => $address,
        "format" => "json",
        "limit" => 1,
        "countrycodes" => "in"
    ]);

echo "<p><b>Testing address:</b> " . htmlspecialchars($address) . "</p>";

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,

    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "Accept-Language: en-IN,en;q=0.9"
    ],

    CURLOPT_USERAGENT =>
        "BloodConnect Student Project/1.0"
]);

$response = curl_exec($ch);

$error = curl_error($ch);

$errno = curl_errno($ch);

$http_code = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

echo "<p><b>HTTP Status:</b> " . $http_code . "</p>";

echo "<p><b>cURL Error Number:</b> " . $errno . "</p>";

echo "<p><b>cURL Error:</b> " .
    htmlspecialchars($error) .
    "</p>";

echo "<h3>Raw Response:</h3>";

echo "<pre>";

echo htmlspecialchars(
    $response
);

echo "</pre>";

if ($response === false) {

    echo "<h3 style='color:red'>❌ Location service connection failed.</h3>";

} elseif ($http_code != 200) {

    echo "<h3 style='color:red'>❌ Nominatim returned HTTP " .
        $http_code .
        "</h3>";

} else {

    $data = json_decode(
        $response,
        true
    );

    if (
        json_last_error() !== JSON_ERROR_NONE
    ) {

        echo "<h3 style='color:red'>❌ Invalid JSON response.</h3>";

    } elseif (empty($data)) {

        echo "<h3 style='color:red'>❌ No location found.</h3>";

    } else {

        echo "<h3 style='color:green'>✅ Location service is working!</h3>";

        echo "<p><b>Latitude:</b> " .
            htmlspecialchars($data[0]["lat"]) .
            "</p>";

        echo "<p><b>Longitude:</b> " .
            htmlspecialchars($data[0]["lon"]) .
            "</p>";

        echo "<p><b>Display Name:</b> " .
            htmlspecialchars($data[0]["display_name"]) .
            "</p>";
    }
}

?>