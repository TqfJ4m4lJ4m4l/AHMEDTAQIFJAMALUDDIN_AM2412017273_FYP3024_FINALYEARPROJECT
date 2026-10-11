<?php
require_once "session_check.php";
require_once "connect.php";

$userID = (int)$_SESSION["user_ID"];
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

if (!in_array($role, ["doctor", "admin"], true)) {
    http_response_code(403);
    exit("Access denied.");
}

$dateFilter = trim($_GET["date"] ?? "");

if ($dateFilter !== "") {
    $date = DateTime::createFromFormat("Y-m-d", $dateFilter);

    if (!$date || $date->format("Y-m-d") !== $dateFilter) {
        http_response_code(400);
        exit("Invalid date.");
    }

    $stmt = $conn->prepare("
        SELECT a.appointment_ID, a.appointment_Date, a.appointment_Time,
               a.appointment_Room, a.appointment_Medium,
               a.appointment_Status, a.appointment_Attendance,
               p.patient_Name, d.doctor_Name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_ID = a.patient_ID
        LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
        WHERE a.appointment_Date = ?
        ORDER BY a.appointment_Time, d.doctor_Name, p.patient_Name
    ");
    $stmt->bind_param("s", $dateFilter);
} else {
    $stmt = $conn->prepare("
        SELECT a.appointment_ID, a.appointment_Date, a.appointment_Time,
               a.appointment_Room, a.appointment_Medium,
               a.appointment_Status, a.appointment_Attendance,
               p.patient_Name, d.doctor_Name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_ID = a.patient_ID
        LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
        ORDER BY a.appointment_Date, a.appointment_Time,
                 d.doctor_Name, p.patient_Name
    ");
}

$stmt->execute();
$schedule = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/styleSchedule.css">
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
<main class="schedule-main">
    <h2>APPOINTMENT SCHEDULE</h2>

    <form method="get" action="schedule.php">
        <label for="date">Show a date:</label>
        <input
            type="date"
            id="date"
            name="date"
            value="<?= htmlspecialchars($dateFilter, ENT_QUOTES, "UTF-8") ?>"
        >
        <button type="submit">Filter</button>
        <a href="schedule.php">Show all dates</a>
    </form>

    <div class="schedule-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Room</th>
                    <th>Medium</th>
                    <th>Status</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($schedule->num_rows > 0): ?>
                    <?php while ($row = $schedule->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["appointment_Date"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["appointment_Time"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["doctor_Name"] ?? "Unassigned", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["appointment_Room"] ?? "Digital", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["appointment_Medium"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["appointment_Status"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($row["appointment_Attendance"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No appointments found for this date.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
        ["ACTIVITY BOARD", "../html/Activity.html", "neuro_button", "Neurodivergent"],
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