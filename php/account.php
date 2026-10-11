<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION["user_ID"])) {
    header("Location: ../html/LoginPage.html");
    exit;
}

$usID = (int)$_SESSION["user_ID"];
/*usID serves as a way to checks which user are you specifically. the int specifies a certain variable within session, converts it back to an integer to be used.
it checks based on user_ID*/
$userStmt = $conn->prepare(
    // https://www.w3schools.com/php/php_mysql_prepared_statements.asp // 
    "SELECT users.users_ID,
            users.users_Email, 
            roles.role_ID, 
            roles.role_Type
     FROM users
     JOIN roles ON roles.users_ID = users.users_ID
     WHERE users.users_ID = ?
     LIMIT 1"
);
/* "$stmt" obviously (I think) stands for "statement". 
As a variable name it's arbitrary, you can name that variable anything you want. 
$stmt is just rather idiomatic.*https://stackoverflow.com/questions/27087081/what-is-stmt-in-php

For this cane, userStmt represents "user statement", 
which means all statements and information it is able to rip out from
users and role*/
$userStmt->bind_param("i", $usID);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
/* bind_param means it sends back the parameters taken within userStmt into the chose parameters, for this case, it
is sent back to usID, but since we also need an integer, we used i as a stand-in*/
if (!$user) {
    http_response_code(404);
    exit("Account information could not be found.");
}

$email = $user["users_Email"];
$role_ID = (int)$user["role_ID"];
$role_Type = ucfirst(strtolower(trim($user["role_Type"])));

/* Basically, emails taken back from user where it checks for the statements if there is users_Email
role_ID works the same way as usID 
role_Type does this: capitalizes the first letter, trims any test from bEInG tHIs to LIKE THIS and then 
removes any extra whitespace or hidden spaces from the beginning and end*/
$roles_Tables = [
    "Patient" => [
        "table" => "patients",
        "name" => "patient_Name",
        "birthdate" => "patient_BirthDate",
        "gender" => "patient_Gender"
    ],
    "Doctor" => [
        "table" => "doctors",
        "name" => "doctor_Name",
        "birthdate" => "doctor_BirthDate",
        "gender" => "doctor_Gender"
    ],
    "Admin" => [
        "table" => "admins",
        "name" => "admin_Name",
        "birthdate" => "admin_BirthDate",
        "gender" => "admin_Gender"
    ]
];
// This just makes tables ready to be displayed depending on their role//
if (!isset($roles_Tables[$role_Type])) {
    http_response_code(403);
    exit("This account has an invalid role.");
}
//error checking ^^^//
$roleDetails = $roles_Tables[$role_Type];
$table = $roleDetails["table"];
$nameColumn = $roleDetails["name"];
$birthdateColumn = $roleDetails["birthdate"];
$genderColumn = $roleDetails["gender"];
/* role details: Checks all the details that can be taken within the roles Tables, this specifically checks anything connected
within role_Type*/
$roles_SQL = "
    SELECT `$nameColumn` AS account_name,
           `$birthdateColumn` AS account_birthdate,
           `$genderColumn` AS account_gender
    FROM `$table`
    WHERE users_ID = ? AND role_ID = ?
    LIMIT 1
";
/*Within roles_SQL, Pick parts of the name column, birth date column and gender column as the displayed name,
birthdate and gender for the user. At the moment they are just empty variables and are just... boxes*/
$roleStmt = $conn->prepare($roles_SQL);
$roleStmt->bind_param("ii", $usID, $role_ID);
$roleStmt->execute();
$role_Data = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();
/* stores active connection to the database, prepares from within SQL */
if (!$role_Data) {
    http_response_code(404);
    exit("Role account information could not be found.");
}

$name = $role_Data["account_name"];
$birthdate = $role_Data["account_birthdate"];
$gender = $role_Data["account_gender"];
$accountID = "ACC" . str_pad((string)$usID, 3, "0", STR_PAD_LEFT);

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
/*Basically Encryption, its a secure, uniquely identified, and random cryptographic string stored in the user's session and embedded in a hidden
form field to verify that state-changing requests originate from the legitimate application, or for in this case, the website.
https://www.phptutorial.net/php-tutorial/php-csrf/, https://stackoverflow.com/questions/6287903/how-to-properly-add-cross-site-request-forgery-csrf-token-using-php*/
$changeMessages = [
    "success" => ["Your account details were updated.", "success"],
    "current_password" => ["The password you entered could not be verified.", "error"],
    "email_invalid" => ["Enter a valid email address.", "error"],
    "email_taken" => ["An account already uses that email address.", "error"],
    "password_mismatch" => ["The new passwords do not match.", "error"],
    "nothing_to_change" => ["Enter a new email or a new password to make a change.", "error"],
    "csrf" => ["The page expired. Refresh it and try again.", "error"],
    "failed" => ["Your account details could not be updated. Please try again.", "error"]
];

$changeCode = $_GET["change"] ?? "";
$changeMessage = $changeMessages[$changeCode] ?? null;

