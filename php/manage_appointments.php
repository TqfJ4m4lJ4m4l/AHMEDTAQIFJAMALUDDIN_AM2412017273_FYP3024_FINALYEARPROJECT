<?php
$patientID = filter_input(INPUT_GET, "patient_ID", FILTER_VALIDATE_INT) ?: "";
$doctorID = filter_input(INPUT_GET, "doctors_ID", FILTER_VALIDATE_INT) ?: "";

$patients = $conn->query("
    SELECT patient_ID, patient_Name
    FROM patients
    ORDER BY patient_Name
");
$doctors = $conn->query("
    SELECT doctors_ID, doctor_Name
    FROM doctors
    ORDER BY doctor_Name
");

$sql = "
    SELECT
        a.appointment_ID,
        a.patient_ID,
        a.doctors_ID,
        a.auth_ID,
        a.booking_ID,
        a.appointment_Date,
        a.appointment_Time,
        a.appointment_Room,
        a.appointment_Status,
        a.appointment_Code,
        p.patient_Name,
        d.doctor_Name
    FROM appointments a
    LEFT JOIN patients p ON p.patient_ID = a.patient_ID
    LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($patientID !== "") {
    $sql .= " AND a.patient_ID = ?";
    $params[] = $patientID;
    $types .= "i";
}

if ($doctorID !== "") {
    $sql .= " AND a.doctors_ID = ?";
    $params[] = $doctorID;
    $types .= "i";
}

$sql .= " ORDER BY a.appointment_ID DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$appointments = $stmt->get_result();
?>

<section class="management-section">
    <h2>Manage Appointments</h2>
    <p>View and manage appointments assigned to patients and doctors.</p>

    <form method="get" action="management.php">
        <input type="hidden" name="section" value="appointments">

        <label for="appointment-patient">Appointments for:</label>
        <select id="appointment-patient" name="patient_ID">
            <option value="">All Patients</option>
            <?php while ($patient = $patients->fetch_assoc()): ?>
                <option
                    value="<?= (int)$patient["patient_ID"] ?>"
                    <?= (string)$patientID === (string)$patient["patient_ID"] ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($patient["patient_Name"], ENT_QUOTES, "UTF-8") ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="appointment-doctor">Assigned to:</label>
        <select id="appointment-doctor" name="doctors_ID">
            <option value="">All Doctors</option>
            <?php while ($doctor = $doctors->fetch_assoc()): ?>
                <option
                    value="<?= (int)$doctor["doctors_ID"] ?>"
                    <?= (string)$doctorID === (string)$doctor["doctors_ID"] ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($doctor["doctor_Name"], ENT_QUOTES, "UTF-8") ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Search</button>
        <a href="management.php?section=appointments">Reset</a>
    </form>

    <table class="management-table management-table--wide">
        <thead>
            <tr>
                <th>Appointment ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Authentication ID</th>
                <th>Booking ID</th>
                <th>Date</th>
                <th>Time</th>
                <th>Room</th>
                <th>Status</th>
                <th>Appointment Code</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($appointments->num_rows > 0): ?>
                <?php while ($row = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row["appointment_ID"] ?></td>
                        <td><?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["doctor_Name"] ?? "Unassigned", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars((string)($row["auth_ID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars((string)($row["booking_ID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["appointment_Date"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["appointment_Time"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["appointment_Room"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["appointment_Status"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["appointment_Code"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <a href="edit_appointments.php?id=<?= (int)$row["appointment_ID"] ?>">
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="11">No appointments found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>