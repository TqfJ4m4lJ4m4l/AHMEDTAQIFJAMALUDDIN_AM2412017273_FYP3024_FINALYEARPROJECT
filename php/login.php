<?php
session_start();
require_once "connect.php";

function loginError(string $code): never
{
    header("Location: ../html/LoginPage.html?error=" . urlencode($code));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../html/LoginPage.html");
    exit;
}

$email = strtolower(trim($_POST["email"] ?? ""));
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    loginError("missing");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    loginError("invalid");
}

try {
    // Find the account using its email address.
    $userStmt = $conn->prepare(
        "SELECT users_ID, users_Password
         FROM users
         WHERE users_Email = ?
         LIMIT 1"
    );

    $userStmt->bind_param("s", $email);
    $userStmt->execute();

    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    // Use one message for either an unknown email or incorrect password.
    if (!$user || !password_verify($password, $user["users_Password"])) {
        loginError("invalid");
    }

    $userID = (int)$user["users_ID"];

    // Get the user's role record and role ID.
    $roleStmt = $conn->prepare(
        "SELECT role_ID, role_Type
         FROM roles
         WHERE users_ID = ?
         LIMIT 1"
    );

    $roleStmt->bind_param("i", $userID);
    $roleStmt->execute();

    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow) {
        loginError("account_setup");
    }

    // Keep the role spelling expected by the role checks used in the app.
    $roleType = ucfirst(strtolower(trim($roleRow["role_Type"])));

    if ($roleType === "Admin") {
        // admin_check.php currently expects this exact value.
        $roleType = "ADMIN";
    }

    // Replace the session ID after successful login.
    session_regenerate_id(true);

    $_SESSION["user_ID"] = $userID;
    $_SESSION["role_ID"] = (int)$roleRow["role_ID"];
    $_SESSION["role_Type"] = $roleType;

    header("Location: account.php");
    exit;
} catch (Throwable $error) {
    loginError("service");
}