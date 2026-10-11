<?php
require_once "session_check.php";
require_once "connect.php";

$userID = (int)$_SESSION["user_ID"];
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

if (!in_array($role, ["patient", "doctor"], true)) {
    http_response_code(403);
    exit("Access denied.");
}

function noteEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$error = "";

if ($role === "doctor") {
    $stmt = $conn->prepare("
        SELECT doctors_ID
        FROM doctors
        WHERE users_ID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        exit("Doctor profile not found.");
    }

    $doctorID = (int)$doctor["doctors_ID"];

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $appointmentID = filter_input(
            INPUT_POST,
            "appointment_ID",
            FILTER_VALIDATE_INT
        );
        $title = trim($_POST["notes_Title"] ?? "");
        $description = trim($_POST["notes_Desc"] ?? "");

        if (!$appointmentID || $title === "" || $description === "") {
            $error = "Choose an appointment and complete both note fields.";
        } elseif (mb_strlen($title) > 255 || mb_strlen($description) > 255) {
            $error = "The title and note must each be 255 characters or fewer.";
        } else {
            $check = $conn->prepare("
                SELECT appointment_ID
                FROM appointments
                WHERE appointment_ID = ?
                  AND doctors_ID = ?
                  AND appointment_Status <> 'Cancelled'
            ");
            $check->bind_param("ii", $appointmentID, $doctorID);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                $error = "You can only write notes for your assigned appointments.";
            } else {
                $payload = json_encode([
                    "appointment_ID" => $appointmentID,
                    "notes_Title" => $title,
                    "notes_Desc" => $description
                ], JSON_UNESCAPED_UNICODE);

                if ($payload === false) {
                    $error = "Could not prepare the note for approval.";
                } else {
                    $requestType = "Notes";
                    $reason = "Create appointment note";
                    $targetTable = "notes";

                    $insert = $conn->prepare("
                        INSERT INTO requests
                            (users_ID, request_Type, request_Reason,
                             request_Desc, request_Status,
                             request_TargetTable, request_TargetID,
                             request_Payload)
                        VALUES (?, ?, ?, ?, 'PENDING', ?, NULL, ?)
                    ");
                    $insert->bind_param(
                        "isssss",
                        $userID,
                        $requestType,
                        $reason,
                        $title,
                        $targetTable,
                        $payload
                    );
                    $insert->execute();

                    header("Location: notes.php?submitted=1");
                    exit();
                }
            }
        }
    }

    $stmt = $conn->prepare("
        SELECT a.appointment_ID, a.appointment_Date, a.appointment_Time,
               p.patient_Name
        FROM appointments a
        JOIN patients p ON p.patient_ID = a.patient_ID
        WHERE a.doctors_ID = ?
          AND a.appointment_Status <> 'Cancelled'
        ORDER BY a.appointment_Date DESC, a.appointment_Time DESC
    ");
    $stmt->bind_param("i", $doctorID);
    $stmt->execute();
    $assignedAppointments = $stmt->get_result();
} else {
    $stmt = $conn->prepare("
        SELECT patient_ID
        FROM patients
        WHERE users_ID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    if (!$patient) {
        exit("Patient profile not found.");
    }

    $patientID = (int)$patient["patient_ID"];

    $stmt = $conn->prepare("
        SELECT n.notes_Title, n.notes_Desc,
               a.appointment_Date, a.appointment_Time,
               d.doctor_Name
        FROM notes n
        JOIN appointments a ON a.appointment_ID = n.appointment_ID
        LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
        WHERE a.patient_ID = ?
        ORDER BY a.appointment_Date DESC,
                 a.appointment_Time DESC,
                 n.notes_ID DESC
    ");
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $patientNotes = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Notes</title>
    <link rel="stylesheet" href="../styles/styleNotes.css?v=3">
            <link rel="stylesheet" href="../styles/styleNav.css?v=2">
            <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>
<header>
    <h1>
        <a href="../html/HomePage.html">
            <img src="../img/neurodivergence.png" alt="Home" width="250">
        </a>
        <nav id="navBar"></nav>
    </h1>
</header>
<?php if (isset($_GET["submitted"]) && $_GET["submitted"] === "1"): ?>
    <div
        id="note-message"
        class="auth-toast auth-toast--success"
        role="status"
        aria-live="polite"
    >
        <span class="auth-toast-message">
            Your note was sent to an administrator for approval.
        </span>
        <button
            class="auth-toast-close"
            type="button"
            aria-label="Dismiss notification"
        >&times;</button>
    </div>
<?php endif; ?>
<main class="notes-main">
    <h2><?= $role === "doctor" ? "Write an Appointment Note" : "Your Notes" ?></h2>

    <?php if ($error !== ""): ?>
        <p role="alert"><?= noteEscape($error) ?></p>
    <?php endif; ?>

    <?php if ($role === "doctor"): ?>
        <form method="post" action="notes.php">
            <label for="appointment_ID">Assigned appointment</label>
            <select id="appointment_ID" name="appointment_ID" required>
                <option value="">Choose an appointment</option>
                <?php while ($appointment = $assignedAppointments->fetch_assoc()): ?>
                    <option value="<?= (int)$appointment["appointment_ID"] ?>">
                        <?= noteEscape($appointment["appointment_Date"]) ?>
                        <?= noteEscape($appointment["appointment_Time"]) ?> —
                        <?= noteEscape($appointment["patient_Name"]) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="notes_Title">Title</label>
            <input id="notes_Title" name="notes_Title" maxlength="255" required>

            <label for="notes_Desc">Note</label>
            <textarea id="notes_Desc" name="notes_Desc" maxlength="255" required></textarea>

            <button type="submit">Send for approval</button>
        </form>
    <?php else: ?>
        <?php if ($patientNotes->num_rows > 0): ?>
            <?php while ($note = $patientNotes->fetch_assoc()): ?>
                <article class="note-card">
                    <h3><?= noteEscape($note["notes_Title"]) ?></h3>
                    <p><?= nl2br(noteEscape($note["notes_Desc"])) ?></p>
                    <small>
                        Appointment: <?= noteEscape($note["appointment_Date"]) ?>
                        <?= noteEscape($note["appointment_Time"]) ?> —
                        <?= noteEscape($note["doctor_Name"] ?? "Doctor") ?>
                    </small>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No approved notes yet.</p>
        <?php endif; ?>
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