$roleKey = strtolower($role_Type);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your account</title>

    <link rel="stylesheet" href="../styles/styleAccount.css">
    <link rel="stylesheet" href="../styles/styleNav.css?v=2">
    <link rel="stylesheet" href="../styles/styleTheme.css">

    <style>
        main.account-main {
            width: min(1200px, calc(100% - 40px));
            max-width: none;
            margin: 0 auto;
            padding: 34px 0 56px;
        }

        .welcome-card {
            margin: 0 0 26px;
            padding: clamp(24px, 4vw, 42px);
            border: 1px solid var(--app-border);
            border-radius: 20px;
            background: var(--app-panel);
            color: var(--app-panel-text);
            box-shadow: 0 8px 22px rgba(32, 33, 45, 0.18);
        }

        .welcome-eyebrow {
            margin: 0 0 8px;
            color: var(--app-accent);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .welcome-card h2 {
            margin: 0;
            color: var(--app-panel-text);
            font-size: clamp(30px, 5vw, 48px);
            line-height: 1.15;
        }

        .welcome-card h2 span {
            display: block;
            color: var(--app-accent);
            overflow-wrap: anywhere;
        }

        .welcome-description {
            margin: 14px 0 0;
            font-size: 17px;
        }

        .account-card {
            margin: 0 0 24px;
            padding: clamp(22px, 4vw, 36px);
            border: 1px solid var(--app-border);
            border-radius: 18px;
            background: var(--app-panel);
            color: var(--app-panel-text);
            box-shadow: 0 8px 22px rgba(32, 33, 45, 0.14);
        }

        .account-card h3 {
            margin: 0 0 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--app-accent);
            color: var(--app-panel-text);
            font-size: 24px;
        }

        .account-id-line {
            margin: -4px 0 20px;
            color: var(--app-panel-text);
            font-weight: 700;
        }

        .account-id-line strong {
            margin-left: 8px;
            color: var(--app-accent);
            font-size: 20px;
        }

        .account-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 24px;
        }

        .account-field {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 6px;
        }

        .account-field label {
            color: var(--app-panel-text);
            font-weight: 700;
        }

        .account-field input {
            width: 100%;
            min-height: 48px;
            padding: 11px 13px;
            border: 1px solid var(--app-border);
            border-radius: 9px;
            background: var(--app-control-bg);
            color: var(--app-control-text);
            font: inherit;
        }

        .account-field input[readonly] {
            background: var(--app-surface);
        }

        .account-field small,
        .account-help {
            color: var(--app-panel-text);
            opacity: 0.86;
        }

        .account-help {
            margin: -8px 0 20px;
        }

        .account-submit {
            min-height: 48px;
            margin-top: 22px;
            padding: 11px 20px;
            border: 1px solid var(--app-accent);
            border-radius: 9px;
            background: var(--app-accent);
            color: #222;
            font: 700 16px Arial, sans-serif;
            cursor: pointer;
        }

        .account-submit:hover {
            filter: brightness(0.96);
        }

        .account-notice {
            margin: 0 0 22px;
            padding: 13px 16px;
            border: 1px solid var(--app-border);
            border-radius: 10px;
            background: var(--app-surface);
            color: var(--app-text);
        }

        .account-notice--success {
            border-color: #6d9b78;
            background: #edf7ef;
            color: #23432b;
        }

        .account-notice--error {
            border-color: #c54650;
            background: #fff0f1;
            color: #6e1720;
        }

        @media (max-width: 700px) {
            main.account-main {
                width: min(100% - 24px, 620px);
                padding-top: 24px;
            }

            .account-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
<!-- it had to be override, it keeps messing up within styleAccount.css -->
</head>

<body class="account-page">
    <header>
        <h1>
            <a href="../html/HomePage.html">
                <img src="../img/neurodivergence.png" alt="Neurodivergence" width="250">
            </a>
            <nav id="navBar"></nav>
        </h1>
    </header>

    <main class="account-main">
        <section class="welcome-card" aria-labelledby="welcome-title">
            <p class="welcome-eyebrow">YOUR ACCOUNT</p>
            <h2 id="welcome-title">
                Welcome back,
                <span><?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?></span>
            </h2>
            <p class="welcome-description">
                Review your account details, update your sign-in information, or change your display settings.
            </p>
        </section>

        <?php if ($changeMessage): ?>
            <div
                class="account-notice account-notice--<?= htmlspecialchars($changeMessage[1], ENT_QUOTES, "UTF-8") ?>"
                role="<?= $changeMessage[1] === "error" ? "alert" : "status" ?>"
            >
                <?= htmlspecialchars($changeMessage[0], ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <section class="account-card" aria-labelledby="account-details-heading">
            <h3 id="account-details-heading">Account information</h3>

            <p class="account-id-line">
                Account ID:
                <strong><?= htmlspecialchars($accountID, ENT_QUOTES, "UTF-8") ?></strong>
            </p>

            <div class="account-fields">
                <div class="account-field">
                    <label for="account-name">Name</label>
                    <input
                        id="account-name"
                        type="text"
                        value="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                        readonly
                    >
                </div>

                <div class="account-field">
                    <label for="account-email">Current email</label>
                    <input
                        id="account-email"
                        type="email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                        readonly
                    >
                </div>

                <div class="account-field">
                    <label for="account-birthdate">Birth date</label>
                    <input
                        id="account-birthdate"
                        type="date"
                        value="<?= htmlspecialchars($birthdate, ENT_QUOTES, "UTF-8") ?>"
                        readonly
                    >
                </div>

                <div class="account-field">
                    <label for="account-gender">Gender</label>
                    <input
                        id="account-gender"
                        type="text"
                        value="<?= htmlspecialchars($gender, ENT_QUOTES, "UTF-8") ?>"
                        readonly
                    >
                </div>

                <div class="account-field">
                    <label for="account-role">Role</label>
                    <input
                        id="account-role"
                        type="text"
                        value="<?= htmlspecialchars(strtoupper($role_Type), ENT_QUOTES, "UTF-8") ?>"
                        readonly
                    >
                </div>
            </div>
        </section>

        <section class="account-card" aria-labelledby="security-heading">
            <h3 id="security-heading">Email and password</h3>
            <p class="account-help">
                Enter your current password to authenticate any change. Leave a new field blank if you do not want to change it.
            </p>

            <form action="changedetails.php" method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
                >

                <div class="account-fields">
                    <div class="account-field">
                        <label for="new-email">New email</label>
                        <input
                            id="new-email"
                            type="email"
                            name="new_email"
                            autocomplete="email"
                            placeholder="Enter a new email address"
                        >
                    </div>

                    <div class="account-field">
                        <label for="authenticate-password">Authenticate Password</label>
                        <input
                            id="authenticate-password"
                            type="password"
                            name="authenticate_password"
                            autocomplete="current-password"
                            required
                        >
                        <small>Enter your current password to verify your identity.</small>
                    </div>

                    <div class="account-field">
                        <label for="new-password">New password</label>
                        <input
                            id="new-password"
                            type="password"
                            name="new_password"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep your current password"
                        >
                    </div>

                    <div class="account-field">
                        <label for="confirm-password">Confirm new password</label>
                        <input
                            id="confirm-password"
                            type="password"
                            name="confirm_password"
                            autocomplete="new-password"
                            placeholder="Re-enter your new password"
                        >
                    </div>
                </div>

                <button class="account-submit" type="submit">Save account changes</button>
            </form>
        </section>
    </main>

    <footer>
        <div class="footer-contact">
            <p>CONTACT US AT:</p>
            <p>EMAIL: prohukm@hctm.ukm.edu.my</p>
            <p>PHONE NUMBER: 03-9145 5555 (OFFICE HOURS: 8:30AM - 6:00PM)</p>
        </div>
        <div class="footer-version"><p>VERSION: 1.0.2</p></div>
        <div class="footer-logo">
            <img src="../img/hctm.png" alt="HCTM Logo">
        </div>
    </footer>

    <script>
        const userRole = <?= json_encode($role_Type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const navBar = document.getElementById("navBar");
        const roleKey = userRole.toLowerCase();

        const navItems = {
            patient: [
                ["REPORT", "reports.php", "report_button", "Report"],
                ["REQUESTS", "requests.php", "request_button", "Request"],
                ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
                ["BOOK AN APPOINTMENT", "booking.php", "appointment_button", "appointments"],
                ["YOUR NOTES", "notes.php", "appointment_button", "appointments"]
            ],
            doctor: [
                ["REPORT", "reports.php", "report_button", "Report"],
                ["REQUESTS", "requests.php", "request_button", "Request"],
                ["SCHEDULE", "schedule.php", "neuro_button", "Neurodivergent"],
                ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
                ["NOTES", "notes.php", "appointment_button", "appointments"]
            ],
            admin: [
                ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
                ["SCHEDULE", "schedule.php", "neuro_button", "Neurodivergent"],
                ["MANAGEMENT", "management.php", "appointment_button", "appointments"]
            ]
        };

        if (navBar && navItems[roleKey]) {
            navBar.innerHTML = navItems[roleKey]
                .map(([label, href, containerClass, buttonClass]) => {
                    let buttonStyle = "";

                    if (buttonClass === "Report") {
                        buttonStyle = "background-color: red;";
                    } else if (buttonClass === "Request") {
                        buttonStyle = "background-color: rgb(255, 192, 65);";
                    }

                    return `
                        <div class="${containerClass}">
                            <button class="${buttonClass}" type="button" style="${buttonStyle}">
                                <a href="${href}">${label}</a>
                            </button>
                        </div>
                    `;
                })
                .join("") + `
                    <div class="dropdown">
                        <button class="dropdown-btn" type="button">
                            <a href="account.php">
                                <img src="../img/login.png" alt="Account" width="45" height="45">
                            </a>
                        </button>
                        <div class="dropdown-content">
                            <a href="updates.php">Inbox</a>
                            <a href="settings.php">Settings</a>
                            ${roleKey !== "admin"
                                ? '<a href="prescription.php">Your Prescriptions</a>'
                                : ""}
                            <a href="logout.php">Log Out</a>
                        </div>
                    </div>
                `;
        }
    </script>

    <script src="../javascript/theme.js" defer></script>
</body>
</html>