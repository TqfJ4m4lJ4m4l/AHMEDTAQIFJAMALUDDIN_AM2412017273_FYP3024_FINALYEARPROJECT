<?php
//Removes session the moment the button is clicked using destroy
session_start();
session_destroy();

header("Location: ../html/LoginPage.html");
exit();
?>