<?php

/*
|--------------------------------------------------------------------------
| BloodConnect Geocoder
|--------------------------------------------------------------------------
|
| create_request.php calls this file.
|
| This file searches:
|
| 1. Photon
| 2. Nominatim
|
|--------------------------------------------------------------------------
*/

header(
    "Content-Type: application/json; charset=UTF-8"
);


/* =========================================================
   ONLY GET REQUESTS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);

    exit;
}


/* =========================================================
   GET SEARCH QUERY
========================================================= */

$query = trim(
    $_GET["q"] ?? ""
);


if ($query === "") {

    echo json_encode([
        "success" => false,
        "message" => "Please enter a location."
    ]);

    exit;
}


/* =========================================================
   PHOTON SEARCH
========================================================= */

function searchPhoton($query)
{

    $url =
        "https://photon.komoot.io/api/?" .
        http_build_query([
            "q" => $query,
            "limit" => 8
        ]);


    $ch = curl_init(
        $url
    );


    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 10,

            CURLOPT_CONNECTTIMEOUT => 5,

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_USERAGENT =>
                "BloodConnect/1.0"

        ]
    );


    $response =
        curl_exec($ch);


    if ($response === false) {

        curl_close($ch);

        return [];

    }


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    if ($httpCode !== 200) {

        return [];

    }


    $data =
        json_decode(
            $response,
            true
        );


    if (
        !isset($data["features"]) ||
        !is_array($data["features"])
    ) {

        return [];

    }


    $results = [];


    foreach (
        $data["features"]
        as $feature
    ) {

        if (
            !isset(
                $feature["geometry"]["coordinates"][0]
            ) ||
            !isset(
                $feature["geometry"]["coordinates"][1]
            )
        ) {

            continue;

        }


        /*
         * GeoJSON uses:
         *
         * coordinates[0] = longitude
         * coordinates[1] = latitude
         */

        $longitude =
            (float)
            $feature["geometry"]["coordinates"][0];


        $latitude =
            (float)
            $feature["geometry"]["coordinates"][1];


        if (
            $latitude < -90 ||
            $latitude > 90 ||
            $longitude < -180 ||
            $longitude > 180
        ) {

            continue;

        }


        $properties =
            $feature["properties"] ?? [];


        $name =
            trim(
                $properties["name"] ?? ""
            );


        $street =
            trim(
                $properties["street"] ?? ""
            );


        $city =
            trim(
                $properties["city"] ?? ""
            );


        $state =
            trim(
                $properties["state"] ?? ""
            );


        $country =
            trim(
                $properties["country"] ?? ""
            );


        $parts = [];


        if ($name !== "") {
            $parts[] = $name;
        }


        if ($street !== "") {
            $parts[] = $street;
        }


        if ($city !== "") {
            $parts[] = $city;
        }


        if ($state !== "") {
            $parts[] = $state;
        }


        if ($country !== "") {
            $parts[] = $country;
        }


        $address =
            implode(
                ", ",
                $parts
            );


        $results[] = [

            "name" => $name,

            "address" => $address,

            "latitude" => $latitude,

            "longitude" => $longitude

        ];

    }


    return $results;
}


/* =========================================================
   NOMINATIM SEARCH
========================================================= */

function searchNominatim($query)
{

    $url =
        "https://nominatim.openstreetmap.org/search?" .
        http_build_query([

            "q" => $query,

            "format" => "jsonv2",

            "limit" => 8,

            "addressdetails" => 1,

            "countrycodes" => "in"

        ]);


    $ch =
        curl_init(
            $url
        );


    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 10,

            CURLOPT_CONNECTTIMEOUT => 5,

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_USERAGENT =>
                "BloodConnect/1.0 (blood donation application)",

            CURLOPT_HTTPHEADER => [

                "Accept: application/json",

                "Referer: http://" .
                ($_SERVER["HTTP_HOST"] ?? "localhost")

            ]

        ]
    );


    $response =
        curl_exec($ch);


    if ($response === false) {

        curl_close($ch);

        return [];

    }


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    if ($httpCode !== 200) {

        return [];

    }


    $data =
        json_decode(
            $response,
            true
        );


    if (!is_array($data)) {

        return [];

    }


    $results = [];


    foreach (
        $data
        as $item
    ) {

        if (
            !isset($item["lat"]) ||
            !isset($item["lon"])
        ) {

            continue;

        }


        $latitude =
            (float)
            $item["lat"];


        $longitude =
            (float)
            $item["lon"];


        if (
            $latitude < -90 ||
            $latitude > 90 ||
            $longitude < -180 ||
            $longitude > 180
        ) {

            continue;

        }


        $displayName =
            $item["display_name"] ??
            "Selected location";


        $name = "";


        if (
            isset($item["name"]) &&
            $item["name"] !== ""
        ) {

            $name =
                $item["name"];

        } else {

            $name =
                $displayName;

        }


        $results[] = [

            "name" => $name,

            "address" => $displayName,

            "latitude" => $latitude,

            "longitude" => $longitude

        ];

    }


    return $results;
}


/* =========================================================
   SEARCH
========================================================= */


/*
 * First try Photon.
 */

$results =
    searchPhoton(
        $query
    );


/*
 * If Photon doesn't find anything,
 * try Nominatim.
 */

if (
    empty($results)
) {

    $results =
        searchNominatim(
            $query
        );

}


/* =========================================================
   REMOVE DUPLICATES
========================================================= */

$uniqueResults = [];


foreach (
    $results
    as $result
) {

    $key =
        $result["latitude"] .
        "," .
        $result["longitude"];


    if (
        !isset(
            $uniqueResults[$key]
        )
    ) {

        $uniqueResults[$key] =
            $result;

    }

}


$results =
    array_values(
        $uniqueResults
    );


/* =========================================================
   RESPONSE
========================================================= */

if (
    empty($results)
) {

    echo json_encode([

        "success" => false,

        "message" =>
            "Location not found. Please try a more complete hospital address."

    ]);

    exit;
}


echo json_encode([

    "success" => true,

    "results" => $results

]);

exit;