<?php
// https://stackoverflow.com/questions/18316501/php-update-prepared-statement?
// https://stackoverflow.com/questions/22322364/php-mysqli-update-query?
// https://stackoverflow.com/questions/37519191/php-mysqli-fetch-assoc-on-joined-queries?
// https://stackoverflow.com/questions/4604965/how-to-include-a-file-only-if-the-file-exists-in-php?
// https://stackoverflow.com/questions/15145954/variable-argument-in-include?
// https://stackoverflow.com/questions/1996122/how-can-i-prevent-xss-with-html-php?
// Begins session.
session_start();

// loads back database connections, ONCE
require_once "connect.php";

// It checks if the person logging in is an admin, if they aren't, they get passed back
if (
    !isset($_SESSION["user_ID"]) ||
    !isset($_SESSION["role_Type"]) ||
    $_SESSION["role_Type"] !== "ADMIN"
) {
    header("Location: ../html/LoginPage.html");
    exit();
}

// Handle changes submitted from management forms.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Assigns doctor to an appointment.
    if (isset($_POST["assign_doctor"])) {
        $appointment_ID = (int)($_POST["appointment_ID"] ?? 0);
        $doctors_ID = (int)($_POST["doctors_ID"] ?? 0);

        $stmt = $conn->prepare("
            UPDATE appointments
            SET doctors_ID = ?
            WHERE appointment_ID = ?
        ");
        if (!$stmt) {
            die("Assign Doctor Error: " . $conn->error);
        }

        $stmt->bind_param("ii", $doctors_ID, $appointment_ID);

        if (!$stmt->execute()) {
            die("Assign Doctor Error: " . $stmt->error);
        }
    }

    // Updates an appointment's doctor, date, time, room, and status. It sends back to the table and scraps the old one.
    if (isset($_POST["update_appointment"])) {
        $appointment_ID = (int)($_POST["appointment_ID"] ?? 0);
        $doctors_ID = (int)($_POST["doctors_ID"] ?? 0);
        $date = $_POST["date"] ?? "";
        $time = $_POST["time"] ?? "";
        $room = $_POST["room"] ?? "";
        $status = $_POST["status"] ?? "";

        $stmt = $conn->prepare("
            UPDATE appointments
            SET
                doctors_ID = ?,
                appointment_Date = ?,
                appointment_Time = ?,
                appointment_Room = ?,
                appointment_Status = ?
            WHERE appointment_ID = ?
        ");

        if (!$stmt) {
            die("Update Appointment Error: " . $conn->error);
        }

        $stmt->bind_param(
            "issssi",
            $doctors_ID,
            $date,
            $time,
            $room,
            $status,
            $appointment_ID
        );

        if (!$stmt->execute()) {
            die("Update Appointment Error: " . $stmt->error);
        }
    }
}

//Receives recent bookings that was made. Sends back to recent activities
$recentBookings = $conn->query("
    SELECT
        b.booking_ID,
        b.booking_Date,
        p.patient_Name,
        p.users_ID AS patient_users_ID
    FROM booking b
    LEFT JOIN patients p ON p.patient_ID = b.patient_ID
    ORDER BY b.booking_ID DESC
    LIMIT 5
");

if (!$recentBookings) {
    die("Booking Query Error: " . $conn->error);
}

// Takes any recent activites regarding prescriptions.
$prescriptions = $conn->query("
    SELECT
        pr.prescription_ID,
        pr.patient_ID,
        p.patient_Name,
        pr.doctors_ID,
        d.doctor_Name,
        pr.appointment_ID,
        pr.prescription_MedicationName,
        pr.prescription_Type,
        pr.prescription_Regulations,
        pr.prescription_Auth
    FROM prescription pr
    JOIN patients p ON pr.patient_ID = p.patient_ID
    LEFT JOIN doctors d ON pr.doctors_ID = d.doctors_ID
    ORDER BY pr.prescription_ID DESC
    LIMIT 5
");

if (!$prescriptions) {
    die("Prescription Query Error: " . $conn->error);
}

// Recent request and reports and taken and shoved back
$requests = $conn->query("
    SELECT
        r.request_ID,
        r.users_ID,
        r.request_Type,
        COALESCE(
            p.patient_Name,
            d.doctor_Name,
            u.users_Email,
            'Unknown'
        ) AS submitted_by
    FROM requests r
    LEFT JOIN users u ON u.users_ID = r.users_ID
    LEFT JOIN patients p ON p.users_ID = r.users_ID
    LEFT JOIN doctors d ON d.users_ID = r.users_ID
    ORDER BY r.request_ID DESC
    LIMIT 5
");

if (!$requests) {
    die("Request Query Error: " . $conn->error);
}

// Get recent reports and identify the person who submitted each one.
$recentReports = $conn->query("
    SELECT
        r.report_ID,
        r.users_ID,
        COALESCE(
            p.patient_Name,
            d.doctor_Name,
            u.users_Email,
            'Unknown'
        ) AS submitted_by
    FROM reports r
    LEFT JOIN users u ON u.users_ID = r.users_ID
    LEFT JOIN patients p ON p.users_ID = r.users_ID
    LEFT JOIN doctors d ON d.users_ID = r.users_ID
    ORDER BY r.report_ID DESC
    LIMIT 5
");

if (!$recentReports) {
    die("Report Query Error: " . $conn->error);
}

// This is to show any recent appointments within the slider
$recentAppointments = $conn->query("
    SELECT
        appointments.appointment_ID,
        appointments.patient_ID,
        patients.patient_Name,
        appointments.doctors_ID,
        doctors.doctor_Name,
        appointments.appointment_Date,
        appointments.appointment_Time,
        appointments.appointment_Room,
        appointments.appointment_Status
    FROM appointments
    JOIN patients
        ON appointments.patient_ID = patients.patient_ID
    LEFT JOIN doctors
        ON appointments.doctors_ID = doctors.doctors_ID
    ORDER BY appointments.appointment_ID DESC
    LIMIT 5
");

if (!$recentAppointments) {
    die("Recent Appointments Query Error: " . $conn->error);
}

// Load patients and doctors for the management forms.
$patients = $conn->query("
    SELECT patient_ID, patient_Name
    FROM patients
    ORDER BY patient_Name
");

if (!$patients) {
    die("Patients Query Error: " . $conn->error);
}

$doctors = $conn->query("
    SELECT doctors_ID, doctor_Name
    FROM doctors
    ORDER BY doctor_Name
");

if (!$doctors) {
    die("Doctors Query Error: " . $conn->error);
}

// The state of patientAppointments remains null unless a patient filter was submitted.
$patientAppointments = null;

if (isset($_GET["patient_ID"]) && $_GET["patient_ID"] !== "") {
    $patient_ID = filter_input(INPUT_GET, "patient_ID", FILTER_VALIDATE_INT);

    if ($patient_ID !== false && $patient_ID !== null) {
        $stmt = $conn->prepare("
            SELECT
                appointments.appointment_ID,
                appointments.patient_ID,
                patients.patient_Name,
                appointments.doctors_ID,
                doctors.doctor_Name,
                appointments.appointment_Date,
                appointments.appointment_Time,
                appointments.appointment_Room,
                appointments.appointment_Status
            FROM appointments
            JOIN patients
                ON appointments.patient_ID = patients.patient_ID
            LEFT JOIN doctors
                ON appointments.doctors_ID = doctors.doctors_ID
            WHERE appointments.patient_ID = ?
            ORDER BY
                appointments.appointment_Date DESC,
                appointments.appointment_Time DESC
        ");

        if (!$stmt) {
            die("Patient Appointment Query Error: " . $conn->error);
        }

        $stmt->bind_param("i", $patient_ID);

        if (!$stmt->execute()) {
            die("Patient Appointment Query Error: " . $stmt->error);
        }

        $patientAppointments = $stmt->get_result();
    }
}

// same case with doctors
$doctorAppointments = null;

if (isset($_GET["doctors_ID"]) && $_GET["doctors_ID"] !== "") {
    $doctors_ID = filter_input(INPUT_GET, "doctors_ID", FILTER_VALIDATE_INT);

    if ($doctors_ID !== false && $doctors_ID !== null) {
        $stmt = $conn->prepare("
            SELECT
                appointments.appointment_ID,
                appointments.patient_ID,
                patients.patient_Name,
                appointments.doctors_ID,
                doctors.doctor_Name,
                appointments.appointment_Date,
                appointments.appointment_Time,
                appointments.appointment_Room,
                appointments.appointment_Status
            FROM appointments
            JOIN patients
                ON appointments.patient_ID = patients.patient_ID
            JOIN doctors
                ON appointments.doctors_ID = doctors.doctors_ID
            WHERE appointments.doctors_ID = ?
            ORDER BY
                appointments.appointment_Date,
                appointments.appointment_Time
        ");

        if (!$stmt) {
            die("Doctor Appointment Query Error: " . $conn->error);
        }

        $stmt->bind_param("i", $doctors_ID);

        if (!$stmt->execute()) {
            die("Doctor Appointment Query Error: " . $stmt->error);
        }

        $doctorAppointments = $stmt->get_result();
    }
}

// Puts new notes that were made within the slider
$notes = $conn->query("
    SELECT
        notes.notes_ID,
        notes.notes_Title,
        notes.notes_Desc,
        appointments.appointment_ID,
        patients.patient_Name,
        doctors.doctor_Name
    FROM notes
    JOIN appointments
        ON notes.appointment_ID = appointments.appointment_ID
    JOIN patients
        ON appointments.patient_ID = patients.patient_ID
    LEFT JOIN doctors
        ON appointments.doctors_ID = doctors.doctors_ID
    ORDER BY notes.notes_ID DESC
    LIMIT 5
");

if (!$notes) {
    die("Notes Query Error: " . $conn->error);
}

// THIS PART: Okay the way it works is like this, within the bottom management table,
// or in this case, admin-management, it makes use of these sections by passing 
// back via section, it checks sections that are connected within $section
// Whenever an admin clicks, it means the state of it is active
// When active, they see if the following button of the following section and connects it back from it being active
// Then one of the many manage tables I use will be sent back as display
// 
$section = $_GET["section"] ?? "appointments";

$sections = [
    "appointments"   => "manage_appointments.php",
    "patients"       => "manage_patients.php",
    "therapists"     => "manage_therapists.php",
    "bookings"       => "manage_bookings.php",
    "prescriptions"  => "manage_prescriptions.php",
    "reports"        => "manage_reports.php",
    "requests"       => "manage_requests.php",
    "notes"          => "manage_notes.php",
    "authentication" => "manage_authentication.php"
];

$selectedSection = $sections[$section] ?? "manage_appointments.php";
?>
<!DOCTYPE html>

<!--$stmt = $this->mysqli->prepare("UPDATE datadump SET content=? WHERE id=?");
/* BK: always check whether the prepare() succeeded */
if ($stmt === false) {
  trigger_error($this->mysqli->error, E_USER_ERROR);
  return;
}
$id = 1;
/* Bind our params */
/* BK: variables must be bound in the same order as the params in your SQL.
 * Some people prefer PDO because it supports named parameter. */
$stmt->bind_param('si', $content, $id);

/* Set our params */
/* BK: No need to use escaping when using parameters, in fact, you must not, 
 * because you'll get literal '\' characters in your content. */
$content = $_POST['content'] ?: '';

/* Execute the prepared Statement */
$status = $stmt->execute();
/* BK: always check whether the execute() succeeded */
if ($status === false) {
  trigger_error($stmt->error, E_USER_ERROR);
}
printf("%d Row inserted.\n", $stmt->affected_rows);

Re your questions:

I get a message from my script saying 0 Rows Inserted

This is because you reversed the order of parameters when you bound them. So you're searching the id column for the numeric value of your $content, which is probably interpreted as 0. So the UPDATE's WHERE clause matches zero rows.

do I need to declare all the fields or is it ok to just update one field??

It's okay to set just one column in an UPDATE statement. Other columns will not be changed.-->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Menu</title>
    <link rel="stylesheet" href="../styles/styleAdmin.css?v=5">
    <link rel="stylesheet" href="../styles/styleNav.css?v=2">
    <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>

<!-- Site header and admin navigation -->
<header>
    <h1>
        <a href="../html/HomePage.html">
            <img src="../img/neurodivergence.png" alt="Login Page" width="250">
        </a>

        <nav>
            <div class="us_button">
                <button class="AboutUs" style="margin: auto;">
                    <a href="../php/appointments.php" style="font-size: larger;">APPOINTMENTS</a>
                </button>
            </div>

            <div class="neuro_button">
                <button class="Neurodivergent">
                    <a href="../php/schedule.php" style="font-size: larger;">SCHEDULE</a>
                </button>
            </div>

            <div class="appointment_button">
                <button class="appointments">
                    <a href="management.php" style="font-size: larger;">MANAGEMENT</a>
                </button>
            </div>

            <div class="dropdown">
                <button class="dropdown-btn">
                    <a href="../php/account.php">
                        <img src="../img/login.png" alt="Login Page" width="70" height="70">
                    </a>
                </button>
                <div class="dropdown-content">
                    <a href="../php/updates.php">Inbox</a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Log Out</a>
                </div>
            </div>
        </nav>
    </h1>
</header>

<!--THIS, THIS IS THE SLIDER.... This displays recent activities of the website-->
<div class="table-slider-container">
    <button class="slider-btn prev-btn" onclick="moveSlide(-1)">
        <span class="leftNextDisplayForRecent"></span>
    </button>

    <div class="table-slider-track" id="sliderTrack">

        <!-- Recent activity details -->
        <div class="table-slide">
            <table>
                <caption>Recent Activity</caption>
                <thead>
                    <tr>
                        <th>Type:</th>
                        <th>ID:</th>
                        <th>Details:</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasRecentActivity =
                        ($recentBookings && $recentBookings->num_rows > 0) ||
                        ($requests && $requests->num_rows > 0) ||
                        ($recentReports && $recentReports->num_rows > 0) ||
                        ($prescriptions && $prescriptions->num_rows > 0);
                    ?>

                    <?php if (!$hasRecentActivity): ?>
                        <tr>
                            <td colspan="3">No recent activity found.</td>
                        </tr>
                    <?php else: ?>

                        <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
                            <?php while ($row = $recentBookings->fetch_assoc()): ?>
                                <tr>
                                    <td>Booking</td>
                                    <td><?= (int)$row["booking_ID"] ?></td>
                                    <td>
                                        Booking made for patient:
                                        <?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?>
                                        of ID:
                                        ACC0<?= (int)($row["patient_users_ID"] ?? 0) ?>
                                        on
                                        <?= htmlspecialchars($row["booking_Date"] ?? "Unknown date", ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($requests && $requests->num_rows > 0): ?>
                            <?php while ($row = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td>Request</td>
                                    <td><?= (int)$row["request_ID"] ?></td>
                                    <td>
                                        Request made by
                                        <?= htmlspecialchars($row["submitted_by"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($recentReports && $recentReports->num_rows > 0): ?>
                            <?php while ($row = $recentReports->fetch_assoc()): ?>
                                <tr>
                                    <td>Report</td>
                                    <td><?= (int)$row["report_ID"] ?></td>
                                    <td>
                                        Report made by
                                        <?= htmlspecialchars($row["submitted_by"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($prescriptions && $prescriptions->num_rows > 0): ?>
                            <?php while ($row = $prescriptions->fetch_assoc()): ?>
                                <tr>
                                    <td>Prescription</td>
                                    <td><?= (int)$row["prescription_ID"] ?></td>
                                    <td>
                                        Prescription made for:
                                        <?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?>
                                        | Medicine:
                                        <?= htmlspecialchars($row["prescription_MedicationName"] ?? "", ENT_QUOTES, "UTF-8") ?>
                                        | Doctor:
                                        <?= htmlspecialchars($row["doctor_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent appointments -->
        <div class="table-slide">
            <table>
                <caption>Recent Appointments</caption>
                <thead>
                    <tr>
                        <th>Appointment ID:</th>
                        <th>Patient:</th>
                        <th>Doctor:</th>
                        <th>Date:</th>
                        <th>Time:</th>
                        <th>Room:</th>
                        <th>Status:</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentAppointments && $recentAppointments->num_rows > 0): ?>
                        <?php while ($row = $recentAppointments->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$row["appointment_ID"] ?></td>
                                <td><?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["doctor_Name"] ?? "Not assigned", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["appointment_Date"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["appointment_Time"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["appointment_Room"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["appointment_Status"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No appointments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent notes -->
        <div class="table-slide">
            <table>
                <caption>Recent Notes</caption>
                <thead>
                    <tr>
                        <th>Note ID:</th>
                        <th>Title:</th>
                        <th>Description:</th>
                        <th>Patient:</th>
                        <th>Doctor:</th>
                        <th>Appointment ID:</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($notes && $notes->num_rows > 0): ?>
                        <?php while ($row = $notes->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int)$row["notes_ID"] ?></td>
                                <td><?= htmlspecialchars($row["notes_Title"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["notes_Desc"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= htmlspecialchars($row["doctor_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                                <td><?= (int)$row["appointment_ID"] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No notes found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <button class="slider-btn next-btn" onclick="moveSlide(1)">
        <span class="rightNextDisplayForRecent"></span>
    </button>
</div>

<br>
<br>

<!-- Sidebar options for choosing which management table to view -->
<div class="admin-management">
    <div class="admin-management-menu">
        <div class="admin-management-title">Management</div>

        <a href="management.php?section=appointments"
           class="management-tab <?= $section === "appointments" ? "active" : "" ?>">
            Appointments
        </a>

        <a href="management.php?section=patients"
           class="management-tab <?= $section === "patients" ? "active" : "" ?>">
            Patients
        </a>

        <a href="management.php?section=therapists"
           class="management-tab <?= $section === "therapists" ? "active" : "" ?>">
            Therapists
        </a>

        <a href="management.php?section=bookings"
           class="management-tab <?= $section === "bookings" ? "active" : "" ?>">
            Bookings
        </a>

        <a href="management.php?section=prescriptions"
           class="management-tab <?= $section === "prescriptions" ? "active" : "" ?>">
            Prescriptions
        </a>

        <a href="management.php?section=reports"
           class="management-tab <?= $section === "reports" ? "active" : "" ?>">
            Reports
        </a>

        <a href="management.php?section=requests"
           class="management-tab <?= $section === "requests" ? "active" : "" ?>">
            Requests
        </a>

        <a href="management.php?section=notes"
           class="management-tab <?= $section === "notes" ? "active" : "" ?>">
            Notes
        </a>

        <a href="management.php?section=authentication"
           class="management-tab <?= $section === "authentication" ? "active" : "" ?>">
            Authentication
        </a>
    </div>

    <!-- The selected management table is loaded here -->
    <div class="admin-management-content">
        <?php
        if (file_exists($selectedSection)) {
            include $selectedSection;
        } else {
            echo "<p>Management file not found.</p>";
        }
        ?>
    </div>
</div>

<!-- Site footer -->
<footer>
    <div class="footer-contact">
        <p>CONTACT US AT:</p>
        <p>EMAIL: prohukm@hctm.ukm.edu.my</p>
        <p>PHONE NUMBER: 03-9145 5555 (OFFICE HOURS: 8:30AM - 6:00PM)</p>
    </div>

    <div class="footer-version">
        <p>VERSION: 1.0.2</p>
    </div>

    <div class="footer-logo">
        <img src="../img/hctm.png" alt="HCTM Logo">
    </div>
</footer>

<!-- Load the slider controls -->
<script src="../javascript/tableSlider.js"></script>

<script>
const managementScrollKey = "managementScrollY";

// Let this script control the page's scroll position after reload.
if ("scrollRestoration" in history) {
    history.scrollRestoration = "manual";
}

// Wrap only management tables, so their headings and filters stay visible.
document
    .querySelectorAll(".admin-management-content table.management-table")
    .forEach((table) => {
        if (table.parentElement?.classList.contains("management-table-scroll")) {
            return;
        }

        const scrollArea = document.createElement("div");
        scrollArea.className = "management-scroll-region management-table-scroll";

        table.parentNode.insertBefore(scrollArea, table);
        scrollArea.appendChild(table);
    });

// Save the current page position when a management option is clicked.
document.querySelectorAll("a.management-tab").forEach((tab) => {
    tab.addEventListener("click", () => {
        sessionStorage.setItem(managementScrollKey, String(window.scrollY));
    });
});

// Restore that position immediately after management.php reloads.
window.addEventListener("pageshow", () => {
    const savedY = sessionStorage.getItem(managementScrollKey);

    if (savedY === null) {
        return;
    }

    sessionStorage.removeItem(managementScrollKey);

    // Disable smooth scrolling for this restoration.
    document.documentElement.style.scrollBehavior = "auto";

    requestAnimationFrame(() => {
        window.scrollTo(0, Number(savedY));
    });
});
</script>
<script src="../javascript/theme.js" defer></script>
</body>
</html>