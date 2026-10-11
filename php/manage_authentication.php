<?php
$sql = "SELECT
            a.auth_ID,
            a.admin_ID,
            a.auth_Status,
            a.auth_Date,
            a.auth_Type,
            a.auth_TargetTable,
            a.auth_TargetID,
            ad.admin_Name
        FROM authentication a
        LEFT JOIN admins ad ON a.admin_ID=ad.admin_ID
        ORDER BY a.auth_ID DESC";

$authentication = $conn->query($sql);
?>

<div class="management-section">
<h2>Manage Authentication</h2>

<table class="management-table management-table--fit">
<thead>
<tr>
<th>Authentication ID:</th>
<th>Administrator:</th>
<th>Status:</th>
<th>Date:</th>
<th>Type:</th>
<th>Type:</th>
<th>At Table:</th>
<th>Table ID:</th>
</tr>
</thead>
<tbody>
<?php while ($row=$authentication->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($row["auth_ID"]); ?></td>
<td><?php echo htmlspecialchars($row["admin_Name"]??"Unknown"); ?></td>
<td><?php echo htmlspecialchars($row["auth_Status"]); ?></td>
<td><?php echo htmlspecialchars($row["auth_Date"]); ?></td>
<td><?php echo htmlspecialchars($row["auth_Type"]); ?></td>
<td><?php echo htmlspecialchars($row["auth_TargetTable"]); ?></td>
<td><?php echo htmlspecialchars($row["auth_TargetID"]); ?></td>
<td><a href="edit.php?type=authentication&id=<?php echo $row["auth_ID"]; ?>">MANAGE</a></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>