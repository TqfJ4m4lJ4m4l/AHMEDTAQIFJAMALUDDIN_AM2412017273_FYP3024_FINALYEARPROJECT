<?php
// This passes back any data within the table and shoots it back to therapists
$sql = "SELECT
            d.doctors_ID,
            d.users_ID,
            d.role_ID,
            d.doctor_Name,
            d.doctor_BirthDate,
            d.doctor_Gender,
            d.doctor_Status,
            u.users_Email
        FROM doctors d
        LEFT JOIN users u ON d.users_ID=u.users_ID
        ORDER BY d.doctors_ID DESC";

$therapists = $conn->query($sql);
?>

<div class="management-section">
<h2>Manage Therapists</h2>

<table class="management-table management-table--fit">
<thead>
<tr>
<th>Doctor ID</th>
<th>Name</th>
<th>Email</th>
<th>User ID</th>
<th>Role ID</th>
<th>Birth Date</th>
<th>Gender</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if ($therapists->num_rows>0): ?>
<?php while ($row=$therapists->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($row["doctors_ID"]); ?></td>
<td><?php echo htmlspecialchars($row["doctor_Name"]); ?></td>
<td><?php echo htmlspecialchars($row["users_Email"]??"Unknown"); ?></td>
<td><?php echo htmlspecialchars($row["users_ID"]); ?></td>
<td><?php echo htmlspecialchars($row["role_ID"]); ?></td>
<td><?php echo htmlspecialchars($row["doctor_BirthDate"]); ?></td>
<td><?php echo htmlspecialchars($row["doctor_Gender"]); ?></td>
<td><?php echo htmlspecialchars($row["doctor_Status"]); ?></td>
<td><a href="edit_therapists.php?id=<?= (int)$row["doctors_ID"] ?>">MANAGE</a></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="8">No therapists found.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>