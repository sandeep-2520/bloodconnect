
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../config/database.php";
require_once "../config/email.php";

$message = "";
$message_type = "";
$show_otp = false;


/* =========================================================
   OTP VERIFICATION
   ========================================================= */

if (isset($_POST["verify_otp"])) {

    $entered_otp = trim($_POST["otp"] ?? "");

    if (!isset($_SESSION["registration_data"])) {

        $message = "Registration session expired. Please register again.";
        $message_type = "error";

    } else {

        $registration_data = $_SESSION["registration_data"];

        if (
            !isset($registration_data["otp_hash"]) ||
            !isset($registration_data["otp_expires"])
        ) {

            $message = "OTP session expired. Please register again.";
            $message_type = "error";
            $show_otp = true;

        } elseif (time() > $registration_data["otp_expires"]) {

            $message = "OTP expired. Please request a new OTP.";
            $message_type = "error";
            $show_otp = true;

        } elseif (
            !password_verify(
                $entered_otp,
                $registration_data["otp_hash"]
            )
        ) {

            $message = "Invalid OTP. Please try again.";
            $message_type = "error";
            $show_otp = true;

        } else {

            try {

                /* =================================================
                   NORMALIZE AND VALIDATE ROLE
                   ================================================= */

                $role = strtolower(
                    trim(
                        $registration_data["role"] ?? ""
                    )
                );

                if (
                    $role !== "donor" &&
                    $role !== "requester"
                ) {

                    throw new Exception(
                        "Invalid account type received."
                    );
                }


                /* =================================================
                   START TRANSACTION
                   ================================================= */

                $conn->begin_transaction();


                /* =================================================
                   INSERT USER

                   IMPORTANT:
                   The ENUM role is inserted as a literal value.

                   This avoids:
                   Data truncated for column 'role'
                   ================================================= */

                if ($role === "donor") {

                    $sql = "
                        INSERT INTO users
                        (
                            name,
                            email,
                            phone,
                            password,
                            role,
                            latitude,
                            longitude
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            'donor',
                            ?,
                            ?
                        )
                    ";

                } else {

                    $sql = "
                        INSERT INTO users
                        (
                            name,
                            email,
                            phone,
                            password,
                            role,
                            latitude,
                            longitude
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            'requester',
                            ?,
                            ?
                        )
                    ";
                }


                $stmt = $conn->prepare($sql);

                if (!$stmt) {

                    throw new Exception(
                        "User registration prepare failed: " .
                        $conn->error
                    );
                }


                $name =
                    $registration_data["name"];

                $email =
                    $registration_data["email"];

                $phone =
                    $registration_data["phone"];

                $password =
                    $registration_data["password"];

                $latitude =
                    floatval(
                        $registration_data["latitude"] ?? 0
                    );

                $longitude =
                    floatval(
                        $registration_data["longitude"] ?? 0
                    );


                /*
                 * Role is NOT included here because it is already
                 * inserted as 'donor' or 'requester' in the SQL.
                 */

                $stmt->bind_param(
                    "ssssdd",
                    $name,
                    $email,
                    $phone,
                    $password,
                    $latitude,
                    $longitude
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "User registration failed: " .
                        $stmt->error
                    );
                }


                $user_id = $conn->insert_id;

                $stmt->close();


                /* =================================================
                   DONOR PROFILE
                   ================================================= */

                if ($role === "donor") {

                    $blood_group =
                        $registration_data["blood_group"] ?? "";

                    $date_of_birth =
                        $registration_data["date_of_birth"] ?? "";

                    $gender =
                        $registration_data["gender"] ?? "";

                    $city =
                        $registration_data["city"] ?? "";

                    $area =
                        $registration_data["area"] ?? "";

                    $pincode =
                        $registration_data["pincode"] ?? "";

                    $last_donation_date =
                        $registration_data["last_donation_date"] ?? "";


                    $stmt2 = $conn->prepare(
                        "INSERT INTO donor_profiles
                        (
                            user_id,
                            blood_group,
                            date_of_birth,
                            gender,
                            city,
                            area,
                            pincode,
                            last_donation_date,
                            availability,
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
                            NULLIF(?, ''),
                            'available',
                            ?,
                            ?
                        )"
                    );


                    if (!$stmt2) {

                        throw new Exception(
                            "Donor profile prepare failed: " .
                            $conn->error
                        );
                    }


                    $stmt2->bind_param(
                        "isssssssdd",
                        $user_id,
                        $blood_group,
                        $date_of_birth,
                        $gender,
                        $city,
                        $area,
                        $pincode,
                        $last_donation_date,
                        $latitude,
                        $longitude
                    );


                    if (!$stmt2->execute()) {

                        throw new Exception(
                            "Donor profile creation failed: " .
                            $stmt2->error
                        );
                    }


                    $stmt2->close();
                }


                /* =================================================
                   COMMIT
                   ================================================= */

                $conn->commit();


                /* =================================================
                   CLEAR REGISTRATION SESSION
                   ================================================= */

                unset(
                    $_SESSION["registration_data"]
                );


                /* =================================================
                   REDIRECT
                   ================================================= */

                header(
                    "Location: login.php?registered=1"
                );

                exit;


            } catch (Exception $e) {

                /*
                 * IMPORTANT:
                 * Do NOT use $conn->in_transaction.
                 */

                $conn->rollback();

                $message =
                    "Registration failed: " .
                    $e->getMessage();

                $message_type =
                    "error";

                $show_otp = true;
            }
        }
    }
}


