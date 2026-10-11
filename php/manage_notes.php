<?php
$notes = $conn->query("
    SELECT
        n.notes_ID,
        n.appointment_ID,
        n.notes_Title,
        n.notes_Desc,
        p.patient_Name,
        d.doctor_Name
    FROM notes n
    LEFT JOIN appointments a ON a.appointment_ID = n.appointment_ID
    LEFT JOIN patients p ON p.patient_ID = a.patient_ID
    LEFT JOIN doctors d ON d.doctors_ID = a.doctors_ID
    ORDER BY n.notes_ID DESC
");
?>

<section class="management-section">
    <h2>Manage Notes</h2>

    <table class="management-table management-table--wide management-table--fill">
        <thead>
            <tr>
                <th>Note ID</th>
                <th>Appointment ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Title</th>
                <th>Note</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($notes && $notes->num_rows > 0): ?>
                <?php while ($row = $notes->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row["notes_ID"] ?></td>
                        <td><?= htmlspecialchars((string)($row["appointment_ID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["patient_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["doctor_Name"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["notes_Title"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["notes_Desc"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <a href="edit_notes.php?id=<?= (int)$row["notes_ID"] ?>">Manage</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No notes found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>