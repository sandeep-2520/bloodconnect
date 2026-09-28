<?php

require_once "config/database.php";

$email = "admin@bloodconnect.com";
$new_password = trim(readline("Enter new password: "));

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE users
     SET password = ?, role = 'admin', status = 'active'
     WHERE email = ?"
);

$stmt->bind_param(
    "ss",
    $hashed_password,
    $email
);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {
        echo "Admin password updated successfully.";
    } else {
        echo "Admin account was not found or no changes were required.";
    }

} else {

    echo "Error updating admin: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>
