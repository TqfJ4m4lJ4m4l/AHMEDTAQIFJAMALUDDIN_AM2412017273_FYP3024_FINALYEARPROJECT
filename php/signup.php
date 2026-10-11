<?php
require_once "connect.php";
require_once "autoassign.php";
/*It is connected to Auto Assign and PHP*/
function signupError(string $code): never
{
    header("Location: ../html/SignUpPage.html?error=" . urlencode($code));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../html/SignUpPage.html");
    exit;
}

$name = mb_strtoupper(trim($_POST["name"] ?? ""));
$email = strtolower(trim($_POST["email"] ?? ""));
$birthdate = trim($_POST["birthdate"] ?? "");
$gender = trim($_POST["gender"] ?? "");
$password = $_POST["password"] ?? "";
$password2 = $_POST["password2"] ?? "";

if (
    $name === "" ||
    $email === "" ||
    $birthdate === "" ||
    $gender === "" ||
    $password === "" ||
    $password2 === ""
) {
    signupError("missing");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    signupError("email_invalid");
}

if ($password !== $password2) {
    signupError("password_mismatch");
}

if (!in_array($gender, ["Male", "Female"], true)) {
    signupError("gender_invalid");
}

$date = DateTimeImmutable::createFromFormat("!Y-m-d", $birthdate);
$dateErrors = DateTimeImmutable::getLastErrors();

if (
    !$date ||
    $date->format("Y-m-d") !== $birthdate ||
    (
        is_array($dateErrors) &&
        ($dateErrors["warning_count"] > 0 || $dateErrors["error_count"] > 0)
    )
) {
    signupError("birthdate_invalid");
}

if ($date > new DateTimeImmutable("today")) {
    signupError("birthdate_future");
}

/* Check that the email is not already attached to an account. */
try {
    $emailCheck = $conn->prepare(
        "SELECT users_ID FROM users WHERE users_Email = ? LIMIT 1"
    );
    $emailCheck->bind_param("s", $email);
    $emailCheck->execute();
    $emailExists = $emailCheck->get_result()->num_rows > 0;
    $emailCheck->close();

    if ($emailExists) {
        signupError("email_taken");
    }

    /*
     * The name is saved in a different table depending on the user's role.
     * Check all three tables so the signup name is unique across roles.
     */
    $nameChecks = [
        "SELECT 1 FROM patients WHERE LOWER(patient_Name) = LOWER(?) LIMIT 1",
        "SELECT 1 FROM doctors WHERE LOWER(doctor_Name) = LOWER(?) LIMIT 1",
        "SELECT 1 FROM admins WHERE LOWER(admin_Name) = LOWER(?) LIMIT 1"
    ];

    foreach ($nameChecks as $sql) {
        $nameCheck = $conn->prepare($sql);
        $nameCheck->bind_param("s", $name);
        $nameCheck->execute();
        $nameExists = $nameCheck->get_result()->num_rows > 0;
        $nameCheck->close();

        if ($nameExists) {
            signupError("name_taken");
        }
    }

    $conn->begin_transaction();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertUser = $conn->prepare(
        "INSERT INTO users (users_Email, users_Password)
         VALUES (?, ?)"
    );
    $insertUser->bind_param("ss", $email, $passwordHash);
    $insertUser->execute();

    $userID = $conn->insert_id;
    $insertUser->close();

    /*
     * This creates the user's role and role-specific record.
     * It uses the existing role assignment function in autoassign.php.
     */
    autoAssignRole($conn, $userID, $name, $birthdate, $gender, $email);

    $conn->commit();

    header("Location: ../html/LoginPage.html?registered=1");
    exit;
} catch (Throwable $error) {
    if ($conn->errno || $conn->connect_errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Keep the user-facing error generic.
        }
    }

    signupError("signup_failed");
}