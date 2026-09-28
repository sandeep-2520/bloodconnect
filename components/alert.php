<?php
/*
|--------------------------------------------------------------------------
| BloodConnect Reusable Alert System
|--------------------------------------------------------------------------
|
| Usage:
|
| $_SESSION["success"] = "Your donation was completed successfully.";
|
| $_SESSION["error"] = "Something went wrong.";
|
| $_SESSION["warning"] = "Please check the information.";
|
| $_SESSION["info"] = "You have a new notification.";
|
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Collect Session Messages
|--------------------------------------------------------------------------
*/

$alert_messages = [];

$alert_types = [
    "success",
    "error",
    "warning",
    "info"
];

foreach ($alert_types as $type) {

    if (isset($_SESSION[$type]) && $_SESSION[$type] !== "") {

        $alert_messages[] = [
            "type" => $type,
            "message" => $_SESSION[$type]
        ];

        unset($_SESSION[$type]);
    }
}

?>


<style>

/* =========================================================
   BLOODCONNECT ALERT CONTAINER
========================================================= */

.bc-alert-container {

    position: fixed;

    top: 85px;
    right: 25px;

    width: min(390px, calc(100% - 30px));

    display: flex;

    flex-direction: column;

    gap: 12px;

    z-index: 99999;

    pointer-events: none;
}


/* =========================================================
   ALERT
========================================================= */

.bc-alert {

    position: relative;

    display: flex;

    align-items: flex-start;

    gap: 13px;

    padding: 16px 17px;

    border-radius: 18px;

    background:
        rgba(255,255,255,0.92);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    border:
        1px solid rgba(255,255,255,0.8);

    box-shadow:
        0 15px 35px rgba(60,10,20,0.18);

    overflow: hidden;

    pointer-events: auto;

    animation:
        bcAlertIn 0.45s ease forwards;
}


/* =========================================================
   LEFT ACCENT
========================================================= */

.bc-alert::before {

    content: "";

    position: absolute;

    left: 0;
    top: 0;

    width: 4px;
    height: 100%;
}


/* =========================================================
   SUCCESS
========================================================= */

.bc-alert.success::before {

    background:
        linear-gradient(
            #16834d,
            #32ad72
        );
}

.bc-alert.success .bc-alert-icon {

    background:
        rgba(22,131,77,0.11);

    color: #16834d;
}


/* =========================================================
   ERROR
========================================================= */

.bc-alert.error::before {

    background:
        linear-gradient(
            #b51232,
            #e53a58
        );
}

.bc-alert.error .bc-alert-icon {

    background:
        rgba(181,18,50,0.11);

    color: #b51232;
}


/* =========================================================
   WARNING
========================================================= */

.bc-alert.warning::before {

    background:
        linear-gradient(
            #d17a0c,
            #e9a42b
        );
}

.bc-alert.warning .bc-alert-icon {

    background:
        rgba(209,122,12,0.11);

    color: #a96108;
}


/* =========================================================
   INFO
========================================================= */

.bc-alert.info::before {

    background:
        linear-gradient(
            #246ca8,
            #4294d0
        );
}

.bc-alert.info .bc-alert-icon {

    background:
        rgba(36,108,168,0.11);

    color: #246ca8;
}


/* =========================================================
   ICON
========================================================= */

.bc-alert-icon {

    flex-shrink: 0;

    width: 40px;
    height: 40px;

    display: flex;

    justify-content: center;
    align-items: center;

    border-radius: 13px;

    font-size: 19px;

    font-weight: bold;
}


/* =========================================================
   CONTENT
========================================================= */

.bc-alert-content {

    flex: 1;

    min-width: 0;
}

.bc-alert-title {

    color: #4d2831;

    font-size: 14px;

    font-weight: 800;

    margin-bottom: 4px;
}

.bc-alert-message {

    color: #76525a;

    font-size: 13px;

    line-height: 1.5;

    word-wrap: break-word;
}


/* =========================================================
   CLOSE BUTTON
========================================================= */

.bc-alert-close {

    flex-shrink: 0;

    width: 28px;
    height: 28px;

    display: flex;

    justify-content: center;
    align-items: center;

    border: none;

    background:
        rgba(100,20,30,0.06);

    color: #76525a;

    border-radius: 50%;

    cursor: pointer;

    font-size: 17px;

    line-height: 1;

    transition: 0.2s;
}

