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
        p.prescription_ID,
        p.patient_ID,
        p.doctors_ID,
        p.appointment_ID,
        p.prescription_MedicationName,
        p.prescription_Type,
        p.prescription_Regulations,
        p.prescription_Auth,
        pt.patient_Name,
        d.doctor_Name
    FROM prescription p
    LEFT JOIN patients pt ON pt.patient_ID = p.patient_ID
    LEFT JOIN doctors d ON d.doctors_ID = p.doctors_ID
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($patientID !== "") {
    $sql .= " AND p.patient_ID = ?";
    $params[] = $patientID;
    $types .= "i";
}

if ($doctorID !== "") {
    $sql .= " AND p.doctors_ID = ?";
    $params[] = $doctorID;
    $types .= "i";
}

$sql .= " ORDER BY p.prescription_ID DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$prescriptions = $stmt->get_result();
?>

<section class="management-section">
    <h2>Manage Prescriptions</h2>
    <p>View and manage prescriptions made for patients by doctors.</p>

    <form method="get" action="management.php">
        <input type="hidden" name="section" value="prescriptions">

        <label for="prescription-patient">Patient:</label>
        <select id="prescription-patient" name="patient_ID">
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

        <label for="prescription-doctor">Doctor:</label>
        <select id="prescription-doctor" name="doctors_ID">
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
        <a href="management.php?section=prescriptions">Reset</a>
    </form>

   <table class="management-table management-table--wide management-table--fill">
        <thead>
            <tr>
                <th>Prescription ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Appointment ID</th>
                <th>Medication</th>
                <th>Type</th>
                <th>Regulations</th>
                <th>Authorization</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($prescriptions->num_rows > 0): ?>
                <?php while ($row = $prescriptions->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row["prescription_ID"] ?></td>
                        <td><?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["doctor_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars((string)($row["appointment_ID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["prescription_MedicationName"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["prescription_Type"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["prescription_Regulations"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["prescription_Auth"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <a href="edit_prescriptions.php?id=<?= (int)$row["prescription_ID"] ?>">
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9">No prescriptions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>