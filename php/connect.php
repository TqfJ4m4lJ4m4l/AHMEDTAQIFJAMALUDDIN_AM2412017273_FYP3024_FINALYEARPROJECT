<?php
// Ref: https://www.youtube.com/watch?v=LiomRvK7AM8&t=2138s, https://www.w3schools.com/php/php_mysql_connect.asp. the rest of the video was confusing and other videos were too confusing
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "neurodivergence_db";
$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);
// Its just the connector. Applies it to all php files so it can connect to not just the database
// But also other pages with functions!
if ($conn->connect_error) {

    die("Connection Failed: " . $conn->connect_error);

}
?>