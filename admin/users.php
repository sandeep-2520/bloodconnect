<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION["user_role"] != "admin") {
    header("Location: ../dashboard.php");
    exit;
}

$message = "";

/*
|--------------------------------------------------------------------------
| Block or activate user
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST["user_id"]) || !isset($_POST["action"])) {

        $message = "Invalid request.";

    } else {

        $user_id = intval($_POST["user_id"]);
        $action = $_POST["action"];

        if ($user_id == $_SESSION["user_id"]) {

            $message = "You cannot change your own account status.";

        } elseif ($action == "block") {

            $stmt = $conn->prepare("
                UPDATE users
                SET status = 'blocked'
                WHERE id = ?
            ");

            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $message = "User blocked successfully.";
            } else {
                $message = "Failed to block user.";
            }

            $stmt->close();

        } elseif ($action == "activate") {

            $stmt = $conn->prepare("
                UPDATE users
                SET status = 'active'
                WHERE id = ?
            ");

            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $message = "User activated successfully.";
            } else {
                $message = "Failed to activate user.";
            }

            $stmt->close();

        } else {

            $message = "Invalid action.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get all users
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        role,
        status,
        created_at
    FROM users
    ORDER BY created_at DESC
");

$stmt->execute();

$users = $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BloodConnect - Manage Users</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: Arial, Helvetica, sans-serif;

    min-height: 100vh;

    color: #ffffff;

    background:
        radial-gradient(circle at 10% 20%, rgba(180, 0, 0, 0.30), transparent 35%),
        radial-gradient(circle at 90% 80%, rgba(255, 40, 40, 0.18), transparent 35%),
        linear-gradient(135deg, #090909, #180404, #0b0b0b);

    overflow-x: hidden;
}


/* =========================================================
   BACKGROUND EFFECTS
========================================================= */

.background-glow {

    position: fixed;

    width: 400px;
    height: 400px;

    border-radius: 50%;

    filter: blur(100px);

    opacity: 0.18;

    pointer-events: none;

    z-index: -1;
}

.glow-one {

    background: #ff0000;

    top: -150px;
    left: -150px;
}

.glow-two {

    background: #8b0000;

    bottom: -150px;
    right: -150px;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    width: 100%;

    padding: 18px 6%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    position: sticky;

    top: 0;

    z-index: 100;

    background: rgba(10, 10, 10, 0.78);

    backdrop-filter: blur(18px);

    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 23px;

    font-weight: bold;

    letter-spacing: 0.5px;
}

