<?php
require_once "session_check.php";
if ($_SESSION["role_Type"] !== "Patient") {

    header("Location: ../html/LoginPage.html");
    exit();

}
?>