<?php
session_start();
require_once "connect.php";

function returnToAccount(string $code): void
{
    header("Location: account.php?change=" . urlencode($code));
    exit;
}

if (!isset($_SESSION["user_ID"])) {
    header("Location: ../html/LoginPage.html");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: account.php");
    exit;
}

$userID = (int)$_SESSION["user_ID"];
$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["csrf_token"] ?? "";

if (
    $submittedToken === "" ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    returnToAccount("csrf");
}

$currentPassword = $_POST["authenticate_password"] ?? "";
$newEmail = strtolower(trim($_POST["new_email"] ?? ""));
$newPassword = $_POST["new_password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if ($currentPassword === "") {
    returnToAccount("current_password");
}

try {
    $userStmt = $conn->prepare(
        "SELECT users_Email, users_Password
         FROM users
         WHERE users_ID = ?
         LIMIT 1"
    );
    $userStmt->bind_param("i", $userID);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user || !password_verify($currentPassword, $user["users_Password"])) {
        returnToAccount("current_password");
    }

    $currentEmail = strtolower(trim($user["users_Email"]));
    $emailChanged = $newEmail !== "" && $newEmail !== $currentEmail;
    $passwordChanged = $newPassword !== "" || $confirmPassword !== "";

    if ($newEmail !== "" && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        returnToAccount("email_invalid");
    }

    if ($passwordChanged && ($newPassword === "" || $newPassword !== $confirmPassword)) {
        returnToAccount("password_mismatch");
    }

    if (!$emailChanged && !$passwordChanged) {
        returnToAccount("nothing_to_change");
    }

    if ($emailChanged) {
        $emailCheck = $conn->prepare(
            "SELECT users_ID
             FROM users
             WHERE users_Email = ? AND users_ID <> ?
             LIMIT 1"
        );
        $emailCheck->bind_param("si", $newEmail, $userID);
        $emailCheck->execute();
        $emailExists = $emailCheck->get_result()->num_rows > 0;
        $emailCheck->close();

        if ($emailExists) {
            returnToAccount("email_taken");
        }
    }

    if ($emailChanged && $passwordChanged) {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare(
            "UPDATE users
             SET users_Email = ?, users_Password = ?
             WHERE users_ID = ?"
        );
        $updateStmt->bind_param("ssi", $newEmail, $newPasswordHash, $userID);
    } elseif ($emailChanged) {
        $updateStmt = $conn->prepare(
            "UPDATE users
             SET users_Email = ?
             WHERE users_ID = ?"
        );
        $updateStmt->bind_param("si", $newEmail, $userID);
    } else {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare(
            "UPDATE users
             SET users_Password = ?
             WHERE users_ID = ?"
        );
        $updateStmt->bind_param("si", $newPasswordHash, $userID);
    }

    $updateStmt->execute();
    $updateStmt->close();

    if ($emailChanged) {
        $_SESSION["email"] = $newEmail;
    }

    returnToAccount("success");
} catch (Throwable $error) {
    returnToAccount("failed");
}