.logo-icon {

    width: 40px;
    height: 40px;

    display: flex;

    justify-content: center;

    align-items: center;

    border-radius: 12px;

    background: linear-gradient(135deg, #ff3333, #9d0000);

    box-shadow: 0 8px 25px rgba(255, 0, 0, 0.25);

    font-size: 20px;
}

.admin-badge {

    padding: 7px 14px;

    border-radius: 30px;

    font-size: 12px;

    font-weight: bold;

    background: rgba(255, 255, 255, 0.08);

    border: 1px solid rgba(255, 255, 255, 0.10);

    color: #ffb0b0;
}

.logout-btn {

    text-decoration: none;

    color: #ffffff;

    padding: 9px 17px;

    border-radius: 10px;

    background: rgba(255, 255, 255, 0.07);

    border: 1px solid rgba(255, 255, 255, 0.10);

    font-size: 13px;

    transition: 0.3s;
}

.logout-btn:hover {

    background: #b30000;

    transform: translateY(-2px);
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    width: 92%;

    max-width: 1450px;

    margin: auto;

    padding: 45px 0 70px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    gap: 20px;

    margin-bottom: 30px;
}

.header-label {

    display: inline-block;

    padding: 7px 13px;

    margin-bottom: 12px;

    border-radius: 20px;

    background: rgba(255, 40, 40, 0.10);

    border: 1px solid rgba(255, 80, 80, 0.18);

    color: #ff9b9b;

    font-size: 12px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.page-header h1 {

    font-size: clamp(30px, 5vw, 48px);

    line-height: 1.1;

    margin-bottom: 10px;
}

.page-header h1 span {

    color: #ff4a4a;
}

.page-header p {

    color: #aaa;

    font-size: 15px;

    max-width: 650px;

    line-height: 1.6;
}


/* =========================================================
   USER COUNT
========================================================= */

.user-count {

    min-width: 160px;

    padding: 20px;

    border-radius: 18px;

    text-align: center;

    background: rgba(255, 255, 255, 0.055);

    border: 1px solid rgba(255, 255, 255, 0.09);

    backdrop-filter: blur(15px);
}

.user-count-number {

    font-size: 32px;

    font-weight: bold;

    color: #ff5555;
}

.user-count-label {

    color: #aaa;

    font-size: 12px;

    margin-top: 5px;
}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    margin-bottom: 25px;

    padding: 16px 20px;

    border-radius: 14px;

    background: rgba(255, 60, 60, 0.10);

    border: 1px solid rgba(255, 80, 80, 0.25);

    color: #ffb0b0;

    font-size: 14px;

    animation: messageSlide 0.4s ease;
}

@keyframes messageSlide {

    from {
        opacity: 0;
        transform: translateY(-8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card {

    background: rgba(255, 255, 255, 0.045);

    border: 1px solid rgba(255, 255, 255, 0.08);

    border-radius: 22px;

    overflow: hidden;

    backdrop-filter: blur(18px);

    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.30);
}


/* =========================================================
   TABLE HEADER
========================================================= */

.table-top {

    padding: 20px 24px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.table-title {

    font-size: 18px;

    font-weight: bold;
}

.table-subtitle {

    margin-top: 5px;

    color: #888;

    font-size: 12px;
}


/* =========================================================
   TABLE WRAPPER
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}

thead {

    background: rgba(255, 0, 0, 0.06);
}

th {

    padding: 16px 18px;

    text-align: left;

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: 0.6px;

    color: #999;

    white-space: nowrap;
}

td {

    padding: 17px 18px;

    border-top: 1px solid rgba(255, 255, 255, 0.055);

    color: #ddd;

    font-size: 13px;

    white-space: nowrap;
}

tbody tr {

    transition: 0.25s;
}

tbody tr:hover {

    background: rgba(255, 255, 255, 0.035);
}


/* =========================================================
   USER ID
========================================================= */

.user-id {

    width: 45px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    background: rgba(255, 255, 255, 0.07);

    color: #aaa;

    font-size: 12px;
}


/* =========================================================
   USER NAME
========================================================= */

.user-name {

    font-weight: bold;

    color: #ffffff;
}

.user-email {

    color: #888;

    font-size: 11px;

    margin-top: 4px;
}


/* =========================================================
   ROLE BADGES
========================================================= */

.role-badge {

    display: inline-flex;

    align-items: center;

    padding: 7px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;

    text-transform: capitalize;
}

.role-admin {

    background: rgba(170, 0, 255, 0.13);

    color: #dca0ff;

    border: 1px solid rgba(170, 0, 255, 0.18);
}

.role-donor {

    background: rgba(0, 180, 100, 0.12);

    color: #6ff0b0;

    border: 1px solid rgba(0, 180, 100, 0.18);
}

.role-requester {

    background: rgba(255, 170, 0, 0.12);

    color: #ffc96b;

    border: 1px solid rgba(255, 170, 0, 0.18);
}


/* =========================================================
   STATUS
========================================================= */

.status-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 7px 11px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.status-active {

    background: rgba(0, 190, 100, 0.10);

    color: #66e5a4;

    border: 1px solid rgba(0, 190, 100, 0.17);
}

.status-blocked {

    background: rgba(255, 60, 60, 0.10);

    color: #ff7777;

    border: 1px solid rgba(255, 60, 60, 0.17);
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.action-form {

    display: inline;
}

.action-btn {

    border: none;

    padding: 9px 15px;

    border-radius: 9px;

    cursor: pointer;

    font-size: 12px;

    font-weight: bold;

    transition: 0.25s;
}

.block-btn {

    background: rgba(255, 60, 60, 0.12);

    color: #ff7979;

    border: 1px solid rgba(255, 60, 60, 0.18);
}

.block-btn:hover {

    background: #b00000;

    color: #fff;

    transform: translateY(-2px);

    box-shadow: 0 7px 20px rgba(255, 0, 0, 0.20);
}

.activate-btn {

    background: rgba(0, 190, 100, 0.11);

    color: #62e5a2;

    border: 1px solid rgba(0, 190, 100, 0.18);
}

.activate-btn:hover {

    background: #008f4c;

    color: #fff;

    transform: translateY(-2px);

    box-shadow: 0 7px 20px rgba(0, 190, 100, 0.20);
}

.current-admin {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 8px 12px;

    border-radius: 9px;

    background: rgba(255, 255, 255, 0.06);

    color: #999;

    font-size: 11px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    padding: 70px 20px;

    text-align: center;

    color: #888;
}

.empty-icon {

    font-size: 45px;

    margin-bottom: 15px;
}

.empty-state h3 {

    color: #ddd;

    margin-bottom: 8px;
}


/* =========================================================
   FOOTER NAVIGATION
========================================================= */

.bottom-actions {

    display: flex;

    justify-content: center;

    gap: 14px;

    margin-top: 30px;

    flex-wrap: wrap;
}

.bottom-btn {

    text-decoration: none;

    padding: 12px 20px;

    border-radius: 11px;

    color: #ddd;

    background: rgba(255, 255, 255, 0.055);

    border: 1px solid rgba(255, 255, 255, 0.09);

    font-size: 13px;

    transition: 0.3s;
}

.bottom-btn:hover {

    background: rgba(255, 255, 255, 0.10);

    transform: translateY(-2px);
}

.dashboard-btn {

    background: linear-gradient(135deg, #b00000, #7d0000);

    border: none;

    color: white;
}

.dashboard-btn:hover {

    background: linear-gradient(135deg, #d00000, #950000);
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .navbar {

        padding: 14px 5%;
    }

    .logo {

        font-size: 18px;
    }

    .logo-icon {

        width: 35px;
        height: 35px;
    }

    .admin-badge {

        display: none;
    }

    .logout-btn {

        padding: 8px 12px;

        font-size: 12px;
    }

    .container {

        width: 94%;

        padding-top: 30px;
    }

    .page-header {

        flex-direction: column;

        align-items: flex-start;
    }

    .user-count {

        width: 100%;
    }

    .table-card {

        border-radius: 16px;
    }

    .table-top {

        padding: 17px;
    }

    th,
    td {

        padding: 13px 14px;
    }

}

</style>

</head>

<body>


<div class="background-glow glow-one"></div>
<div class="background-glow glow-two"></div>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

    <div class="logo">

        <div class="logo-icon">
            🩸
        </div>

        <span>
            BloodConnect
        </span>

    </div>


    <div style="display:flex; align-items:center; gap:12px;">

        <span class="admin-badge">
            🛡️ ADMIN
        </span>

        <a
            href="../auth/logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="container">


    <!-- PAGE HEADER -->

    <section class="page-header">

        <div>

            <span class="header-label">
                👥 User Management
            </span>

            <h1>
                Manage <span>Users</span>
            </h1>

            <p>
                View registered users, monitor account status,
                and control access to the BloodConnect platform.
            </p>

        </div>


        <div class="user-count">

            <div class="user-count-number">
                <?php echo $users->num_rows; ?>
            </div>

            <div class="user-count-label">
                Registered Users
            </div>

        </div>

    </section>


    <!-- MESSAGE -->

    <?php if (!empty($message)): ?>

        <div class="message">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- USER TABLE -->

    <section class="table-card">


        <div class="table-top">

            <div>

                <div class="table-title">
                    All Users
                </div>

                <div class="table-subtitle">
                    Manage account access and view user information
                </div>

            </div>

        </div>


        <?php if ($users->num_rows > 0): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>ID</th>

                        <th>User</th>

                        <th>Phone</th>

                        <th>Role</th>

                        <th>Status</th>

                        <th>Registered</th>

                        <th>Action</th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php while ($user = $users->fetch_assoc()): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <div class="user-id">

                                    #<?php echo htmlspecialchars($user["id"]); ?>

                                </div>

                            </td>


                            <!-- USER -->

                            <td>

                                <div class="user-name">

                                    <?php echo htmlspecialchars($user["name"]); ?>

                                </div>

                                <div class="user-email">

                                    <?php echo htmlspecialchars($user["email"]); ?>

                                </div>

                            </td>


                            <!-- PHONE -->

                            <td>

                                <?php echo htmlspecialchars($user["phone"]); ?>

                            </td>


                            <!-- ROLE -->

                            <td>

                                <?php

                                $role_class = "role-requester";

                                $role_icon = "👤";

                                if ($user["role"] == "admin") {

                                    $role_class = "role-admin";
                                    $role_icon = "🛡️";

                                } elseif ($user["role"] == "donor") {

                                    $role_class = "role-donor";
                                    $role_icon = "🩸";

                                }

                                ?>

                                <span class="role-badge <?php echo $role_class; ?>">

                                    <?php echo $role_icon; ?>

                                    <?php echo htmlspecialchars($user["role"]); ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php if ($user["status"] == "active"): ?>

                                    <span class="status-badge status-active">

                                        🟢

                                        Active

                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-blocked">

                                        🔴

                                        Blocked

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime($user["created_at"])
                                    )
                                );

                                ?>

                            </td>


                            <!-- ACTION -->

                            <td>


                                <?php if ($user["id"] != $_SESSION["user_id"]): ?>


                                    <?php if ($user["status"] == "active"): ?>


                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm('Are you sure you want to block this user?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?php echo $user["id"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="block"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn block-btn"
                                            >

                                                🚫 Block

                                            </button>

                                        </form>


                                    <?php else: ?>


                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm('Activate this user account?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?php echo $user["id"]; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn activate-btn"
                                            >

                                                ✓ Activate

                                            </button>

                                        </form>


                                    <?php endif; ?>


                                <?php else: ?>


                                    <span class="current-admin">

                                        🛡️ Current Admin

                                    </span>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty-state">

                <div class="empty-icon">
                    👥
                </div>

                <h3>
                    No Users Found
                </h3>

                <p>
                    There are currently no registered users.
                </p>

            </div>


        <?php endif; ?>


    </section>


    <!-- BOTTOM ACTIONS -->

    <div class="bottom-actions">

        <a
            href="dashboard.php"
            class="bottom-btn dashboard-btn"
        >
            ← Admin Dashboard
        </a>

        <a
            href="requests.php"
            class="bottom-btn"
        >
            🩸 Blood Requests
        </a>

        <a
            href="donations.php"
            class="bottom-btn"
        >
            🤝 Donations
        </a>

    </div>


</main>


</body>

</html>