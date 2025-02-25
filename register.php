<?php
session_start();
include "includes/db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Securely hash password


    $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkUser->bind_param("ss", $username, $email);
    $checkUser->execute();
    $checkUser->store_result();

    if ($checkUser->num_rows > 0) {
        echo "<p style='color: red;'>Username or Email already exists!</p>";
    } else {

        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>Registration successful! <a href='login.php'>Login here</a></p>";
        } else {
            echo "<p style='color: red;'>Error: " . $stmt->error . "</p>";
        }
    }

    $checkUser->close();
}
?>


<form method="POST">
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">R
