<?php
require_once "session_check.php";
require_once "connect.php";

$userID = (int)$_SESSION["user_ID"];
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

if (!in_array($role, ["patient", "doctor", "admin"], true)) {
    http_response_code(403);
    exit("Access denied.");
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function sendNotification(mysqli $conn, int $userID, string $message): void
{
    $stmt = $conn->prepare("
        INSERT INTO notifications (users_ID, notification_Message)
        VALUES (?, ?)
    ");
    $stmt->bind_param("is", $userID, $message);
    $stmt->execute();
}

function getAdminID(mysqli $conn, int $userID): ?int
{
    $stmt = $conn->prepare("
        SELECT admin_ID
        FROM admins
        WHERE users_ID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int)$row["admin_ID"] : null;
}

function insertAuth(
    mysqli $conn,
    int $adminID,
    string $status,
    string $type,
    string $table,
    int $targetID
): int {
    $stmt = $conn->prepare("
        INSERT INTO authentication
            (admin_ID, auth_Status, auth_Date, auth_Type,
             auth_TargetTable, auth_TargetID)
        VALUES (?, ?, CURDATE(), ?, ?, ?)
    ");
    $stmt->bind_param("isssi", $adminID, $status, $type, $table, $targetID);
    $stmt->execute();

    return (int)$conn->insert_id;
}

$patientID = null;
$doctorID = null;

if ($role === "patient") {
    $stmt = $conn->prepare("
        SELECT patient_ID
        FROM patients
        WHERE users_ID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();

    if (!$profile) {
        exit("Patient profile not found.");
    }

    $patientID = (int)$profile["patient_ID"];
} elseif ($role === "doctor") {
    $stmt = $conn->prepare("
        SELECT doctors_ID
        FROM doctors
        WHERE users_ID = ?
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();

    if (!$profile) {
        exit("Doctor profile not found.");
    }

    $doctorID = (int)$profile["doctors_ID"];
}

$error = "";
$transactionStarted = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        /*
         * A patient can check in only to their own approved appointment
         * on the appointment date, using its appointment code.
         */
        if ($action === "check_in" && $role === "patient") {
            $appointmentID = filter_input(
                INPUT_POST,
                "appointment_ID",
                FILTER_VALIDATE_INT
            );
            $code = trim($_POST["appointment_Code"] ?? "");

            if (!$appointmentID || $code === "") {
                throw new RuntimeException("Enter the appointment code.");
            }

            $stmt = $conn->prepare("
                UPDATE appointments
                SET appointment_Attendance = 'Checked In'
                WHERE appointment_ID = ?
                  AND patient_ID = ?
                  AND appointment_Code = ?
                  AND appointment_Date = CURDATE()
                  AND appointment_Status = 'Approved'
            ");
            $stmt->bind_param("iis", $appointmentID, $patientID, $code);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    "Check-in failed. Confirm the code and that this is an approved appointment for today."
                );
            }

            header("Location: appointments.php?checked_in=1");
            exit();
        }

        /*
         * A doctor can decide bookings requested for that doctor.
         * A manager can decide any pending booking.
         */
        if (
            $action === "booking_decision" &&
            in_array($role, ["doctor", "admin"], true)
        ) {
            $bookingID = filter_input(
                INPUT_POST,
                "booking_ID",
                FILTER_VALIDATE_INT
            );
            $decision = $_POST["decision"] ?? "";

            if (
                !$bookingID ||
                !in_array($decision, ["APPROVED", "NOT APPROVED"], true)
            ) {
                throw new RuntimeException("Invalid booking decision.");
            }

            $conn->begin_transaction();
            $transactionStarted = true;

            /*
             * booking_Doctors stores a doctor name. Match that name to the
             * doctor's database ID so approval can use the correct foreign key.
             */
            $stmt = $conn->prepare("
                SELECT
                    b.booking_ID,
                    b.patient_ID,
                    b.booking_Date,
                    b.booking_Time,
                    b.booking_Medium,
                    b.booking_Status,
                    b.booking_Doctors,
                    (
                        SELECT d.doctors_ID
                        FROM doctors d
                        WHERE LOWER(TRIM(d.doctor_Name)) =
                              LOWER(TRIM(b.booking_Doctors))
                        LIMIT 1
                    ) AS requested_doctors_ID
                FROM booking b
                WHERE b.booking_ID = ?
                FOR UPDATE
            ");
            $stmt->bind_param("i", $bookingID);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();

            if (!$booking || $booking["booking_Status"] !== "PENDING") {
                throw new RuntimeException(
                    "That booking is missing or has already been decided."
                );
            }

            if (
                $role === "doctor" &&
                (int)($booking["requested_doctors_ID"] ?? 0) !== $doctorID
            ) {
                throw new RuntimeException(
                    "This booking was not requested for your doctor profile."
                );
            }

            $authID = null;

            if ($role === "admin") {
                $adminID = getAdminID($conn, $userID);

                if (!$adminID) {
                    throw new RuntimeException("Administrator profile not found.");
                }

                $authID = insertAuth(
                    $conn,
                    $adminID,
                    $decision,
                    "APPROVE BOOKING",
                    "booking",
                    $bookingID
                );
            }

            if ($decision === "APPROVED") {
                if ($role === "doctor") {
                    $selectedDoctorID = $doctorID;
                } else {
                    $selectedDoctorID = (int)(
                        $booking["requested_doctors_ID"] ?? 0
                    );
                }

                if (!$selectedDoctorID) {
                    throw new RuntimeException(
                        "The requested doctor could not be matched to a doctor profile."
                    );
                }

                $room = trim($_POST["room"] ?? "");
                $medium = $booking["booking_Medium"];

                if (!in_array($medium, ["Digital", "One on One", "Non-vocal"], true)) {
                    throw new RuntimeException("The booking has an invalid medium.");
                }

                if ($medium === "Digital") {
                    $room = null;
                } elseif (!in_array($room, ["1", "2", "3", "4", "5", "6"], true)) {
                    throw new RuntimeException("Choose a valid room.");
                }

                $code = bin2hex(random_bytes(16));
                $status = "Approved";
                $attendance = "Not Checked In";

                $stmt = $conn->prepare("
                    INSERT INTO appointments
                        (patient_ID, doctors_ID, auth_ID, booking_ID,
                         appointment_Date, appointment_Time, appointment_Room,
                         appointment_Status, appointment_Code, appointment_Medium,
                         appointment_Attendance)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iiiisssssss",
                    $booking["patient_ID"],
                    $selectedDoctorID,
                    $authID,
                    $bookingID,
                    $booking["booking_Date"],
                    $booking["booking_Time"],
                    $room,
                    $status,
                    $code,
                    $medium,
                    $attendance
                );
                $stmt->execute();
            }

            $stmt = $conn->prepare("
                UPDATE booking
                SET booking_Status = ?
                WHERE booking_ID = ?
            ");
            $stmt->bind_param("si", $decision, $bookingID);
            $stmt->execute();

            // Notify the patient about this booking decision.
            $patientUserStmt = $conn->prepare("
                SELECT p.users_ID
                FROM booking b
                JOIN patients p ON p.patient_ID = b.patient_ID
                WHERE b.booking_ID = ?
            ");
            $patientUserStmt->bind_param("i", $bookingID);
            $patientUserStmt->execute();
            $patientUser = $patientUserStmt->get_result()->fetch_assoc();

            if ($patientUser && !empty($patientUser["users_ID"])) {
                $bookingNotice = $decision === "APPROVED"
                    ? "Your appointment booking was approved."
                    : "Your appointment booking was not approved.";

                sendNotification(
                    $conn,
                    (int)$patientUser["users_ID"],
                    $bookingNotice
                );
            }

            $conn->commit();
            $transactionStarted = false;

            header("Location: appointments.php?booking_updated=1");
            exit();
        }

        /*
         * Doctors create appointments for themselves.
         * Admins can choose both the patient and doctor.
         */
        if (
            $action === "create_appointment" &&
            in_array($role, ["doctor", "admin"], true)
        ) {
            $newPatientID = filter_input(
                INPUT_POST,
                "patient_ID",
                FILTER_VALIDATE_INT
            );

            $newDoctorID = $role === "doctor"
                ? $doctorID
                : filter_input(INPUT_POST, "doctors_ID", FILTER_VALIDATE_INT);

            $date = trim($_POST["date"] ?? "");
            $time = trim($_POST["time"] ?? "");
            $medium = $_POST["medium"] ?? "";
            $room = trim($_POST["room"] ?? "");

            if (!$newPatientID || !$newDoctorID || $date === "" || $time === "") {
                throw new RuntimeException("Complete all required appointment fields.");
            }

            if (!in_array($medium, ["Digital", "One on One", "Non-vocal"], true)) {
                throw new RuntimeException("Choose a valid appointment medium.");
            }

            if ($medium === "Digital") {
                $room = null;
            } elseif (!in_array($room, ["1", "2", "3", "4", "5", "6"], true)) {
                throw new RuntimeException("Choose a valid room.");
            }

            $code = bin2hex(random_bytes(16));
            $status = "Approved";
            $attendance = "Not Checked In";
            $authID = null;
            $bookingIDForAppointment = null;

            $conn->begin_transaction();
            $transactionStarted = true;

            $stmt = $conn->prepare("
                INSERT INTO appointments
                    (patient_ID, doctors_ID, auth_ID, booking_ID,
                     appointment_Date, appointment_Time, appointment_Room,
                     appointment_Status, appointment_Code, appointment_Medium,
                     appointment_Attendance)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iiiisssssss",
                $newPatientID,
                $newDoctorID,
                $authID,
                $bookingIDForAppointment,
                $date,
                $time,
                $room,
                $status,
                $code,
                $medium,
                $attendance
            );
            $stmt->execute();

            $newAppointmentID = (int)$conn->insert_id;

            if ($role === "admin") {
                $adminID = getAdminID($conn, $userID);

                if (!$adminID) {
                    throw new RuntimeException("Administrator profile not found.");
                }

                $newAuthID = insertAuth(
                    $conn,
                    $adminID,
                    "APPROVED",
                    "APPROVE APPOINTMENT",
                    "appointments",
                    $newAppointmentID
                );

                $stmt = $conn->prepare("
                    UPDATE appointments
                    SET auth_ID = ?
                    WHERE appointment_ID = ?
                ");
                $stmt->bind_param("ii", $newAuthID, $newAppointmentID);
                $stmt->execute();
            }

            // Notify the patient about the appointment just created.
            $patientUserStmt = $conn->prepare("
                SELECT users_ID
                FROM patients
                WHERE patient_ID = ?
            ");
            $patientUserStmt->bind_param("i", $newPatientID);
            $patientUserStmt->execute();
            $patientUser = $patientUserStmt->get_result()->fetch_assoc();

            if ($patientUser && !empty($patientUser["users_ID"])) {
                sendNotification(
                    $conn,
                    (int)$patientUser["users_ID"],
                    "A new appointment was created for you."
                );
            }

            $conn->commit();
            $transactionStarted = false;

            header("Location: appointments.php?created=1");
            exit();
        }

        throw new RuntimeException("You are not allowed to perform that action.");
    } catch (Throwable $e) {
        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                // Keep the original error if rollback also fails.
            }

            $transactionStarted = false;
        }

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : "The action could not be completed.";
    }
}

/* Patients see their own appointments and notes for those appointments. */
if ($role === "patient") {
    $stmt = $conn->prepare("
        SELECT a.*,
               d.doctor_Name,
               (
                   SELECT GROUP_CONCAT(
                       CONCAT_WS(': ', n.notes_Title, n.notes_Desc)
                       SEPARATOR '\n'
                   )
                   FROM notes n
                   WHERE n.appointment_ID = a.appointment_ID
               ) AS notes_preview
        FROM appointments a
        LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
        WHERE a.patient_ID = ?
        ORDER BY a.appointment_Date DESC, a.appointment_Time DESC
    ");
    $stmt->bind_param("i", $patientID);
} elseif ($role === "doctor" && ($_GET["view"] ?? "mine") !== "all") {
    $stmt = $conn->prepare("
        SELECT a.*, p.patient_Name, d.doctor_Name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_ID = a.patient_ID
        LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
        WHERE a.doctors_ID = ?
        ORDER BY a.appointment_Date DESC, a.appointment_Time DESC
    ");
    $stmt->bind_param("i", $doctorID);
} else {
    // Admins see all appointments. Doctors can choose the all-doctors view.
    $stmt = $conn->prepare("
        SELECT a.*, p.patient_Name, d.doctor_Name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_ID = a.patient_ID
        LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
        ORDER BY a.appointment_Date DESC, a.appointment_Time DESC
    ");
}

$stmt->execute();
$appointments = $stmt->get_result();

$pendingBookings = null;
$patientOptions = [];
$doctorOptions = [];

if ($role === "doctor") {
    /*
     * Doctors only see pending bookings that requested their profile.
     * booking_Doctors stores the requested doctor's name.
     */
    $pendingStmt = $conn->prepare("
        SELECT
            b.*,
            p.patient_Name,
            d.doctors_ID AS requested_doctors_ID
        FROM booking b
        LEFT JOIN patients p
            ON p.patient_ID = b.patient_ID
        JOIN doctors d
            ON LOWER(TRIM(d.doctor_Name)) =
               LOWER(TRIM(b.booking_Doctors))
        WHERE b.booking_Status = 'PENDING'
          AND d.doctors_ID = ?
        ORDER BY b.booking_Date, b.booking_Time
    ");
    $pendingStmt->bind_param("i", $doctorID);
    $pendingStmt->execute();
    $pendingBookings = $pendingStmt->get_result();
}

if (in_array($role, ["doctor", "admin"], true)) {
    if ($role === "admin") {
        // Managers see all pending bookings and each requested doctor.
        $pendingBookings = $conn->query("
            SELECT
                b.*,
                p.patient_Name,
                d.doctors_ID AS requested_doctors_ID
            FROM booking b
            LEFT JOIN patients p
                ON p.patient_ID = b.patient_ID
            LEFT JOIN doctors d
                ON LOWER(TRIM(d.doctor_Name)) =
                   LOWER(TRIM(b.booking_Doctors))
            WHERE b.booking_Status = 'PENDING'
            ORDER BY b.booking_Date, b.booking_Time
        ");
    }

    $patients = $conn->query("
        SELECT patient_ID, patient_Name
        FROM patients
        ORDER BY patient_Name
    ");
    $patientOptions = $patients->fetch_all(MYSQLI_ASSOC);

    // Used by the manager's manual Create Appointment form.
    $doctors = $conn->query("
        SELECT doctors_ID, doctor_Name
        FROM doctors
        WHERE doctor_Status = 'AVAILABLE'
        ORDER BY doctor_Name
    ");
    $doctorOptions = $doctors->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments</title>
    <link rel="stylesheet" href="../styles/styleAppointments.css?v=2">
    <link rel="stylesheet" href="../styles/styleNav.css?v=2">
    <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>
<header>
    <h1>
        <a href="../html/HomePage.html">
            <img src="../img/neurodivergence.png" alt="Login Page" width="250">
        </a>
        <nav id="navBar"></nav>
    </h1>
</header>

<main class="appointments-main">
    <h2>APPOINTMENTS</h2>

    <?php if ($error !== ""): ?>
        <p role="alert"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if (isset($_GET["checked_in"])): ?>
        <p role="status">You have checked in.</p>
    <?php endif; ?>

    <?php if (isset($_GET["booking_updated"])): ?>
        <div class="appointment-notice" role="status" aria-live="polite">
            <span>
                <strong>Booking decision saved</strong>
                <span>Your decision has been recorded.</span>
            </span>
            <button
                class="appointment-notice-close"
                type="button"
                aria-label="Dismiss notification"
            >&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["created"])): ?>
        <div class="appointment-notice" role="status" aria-live="polite">
            <span>
                <strong>Appointment created</strong>
                <span>The appointment has been saved.</span>
            </span>
            <button
                class="appointment-notice-close"
                type="button"
                aria-label="Dismiss notification"
            >&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($role === "doctor"): ?>
        <p>
            <a href="appointments.php?view=mine">My appointments</a> |
            <a href="appointments.php?view=all">All doctors appointments</a>
        </p>
    <?php endif; ?>

    <section>
        <h3><?= $role === "patient" ? "My Appointments" : "Appointment List" ?></h3>

        <?php if ($appointments->num_rows > 0): ?>
            <div class="appointments-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <?php if ($role !== "patient"): ?>
                                <th>Patient</th>
                            <?php endif; ?>
                            <th>Doctor</th>
                            <th>Room</th>
                            <th>Medium</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <?php if ($role === "patient"): ?>
                                <th>Check-in code</th>
                                <th>Notes preview</th>
                                <th>Check in</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $appointments->fetch_assoc()): ?>
                            <tr>
                                <td><?= h($row["appointment_Date"]) ?></td>
                                <td><?= h($row["appointment_Time"]) ?></td>

                                <?php if ($role !== "patient"): ?>
                                    <td><?= h($row["patient_Name"] ?? "Unknown") ?></td>
                                <?php endif; ?>

                                <td><?= h($row["doctor_Name"] ?? "Not assigned") ?></td>
                                <td><?= h($row["appointment_Room"] ?? "Digital") ?></td>
                                <td><?= h($row["appointment_Medium"] ?? "") ?></td>
                                <td><?= h($row["appointment_Status"] ?? "") ?></td>
                                <td><?= h($row["appointment_Attendance"] ?? "Not Checked In") ?></td>

                                <?php if ($role === "patient"): ?>
                                    <td class="appointment-code">
                                        <?= h($row["appointment_Code"] ?? "No code") ?>
                                    </td>
                                    <td>
                                        <?= nl2br(h($row["notes_preview"] ?? "No notes yet.")) ?>
                                    </td>
                                    <td>
                                        <?php if (
                                            $row["appointment_Status"] === "Approved" &&
                                            $row["appointment_Date"] === date("Y-m-d") &&
                                            $row["appointment_Attendance"] !== "Checked In"
                                        ): ?>
                                            <form method="post" action="appointments.php">
                                                <input type="hidden" name="action" value="check_in">
                                                <input
                                                    type="hidden"
                                                    name="appointment_ID"
                                                    value="<?= (int)$row["appointment_ID"] ?>"
                                                >
                                                <label>
                                                    Enter code:
                                                    <input
                                                        type="text"
                                                        name="appointment_Code"
                                                        required
                                                    >
                                                </label>
                                                <button type="submit">Check in</button>
                                            </form>
                                        <?php else: ?>
                                            &mdash;
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No appointments found.</p>
        <?php endif; ?>
    </section>

    <?php if (in_array($role, ["doctor", "admin"], true)): ?>
        <section>
            <h3>Pending Bookings</h3>

            <?php if ($pendingBookings && $pendingBookings->num_rows > 0): ?>
                <?php while ($booking = $pendingBookings->fetch_assoc()): ?>
                    <article class="booking-card">
                        <div class="booking-details">
                            <p>
                                Patient: <?= h($booking["patient_Name"] ?? "Unknown") ?><br>
                                Date: <?= h($booking["booking_Date"]) ?><br>
                                Time: <?= h($booking["booking_Time"]) ?><br>
                                Requested doctor:
                                <?= h($booking["booking_Doctors"] ?? "Not specified") ?><br>
                                Medium: <?= h($booking["booking_Medium"] ?? "") ?><br>
                                Description: <?= h($booking["booking_Desc"] ?? "None") ?>
                            </p>
                        </div>

                        <form class="booking-decision-form" method="post" action="appointments.php">
                            <input type="hidden" name="action" value="booking_decision">
                            <input
                                type="hidden"
                                name="booking_ID"
                                value="<?= (int)$booking["booking_ID"] ?>"
                            >

                            <label>
                                <span>Room <small>(not needed for Digital)</small></span>
                                <select name="room">
                                    <option value="">Digital / no room</option>
                                    <?php foreach (["1", "2", "3", "4", "5", "6"] as $room): ?>
                                        <option value="<?= $room ?>"><?= $room ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Requested doctor</span>
                                <input
                                    type="text"
                                    value="<?= h($booking["booking_Doctors"] ?? "Not specified") ?>"
                                    readonly
                                >
                            </label>

                            <div class="booking-decision-actions">
                                <button type="submit" name="decision" value="APPROVED">
                                    Approve and create appointment
                                </button>

                                <button type="submit" name="decision" value="NOT APPROVED">
                                    Decline booking
                                </button>
                            </div>
                        </form>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No pending bookings.</p>
            <?php endif; ?>
        </section>

        <section class="create-appointment-section">
            <h3>Create an Appointment</h3>

            <form class="appointment-create-form" method="post" action="appointments.php">
                <input type="hidden" name="action" value="create_appointment">

                <label>
                    Patient
                    <select name="patient_ID" required>
                        <option value="">Choose a patient</option>
                        <?php foreach ($patientOptions as $patient): ?>
                            <option value="<?= (int)$patient["patient_ID"] ?>">
                                <?= h($patient["patient_Name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($role === "admin"): ?>
                    <label>
                        Doctor
                        <select name="doctors_ID" required>
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctorOptions as $doctor): ?>
                                <option value="<?= (int)$doctor["doctors_ID"] ?>">
                                    <?= h($doctor["doctor_Name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <label>
                    Date
                    <input type="date" name="date" required>
                </label>

                <label>
                    Time
                    <input type="time" name="time" required>
                </label>

                <label>
                    Medium
                    <select name="medium" required>
                        <option value="">Choose a medium</option>
                        <option value="Digital">Digital</option>
                        <option value="One on One">One on One</option>
                        <option value="Non-vocal">Non-vocal</option>
                    </select>
                </label>

                <label>
                    Room <span>(not needed for Digital)</span>
                    <select name="room">
                        <option value="">Digital / no room</option>
                        <?php foreach (["1", "2", "3", "4", "5", "6"] as $room): ?>
                            <option value="<?= $room ?>"><?= $room ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit">Create appointment</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($role === "admin"): ?>
        <p><a href="management.php?section=appointments">Manage appointments</a></p>
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

<script>
document.querySelectorAll(".appointment-notice-close").forEach((button) => {
    button.addEventListener("click", () => {
        button.closest(".appointment-notice")?.remove();
    });
});
</script>

<script src="../javascript/theme.js" defer></script>
</body>
</html>