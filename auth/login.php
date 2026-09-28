<?php

session_start();

require_once "../config/database.php";

$message = "";


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {

        $message = "Please enter email and password.";

    } else {

        // Find user by email
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role, status
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {

            $user = $result->fetch_assoc();


            // Check account status
            if ($user["status"] == "blocked") {

                $message = "Your account has been blocked.";

            } else {

                // Verify password
                if (
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {

                    // Regenerate session ID
                    session_regenerate_id(true);


                    // Store user information in session
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = $user["role"];


                    /*
                    |--------------------------------------------------------------------------
                    | Role-Based Redirect
                    |--------------------------------------------------------------------------
                    */

                    if ($user["role"] == "admin") {

                        header(
                            "Location: ../admin/dashboard.php"
                        );

                        exit;

                    } elseif ($user["role"] == "donor") {

                        header(
                            "Location: ../donor/dashboard.php"
                        );

                        exit;

                    } elseif ($user["role"] == "requester") {

                        header(
                            "Location: ../requester/dashboard.php"
                        );

                        exit;

                    } else {

                        header(
                            "Location: ../dashboard.php"
                        );

                        exit;
                    }

                } else {

                    $message = "Invalid email or password.";
                }
            }

        } else {

            $message = "Invalid email or password.";
        }

        $stmt->close();
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

    <title>Login | BloodConnect</title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                radial-gradient(
                    circle at 10% 15%,
                    rgba(220, 38, 38, 0.18),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(153, 27, 27, 0.18),
                    transparent 35%
                ),
                #09090b;

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;

            overflow: hidden;
        }


        /*
        |--------------------------------------------------------------------------
        | Background Glows
        |--------------------------------------------------------------------------
        */

        .glow {

            position: fixed;

            width: 300px;

            height: 300px;

            border-radius: 50%;

            filter: blur(110px);

            opacity: 0.23;

            pointer-events: none;
        }


        .glow-one {

            background: #dc2626;

            top: -130px;

            left: -130px;
        }


        .glow-two {

            background: #991b1b;

            right: -130px;

            bottom: -130px;
        }


        /*
        |--------------------------------------------------------------------------
        | Main Container
        |--------------------------------------------------------------------------
        */

        .container {

            width: 100%;

            max-width: 440px;

            position: relative;

            z-index: 2;
        }


        /*
        |--------------------------------------------------------------------------
        | Logo
        |--------------------------------------------------------------------------
        */

        .logo-section {

            text-align: center;

            margin-bottom: 25px;
        }


        .logo {

            display: inline-flex;

            align-items: center;

            gap: 10px;

            font-size: 30px;

            font-weight: 800;

            letter-spacing: -1px;
        }


        .logo-icon {

            width: 48px;

            height: 48px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 14px;

            background:
                linear-gradient(
                    135deg,
                    #dc2626,
                    #991b1b
                );

            box-shadow:
                0 12px 35px rgba(220, 38, 38, 0.32);

            font-size: 24px;
        }


        .logo span {

            color: #ef4444;
        }


        .tagline {

            color: #a1a1aa;

            font-size: 14px;

            margin-top: 9px;
        }


        /*
        |--------------------------------------------------------------------------
        | Login Card
        |--------------------------------------------------------------------------
        */

        .login-card {

            background:
                rgba(24, 24, 27, 0.86);

            border:
                1px solid rgba(255, 255, 255, 0.08);

            border-radius: 24px;

            padding: 35px;

            backdrop-filter: blur(20px);

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.45);
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .card-header {

            text-align: center;

            margin-bottom: 28px;
        }


        .card-header h1 {

            font-size: 28px;

            margin-bottom: 8px;
        }


        .card-header p {

            color: #a1a1aa;

            font-size: 14px;

            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Error Message
        |--------------------------------------------------------------------------
        */

        .message {

            padding: 14px 15px;

            margin-bottom: 20px;

            border-radius: 12px;

            background:
                rgba(127, 29, 29, 0.28);

            border:
                1px solid rgba(239, 68, 68, 0.35);

            color: #fca5a5;

            font-size: 14px;

            line-height: 1.5;

            text-align: left;
        }


        /*
        |--------------------------------------------------------------------------
        | Form
        |--------------------------------------------------------------------------
        */

        .form-group {

            margin-bottom: 20px;
        }


        label {

            display: block;

            color: #d4d4d8;

            font-size: 13px;

            font-weight: 600;

            margin-bottom: 8px;
        }


        .required {

            color: #ef4444;
        }


        input {

            width: 100%;

            padding: 14px;

            border-radius: 12px;

            border:
                1px solid rgba(255, 255, 255, 0.09);

            background:
                rgba(9, 9, 11, 0.75);

            color: #ffffff;

            outline: none;

            font-size: 14px;

            transition:
                border 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }


        input::placeholder {

            color: #71717a;
        }


        input:focus {

            border-color: #ef4444;

            background:
                rgba(24, 24, 27, 0.95);

            box-shadow:
                0 0 0 3px rgba(239, 68, 68, 0.10);
        }


        /*
        |--------------------------------------------------------------------------
        | Password Area
        |--------------------------------------------------------------------------
        */

        .password-wrapper {

            position: relative;
        }


        .password-wrapper input {

            padding-right: 75px;
        }


        .toggle-password {

            position: absolute;

            right: 12px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #a1a1aa;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            padding: 5px;
        }


        .toggle-password:hover {

            color: #f87171;
        }


        /*
        |--------------------------------------------------------------------------
        | Login Button
        |--------------------------------------------------------------------------
        */

        .login-btn {

            width: 100%;

            border: none;

            border-radius: 13px;

            padding: 15px;

            background:
                linear-gradient(
                    135deg,
                    #dc2626,
                    #991b1b
                );

            color: #ffffff;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 12px 30px rgba(220, 38, 38, 0.22);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .login-btn:hover {

            transform: translateY(-2px);

            box-shadow:
                0 16px 35px rgba(220, 38, 38, 0.32);
        }


        .login-btn:active {

            transform: translateY(0);
        }


        /*
        |--------------------------------------------------------------------------
        | Divider
        |--------------------------------------------------------------------------
        */

        .divider {

            display: flex;

            align-items: center;

            gap: 12px;

            margin: 25px 0;

            color: #52525b;

            font-size: 12px;
        }


        .divider::before,
        .divider::after {

            content: "";

            flex: 1;

            height: 1px;

            background:
                rgba(255, 255, 255, 0.07);
        }


        /*
        |--------------------------------------------------------------------------
        | Register Link
        |--------------------------------------------------------------------------
        */

        .register-link {

            text-align: center;

            color: #a1a1aa;

            font-size: 14px;
        }


        .register-link a {

            color: #f87171;

            font-weight: 700;

            text-decoration: none;
        }


        .register-link a:hover {

            text-decoration: underline;
        }


        /*
        |--------------------------------------------------------------------------
        | Info Cards
        |--------------------------------------------------------------------------
        */

        .info-row {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

            margin-top: 22px;
        }


        .info-card {

            padding: 12px;

            border-radius: 12px;

            background:
                rgba(255, 255, 255, 0.025);

            border:
                1px solid rgba(255, 255, 255, 0.05);

            text-align: center;
        }


        .info-icon {

            font-size: 18px;

            margin-bottom: 5px;
        }


        .info-text {

            color: #a1a1aa;

            font-size: 11px;

            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {

            text-align: center;

            color: #52525b;

            font-size: 12px;

            margin-top: 22px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 500px) {

            body {

                padding: 18px 12px;
            }


            .login-card {

                padding: 25px 20px;

                border-radius: 20px;
            }


            .logo {

                font-size: 26px;
            }


            .logo-icon {

                width: 42px;

                height: 42px;
            }


            .card-header h1 {

                font-size: 24px;
            }


            .info-row {

                grid-template-columns: 1fr;
            }
        }

    </style>

</head>


<body>


    <div class="glow glow-one"></div>

    <div class="glow glow-two"></div>


    <div class="container">


        <!-- Logo -->

        <div class="logo-section">

            <div class="logo">

                <div class="logo-icon">
                    🩸
                </div>

                Blood<span>Connect</span>

            </div>


            <div class="tagline">

                Connecting donors with people in need

            </div>

        </div>


        <!-- Login Card -->

        <div class="login-card">


            <div class="card-header">

                <h1>Welcome Back</h1>

                <p>
                    Login to your BloodConnect account
                </p>

            </div>


            <?php if (!empty($message)): ?>

                <div class="message">

                    <?php
                        echo htmlspecialchars($message);
                    ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                id="loginForm"
            >


                <!-- Email -->

                <div class="form-group">

                    <label for="email">

                        Email Address

                        <span class="required">*</span>

                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email address"
                        value="<?php
                            echo isset($_POST["email"])
                                ? htmlspecialchars($_POST["email"])
                                : "";
                        ?>"
                        autocomplete="email"
                        required
                    >

                </div>


                <!-- Password -->

                <div class="form-group">

                    <label for="password">

                        Password

                        <span class="required">*</span>

                    </label>


                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <!-- Login -->

                <button
                    type="submit"
                    class="login-btn"
                    id="loginButton"
                >

                    Login to BloodConnect

                </button>


            </form>


            <!-- Divider -->

            <div class="divider">
                OR
            </div>


            <!-- Register -->

            <div class="register-link">

                Don't have an account?

                <a href="register.php">
                    Create an account
                </a>

            </div>


            <!-- Information -->

            <div class="info-row">


                <div class="info-card">

                    <div class="info-icon">
                        🩸
                    </div>

                    <div class="info-text">
                        Find and help blood donors
                    </div>

                </div>


                <div class="info-card">

                    <div class="info-icon">
                        ❤️
                    </div>

                    <div class="info-text">
                        Help save lives
                    </div>

                </div>


            </div>


        </div>


        <div class="footer">

            © <?php echo date("Y"); ?> BloodConnect.
            Together, we can save lives.

        </div>


    </div>


    <script>

        /*
        |--------------------------------------------------------------------------
        | Show / Hide Password
        |--------------------------------------------------------------------------
        */

        const password =
            document.getElementById("password");

        const togglePassword =
            document.getElementById("togglePassword");


        togglePassword.addEventListener(
            "click",
            function () {

                if (password.type === "password") {

                    password.type = "text";

                    togglePassword.textContent = "Hide";

                } else {

                    password.type = "password";

                    togglePassword.textContent = "Show";
                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Prevent Double Submission
        |--------------------------------------------------------------------------
        */

        document
            .getElementById("loginForm")
            .addEventListener(
                "submit",
                function () {

                    const button =
                        document.getElementById("loginButton");

                    button.textContent = "Logging in...";

                    button.disabled = true;

                }
            );

    </script>

</body>

</html>