/* =========================================================
   RESEND OTP
   ========================================================= */

if (isset($_POST["resend_otp"])) {

    if (!isset($_SESSION["registration_data"])) {

        $message =
            "Registration session expired. Please register again.";

        $message_type =
            "error";

    } else {

        $registration_data =
            $_SESSION["registration_data"];


        $otp = str_pad(
            random_int(0, 999999),
            6,
            "0",
            STR_PAD_LEFT
        );


        $_SESSION["registration_data"]["otp_hash"] =
            password_hash(
                $otp,
                PASSWORD_DEFAULT
            );


        $_SESSION["registration_data"]["otp_expires"] =
            time() + 300;


        try {

            $sent = sendOTPEmail(
                $registration_data["email"],
                $registration_data["name"],
                $otp
            );


            if ($sent) {

                $message =
                    "A new OTP has been sent to your email.";

                $message_type =
                    "success";

            } else {

                $message =
                    "Unable to send OTP. Please try again.";

                $message_type =
                    "error";
            }

        } catch (Exception $e) {

            $message =
                "Unable to send OTP: " .
                $e->getMessage();

            $message_type =
                "error";
        }


        $show_otp = true;
    }
}


/* =========================================================
   START REGISTRATION
   ========================================================= */

if (isset($_POST["register"])) {

    /*
     * Remove old registration data so an old role/OTP
     * cannot be reused.
     */

    unset(
        $_SESSION["registration_data"]
    );


    $name =
        trim(
            $_POST["name"] ?? ""
        );

    $email =
        trim(
            $_POST["email"] ?? ""
        );

    $phone =
        trim(
            $_POST["phone"] ?? ""
        );

    $password =
        $_POST["password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    /*
     * Normalize role
     */

    $role =
        strtolower(
            trim(
                $_POST["role"] ?? ""
            )
        );


    $latitude =
        !empty($_POST["latitude"])
            ? floatval($_POST["latitude"])
            : 0;

    $longitude =
        !empty($_POST["longitude"])
            ? floatval($_POST["longitude"])
            : 0;


    /* =========================================================
       BASIC VALIDATION
       ========================================================= */

    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password) ||
        empty($role)
    ) {

        $message =
            "Please fill all required fields.";

        $message_type =
            "error";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

        $message_type =
            "error";

    } elseif (
        !preg_match(
            "/^[0-9]{10}$/",
            $phone
        )
    ) {

        $message =
            "Please enter a valid 10-digit phone number.";

        $message_type =
            "error";

    } elseif (
        strlen($password) < 6
    ) {

        $message =
            "Password must contain at least 6 characters.";

        $message_type =
            "error";

    } elseif (
        $password !== $confirm_password
    ) {

        $message =
            "Passwords do not match.";

        $message_type =
            "error";

    } elseif (
        $role !== "donor" &&
        $role !== "requester"
    ) {

        $message =
            "Invalid account type.";

        $message_type =
            "error";

    } else {


        /* =====================================================
           DONOR DATA
           ===================================================== */

        $blood_group = "";
        $date_of_birth = "";
        $gender = "";
        $city = "";
        $area = "";
        $pincode = "";
        $last_donation_date = "";


        if ($role === "donor") {

            $blood_group =
                trim(
                    $_POST["blood_group"] ?? ""
                );

            $date_of_birth =
                $_POST["date_of_birth"] ?? "";

            $gender =
                trim(
                    $_POST["gender"] ?? ""
                );

            $city =
                trim(
                    $_POST["city"] ?? ""
                );

            $area =
                trim(
                    $_POST["area"] ?? ""
                );

            $pincode =
                trim(
                    $_POST["pincode"] ?? ""
                );

            $last_donation_date =
                $_POST["last_donation_date"] ?? "";


            if (
                empty($blood_group) ||
                empty($date_of_birth) ||
                empty($gender) ||
                empty($city)
            ) {

                $message =
                    "Please fill all donor details.";

                $message_type =
                    "error";

            } elseif (
                !empty($pincode) &&
                !preg_match(
                    "/^[0-9]{6}$/",
                    $pincode
                )
            ) {

                $message =
                    "Please enter a valid 6-digit pincode.";

                $message_type =
                    "error";
            }
        }


        /* =====================================================
           CONTINUE REGISTRATION
           ===================================================== */

        if (empty($message)) {


            /* =================================================
               CHECK EMAIL
               ================================================= */

            $stmt = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );


            if (!$stmt) {

                $message =
                    "Database error: " .
                    $conn->error;

                $message_type =
                    "error";

            } else {

                $stmt->bind_param(
                    "s",
                    $email
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();


                if ($result->num_rows > 0) {

                    $message =
                        "Email already registered.";

                    $message_type =
                        "error";

                } else {


                    /* =========================================
                       CHECK PHONE
                       ========================================= */

                    $stmt2 = $conn->prepare(
                        "SELECT id
                         FROM users
                         WHERE phone = ?
                         LIMIT 1"
                    );


                    if (!$stmt2) {

                        $message =
                            "Database error: " .
                            $conn->error;

                        $message_type =
                            "error";

                    } else {

                        $stmt2->bind_param(
                            "s",
                            $phone
                        );

                        $stmt2->execute();

                        $result2 =
                            $stmt2->get_result();


                        if ($result2->num_rows > 0) {

                            $message =
                                "Phone number already registered.";

                            $message_type =
                                "error";

                        } else {


                            /* =================================
                               GENERATE OTP
                               ================================= */

                            $otp = str_pad(
                                random_int(
                                    0,
                                    999999
                                ),
                                6,
                                "0",
                                STR_PAD_LEFT
                            );


                            $otp_hash =
                                password_hash(
                                    $otp,
                                    PASSWORD_DEFAULT
                                );


                            $otp_expires =
                                time() + 300;


                            /* =================================
                               HASH PASSWORD
                               ================================= */

                            $hashed_password =
                                password_hash(
                                    $password,
                                    PASSWORD_DEFAULT
                                );


                            /* =================================
                               SAVE REGISTRATION DATA
                               ================================= */

                            $_SESSION[
                                "registration_data"
                            ] = [

                                "name" =>
                                    $name,

                                "email" =>
                                    $email,

                                "phone" =>
                                    $phone,

                                "password" =>
                                    $hashed_password,

                                "role" =>
                                    $role,

                                "blood_group" =>
                                    $blood_group,

                                "date_of_birth" =>
                                    $date_of_birth,

                                "gender" =>
                                    $gender,

                                "city" =>
                                    $city,

                                "area" =>
                                    $area,

                                "pincode" =>
                                    $pincode,

                                "last_donation_date" =>
                                    $last_donation_date,

                                "latitude" =>
                                    $latitude,

                                "longitude" =>
                                    $longitude,

                                "otp_hash" =>
                                    $otp_hash,

                                "otp_expires" =>
                                    $otp_expires
                            ];


                            /* =================================
                               SEND OTP
                               ================================= */

                            try {

                                $sent =
                                    sendOTPEmail(
                                        $email,
                                        $name,
                                        $otp
                                    );


                                if ($sent) {

                                    $message =
                                        "OTP sent to your email. Please check your inbox.";

                                    $message_type =
                                        "success";

                                    $show_otp =
                                        true;

                                } else {

                                    unset(
                                        $_SESSION[
                                            "registration_data"
                                        ]
                                    );

                                    $message =
                                        "Unable to send OTP. Please check your email configuration.";

                                    $message_type =
                                        "error";
                                }

                            } catch (Exception $e) {

                                unset(
                                    $_SESSION[
                                        "registration_data"
                                    ]
                                );

                                $message =
                                    "Email error: " .
                                    $e->getMessage();

                                $message_type =
                                    "error";
                            }
                        }

                        $stmt2->close();
                    }
                }

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   KEEP OTP SCREEN VISIBLE
   ========================================================= */

if (
    isset(
        $_SESSION["registration_data"]
    ) &&
    isset(
        $_SESSION["registration_data"]["otp_hash"]
    )
) {

    if (
        isset($_POST["verify_otp"]) ||
        isset($_POST["resend_otp"]) ||
        $show_otp
    ) {

        $show_otp = true;
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
BloodConnect - Registration
</title>


<style>

* {
    box-sizing: border-box;
}


html {
    scroll-behavior: smooth;
}


body {

    margin: 0;

    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #f5f5f5;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(220, 38, 38, 0.18),
            transparent 35%
        ),
        radial-gradient(
            circle at 90% 20%,
            rgba(239, 68, 68, 0.10),
            transparent 30%
        ),
        #080b12;

    padding: 35px 15px;

    overflow-x: hidden;
}


body::before {

    content: "";

    position: fixed;

    width: 300px;

    height: 300px;

    border-radius: 50%;

    background:
        rgba(220, 38, 38, 0.12);

    filter: blur(100px);

    top: 15%;

    left: -120px;

    pointer-events: none;

    z-index: -1;
}


body::after {

    content: "";

    position: fixed;

    width: 300px;

    height: 300px;

    border-radius: 50%;

    background:
        rgba(239, 68, 68, 0.08);

    filter: blur(100px);

    bottom: 5%;

    right: -120px;

    pointer-events: none;

    z-index: -1;
}


.container {

    width: 100%;

    max-width: 1100px;

    margin: auto;
}


.intro-section {

    text-align: center;

    margin-bottom: 28px;
}


.logo {

    font-size: 38px;

    font-weight: 800;

    margin-bottom: 8px;

    color: #ffffff;
}


.subtitle {

    color: #a9a9b2;

    font-size: 15px;

    margin-bottom: 24px;
}


.hero-banner {

    background:
        linear-gradient(
            135deg,
            rgba(127, 29, 29, 0.38),
            rgba(24, 24, 31, 0.92)
        );

    border:
        1px solid rgba(239, 68, 68, 0.22);

    border-radius: 24px;

    padding: 35px 28px;

    margin-bottom: 18px;

    box-shadow:
        0 20px 50px rgba(0,0,0,0.35);
}


.project-tag {

    display: inline-block;

    padding: 7px 14px;

    border-radius: 30px;

    background:
        rgba(239, 68, 68, 0.12);

    border:
        1px solid rgba(239, 68, 68, 0.3);

    color: #ff6b6b;

    font-size: 11px;

    font-weight: 700;

    letter-spacing: 1.3px;

    margin-bottom: 13px;
}


.hero-banner h1 {

    margin: 5px 0 12px;

    font-size: 34px;

    color: #ffffff;
}


.hero-banner p {

    max-width: 820px;

    margin: auto;

    color: #b9b9c2;

    line-height: 1.75;

    font-size: 14px;
}


.project-purpose {

    background:
        rgba(17, 19, 27, 0.85);

    border:
        1px solid rgba(255,255,255,0.07);

    border-radius: 18px;

    padding: 23px;

    margin-bottom: 18px;

    text-align: left;
}


.project-purpose h2 {

    margin-top: 0;

    color: #ff5c5c;

    font-size: 21px;
}


.project-purpose p {

    margin: 0;

    color: #a9a9b2;

    line-height: 1.7;

    font-size: 14px;
}


.feature-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 18px;
}


.feature-banner {

    background:
        linear-gradient(
            145deg,
            rgba(24, 27, 36, 0.95),
            rgba(13, 15, 21, 0.95)
        );

    border:
        1px solid rgba(255,255,255,0.07);

    border-radius: 18px;

    padding: 20px 16px;

    min-height: 165px;

    text-align: left;

    transition:
        transform 0.25s ease,
        border-color 0.25s ease;
}


.feature-banner:hover {

    transform:
        translateY(-5px);

    border-color:
        rgba(239,68,68,0.35);
}


.feature-icon {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        rgba(239,68,68,0.10);

    font-size: 25px;

    margin-bottom: 13px;
}


.feature-banner h3 {

    margin: 0 0 8px;

    color: #f5f5f5;

    font-size: 15px;
}


.feature-banner p {

    margin: 0;

    color: #92929d;

    font-size: 12px;

    line-height: 1.55;
}


.emergency-banner {

    display: flex;

    align-items: center;

    gap: 18px;

    background:
        linear-gradient(
            135deg,
            rgba(127,29,29,0.50),
            rgba(39,15,18,0.85)
        );

    border:
        1px solid rgba(239,68,68,0.25);

    border-radius: 18px;

    padding: 20px;

    margin-bottom: 20px;
}


.emergency-icon {

    width: 55px;

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 15px;

    background:
        rgba(239,68,68,0.14);

    font-size: 28px;

    flex-shrink: 0;
}


.emergency-content h3 {

    margin: 0 0 5px;

    color: #ff6b6b;

    font-size: 17px;
}


.emergency-content p {

    margin: 0;

    color: #aaaab3;

    font-size: 13px;

    line-height: 1.6;
}


.contact-section {

    margin-bottom: 25px;
}


.contact-title {

    color: #eeeeee;

    font-size: 18px;

    font-weight: 700;

    margin-bottom: 12px;
}


.contact-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;
}


.contact-card {

    display: flex;

    align-items: center;

    gap: 13px;

    text-align: left;

    background:
        rgba(17,19,27,0.85);

    border:
        1px solid rgba(255,255,255,0.07);

    border-radius: 15px;

    padding: 15px;
}


.contact-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    background:
        rgba(239,68,68,0.10);

    font-size: 20px;

    flex-shrink: 0;
}


.contact-card strong {

    display: block;

    color: #eeeeee;

    font-size: 12px;

    margin-bottom: 3px;
}


.contact-card a {

    color: #9f9faa;

    text-decoration: none;

    font-size: 13px;

    word-break: break-word;
}


.card {

    background:
        linear-gradient(
            145deg,
            rgba(20,22,30,0.97),
            rgba(11,13,19,0.97)
        );

    border:
        1px solid rgba(255,255,255,0.08);

    border-radius: 22px;

    padding: 30px;

    max-width: 620px;

    margin: auto;

    box-shadow:
        0 25px 60px rgba(0,0,0,0.45);
}


.card h2 {

    text-align: center;

    margin-top: 0;

    margin-bottom: 25px;

    color: #f5f5f5;

    font-size: 21px;
}


.message {

    padding: 13px 15px;

    border-radius: 12px;

    margin-bottom: 18px;

    text-align: center;

    font-size: 13px;
}


.message.error {

    background:
        rgba(220,38,38,0.10);

    color: #ff7777;

    border:
        1px solid rgba(239,68,68,0.25);
}


.message.success {

    background:
        rgba(34,197,94,0.08);

    color: #6ee7a0;

    border:
        1px solid rgba(34,197,94,0.2);
}


.form-group {

    margin-bottom: 17px;
}


.form-group label {

    display: block;

    color: #c8c8cf;

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 7px;
}


.form-group input,
.form-group select {

    width: 100%;

    padding: 12px 13px;

    border:
        1px solid #292c36;

    border-radius: 11px;

    background:
        #10131a;

    color: #eeeeee;

    font-size: 14px;

    outline: none;
}


.form-group input:focus,
.form-group select:focus {

    border-color:
        rgba(239,68,68,0.6);

    box-shadow:
        0 0 0 3px rgba(239,68,68,0.08);
}


#donorFields {

    display: none;

    background:
        rgba(127,29,29,0.08);

    border:
        1px solid rgba(239,68,68,0.14);

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 18px;
}


