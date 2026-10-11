<?php
require_once "Admin_check.php";
require_once "connect.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid record ID.");
}

// This file always edits doctors; table/column names are fixed here.
$stmt = $conn->prepare("
    SELECT doctors_ID, doctor_Name, doctor_Status
    FROM doctors
    WHERE doctors_ID = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();

$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    http_response_code(404);
    exit("Doctor not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["doctor_Name"] ?? "");
    $status = $_POST["doctor_Status"] ?? "";
    $decision = $_POST["auth_Status"] ?? "";

    $validStatuses = ["AVAILABLE", "ON LEAVE"];
    $validDecisions = ["APPROVED", "NOT APPROVED"];

    if (
        $name === "" ||
        !in_array($status, $validStatuses, true) ||
        !in_array($decision, $validDecisions, true)
    ) {
        exit("Invalid form values.");
    }

    // Find this admin's admin_ID using the logged-in user_ID.
    $adminStmt = $conn->prepare("
        SELECT admin_ID
        FROM admins
        WHERE users_ID = ?
    ");
    $adminStmt->bind_param("i", $_SESSION["user_ID"]);
    $adminStmt->execute();

    $admin = $adminStmt->get_result()->fetch_assoc();

    if (!$admin) {
        exit("Administrator record not found.");
    }

    $conn->begin_transaction();

    try {
        // Record the decision first.
        $authStmt = $conn->prepare("
            INSERT INTO authentication
                (admin_ID, auth_Status, auth_Date, auth_Type,
                 auth_TargetTable, auth_TargetID)
            VALUES (?, ?, CURDATE(), 'MODIFY DATA', 'doctors', ?)
        ");
        $authStmt->bind_param(
            "isi",
            $admin["admin_ID"],
            $decision,
            $id
        );
        $authStmt->execute();

        // Apply the change only when approved.
        if ($decision === "APPROVED") {
            $updateStmt = $conn->prepare("
                UPDATE doctors
                SET doctor_Name = ?, doctor_Status = ?
                WHERE doctors_ID = ?
            ");
            $updateStmt->bind_param("ssi", $name, $status, $id);
            $updateStmt->execute();
        }

        $conn->commit();

        header("Location: management.php?section=therapists");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Therapist</title>
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
    <h2>Edit Therapist</h2>

    <form method="post">
        <label for="doctor_Name">Name</label>
        <input id="doctor_Name" name="doctor_Name" maxlength="255"
               value="<?= htmlspecialchars($doctor["doctor_Name"] ?? "", ENT_QUOTES, "UTF-8") ?>"
               required>

        <label for="doctor_Status">Status</label>
        <select id="doctor_Status" name="doctor_Status" required>
            <option value="AVAILABLE"
                <?= $doctor["doctor_Status"] === "AVAILABLE" ? "selected" : "" ?>>
                Available
            </option>
            <option value="ON LEAVE"
                <?= $doctor["doctor_Status"] === "ON LEAVE" ? "selected" : "" ?>>
                On leave
            </option>
        </select>

        <label for="auth_Status">Admin decision</label>
        <select id="auth_Status" name="auth_Status" required>
            <option value="APPROVED">APPROVED</option>
            <option value="NOT APPROVED">NOT APPROVED</option>
        </select>

        <button type="submit">Submit</button>
        <a href="management.php?section=therapists">Cancel</a>
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