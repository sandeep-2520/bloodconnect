<?php

/*
|--------------------------------------------------------------------------
| BloodConnect Email OTP - Brevo API
|--------------------------------------------------------------------------
*/

function sendOTPEmail($email, $name, $otp)
{
    /*
    |--------------------------------------------------------------------------
    | PUT YOUR BREVO API KEY HERE
    |--------------------------------------------------------------------------
    */

    $apiKey = getenv("BREVO_API_KEY");
    /*
    |--------------------------------------------------------------------------
    | YOUR VERIFIED BREVO SENDER EMAIL
    |--------------------------------------------------------------------------
    */

    $senderEmail = "bloodconnectall1@gmail.com";

    $senderName = "BloodConnect";


    /*
    |--------------------------------------------------------------------------
    | BREVO API URL
    |--------------------------------------------------------------------------
    */

    $url = "https://api.brevo.com/v3/smtp/email";


    /*
    |--------------------------------------------------------------------------
    | EMAIL DATA
    |--------------------------------------------------------------------------
    */

    $data = [

        "sender" => [

            "name" => $senderName,

            "email" => $senderEmail
        ],

        "to" => [

            [
                "email" => $email,

                "name" => $name
            ]

        ],

        "subject" =>
            "BloodConnect - Email Verification OTP",

        "htmlContent" => "

            <!DOCTYPE html>

            <html>

            <head>

                <meta charset='UTF-8'>

                <title>
                    BloodConnect OTP
                </title>

            </head>

            <body style='
                margin:0;
                padding:0;
                background:#f3f4f6;
                font-family:Arial, sans-serif;
            '>

                <div style='
                    max-width:600px;
                    margin:40px auto;
                    background:white;
                    border-radius:12px;
                    overflow:hidden;
                    box-shadow:0 4px 15px rgba(0,0,0,0.1);
                '>

                    <div style='
                        background:#dc2626;
                        color:white;
                        padding:25px;
                        text-align:center;
                    '>

                        <h1 style='
                            margin:0;
                        '>
                            🩸 BloodConnect
                        </h1>

                        <p>
                            Blood Donation & Emergency Support
                        </p>

                    </div>


                    <div style='
                        padding:30px;
                    '>

                        <h2>
                            Hello " .
                            htmlspecialchars($name) .
                            ",
                        </h2>

                        <p style='
                            font-size:16px;
                            color:#444;
                        '>

                            Thank you for registering with
                            <strong>
                                BloodConnect
                            </strong>.

                        </p>


                        <p style='
                            font-size:16px;
                            color:#444;
                        '>

                            Your email verification OTP is:

                        </p>


                        <div style='
                            text-align:center;
                            margin:30px 0;
                        '>

                            <span style='
                                display:inline-block;
                                background:#fee2e2;
                                color:#dc2626;
                                padding:18px 30px;
                                border-radius:10px;
                                font-size:32px;
                                font-weight:bold;
                                letter-spacing:8px;
                            '>

                                " .
                                htmlspecialchars($otp) .
                                "

                            </span>

                        </div>


                        <p style='
                            text-align:center;
                            color:#666;
                        '>

                            This OTP is valid for
                            <strong>
                                10 minutes
                            </strong>.

                        </p>


                        <p style='
                            color:#666;
                            font-size:14px;
                        '>

                            If you did not request this OTP,
                            please ignore this email.

                        </p>

                    </div>


                    <div style='
                        background:#f9fafb;
                        padding:20px;
                        text-align:center;
                        color:#888;
                        font-size:13px;
                    '>

                        © " .
                        date("Y") .
                        " BloodConnect

                    </div>

                </div>

            </body>

            </html>

        ",

        "textContent" =>
            "Your BloodConnect verification OTP is: "
            . $otp
            . ". This OTP is valid for 10 minutes."
    ];


    /*
    |--------------------------------------------------------------------------
    | CURL REQUEST
    |--------------------------------------------------------------------------
    */

    $ch = curl_init($url);


    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        [

            "accept: application/json",

            "api-key: " . $apiKey,

            "content-type: application/json"

        ]
    );


    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($data)
    );


    $response =
        curl_exec($ch);


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    $curlError =
        curl_error($ch);


    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | CURL ERROR
    |--------------------------------------------------------------------------
    */

    if ($response === false) {

        return "EMAIL ERROR: " . $curlError;
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode >= 200 &&
        $httpCode < 300
    ) {

        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | BREVO ERROR
    |--------------------------------------------------------------------------
    */

    $errorData =
        json_decode(
            $response,
            true
        );


    if (
        is_array($errorData)
        &&
        isset($errorData["message"])
    ) {

        return "EMAIL ERROR: "
            . $errorData["message"];
    }


    return "EMAIL ERROR: "
        . $response;
}