#donorFields h3 {

    margin-top: 0;

    color: #ff6464;

    font-size: 17px;
}


.location-box {

    background:
        rgba(255,255,255,0.025);

    border:
        1px solid rgba(255,255,255,0.07);

    border-radius: 12px;

    padding: 13px;

    margin-bottom: 18px;

    color: #888994;

    font-size: 12px;

    line-height: 1.5;
}


.location-status {

    color: #ff6666;

    font-weight: 600;
}


button {

    width: 100%;

    border: none;

    padding: 14px;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #b91c1c,
            #ef4444
        );

    color: #ffffff;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 12px 28px rgba(239,68,68,0.30);
}


.resend-btn {

    margin-top: 10px;

    background:
        #1d2028;

    color: #c8c8cf;

    box-shadow: none;
}


.login-link {

    text-align: center;

    margin-top: 19px;

    color: #777984;

    font-size: 13px;
}


.login-link a {

    color: #ff6262;

    font-weight: 600;

    text-decoration: none;
}


.otp-box {

    text-align: center;
}


.otp-box p {

    color: #888994;

    font-size: 13px;

    line-height: 1.6;
}


.otp-box input {

    text-align: center;

    font-size: 25px;

    font-weight: 700;

    letter-spacing: 8px;

    padding: 15px;
}


