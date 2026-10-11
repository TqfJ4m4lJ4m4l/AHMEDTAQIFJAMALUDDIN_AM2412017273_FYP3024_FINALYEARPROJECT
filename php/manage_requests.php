<?php
$requests = $conn->query("
    SELECT
        r.request_ID,
        r.users_ID,
        r.role_ID,
        r.request_Type,
        r.request_Reason,
        r.request_Desc,
        r.request_Status,
        r.request_TargetTable,
        r.request_TargetID,
        r.request_Payload,
        u.users_Email
    FROM requests r
    LEFT JOIN users u ON u.users_ID = r.users_ID
    ORDER BY r.request_ID DESC
");
?>

<section class="management-section">
    <h2>Manage Requests</h2>

    <table class="management-table management-table--wide management-table--fill">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>User</th>
                <th>Role ID</th>
                <th>Type</th>
                <th>Reason</th>
                <th>Description</th>
                <th>Status</th>
                <th>Target Table</th>
                <th>Target ID</th>
                <th>Description:</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($requests && $requests->num_rows > 0): ?>
                <?php while ($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row["request_ID"] ?></td>
                        <td><?= htmlspecialchars($row["users_Email"] ?? "Unknown", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars((string)($row["role_ID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["request_Type"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["request_Reason"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["request_Desc"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["request_Status"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["request_TargetTable"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars((string)($row["request_TargetID"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["request_Payload"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <?php if ($row["request_Type"] === "General"): ?>
                                <a href="view.php?type=request&amp;id=<?= (int)$row["request_ID"] ?>">View</a>
                            <?php else: ?>
                                <a href="edit_requests.php?id=<?= (int)$row["request_ID"] ?>">Review</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="11">No requests found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>