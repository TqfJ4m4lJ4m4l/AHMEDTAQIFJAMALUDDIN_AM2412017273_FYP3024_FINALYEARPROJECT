<?php
$doctor = trim($_GET["doctor"] ?? "");

$sql = "
    SELECT
        b.booking_ID,
        b.patient_ID,
        b.booking_Doctors,
        b.booking_Date,
        b.booking_Time,
        b.booking_Medium,
        b.booking_Desc
    FROM booking b
";

$params = [];
$types = "";

if ($doctor !== "") {
    $sql .= " WHERE b.booking_Doctors = ?";
    $params[] = $doctor;
    $types = "s";
}

$sql .= " ORDER BY b.booking_ID DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$bookings = $stmt->get_result();
$doctors = $conn->query("
    SELECT DISTINCT booking_Doctors
    FROM booking
    WHERE booking_Doctors IS NOT NULL
    ORDER BY booking_Doctors
");
?>

<section class="management-section">
    <h2>Manage Bookings</h2>

    <form method="get" action="management.php">
        <input type="hidden" name="section" value="bookings">

        <label for="booking-doctor">Bookings for:</label>
        <select id="booking-doctor" name="doctor">
            <option value="">All Doctors</option>
            <?php while ($row = $doctors->fetch_assoc()): ?>
                <option
                    value="<?= htmlspecialchars($row["booking_Doctors"], ENT_QUOTES, "UTF-8") ?>"
                    <?= $doctor === $row["booking_Doctors"] ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($row["booking_Doctors"], ENT_QUOTES, "UTF-8") ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Search</button>
        <a href="management.php?section=bookings">Reset</a>
    </form>

    <table class="management-table management-table--wide management-table--fill">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Patient ID</th>
                <th>Doctor</th>
                <th>Date</th>
                <th>Time</th>
                <th>Medium</th>
                <th>Description</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($bookings->num_rows > 0): ?>
                <?php while ($row = $bookings->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$row["booking_ID"] ?></td>
                        <td><?= htmlspecialchars((string)$row["patient_ID"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["booking_Doctors"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["booking_Date"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["booking_Time"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["booking_Medium"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($row["booking_Desc"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <a href="edit_bookings.php?type=booking&amp;id=<?= (int)$row["booking_ID"] ?>">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">No bookings found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>