.footer {

    text-align: center;

    color: #666873;

    font-size: 11px;

    line-height: 1.7;

    margin-top: 25px;
}


@media (max-width: 850px) {

    .feature-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media (max-width: 600px) {

    body {

        padding:
            20px 10px;
    }


    .hero-banner h1 {

        font-size: 25px;
    }


    .feature-grid {

        grid-template-columns: 1fr;
    }


    .contact-grid {

        grid-template-columns: 1fr;
    }


    .emergency-banner {

        align-items: flex-start;
    }


    .card {

        padding: 23px 17px;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         PROJECT INTRODUCTION
         ===================================================== -->

    <div class="intro-section">


        <div class="logo">
            🩸 BloodConnect
        </div>


        <div class="subtitle">
            Connecting Blood Donors With Those In Need
        </div>


        <div class="hero-banner">

            <div class="project-tag">
                SMART BLOOD DONATION PLATFORM
            </div>


            <h1>
                Every Drop Can Save a Life ❤️
            </h1>


            <p>

                BloodConnect is a smart blood donation platform
                designed to connect people who need blood with
                eligible blood donors. Our system makes it easier
                to find compatible donors, communicate with them,
                share locations and coordinate blood donation.

            </p>

        </div>


        <div class="project-purpose">

            <h2>
                🎯 Why BloodConnect?
            </h2>


            <p>

                Finding blood during an emergency can be difficult
                and time-consuming. BloodConnect provides a
                centralized platform where requesters can create
                blood requests and eligible donors can respond.
                The system supports blood-group matching,
                location-based donor identification, communication,
                notifications and donation status tracking.

            </p>

        </div>


        <div class="feature-grid">


            <div class="feature-banner">

                <div class="feature-icon">
                    📍
                </div>

                <h3>
                    Location-Based Matching
                </h3>

                <p>
                    Helps identify nearby eligible donors using
                    location information.
                </p>

            </div>


            <div class="feature-banner">

                <div class="feature-icon">
                    🤝
                </div>

                <h3>
                    Two-Way Communication
                </h3>

                <p>
                    Requesters and donors can communicate through
                    the donation request process.
                </p>

            </div>


            <div class="feature-banner">

                <div class="feature-icon">
                    🔔
                </div>

                <h3>
                    Notifications
                </h3>

                <p>
                    Important blood request and donor updates
                    can be delivered through notifications.
                </p>

            </div>


            <div class="feature-banner">

                <div class="feature-icon">
                    🩸
                </div>

                <h3>
                    Blood Group Matching
                </h3>

                <p>
                    Requests can be matched with compatible
                    blood groups.
                </p>

            </div>


        </div>


        <div class="emergency-banner">

            <div class="emergency-icon">
                🚨
            </div>


            <div class="emergency-content">

                <h3>
                    Built for Urgent Blood Requirements
                </h3>


                <p>

                    When every minute matters, BloodConnect helps
                    organize blood requests, donor responses,
                    location information and communication in one
                    platform.

                </p>

            </div>

        </div>


        <div class="contact-section">

            <div class="contact-title">
                📞 Contact BloodConnect
            </div>


            <div class="contact-grid">


                <div class="contact-card">

                    <div class="contact-icon">
                        📧
                    </div>


                    <div>

                        <strong>
                            Email
                        </strong>

                        <a href="mailto:soloking3718@gmail.com">
                            soloking3718@gmail.com
                        </a>

                    </div>

                </div>


                <div class="contact-card">

                    <div class="contact-icon">
                        📱
                    </div>


                    <div>

                        <strong>
                            Phone
                        </strong>

                        <a href="tel:8106389767">
                            8106389767
                        </a>

                    </div>

                </div>


            </div>

        </div>

    </div>



    <!-- =====================================================
         REGISTRATION CARD
         ===================================================== -->

    <div class="card">


        <?php if ($message !== ""): ?>

            <div class="message <?= htmlspecialchars($message_type) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>



        <?php if ($show_otp): ?>


            <!-- =================================================
                 OTP SCREEN
                 ================================================= -->

            <div class="otp-box">


                <h2>
                    🔐 Verify Your Email
                </h2>


                <p>

                    Enter the 6-digit OTP sent to:

                    <br>

                    <strong>

                        <?= htmlspecialchars(
                            $_SESSION["registration_data"]["email"]
                            ?? ""
                        ) ?>

                    </strong>

                </p>


                <form method="POST">


                    <div class="form-group">

                        <label>
                            Enter OTP
                        </label>


                        <input
                            type="text"
                            name="otp"
                            maxlength="6"
                            minlength="6"
                            pattern="[0-9]{6}"
                            placeholder="000000"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        name="verify_otp"
                    >
                        Verify OTP & Complete Registration
                    </button>


                </form>


                <form method="POST">


                    <button
                        type="submit"
                        name="resend_otp"
                        class="resend-btn"
                    >
                        🔄 Resend OTP
                    </button>


                </form>


                <div class="login-link">

                    Want to start again?

                    <a href="register.php">
                        Register Again
                    </a>

                </div>


            </div>


        <?php else: ?>


            <!-- =================================================
                 REGISTRATION FORM
                 ================================================= -->

            <h2>
                🩸 Create Your BloodConnect Account
            </h2>


            <form
                method="POST"
                id="registrationForm"
            >


                <div class="form-group">

                    <label>
                        Full Name *
                    </label>


                    <input
                        type="text"
                        name="name"
                        placeholder="Enter your full name"
                        required
                        value="<?= htmlspecialchars(
                            $_POST["name"] ?? ""
                        ) ?>"
                    >

                </div>



                <div class="form-group">

                    <label>
                        Email Address *
                    </label>


                    <input
                        type="email"
                        name="email"
                        placeholder="Enter your email"
                        required
                        value="<?= htmlspecialchars(
                            $_POST["email"] ?? ""
                        ) ?>"
                    >

                </div>



                <div class="form-group">

                    <label>
                        Phone Number *
                    </label>


                    <input
                        type="tel"
                        name="phone"
                        maxlength="10"
                        pattern="[0-9]{10}"
                        placeholder="10-digit mobile number"
                        required
                        value="<?= htmlspecialchars(
                            $_POST["phone"] ?? ""
                        ) ?>"
                    >

                </div>



                <div class="form-group">

                    <label>
                        Password *
                    </label>


                    <input
                        type="password"
                        name="password"
                        id="password"
                        minlength="6"
                        placeholder="Minimum 6 characters"
                        required
                    >

                </div>



                <div class="form-group">

                    <label>
                        Confirm Password *
                    </label>


                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        minlength="6"
                        placeholder="Re-enter your password"
                        required
                    >

                </div>



                <div class="form-group">

                    <label>
                        I want to register as *
                    </label>


                    <select
                        name="role"
                        id="role"
                        required
                    >

                        <option value="">
                            Select Account Type
                        </option>


                        <option
                            value="donor"
                            <?= (
                                ($_POST["role"] ?? "") === "donor"
                            )
                                ? "selected"
                                : "" ?>
                        >
                            🩸 Blood Donor
                        </option>


                        <option
                            value="requester"
                            <?= (
                                ($_POST["role"] ?? "") === "requester"
                            )
                                ? "selected"
                                : "" ?>
                        >
                            🆘 Blood Requester
                        </option>


                    </select>

                </div>



                <!-- =================================================
                     DONOR FIELDS
                     ================================================= -->

                <div id="donorFields">


                    <h3>
                        🩸 Donor Information
                    </h3>


                    <div class="form-group">

                        <label>
                            Blood Group *
                        </label>


                        <select
                            name="blood_group"
                        >

                            <option value="">
                                Select Blood Group
                            </option>


                            <?php

                            $blood_groups = [
                                "A+",
                                "A-",
                                "B+",
                                "B-",
                                "AB+",
                                "AB-",
                                "O+",
                                "O-"
                            ];

                            foreach (
                                $blood_groups
                                as $group
                            ):

                            ?>

                                <option
                                    value="<?= htmlspecialchars($group) ?>"
                                    <?= (
                                        ($_POST["blood_group"] ?? "")
                                        === $group
                                    )
                                        ? "selected"
                                        : "" ?>
                                >

                                    <?= htmlspecialchars($group) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>



                    <div class="form-group">

                        <label>
                            Date of Birth *
                        </label>


                        <input
                            type="date"
                            name="date_of_birth"
                            value="<?= htmlspecialchars(
                                $_POST["date_of_birth"] ?? ""
                            ) ?>"
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Gender *
                        </label>


                        <select
                            name="gender"
                        >

                            <option value="">
                                Select Gender
                            </option>


                            <option
                                value="male"
                                <?= (
                                    ($_POST["gender"] ?? "")
                                    === "male"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                Male
                            </option>


                            <option
                                value="female"
                                <?= (
                                    ($_POST["gender"] ?? "")
                                    === "female"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                Female
                            </option>


                            <option
                                value="other"
                                <?= (
                                    ($_POST["gender"] ?? "")
                                    === "other"
                                )
                                    ? "selected"
                                    : "" ?>
                            >
                                Other
                            </option>

                        </select>

                    </div>



                    <div class="form-group">

                        <label>
                            City *
                        </label>


                        <input
                            type="text"
                            name="city"
                            placeholder="Enter your city"
                            value="<?= htmlspecialchars(
                                $_POST["city"] ?? ""
                            ) ?>"
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Area
                        </label>


                        <input
                            type="text"
                            name="area"
                            placeholder="Enter your area"
                            value="<?= htmlspecialchars(
                                $_POST["area"] ?? ""
                            ) ?>"
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Pincode
                        </label>


                        <input
                            type="text"
                            name="pincode"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="6-digit pincode"
                            value="<?= htmlspecialchars(
                                $_POST["pincode"] ?? ""
                            ) ?>"
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Last Donation Date
                        </label>


                        <input
                            type="date"
                            name="last_donation_date"
                            value="<?= htmlspecialchars(
                                $_POST["last_donation_date"] ?? ""
                            ) ?>"
                        >

                    </div>


                </div>



                <!-- =================================================
                     LOCATION
                     ================================================= -->

                <div class="location-box">

                    📍 <strong>Location Services</strong>

                    <br><br>

                    Your location helps BloodConnect identify
                    nearby donors and calculate approximate
                    distance.

                    <br><br>

                    <span
                        id="locationStatus"
                        class="location-status"
                    >
                        Detecting location...
                    </span>

                </div>


                <input
                    type="hidden"
                    name="latitude"
                    id="latitude"
                    value="<?= htmlspecialchars(
                        $_POST["latitude"] ?? "0"
                    ) ?>"
                >


                <input
                    type="hidden"
                    name="longitude"
                    id="longitude"
                    value="<?= htmlspecialchars(
                        $_POST["longitude"] ?? "0"
                    ) ?>"
                >


                <button
                    type="submit"
                    name="register"
                >
                    🩸 Create BloodConnect Account
                </button>


            </form>


            <div class="login-link">

                Already have an account?

                <a href="login.php">
                    Login Here
                </a>

            </div>


        <?php endif; ?>


    </div>


    <div class="footer">

        🩸 BloodConnect © <?= date("Y") ?>

        <br>

        Connecting People • Saving Lives

    </div>


