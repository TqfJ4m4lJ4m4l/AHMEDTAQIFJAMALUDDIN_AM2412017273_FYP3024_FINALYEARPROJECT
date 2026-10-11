<?php
require_once "session_check.php";
require_once "connect.php";

// Get the signed-in user's ID and role.
$userID = (int)$_SESSION["user_ID"];
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

// Only patients and doctors can submit reports.
if (!in_array($role, ["patient", "doctor"], true)) {
    http_response_code(403);
    exit("Reports can be submitted by patients and doctors.");
}

// These are the report categories shown in the form.
$reportTypes = [
    "General",
    "Appointment",
    "Prescription",
    "Website",
    "Other"
];

$error = "";

// Handle a submitted report.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $type = trim($_POST["report_Type"] ?? "");
    $reason = trim($_POST["report_Reason"] ?? "");
    $description = trim($_POST["report_Desc"] ?? "");

    // Check the form values before inserting them.
    if (!in_array($type, $reportTypes, true)) {
        $error = "Choose a report category.";
    } elseif ($reason === "" || mb_strlen($reason) > 255) {
        $error = "Enter a reason between 1 and 255 characters.";
    } elseif ($description === "" || mb_strlen($description) > 255) {
        $error = "Enter a description between 1 and 255 characters.";
    } else {
        // Save the report against the signed-in user's account.
        $stmt = $conn->prepare("
            INSERT INTO reports
                (users_ID, report_Type, report_Reason, report_Desc)
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt) {
            $error = "We couldn't prepare your report. Please try again.";
        } else {
            $stmt->bind_param("isss", $userID, $type, $reason, $description);

            if (!$stmt->execute()) {
                $error = "We couldn't save your report. Please try again.";
            } else {
                // Notify admin accounts that a report needs review.
                $notice = "A new report was submitted and is ready for review.";

                $notifyAdmins = $conn->prepare("
                    INSERT INTO notifications (users_ID, notification_Message)
                    SELECT users_ID, ?
                    FROM admins
                    WHERE users_ID IS NOT NULL
                ");

                if ($notifyAdmins) {
                    $notifyAdmins->bind_param("s", $notice);
                    $notifyAdmins->execute();
                    $notifyAdmins->close();
                }

                // Refresh the page without resubmitting the form.
                header("Location: reports.php?submitted=1");
                exit();
            }

            $stmt->close();
        }
    }
}

// Escape dynamic text before displaying it in the page.
function reportEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Report</title>
    <link rel="stylesheet" href="../styles/styleReport.css?v=5">
    <link rel="stylesheet" href="../styles/styleNav.css?v=3">
    <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>

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

<main class="report-main">
    <div class="report-heading">
        <p class="report-eyebrow">Support and safety</p>
        <h2>Submit a Report</h2>
        <p>
            Use this form to tell us about a concern. Your report will be sent
            to the team for review.
        </p>
    </div>

    <?php if (isset($_GET["submitted"])): ?>
        <div class="report-notice report-notice--success" role="status" aria-live="polite">
            <strong>Report submitted</strong>
            <span>Your report has been sent for review.</span>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="report-notice report-notice--error" role="alert">
            <strong>Report not submitted</strong>
            <span><?= reportEscape($error) ?></span>
        </div>
    <?php endif; ?>

    <section class="report-card" aria-labelledby="report-form-title">
        <div class="report-card-heading">
            <span class="report-warning-mark" aria-hidden="true">!</span>
            <div>
                <h3 id="report-form-title">Report details</h3>
                <p>Complete each field so the team can understand your concern.</p>
            </div>
        </div>

        <form class="report-form" action="reports.php" method="post">
            <fieldset class="report-categories">
                <legend>Report category</legend>

                <?php foreach ($reportTypes as $type): ?>
                    <label class="report-category-option">
                        <input
                            type="radio"
                            name="report_Type"
                            value="<?= reportEscape($type) ?>"
                            required
                        >
                        <span><?= reportEscape($type) ?></span>
                    </label>
                <?php endforeach; ?>

                <p class="report-help">
                    Choose the category that best matches your concern.
                </p>
            </fieldset>

            <div class="report-fields">
                <label for="report_Reason">Reason</label>
                <input
                    id="report_Reason"
                    type="text"
                    name="report_Reason"
                    maxlength="255"
                    placeholder="Briefly describe the reason"
                    required
                >

                <label for="report_Desc">Description</label>
                <textarea
                    id="report_Desc"
                    name="report_Desc"
                    maxlength="255"
                    rows="8"
                    placeholder="Add the details the team should know"
                    required
                ></textarea>

                <p class="report-help">
                    Please do not include information that is unrelated to this report.
                </p>

                <button type="submit">Submit report</button>
            </div>
        </form>
    </section>
</main>

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