<?php
require_once "session_check.php";
require_once "connect.php";

// Get the signed-in user's details.
$userID = (int)($_SESSION["user_ID"] ?? 0);
$roleID = (int)($_SESSION["role_ID"] ?? 0);
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

// Each role sees only the request types that apply to them.
$requestTypesByRole = [
    "patient" => [
        "General",
        "Appointments",
        "Prescriptions"
    ],
    "doctor" => [
        "General",
        "Appointments",
        "Attendance",
        "Prescriptions",
        "Patients"
    ],
];

// Only patients and doctors can submit requests.
if (!isset($requestTypesByRole[$role])) {
    http_response_code(403);
    exit("Access denied.");
}

$requestTypes = $requestTypesByRole[$role];
$error = "";

// Escape dynamic text before displaying it in the page.
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Handle a submitted request.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestType = trim($_POST["request_Type"] ?? "");
    $reason = trim($_POST["request_Reason"] ?? "");
    $description = trim($_POST["request_Desc"] ?? "");

    // Check the submitted values.
    if (!in_array($requestType, $requestTypes, true)) {
        $error = "Choose a request type.";
    } elseif ($reason === "" || mb_strlen($reason) > 255) {
        $error = "Enter a reason between 1 and 255 characters.";
    } elseif ($description === "" || mb_strlen($description) > 255) {
        $error = "Enter a description between 1 and 255 characters.";
    } else {
        $status = "PENDING";
        $targetTable = null;
        $targetID = null;
        $payload = null;

        try {
            // Save the request for later review.
            $stmt = $conn->prepare("
                INSERT INTO requests
                    (users_ID, role_ID, request_Type, request_Reason,
                     request_Desc, request_Status, request_TargetTable,
                     request_TargetID, request_Payload)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new RuntimeException("Could not prepare the request.");
            }

            $stmt->bind_param(
                "iisssssis",
                $userID,
                $roleID,
                $requestType,
                $reason,
                $description,
                $status,
                $targetTable,
                $targetID,
                $payload
            );

            if (!$stmt->execute()) {
                throw new RuntimeException("Could not submit the request.");
            }

            $stmt->close();

            // Redirect after success so refreshing won't submit it again.
            header("Location: requests.php?submitted=1");
            exit();
        } catch (Throwable $exception) {
            // Keep the error understandable on the page.
            $error = "We couldn't submit your request. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests</title>
    <link rel="stylesheet" href="../styles/styleRequests.css?v=5">
    <link rel="stylesheet" href="../styles/styleNav.css?v=3">
    <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>

<!-- Site logo and navigation -->
<header>
    <h1>
        <a href="../html/HomePage.html">
            <img
                src="../img/neurodivergence.png"
                alt="Neurodivergence home"
                width="250"
            >
        </a>
        <nav id="navBar" aria-label="Main navigation"></nav>
    </h1>
</header>

<!-- Request form and page messages -->
<main class="request-main">
    <div class="request-heading">
        <p class="request-eyebrow">Support and account changes</p>
        <h2>Submit a Request</h2>
        <p>Choose a request type and explain what you need help with.</p>
    </div>

    <?php if (isset($_GET["submitted"])): ?>
        <div class="request-notice" role="status" aria-live="polite">
            <strong>Request submitted</strong>
            <span>Your request has been sent for review.</span>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="request-notice request-notice--error" role="alert">
            <strong>Request not submitted</strong>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Request form panel -->
    <form class="book_Disp request-form" method="post" action="requests.php">
        <div class="request-card-heading">
            <span class="request-card-mark" aria-hidden="true">?</span>
            <div>
                <h3>Request details</h3>
                <p>Complete each field so the team can understand your request.</p>
            </div>
        </div>

        <div class="request-layout">
            <!-- The available request categories depend on the user's role -->
            <fieldset class="request-options">
                <legend>Request type</legend>

                <?php foreach ($requestTypes as $index => $type): ?>
                    <label class="request-option">
                        <input
                            type="radio"
                            name="request_Type"
                            value="<?= e($type) ?>"
                            <?= $index === 0 ? "required" : "" ?>
                        >
                        <span><?= e($type) ?></span>
                    </label>
                <?php endforeach; ?>

                <p class="request-help">
                    Choose the option that best matches what you need.
                </p>
            </fieldset>

            <!-- Request reason and description -->
            <div class="request-fields">
                <label for="request_Reason">Reason</label>
                <input
                    id="request_Reason"
                    name="request_Reason"
                    type="text"
                    maxlength="255"
                    placeholder="Briefly explain your request"
                    required
                >

                <label for="request_Desc">Description</label>
                <textarea
                    id="request_Desc"
                    name="request_Desc"
                    rows="8"
                    maxlength="255"
                    placeholder="Add any details the team should know"
                    required
                ></textarea>

                <p class="request-help">
                    You can keep your explanation short and clear.
                </p>

                <button id="confirmButton" type="submit">
                    Submit request
                </button>
            </div>
        </div>
    </form>
</main>

<!-- Site footer -->
<footer>
    <div class="footer-contact">
        <p>Contact us at:</p>
        <p>Email: prohukm@hctm.ukm.edu.my</p>
        <p>Phone: 03-9145 5555 (Office hours: 8:30am–6:00pm)</p>
    </div>

    <div class="footer-version">
        <p>Version: 1.0.2</p>
    </div>

    <div class="footer-logo">
        <img src="../img/hctm.png" alt="HCTM logo">
    </div>
</footer>

<!-- Build navigation links for the signed-in user's role -->
<script>
const userRole = <?= json_encode(
    $role,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const navBar = document.getElementById("navBar");

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
    ]
};

if (navBar && navItems[userRole]) {
    navBar.innerHTML = navItems[userRole]
        .map(([label, href, containerClass, buttonClass]) => `
            <div class="${containerClass}">
                <button class="${buttonClass}" type="button">
                    <a href="${href}">${label}</a>
                </button>
            </div>
        `)
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
                    <a href="prescription.php">Your Prescriptions</a>
                    <a href="logout.php">Log Out</a>
                </div>
            </div>
        `;
}
</script>
<script src="../javascript/theme.js" defer></script>
</body>
</html>