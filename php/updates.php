<?php
require_once "session_check.php";
require_once "connect.php";

$userID = (int)$_SESSION["user_ID"];
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

$stmt = $conn->prepare("
    SELECT notification_Message, notification_CreatedAt
    FROM notifications
    WHERE users_ID = ?
    ORDER BY notification_CreatedAt DESC
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$notifications = $stmt->get_result();

function updateEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/styleUpdates.css">
        <link rel="stylesheet" href="../styles/styleNav.css?v=2">
        <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>
    <header>
<h1>
<a href="../html/HomePage.html">
<img src="../img/neurodivergence.png"
     alt="Login Page"
     width="250">
</a>
<nav id= "navBar">
</nav>
</h1>
</header>
<main class="updates-main">
    <h2>INBOX</h2>

    <?php if ($notifications->num_rows > 0): ?>
        <?php while ($notification = $notifications->fetch_assoc()): ?>
            <article class="update-card">
                <p><?= nl2br(updateEscape($notification["notification_Message"])) ?></p>
                <time>
                    <?= updateEscape($notification["notification_CreatedAt"]) ?>
                </time>
            </article>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No new notifications within your inbox yet.</p>
    <?php endif; ?>
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
    ],
    admin: [
        ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
        ["SCHEDULE", "schedule.php", "neuro_button", "Neurodivergent"],
        ["MANAGEMENT", "management.php", "appointment_button", "appointments"]
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
                    ${userRole !== "admin"
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