<?php
session_start();
if (!isset($_SESSION["user_ID"])) {

    header("Location: ../html/LoginPage.html");
    exit();

}
?>