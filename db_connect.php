<?php
/*
 * ==========================================================
 * DATABASE CONNECTION
 * ==========================================================
 */

$servername = "localhost";
$username   = "root";     // XAMPP Default
$password   = "";         // XAMPP Default
$dbname     = "cosmos";   // Ensure this matches your DB name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode([
        "status" => "error", 
        "message" => "Connection failed: " . $conn->connect_error
    ]));
}
?>