
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config/database.php";

/*
|--------------------------------------------------------------------------
| Existing Admin
|--------------------------------------------------------------------------
*/

$email = "admin@gmail.com";
$new_password = trim(readline("Enter new admin password: "));;

$hashed_password = password_hash(
    $new_password,
    PASSWORD_DEFAULT
);

/*
|--------------------------------------------------------------------------
| Update existing admin
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "UPDATE users
     SET password = ?,
         role = 'admin',
         status = 'active'
     WHERE email = ?"
);

$stmt->bind_param(
    "ss",
    $hashed_password,
    $email
);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        echo "<h2>Admin password reset successfully.</h2>";

        echo "<p><strong>Email:</strong> admin@gmail.com</p>";
        echo "<p><strong>Role:</strong> admin</p>";
        echo "<p><strong>Status:</strong> active</p>";

    } else {

        echo "<h2>No changes were made.</h2>";
        echo "<p>Please check that the admin account exists.</p>";
    }

} else {

    echo "<h2>Failed to reset password.</h2>";

    echo "<p>"
        . htmlspecialchars($stmt->error)
        . "</p>";
}

$stmt->close();
$conn->close();

?>

