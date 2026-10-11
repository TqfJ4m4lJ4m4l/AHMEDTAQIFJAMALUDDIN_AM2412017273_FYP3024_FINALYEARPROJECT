<?php
require_once "Admin_check.php";
require_once "connect.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid appointment ID.");
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$stmt = $conn->prepare(
    "SELECT * FROM appointments WHERE appointment_ID = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    http_response_code(404);
    exit("Appointment not found.");
}

$doctors = $conn->query(
    "SELECT doctors_ID, doctor_Name FROM doctors ORDER BY doctor_Name"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $doctorID = filter_input(INPUT_POST, "doctors_ID", FILTER_VALIDATE_INT);
    $date = $_POST["date"] ?? "";
    $time = $_POST["time"] ?? "";
    $room = $_POST["room"] ?? "";
    $status = $_POST["status"] ?? "";
    $decision = $_POST["decision"] ?? "";

    if (
        !$doctorID || $date === "" || $time === "" ||
        !in_array($room, ["1", "2", "3", "4", "5", "6"], true) ||
        !in_array($status, ["Cancelled", "Delayed", "Approved"], true) ||
        !in_array($decision, ["APPROVED", "NOT APPROVED"], true)
    ) {
        exit("Invalid form values.");
    }

    $adminStmt = $conn->prepare(
        "SELECT admin_ID FROM admins WHERE users_ID = ?"
    );
    $adminStmt->bind_param("i", $_SESSION["user_ID"]);
    $adminStmt->execute();
    $admin = $adminStmt->get_result()->fetch_assoc();

    if (!$admin) {
        exit("Administrator record not found.");
    }

    $conn->begin_transaction();

    try {
        $authType = "MODIFY DATA";
        $targetTable = "appointments";

        $authStmt = $conn->prepare("
            INSERT INTO authentication
                (admin_ID, auth_Status, auth_Date, auth_Type,
                 auth_TargetTable, auth_TargetID)
            VALUES (?, ?, CURDATE(), ?, ?, ?)
        ");
        $authStmt->bind_param(
            "isssi",
            $admin["admin_ID"],
            $decision,
            $authType,
            $targetTable,
            $id
        );
        $authStmt->execute();
        $authID = $conn->insert_id;

        if ($decision === "APPROVED") {
            $updateStmt = $conn->prepare("
                UPDATE appointments
                SET doctors_ID = ?, appointment_Date = ?, appointment_Time = ?,
                    appointment_Room = ?, appointment_Status = ?, auth_ID = ?
                WHERE appointment_ID = ?
            ");
            $updateStmt->bind_param(
                "issssii",
                $doctorID,
                $date,
                $time,
                $room,
                $status,
                $authID,
                $id
            );
            $updateStmt->execute();
        }

        $conn->commit();
        header("Location: management.php?section=appointments");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        exit("Could not update appointment: " . e($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Appointment</title>
    <link rel="stylesheet" href="../styles/styleAdmin.css?v=4">
    <link rel="stylesheet" href="../styles/styleEdit.css">
        <link rel="stylesheet" href="../styles/styleNav.css?v=2">
        <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<body>
<header>
<h1>
<a href="../html/HomePage.html">
<img src="../img/neurodivergence.png" alt="Login Page" width="250">
</a>
  <nav>
    <div class="us_button">
      <button class="AboutUs" style="margin: auto;">
        <a href="appointments.php" style="font-size: larger;">APPOINTMENTS</a>
      </button>
    </div>
    <div class="neuro_button">
      <button class="Neurodivergent">
        <a href="schedule.php" style="font-size: larger;">REQUESTS</a>
      </button>
    </div>
    <div class="appointment_button">
      <button class="appointments">
        <a href="management.php" style="font-size: larger;">MANAGEMENT</a>
    </button>
    </div>
       <div class="dropdown">
         <button class="dropdown-btn">
           <a href="../html/LoginPage.html"><img src="../img/login.png" alt="Login Page" width="70" height="70"></a>
         </button>
           <div class="dropdown-content">
             <a href="updates.php">Inbox</a>
             <a href="settings.php">Settings</a>
             <a href="logout.php">Log Out</a>
           </div>
    </div>
  </nav>
</h1>
</header>
<main>
    <h2>Edit Appointment #<?= (int)$appointment["appointment_ID"] ?></h2>

    <form method="post">
        <label for="doctors_ID">Doctor</label>
        <select name="doctors_ID" id="doctors_ID" required>
            <?php while ($doctor = $doctors->fetch_assoc()): ?>
                <option value="<?= (int)$doctor["doctors_ID"] ?>"
                    <?= (int)$appointment["doctors_ID"] === (int)$doctor["doctors_ID"]
                        ? "selected" : "" ?>>
                    <?= e($doctor["doctor_Name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="date">Date</label>
        <input id="date" name="date" type="date"
               value="<?= e($appointment["appointment_Date"]) ?>" required>

        <label for="time">Time</label>
        <input id="time" name="time" type="time"
               value="<?= e(substr((string)$appointment["appointment_Time"], 0, 5)) ?>"
               required>

        <label for="room">Room</label>
        <select id="room" name="room" required>
            <?php foreach (["1", "2", "3", "4", "5", "6"] as $room): ?>
                <option value="<?= $room ?>"
                    <?= $appointment["appointment_Room"] === $room ? "selected" : "" ?>>
                    <?= $room ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="status">Status</label>
        <select id="status" name="status" required>
            <?php foreach (["Approved", "Delayed", "Cancelled"] as $status): ?>
                <option value="<?= e($status) ?>"
                    <?= $appointment["appointment_Status"] === $status ? "selected" : "" ?>>
                    <?= e($status) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="decision">Admin decision</label>
        <select id="decision" name="decision" required>
            <option value="APPROVED">APPROVED</option>
            <option value="NOT APPROVED">NOT APPROVED</option>
        </select>

        <button type="submit">Submit</button>
        <a href="management.php?section=appointments">Cancel</a>
    </form>
</main>
<footer>
<div class="footer-contact">
<p>CONTACT US AT:</p>
<p>EMAIL: prohukm@hctm.ukm.edu.my</p>
<p>PHONE NUMBER: 03-9145 5555 (OFFICE HOURS: 8:30AM - 6:00PM)</p>
</div>
<div class="footer-version">
<p>VERSION: 1.0.2</p>
</div>
<div class="footer-logo">
<img src="../img/hctm.png" alt="HCTM Logo">
</div>
</footer>
<script src="../javascript/theme.js" defer></script>
</body>
</html>