</div>



<script>

/* =========================================================
   ROLE TOGGLE
   ========================================================= */

const roleSelect =
    document.getElementById("role");

const donorFields =
    document.getElementById("donorFields");


function toggleDonorFields() {

    if (!roleSelect || !donorFields) {
        return;
    }


    if (roleSelect.value === "donor") {

        donorFields.style.display =
            "block";

    } else {

        donorFields.style.display =
            "none";
    }
}


if (roleSelect) {

    roleSelect.addEventListener(
        "change",
        toggleDonorFields
    );

    toggleDonorFields();
}


/* =========================================================
   PASSWORD MATCH
   ========================================================= */

const registrationForm =
    document.getElementById(
        "registrationForm"
    );


if (registrationForm) {

    registrationForm.addEventListener(
        "submit",
        function(event) {

            const password =
                document.getElementById(
                    "password"
                ).value;


            const confirmPassword =
                document.getElementById(
                    "confirm_password"
                ).value;


            if (
                password !==
                confirmPassword
            ) {

                event.preventDefault();

                alert(
                    "Passwords do not match."
                );

                return;
            }


            if (
                roleSelect &&
                roleSelect.value === "donor"
            ) {

                const bloodGroup =
                    document.querySelector(
                        '[name="blood_group"]'
                    ).value;


                const dob =
                    document.querySelector(
                        '[name="date_of_birth"]'
                    ).value;


                const gender =
                    document.querySelector(
                        '[name="gender"]'
                    ).value;


                const city =
                    document.querySelector(
                        '[name="city"]'
                    ).value.trim();


                if (
                    !bloodGroup ||
                    !dob ||
                    !gender ||
                    !city
                ) {

                    event.preventDefault();

                    alert(
                        "Please fill all required donor details."
                    );

                    return;
                }
            }

        }
    );
}


/* =========================================================
   GET LOCATION
   ========================================================= */

const locationStatus =
    document.getElementById(
        "locationStatus"
    );


function getLocation() {

    if (!navigator.geolocation) {

        if (locationStatus) {

            locationStatus.innerText =
                "Location services are not supported by your browser.";
        }

        return;
    }


    navigator.geolocation.getCurrentPosition(

        function(position) {

            const latitude =
                position.coords.latitude;


            const longitude =
                position.coords.longitude;


            document.getElementById(
                "latitude"
            ).value =
                latitude;


            document.getElementById(
                "longitude"
            ).value =
                longitude;


            if (locationStatus) {

                locationStatus.innerText =
                    "✓ Location detected successfully";
            }

        },


        function(error) {

            if (!locationStatus) {
                return;
            }


            if (error.code === 1) {

                locationStatus.innerText =
                    "Location permission denied. You can continue registration.";

            } else if (error.code === 2) {

                locationStatus.innerText =
                    "Location unavailable. You can continue registration.";

            } else {

                locationStatus.innerText =
                    "Unable to detect location. You can continue registration.";
            }

        },


        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }

    );
}


window.addEventListener(
    "load",
    getLocation
);

</script>


</body>

</html>