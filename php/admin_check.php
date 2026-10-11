<?php
require_once "session_check.php";
if ($_SESSION["role_Type"] !== "ADMIN") {
    header("Location: ../html/LoginPage.html");
    exit();
}
?>