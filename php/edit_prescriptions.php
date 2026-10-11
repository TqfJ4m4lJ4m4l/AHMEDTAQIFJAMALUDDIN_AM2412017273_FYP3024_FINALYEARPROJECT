<?php
require_once "Admin_check.php";
require_once "connect.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid prescription ID.");
}

$stmt = $conn->prepare("
    SELECT
        prescription_ID,
        patient_ID,
        doctors_ID,
        appointment_ID,
        prescription_MedicationName,
        prescription_Type,
        prescription_Regulations,
        prescription_Auth
    FROM prescription
    WHERE prescription_ID = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();

$prescription = $stmt->get_result()->fetch_assoc();

if (!$prescription) {
    http_response_code(404);
    exit("Prescription not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medication = trim($_POST["medication"] ?? "");
    $type = trim($_POST["type"] ?? "");
    $regulations = trim($_POST["regulations"] ?? "");
    $prescriptionAuth = $_POST["prescription_Auth"] ?? "";
    $decision = $_POST["decision"] ?? "";

    $validPrescriptionAuth = [
        "Approved",
        "Approved with Precaution",
    ];
    $validDecisions = [
        "APPROVED",
        "NOT APPROVED",
    ];

    if (
        $medication === "" ||
        $type === "" ||
        !in_array($prescriptionAuth, $validPrescriptionAuth, true) ||
        !in_array($decision, $validDecisions, true)
    ) {
        exit("Invalid form values.");
    }

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
        $authType = "MODIFY DATA";
        $targetTable = "prescription";

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

        if ($decision === "APPROVED") {
            $updateStmt = $conn->prepare("
                UPDATE prescription
                SET prescription_MedicationName = ?,
                    prescription_Type = ?,
                    prescription_Regulations = ?,
                    prescription_Auth = ?
                WHERE prescription_ID = ?
            ");
            $updateStmt->bind_param(
                "ssssi",
                $medication,
                $type,
                $regulations,
                $prescriptionAuth,
                $id
            );
            $updateStmt->execute();
        }

        $conn->commit();

        header("Location: management.php?section=prescriptions");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription</title>
<link rel="stylesheet" href="../styles/styleAdmin.css?v=4">
    <link rel="stylesheet" href="../styles/styleEdit.css">
        <link rel="stylesheet" href="../styles/styleNav.css?v=2">
        <link rel="stylesheet" href="../styles/styleTheme.css">
</head>
<header>
<body>
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
    <h2>Edit Prescription #<?= (int)$prescription["prescription_ID"] ?></h2>

    <p>Patient ID: <?= e($prescription["patient_ID"]) ?></p>
    <p>Doctor ID: <?= e($prescription["doctors_ID"]) ?></p>
    <p>Appointment ID: <?= e($prescription["appointment_ID"]) ?></p>

    <form method="POST">
        <label for="medication">Medication</label>
        <input
            id="medication"
            name="medication"
            type="text"
            maxlength="255"
            value="<?= e($prescription["prescription_MedicationName"]) ?>"
            required
        >

        <label for="type">Type</label>
        <input
            id="type"
            name="type"
            type="text"
            maxlength="255"
            value="<?= e($prescription["prescription_Type"]) ?>"
            required
        >

        <label for="regulations">Regulations</label>
        <input
            id="regulations"
            name="regulations"
            type="text"
            maxlength="255"
            value="<?= e($prescription["prescription_Regulations"]) ?>"
        >

        <label for="prescription_Auth">Prescription authorization</label>
        <select id="prescription_Auth" name="prescription_Auth" required>
            <option value="Approved"
                <?= $prescription["prescription_Auth"] === "Approved" ? "selected" : "" ?>>
                Approved
            </option>
            <option value="Approved with Precaution"
                <?= $prescription["prescription_Auth"] === "Approved with Precaution" ? "selected" : "" ?>>
                Approved with Precaution
            </option>
        </select>

        <label for="decision">Admin decision</label>
        <select id="decision" name="decision" required>
            <option value="">Choose a decision</option>
            <option value="APPROVED">APPROVED — save these changes</option>
            <option value="NOT APPROVED">NOT APPROVED — leave prescription unchanged</option>
        </select>

        <button type="submit">Submit decision</button>
        <a href="management.php?section=prescriptions">Cancel</a>
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