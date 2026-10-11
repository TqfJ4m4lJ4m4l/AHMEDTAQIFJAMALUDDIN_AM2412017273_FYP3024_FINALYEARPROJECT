<?php
$patients = $conn->query("
    SELECT patient_ID, users_ID, patient_Name,
           patient_BirthDate, patient_Gender
    FROM patients
    ORDER BY patient_Name
");
?>

<h2>MANAGE PATIENTS</h2>

<table class="management-table management-table--fit">
    <thead>
        <tr>
            <th>Patient ID</th>
            <th>Name</th>
            <th>Birth Date</th>
            <th>Gender</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($patients && $patients->num_rows > 0): ?>
            <?php while ($row = $patients->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row["patient_ID"] ?></td>
                    <td><?= htmlspecialchars($row["patient_Name"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["patient_BirthDate"] ?? "") ?></td>
                    <td><?= htmlspecialchars($row["patient_Gender"] ?? "") ?></td>
                    <td>
                        <a
                            href="view.php?type=patients&amp;id=<?= (int)$row["patient_ID"] ?>"
                            style="color: #222; text-decoration: none;"
                        >VIEW</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No patients found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>