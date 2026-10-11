<?php
$reports = $conn->query("
    SELECT
        r.report_ID,
        r.users_ID,
        r.role_ID,
        r.report_Type,
        r.report_Reason,
        r.report_Desc,
        u.users_Email
    FROM reports r
    LEFT JOIN users u ON u.users_ID = r.users_ID
    ORDER BY r.report_ID DESC
");
?>

<section class="management-section">
    <h2>Manage Reports</h2>

   <table class="management-table management-table--wide management-table--fill">
        <thead>
            <tr>
                <th>Report ID</th>
                <th>User</th>
                <th>Role ID</th>
                <th>Type</th>
                <th>Reason</th>
                <th>Description</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports && $reports->num_rows > 0): ?>
                <?php while ($row = $reports->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row["report_ID"] ?></td>
                        <td><?= htmlspecialchars($row["users_Email"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars((string)($row["role_ID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["report_Type"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["report_Reason"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["report_Desc"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <a href="view.php?type=report&amp;id=<?= (int)$row["report_ID"] ?>">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No reports found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>