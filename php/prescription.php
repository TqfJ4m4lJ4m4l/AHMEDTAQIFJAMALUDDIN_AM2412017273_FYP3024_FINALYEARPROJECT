<?php
require_once "session_check.php";
require_once "connect.php";

$userID = (int)($_SESSION["user_ID"] ?? 0);
$role = strtolower(trim($_SESSION["role_Type"] ?? ""));

if (!in_array($role, ["patient", "doctor"], true)) {
    http_response_code(403);
    exit("Access denied.");
}

function prescriptionEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function prescriptionFetchAll(mysqli_result $result): array
{
    return $result->fetch_all(MYSQLI_ASSOC);
}

$error = "";
$patient = null;
$patientID = 0;
$prescriptions = [];
$doctorPatients = [];
$assignedAppointments = [];
$selectedPatientID = 0;

/*
 * Load the signed-in patient's own profile.
 * A patient cannot choose another patient's ID through the URL.
 */
if ($role === "patient") {
    $stmt = $conn->prepare("
        SELECT patient_ID,
               users_ID,
               patient_Name,
               patient_BirthDate,
               patient_Diagnosis
        FROM patients
        WHERE users_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    if (!$patient) {
        http_response_code(403);
        exit("Patient profile not found.");
    }

    $patientID = (int)$patient["patient_ID"];
}

/*
 * Load the signed-in doctor's profile and the patients connected
 * to the doctor's non-cancelled appointments.
 */
if ($role === "doctor") {
    $stmt = $conn->prepare("
        SELECT doctors_ID
        FROM doctors
        WHERE users_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        http_response_code(403);
        exit("Doctor profile not found.");
    }

    $doctorID = (int)$doctor["doctors_ID"];

    $stmt = $conn->prepare("
        SELECT DISTINCT
               p.patient_ID,
               p.users_ID,
               p.patient_Name,
               p.patient_BirthDate,
               p.patient_Diagnosis
        FROM patients p
        INNER JOIN appointments a
            ON a.patient_ID = p.patient_ID
        WHERE a.doctors_ID = ?
          AND a.appointment_Status <> 'Cancelled'
        ORDER BY p.patient_Name
    ");
    $stmt->bind_param("i", $doctorID);
    $stmt->execute();
    $doctorPatients = prescriptionFetchAll($stmt->get_result());

    /*
     * Accept a selected patient only if they appear in the doctor's
     * assigned-patient list. Otherwise choose the first available patient.
     */
    $requestedPatientID = filter_input(
        INPUT_GET,
        "patient_ID",
        FILTER_VALIDATE_INT
    );

    foreach ($doctorPatients as $doctorPatient) {
        if ((int)$doctorPatient["patient_ID"] === (int)$requestedPatientID) {
            $selectedPatientID = (int)$doctorPatient["patient_ID"];
            break;
        }
    }

    if ($selectedPatientID === 0 && !empty($doctorPatients)) {
        $selectedPatientID = (int)$doctorPatients[0]["patient_ID"];
    }

    if ($selectedPatientID > 0) {
        foreach ($doctorPatients as $doctorPatient) {
            if ((int)$doctorPatient["patient_ID"] === $selectedPatientID) {
                $patient = $doctorPatient;
                $patientID = $selectedPatientID;
                break;
            }
        }
    }

    /*
     * Create a prescription from an appointment assigned to this doctor.
     * The patient ID is looked up from the appointment instead of trusting
     * a patient ID sent by the browser.
     */
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $appointmentID = filter_input(
            INPUT_POST,
            "appointment_ID",
            FILTER_VALIDATE_INT
        );

        $medication = trim($_POST["prescription_MedicationName"] ?? "");
        $type = trim($_POST["prescription_Type"] ?? "");
        $regulations = trim($_POST["prescription_Regulations"] ?? "");

        if (!$appointmentID || $medication === "" || $type === "") {
            $error = "Choose an appointment and enter the medication and type or dosage.";
        } elseif (
            mb_strlen($medication) > 255 ||
            mb_strlen($type) > 255 ||
            mb_strlen($regulations) > 255
        ) {
            $error = "Each prescription field must be 255 characters or fewer.";
        } else {
            $stmt = $conn->prepare("
                SELECT patient_ID
                FROM appointments
                WHERE appointment_ID = ?
                  AND doctors_ID = ?
                  AND appointment_Status <> 'Cancelled'
                LIMIT 1
            ");
            $stmt->bind_param("ii", $appointmentID, $doctorID);
            $stmt->execute();
            $appointment = $stmt->get_result()->fetch_assoc();

            if (!$appointment) {
                $error = "That appointment is not assigned to you or has been cancelled.";
            } else {
                $prescriptionPatientID = (int)$appointment["patient_ID"];
                $authorization = "Approved";

                $stmt = $conn->prepare("
                    INSERT INTO prescription (
                        patient_ID,
                        doctors_ID,
                        appointment_ID,
                        prescription_MedicationName,
                        prescription_Type,
                        prescription_Regulations,
                        prescription_Auth
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iiissss",
                    $prescriptionPatientID,
                    $doctorID,
                    $appointmentID,
                    $medication,
                    $type,
                    $regulations,
                    $authorization
                );
                $stmt->execute();

                /*
                 * Notify the patient if the notifications table is available.
                 */
                $notice = "A prescription was added for one of your appointments.";
                $noticeStmt = $conn->prepare("
                    INSERT INTO notifications (users_ID, notification_Message)
                    SELECT users_ID, ?
                    FROM patients
                    WHERE patient_ID = ?
                      AND users_ID IS NOT NULL
                ");

                if ($noticeStmt) {
                    $noticeStmt->bind_param("si", $notice, $prescriptionPatientID);
                    $noticeStmt->execute();
                }

                header(
                    "Location: prescription.php?prescribed=1&patient_ID=" .
                    $prescriptionPatientID
                );
                exit();
            }
        }
    }

    /*
     * The form only offers appointments for the patient currently selected.
     */
    if ($selectedPatientID > 0) {
        $stmt = $conn->prepare("
            SELECT
                a.appointment_ID,
                a.appointment_Date,
                a.appointment_Time,
                p.patient_Name
            FROM appointments a
            INNER JOIN patients p
                ON p.patient_ID = a.patient_ID
            WHERE a.doctors_ID = ?
              AND a.patient_ID = ?
              AND a.appointment_Status <> 'Cancelled'
            ORDER BY a.appointment_Date DESC, a.appointment_Time DESC
        ");
        $stmt->bind_param("ii", $doctorID, $selectedPatientID);
    } else {
        $stmt = $conn->prepare("
            SELECT
                a.appointment_ID,
                a.appointment_Date,
                a.appointment_Time,
                p.patient_Name
            FROM appointments a
            INNER JOIN patients p
                ON p.patient_ID = a.patient_ID
            WHERE a.doctors_ID = ?
              AND a.appointment_Status <> 'Cancelled'
            ORDER BY a.appointment_Date DESC, a.appointment_Time DESC
        ");
        $stmt->bind_param("i", $doctorID);
    }

    $stmt->execute();
    $assignedAppointments = prescriptionFetchAll($stmt->get_result());
}

/*
 * Load the prescription history for the signed-in patient or the
 * doctor-selected patient.
 */
if ($patientID > 0) {
    $stmt = $conn->prepare("
        SELECT
            rx.prescription_ID,
            rx.appointment_ID,
            rx.prescription_MedicationName,
            rx.prescription_Type,
            rx.prescription_Regulations,
            rx.prescription_Auth,
            a.appointment_Date,
            a.appointment_Time,
            d.doctor_Name
        FROM prescription rx
        LEFT JOIN appointments a
            ON a.appointment_ID = rx.appointment_ID
        LEFT JOIN doctors d
            ON d.doctors_ID = rx.doctors_ID
        WHERE rx.patient_ID = ?
        ORDER BY rx.prescription_ID DESC
    ");
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $prescriptions = prescriptionFetchAll($stmt->get_result());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions</title>
    <link rel="stylesheet" href="../styles/stylePres.css?v=3">
    <link rel="stylesheet" href="../styles/styleNav.css?v=3">  
    <link rel="stylesheet" href="../styles/styleTheme.css?v=2">
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

<main class="prescription-main">
    <h2 class="prescription-page-title">PRESCRIPTIONS</h2>

    <?php if (isset($_GET["prescribed"])): ?>
        <p class="prescription-notice" role="status">
            The prescription was saved and the patient was notified.
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="prescription-error" role="alert">
            <?= prescriptionEscape($error) ?>
        </p>
    <?php endif; ?>

    <!-- Prescription history appears first on the page. -->
    <section class="prescription-panel prescription-history">
        <div class="prescription-panel-heading">
            <div>
                <p class="prescription-eyebrow">Medication record</p>
                <h2>Prescription history</h2>
                <p class="prescription-panel-description">
                    Prescriptions are shown with the appointment they were written for.
                </p>
            </div>

            <?php if ($role === "doctor" && !empty($doctorPatients)): ?>
                <form class="patient-filter" method="get" action="prescription.php">
                    <label for="patient_ID">View patient</label>
                    <select id="patient_ID" name="patient_ID" onchange="this.form.submit()">
                        <?php foreach ($doctorPatients as $doctorPatient): ?>
                            <option
                                value="<?= (int)$doctorPatient["patient_ID"] ?>"
                                <?= (int)$doctorPatient["patient_ID"] === $selectedPatientID ? "selected" : "" ?>
                            >
                                <?= prescriptionEscape($doctorPatient["patient_Name"]) ?>
                                (ID <?= (int)$doctorPatient["users_ID"] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript>
                        <button type="submit">Show patient</button>
                    </noscript>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($role === "doctor" && !$patient): ?>
            <p class="prescription-empty">
                No patients with an active appointment are assigned to you yet.
            </p>
        <?php elseif (!empty($prescriptions)): ?>
            <div class="prescription-table-scroll">
                <table class="prescription-table">
                    <thead>
                        <tr>
                            <th scope="col">Prescription</th>
                            <th scope="col">Appointment</th>
                            <th scope="col">Date and time</th>
                            <th scope="col">Medication</th>
                            <th scope="col">Type / dosage</th>
                            <th scope="col">Doctor</th>
                            <th scope="col">Authorization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $row): ?>
                            <tr>
                                <td>#<?= (int)$row["prescription_ID"] ?></td>
                                <td>
                                    <?= $row["appointment_ID"] !== null
                                        ? "#" . (int)$row["appointment_ID"]
                                        : "Not linked" ?>
                                </td>
                                <td>
                                    <?php if (!empty($row["appointment_Date"])): ?>
                                        <?= prescriptionEscape($row["appointment_Date"]) ?>
                                        <?php if (!empty($row["appointment_Time"])): ?>
                                            <span class="table-secondary">
                                                <?= prescriptionEscape($row["appointment_Time"]) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Date unavailable
                                    <?php endif; ?>
                                </td>
                                <td><?= prescriptionEscape($row["prescription_MedicationName"]) ?></td>
                                <td><?= prescriptionEscape($row["prescription_Type"]) ?></td>
                                <td><?= prescriptionEscape($row["doctor_Name"] ?? "Not listed") ?></td>
                                <td>
                                    <span class="authorization-badge">
                                        <?= prescriptionEscape($row["prescription_Auth"] ?? "Not listed") ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="prescription-empty">
                No prescriptions are recorded for this patient.
            </p>
        <?php endif; ?>
    </section>

    <!-- Patient details and medication details sit together below the history. -->
    <?php if ($patient): ?>
        <div class="prescription-detail-grid">
            <section class="prescription-panel patient-card">
                <p class="prescription-eyebrow">Selected record</p>
                <h2>Patient information</h2>

                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Name</dt>
                        <dd><?= prescriptionEscape($patient["patient_Name"]) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>User ID</dt>
                        <dd><?= (int)$patient["users_ID"] ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Birth date</dt>
                        <dd><?= prescriptionEscape($patient["patient_BirthDate"] ?? "Not provided") ?></dd>
                    </div>
                    <div class="detail-row detail-row-tall">
                        <dt>Diagnosis</dt>
                        <dd><?= prescriptionEscape($patient["patient_Diagnosis"] ?? "Not provided") ?></dd>
                    </div>
                </dl>
            </section>

            <section class="prescription-panel medication-card">
                <p class="prescription-eyebrow">Current record</p>
                <h2>Prescription medication</h2>

                <?php if (!empty($prescriptions)): ?>
                    <div class="medication-detail-list">
                        <?php foreach ($prescriptions as $row): ?>
                            <article class="medication-detail">
                                <h3><?= prescriptionEscape($row["prescription_MedicationName"]) ?></h3>

                                <dl class="medication-meta">
                                    <div>
                                        <dt>Type / dosage</dt>
                                        <dd><?= prescriptionEscape($row["prescription_Type"]) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Doctor</dt>
                                        <dd><?= prescriptionEscape($row["doctor_Name"] ?? "Not listed") ?></dd>
                                    </div>
                                    <div>
                                        <dt>Appointment</dt>
                                        <dd>
                                            <?php if ($row["appointment_ID"] !== null): ?>
                                                #<?= (int)$row["appointment_ID"] ?>
                                            <?php else: ?>
                                                Not linked
                                            <?php endif; ?>
                                            <?php if (!empty($row["appointment_Date"])): ?>
                                                &middot; <?= prescriptionEscape($row["appointment_Date"]) ?>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Authorization</dt>
                                        <dd><?= prescriptionEscape($row["prescription_Auth"] ?? "Not listed") ?></dd>
                                    </div>
                                </dl>

                                <div class="medication-instructions">
                                    <h4>Instructions and precautions</h4>
                                    <p>
                                        <?= prescriptionEscape(
                                            $row["prescription_Regulations"] !== ""
                                                ? $row["prescription_Regulations"]
                                                : "No instructions listed."
                                        ) ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="prescription-empty prescription-empty-light">
                        No prescription details are available yet.
                    </p>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

    <!-- Only doctors can create prescriptions. This section sits below the details. -->
    <?php if ($role === "doctor"): ?>
        <section class="prescription-panel prescription-create">
            <div class="prescription-panel-heading">
                <div>
                    <p class="prescription-eyebrow">Doctor tools</p>
                    <h2>Make a prescription</h2>
                    <p class="prescription-panel-description">
                        Choose one of your appointments and enter the medication instructions.
                    </p>
                </div>
            </div>

            <?php if (empty($assignedAppointments)): ?>
                <p class="prescription-empty">
                    There are no eligible appointments available for a prescription.
                </p>
            <?php else: ?>
                <form class="prescription-form" method="post" action="prescription.php">
                    <?php if ($selectedPatientID > 0): ?>
                        <input
                            type="hidden"
                            name="return_patient_ID"
                            value="<?= $selectedPatientID ?>"
                        >
                    <?php endif; ?>

                    <div class="prescription-form-grid">
                        <div class="form-field form-field-wide">
                            <label for="appointment_ID">Patient appointment</label>
                            <select id="appointment_ID" name="appointment_ID" required>
                                <option value="">Choose an appointment</option>
                                <?php foreach ($assignedAppointments as $appointment): ?>
                                    <option value="<?= (int)$appointment["appointment_ID"] ?>">
                                        <?= prescriptionEscape($appointment["appointment_Date"]) ?>
                                        <?= prescriptionEscape($appointment["appointment_Time"]) ?>
                                        - <?= prescriptionEscape($appointment["patient_Name"]) ?>
                                        (appointment #<?= (int)$appointment["appointment_ID"] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="prescription_MedicationName">Medication</label>
                            <input
                                id="prescription_MedicationName"
                                name="prescription_MedicationName"
                                type="text"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="prescription_Type">Type / dosage</label>
                            <input
                                id="prescription_Type"
                                name="prescription_Type"
                                type="text"
                                maxlength="255"
                                placeholder="For example, tablet and dosage"
                                required
                            >
                        </div>

                        <div class="form-field form-field-wide">
                            <label for="prescription_Regulations">
                                Instructions and precautions
                            </label>
                            <textarea
                                id="prescription_Regulations"
                                name="prescription_Regulations"
                                maxlength="255"
                                rows="4"
                                placeholder="Add the instructions the patient should follow"
                            ></textarea>
                        </div>
                    </div>

                    <button class="prescription-submit" type="submit">
                        Save prescription
                    </button>
                </form>
            <?php endif; ?>
        </section>
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