.bc-alert-close:hover {

    background:
        rgba(100,20,30,0.12);

    color: #65101e;

    transform: rotate(90deg);
}


/* =========================================================
   PROGRESS BAR
========================================================= */

.bc-alert-progress {

    position: absolute;

    left: 0;
    bottom: 0;

    height: 3px;

    width: 100%;

    transform-origin: left;

    animation:
        bcAlertProgress 5s linear forwards;
}

.bc-alert.success .bc-alert-progress {
    background: #16834d;
}

.bc-alert.error .bc-alert-progress {
    background: #b51232;
}

.bc-alert.warning .bc-alert-progress {
    background: #d17a0c;
}

.bc-alert.info .bc-alert-progress {
    background: #246ca8;
}


/* =========================================================
   ANIMATIONS
========================================================= */

@keyframes bcAlertIn {

    from {

        opacity: 0;

        transform:
            translateX(35px)
            scale(0.96);
    }

    to {

        opacity: 1;

        transform:
            translateX(0)
            scale(1);
    }
}


@keyframes bcAlertOut {

    from {

        opacity: 1;

        transform:
            translateX(0)
            scale(1);
    }

    to {

        opacity: 0;

        transform:
            translateX(35px)
            scale(0.96);
    }
}


@keyframes bcAlertProgress {

    from {
        transform: scaleX(1);
    }

    to {
        transform: scaleX(0);
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 600px) {

    .bc-alert-container {

        top: 75px;

        right: 15px;

        width:
            calc(100% - 30px);
    }

    .bc-alert {

        padding: 14px;

        border-radius: 16px;
    }

    .bc-alert-icon {

        width: 36px;
        height: 36px;

        font-size: 17px;
    }

    .bc-alert-title {

        font-size: 13px;
    }

    .bc-alert-message {

        font-size: 12px;
    }
}

</style>


<?php if (!empty($alert_messages)): ?>

<div class="bc-alert-container" id="bcAlertContainer">

    <?php foreach ($alert_messages as $alert): ?>

        <?php

        $type = $alert["type"];

        $message = htmlspecialchars(
            $alert["message"],
            ENT_QUOTES,
            "UTF-8"
        );


        /*
        |--------------------------------------------------------------------------
        | Alert Titles
        |--------------------------------------------------------------------------
        */

        switch ($type) {

            case "success":

                $icon = "✓";
                $title = "Success";

                break;


            case "error":

                $icon = "!";
                $title = "Something went wrong";

                break;


            case "warning":

                $icon = "⚠";
                $title = "Please check";

                break;


            default:

                $icon = "i";
                $title = "Information";

                break;
        }

        ?>

        <div
            class="bc-alert <?php echo $type; ?>"
            data-alert
        >

            <div class="bc-alert-icon">

                <?php echo $icon; ?>

            </div>


            <div class="bc-alert-content">

                <div class="bc-alert-title">

                    <?php echo $title; ?>

                </div>

                <div class="bc-alert-message">

                    <?php echo $message; ?>

                </div>

            </div>


            <button
                type="button"
                class="bc-alert-close"
                onclick="closeBloodConnectAlert(this)"
                aria-label="Close notification"
            >
                ×
            </button>


            <div class="bc-alert-progress"></div>

        </div>

    <?php endforeach; ?>

</div>

<?php endif; ?>


<script>

/*
|--------------------------------------------------------------------------
| Close BloodConnect Alert
|--------------------------------------------------------------------------
*/

function closeBloodConnectAlert(button) {

    const alert =
        button.closest(".bc-alert");

    if (!alert) {
        return;
    }

    alert.style.animation =
        "bcAlertOut 0.35s ease forwards";

    setTimeout(function () {

        alert.remove();

    }, 350);
}


/*
|--------------------------------------------------------------------------
| Auto Close Alerts
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const alerts =
            document.querySelectorAll(
                "[data-alert]"
            );

        alerts.forEach(function (alert) {

            setTimeout(function () {

                if (!alert.isConnected) {
                    return;
                }

                alert.style.animation =
                    "bcAlertOut 0.35s ease forwards";

                setTimeout(function () {

                    alert.remove();

                }, 350);

            }, 5000);

        });

    }
);

</script>