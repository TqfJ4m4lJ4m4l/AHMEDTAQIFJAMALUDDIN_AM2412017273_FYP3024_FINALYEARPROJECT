<?php
require_once "Admin_check.php";
require_once "connect.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid note ID.");
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$stmt = $conn->prepare(
    "SELECT notes_ID, notes_Title, notes_Desc FROM notes WHERE notes_ID = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();

if (!$note) {
    http_response_code(404);
    exit("Note not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $decision = $_POST["decision"] ?? "";

    if ($title === "" || !in_array($decision, ["APPROVED", "NOT APPROVED"], true)) {
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
        $targetTable = "notes";

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
                UPDATE notes
                SET notes_Title = ?, notes_Desc = ?
                WHERE notes_ID = ?
            ");
            $updateStmt->bind_param("ssi", $title, $description, $id);
            $updateStmt->execute();
        }

        $conn->commit();
        header("Location: management.php?section=notes");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        exit("Could not update note: " . e($e->getMessage()));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Note</title>
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
    <h2>Edit Note #<?= (int)$note["notes_ID"] ?></h2>

    <form method="post">
        <label for="title">Title</label>
        <input id="title" name="title" maxlength="255"
               value="<?= e($note["notes_Title"]) ?>" required>

        <label for="description">Description</label>
        <textarea id="description" name="description"><?= e($note["notes_Desc"]) ?></textarea>

        <label for="decision">Admin decision</label>
        <select id="decision" name="decision" required>
            <option value="APPROVED">APPROVED</option>
            <option value="NOT APPROVED">NOT APPROVED</option>
        </select>

        <button type="submit">Submit</button>
        <a href="management.php?section=notes">Cancel</a>
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