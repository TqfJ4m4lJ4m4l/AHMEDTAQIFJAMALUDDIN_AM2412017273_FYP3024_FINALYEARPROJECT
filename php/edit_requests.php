<?php
require_once "Admin_check.php";
require_once "connect.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid request ID.");
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$stmt = $conn->prepare("
    SELECT request_ID, users_ID, role_ID, request_Type,
           request_Reason, request_Desc, request_Status,
           request_TargetTable, request_TargetID, request_Payload
    FROM requests
    WHERE request_ID = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    http_response_code(404);
    exit("Request not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $decision = $_POST["decision"] ?? "";

    if (!in_array($decision, ["APPROVED", "NOT APPROVED"], true)) {
        exit("Choose a valid decision.");
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
        // Lock and recheck the request so it cannot be reviewed twice.
        $lockStmt = $conn->prepare("
           SELECT request_Status, request_Type, request_TargetTable,
           request_TargetID, users_ID, request_Payload
           FROM requests
           WHERE request_ID = ?
           FOR UPDATE
           ");
        $lockStmt->bind_param("i", $id);
        $lockStmt->execute();
        $lockedRequest = $lockStmt->get_result()->fetch_assoc();

        if (!$lockedRequest || $lockedRequest["request_Status"] !== "PENDING") {
        throw new RuntimeException("This request has already been reviewed.");
        }

        $authType = "MODIFY DATA";
$targetTable = $lockedRequest["request_TargetTable"];
$targetID = $lockedRequest["request_TargetID"];

if (
    $decision === "APPROVED" &&
    $lockedRequest["request_Type"] === "Notes"
) {
    $payload = json_decode($lockedRequest["request_Payload"] ?? "", true);

    if (!is_array($payload)) {
        throw new RuntimeException("The note request payload is invalid.");
    }

    $appointmentID = filter_var(
        $payload["appointment_ID"] ?? null,
        FILTER_VALIDATE_INT
    );
    $title = trim($payload["notes_Title"] ?? "");
    $description = trim($payload["notes_Desc"] ?? "");

    if (
        !$appointmentID ||
        $title === "" ||
        $description === "" ||
        mb_strlen($title) > 255 ||
        mb_strlen($description) > 255
    ) {
        throw new RuntimeException("The note request has invalid fields.");
    }

    // Verify the requesting doctor is assigned to this appointment.
    $assignmentStmt = $conn->prepare("
        SELECT a.appointment_ID
        FROM appointments a
        JOIN doctors d ON d.doctors_ID = a.doctors_ID
        WHERE a.appointment_ID = ?
          AND d.users_ID = ?
          AND a.appointment_Status <> 'Cancelled'
    ");
    $assignmentStmt->bind_param(
        "ii",
        $appointmentID,
        $lockedRequest["users_ID"]
    );
    $assignmentStmt->execute();

    if (!$assignmentStmt->get_result()->fetch_assoc()) {
        throw new RuntimeException(
            "The requesting doctor is not assigned to this appointment."
        );
    }

    $noteStmt = $conn->prepare("
        INSERT INTO notes (appointment_ID, notes_Title, notes_Desc)
        VALUES (?, ?, ?)
    ");
    $noteStmt->bind_param("iss", $appointmentID, $title, $description);
    $noteStmt->execute();

    $targetTable = "notes";
    $targetID = (int)$conn->insert_id;
}

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
    $targetID
);
$authStmt->execute();

        $updateStmt = $conn->prepare("
    UPDATE requests
    SET request_Status = ?,
        request_TargetTable = ?,
        request_TargetID = ?
    WHERE request_ID = ?
");
$updateStmt->bind_param(
    "ssii",
    $decision,
    $targetTable,
    $targetID,
    $id
);
$updateStmt->execute();
if (!empty($lockedRequest["users_ID"])) {
    $notice = $decision === "APPROVED"
        ? "Your appointment note was approved and is now available to the patient."
        : "Your appointment note request was not approved.";

    $noticeStmt = $conn->prepare("
        INSERT INTO notifications (users_ID, notification_Message)
        VALUES (?, ?)
    ");
    $noticeStmt->bind_param(
        "is",
        $lockedRequest["users_ID"],
        $notice
    );
    $noticeStmt->execute();
}
        $conn->commit();

        header("Location: management.php?section=requests");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        exit(e($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEURODIVERGENT - Review Request</title>
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
    <h2>Review Request #<?= (int)$request["request_ID"] ?></h2>

    <p><strong>Type:</strong> <?= e($request["request_Type"]) ?></p>
    <p><strong>Reason:</strong> <?= e($request["request_Reason"]) ?></p>
    <p><strong>Description:</strong> <?= e($request["request_Desc"]) ?></p>
    <p><strong>Current status:</strong> <?= e($request["request_Status"]) ?></p>
    <p><strong>Target:</strong> <?= e($request["request_TargetTable"]) ?>
        #<?= e($request["request_TargetID"]) ?></p>
    <p><strong>Description:</strong> <?= e($request["request_Payload"]) ?></p>

    <?php if ($request["request_Status"] === "PENDING"): ?>
        <form method="post">
            <label for="decision">Decision</label>
            <select id="decision" name="decision" required>
                <option value="">Choose a decision</option>
                <option value="APPROVED">APPROVED</option>
                <option value="NOT APPROVED">NOT APPROVED</option>
            </select>

            <button type="submit">Submit decision</button>
        </form>
    <?php else: ?>
        <p>This request has already been reviewed.</p>
    <?php endif; ?>

    <a href="management.php?section=requests">Back to requests</a>
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