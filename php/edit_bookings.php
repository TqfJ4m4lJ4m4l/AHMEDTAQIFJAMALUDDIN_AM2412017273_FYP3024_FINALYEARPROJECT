<?php
require_once "Admin_check.php";
require_once "connect.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid booking ID.");
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$stmt = $conn->prepare("SELECT * FROM booking WHERE booking_ID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    http_response_code(404);
    exit("Booking not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $validActions = ["approve", "cancel"];

    if (!in_array($action, $validActions, true)) {
        exit("Invalid action.");
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
        // Lock this booking while processing the action.
        $lockStmt = $conn->prepare(
            "SELECT * FROM booking WHERE booking_ID = ? FOR UPDATE"
        );
        $lockStmt->bind_param("i", $id);
        $lockStmt->execute();
        $lockedBooking = $lockStmt->get_result()->fetch_assoc();

        if (!$lockedBooking) {
            throw new RuntimeException("Booking no longer exists.");
        }

        if ($action === "approve") {
            $room = $_POST["room"] ?? "";
            $status = $_POST["status"] ?? "";

            if (!in_array($room, ["1", "2", "3", "4", "5", "6"], true)) {
                throw new RuntimeException("Choose a valid appointment room.");
            }

            if (!in_array($status, ["Cancelled", "Delayed", "Approved"], true)) {
                throw new RuntimeException("Choose a valid appointment status.");
            }

            $existingStmt = $conn->prepare(
                "SELECT appointment_ID FROM appointments WHERE booking_ID = ?"
            );
            $existingStmt->bind_param("i", $id);
            $existingStmt->execute();

            if ($existingStmt->get_result()->num_rows > 0) {
                throw new RuntimeException("This booking already has an appointment.");
            }

            $doctorStmt = $conn->prepare(
                "SELECT doctors_ID FROM doctors WHERE doctor_Name = ?"
            );
            $doctorStmt->bind_param("s", $lockedBooking["booking_Doctors"]);
            $doctorStmt->execute();
            $doctor = $doctorStmt->get_result()->fetch_assoc();

            if (!$doctor) {
                throw new RuntimeException("The booking's doctor was not found.");
            }

            $authType = "APPROVE BOOKING";
            $targetTable = "booking";
            $authStatus = "APPROVED";

            $authStmt = $conn->prepare("
                INSERT INTO authentication
                    (admin_ID, auth_Status, auth_Date, auth_Type,
                     auth_TargetTable, auth_TargetID)
                VALUES (?, ?, CURDATE(), ?, ?, ?)
            ");
            $authStmt->bind_param(
                "isssi",
                $admin["admin_ID"],
                $authStatus,
                $authType,
                $targetTable,
                $id
            );
            $authStmt->execute();
            $authID = $conn->insert_id;

            $appointmentCode = bin2hex(random_bytes(16));

            $appointmentStmt = $conn->prepare("
            INSERT INTO appointments
                (patient_ID, doctors_ID, auth_ID, booking_ID,
                appointment_Date, appointment_Time, appointment_Room,
                appointment_Status, appointment_Code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $appointmentStmt->bind_param(
              "iiiisssss",
              $lockedBooking["patient_ID"],
              $doctor["doctors_ID"],
              $authID,
              $id,
              $lockedBooking["booking_Date"],
              $lockedBooking["booking_Time"],
              $room,
              $status,
              $appointmentCode
              );
             $appointmentStmt->execute();
        } else {
            // A booking that already has an appointment cannot be deleted.
            $existingStmt = $conn->prepare(
                "SELECT appointment_ID FROM appointments WHERE booking_ID = ?"
            );
            $existingStmt->bind_param("i", $id);
            $existingStmt->execute();

            if ($existingStmt->get_result()->num_rows > 0) {
                throw new RuntimeException(
                    "This booking has an appointment and cannot be cancelled here."
                );
            }

            $authType = "REMOVE DATA";
            $targetTable = "booking";
            $authStatus = "APPROVED";

            $authStmt = $conn->prepare("
                INSERT INTO authentication
                    (admin_ID, auth_Status, auth_Date, auth_Type,
                     auth_TargetTable, auth_TargetID)
                VALUES (?, ?, CURDATE(), ?, ?, ?)
            ");
            $authStmt->bind_param(
                "isssi",
                $admin["admin_ID"],
                $authStatus,
                $authType,
                $targetTable,
                $id
            );
            $authStmt->execute();

            $deleteStmt = $conn->prepare(
                "DELETE FROM booking WHERE booking_ID = ?"
            );
            $deleteStmt->bind_param("i", $id);
            $deleteStmt->execute();
        }

        $conn->commit();
        header("Location: management.php?section=bookings");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        exit("Could not process booking: " . e($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Booking</title>
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
    <h2>Booking #<?= (int)$booking["booking_ID"] ?></h2>
    <p>Patient: <?= e($booking["patient_ID"]) ?></p>
    <p>Doctor: <?= e($booking["booking_Doctors"]) ?></p>
    <p>Date: <?= e($booking["booking_Date"]) ?></p>
    <p>Time: <?= e($booking["booking_Time"]) ?></p>
    <p>Medium: <?= e($booking["booking_Medium"]) ?></p>
    <p>Description: <?= e($booking["booking_Desc"]) ?></p>

    <form method="post">
        <h3>Approve and create appointment</h3>

        <label for="room">Room</label>
        <select name="room" id="room" required>
            <option value="">Choose room</option>
            <?php foreach (["1", "2", "3", "4", "5", "6"] as $room): ?>
                <option value="<?= $room ?>"><?= $room ?></option>
            <?php endforeach; ?>
        </select>

        <label for="status">Appointment status</label>
        <select name="status" id="status" required>
            <option value="Approved">Approved</option>
            <option value="Delayed">Delayed</option>
            <option value="Cancelled">Cancelled</option>
        </select>

        <button type="submit" name="action" value="approve">Approve booking</button>
        <button type="submit" name="action" value="cancel"
                onclick="return confirm('Cancel and remove this booking?')">
            Cancel booking
        </button>
    </form>

    <p><a href="management.php?section=bookings">Back</a></p>
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