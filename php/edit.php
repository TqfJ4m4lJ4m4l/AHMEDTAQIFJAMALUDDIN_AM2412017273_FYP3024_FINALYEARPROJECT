<?php
require_once "Admin_check.php";
require_once "connect.php";

$type = $_GET["type"] ?? "";
$id = $_GET["id"] ?? "";

if ($type === "" || $id === "") {
    die("Invalid management request.");
}

if ($type === "booking") {
    $stmt = $conn->prepare("SELECT * FROM booking WHERE booking_ID=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        die("Booking not found.");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $doctor = $_POST["doctor"];
        $date = $_POST["date"];
        $time = $_POST["time"];
        $medium = $_POST["medium"];
        $description = $_POST["description"];

        $stmt = $conn->prepare("UPDATE booking SET booking_Doctors=?,booking_Date=?,booking_Time=?,booking_Medium=?,booking_Desc=? WHERE booking_ID=?");
        $stmt->bind_param("sssssi",$doctor,$date,$time,$medium,$description,$id);
        $stmt->execute();

        header("Location: management.php?section=bookings");
        exit();
    }
}
elseif ($type === "prescription") {
    $stmt = $conn->prepare("SELECT * FROM prescription WHERE prescription_ID=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        die("Prescription not found.");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $medication = $_POST["medication"];
        $prescriptionType = $_POST["type"];
        $regulations = $_POST["regulations"];
        $auth = $_POST["auth"];

        $stmt = $conn->prepare("UPDATE prescription SET prescription_MedicationName=?,prescription_Type=?,prescription_Regulations=?,prescription_Auth=? WHERE prescription_ID=?");
        $stmt->bind_param("ssssi",$medication,$prescriptionType,$regulations,$auth,$id);
        $stmt->execute();

        header("Location: management.php?section=prescriptions");
        exit();
    }
}
elseif ($type === "report") {
    $stmt = $conn->prepare("SELECT * FROM reports WHERE report_ID=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        die("Report not found.");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $reportType = $_POST["report_Type"];
        $reason = $_POST["report_Reason"];
        $description = $_POST["report_Desc"];

        $stmt = $conn->prepare("UPDATE reports SET report_Type=?,report_Reason=?,report_Desc=? WHERE report_ID=?");
        $stmt->bind_param("sssi",$reportType,$reason,$description,$id);
        $stmt->execute();

        header("Location: management.php?section=reports");
        exit();
    }
}
elseif ($type === "note") {
    $stmt = $conn->prepare("SELECT * FROM notes WHERE notes_ID=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        die("Note not found.");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $title = $_POST["title"];
        $description = $_POST["description"];

        $stmt = $conn->prepare("UPDATE notes SET notes_Title=?,notes_Desc=? WHERE notes_ID=?");
        $stmt->bind_param("ssi",$title,$description,$id);
        $stmt->execute();

        header("Location: management.php?section=notes");
        exit();
    }
}
elseif ($type === "authentication") {
    $stmt = $conn->prepare("SELECT * FROM authentication WHERE auth_ID=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
        die("Authentication record not found.");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $status = $_POST["status"];

        $stmt = $conn->prepare("UPDATE authentication SET auth_Status=? WHERE auth_ID=?");
        $stmt->bind_param("si",$status,$id);
        $stmt->execute();

        header("Location: management.php?section=authentication");
        exit();
    }
}
else {
    die("Invalid management type.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Management</title>
<link rel="stylesheet" href="../styles/styleAdmin.css">
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
<div class="admin-management-box">

<?php if ($type === "booking"): ?>

<h2>Edit Booking</h2>

<form method="POST">
<label>Doctor</label>
<input type="text" name="doctor" value="<?php echo htmlspecialchars($data["booking_Doctors"]); ?>">

<label>Date</label>
<input type="date" name="date" value="<?php echo htmlspecialchars($data["booking_Date"]); ?>">

<label>Time</label>
<input type="time" name="time" value="<?php echo htmlspecialchars($data["booking_Time"]); ?>">

<label>Medium</label>
<select name="medium">
<option value="Digital" <?php echo $data["booking_Medium"]==="Digital"?"selected":""; ?>>Digital</option>
<option value="One on One" <?php echo $data["booking_Medium"]==="One on One"?"selected":""; ?>>One on One</option>
<option value="Non-vocal" <?php echo $data["booking_Medium"]==="Non-vocal"?"selected":""; ?>>Non-vocal</option>
</select>

<label>Description</label>
<textarea name="description"><?php echo htmlspecialchars($data["booking_Desc"]); ?></textarea>

<button type="submit">SAVE CHANGES</button>
<a href="management.php?section=bookings">CANCEL</a>
</form>

<?php elseif ($type === "prescription"): ?>

<h2>Edit Prescription</h2>

<form method="POST">
<label>Medication</label>
<input type="text" name="medication" value="<?php echo htmlspecialchars($data["prescription_MedicationName"]); ?>">

<label>Type</label>
<input type="text" name="type" value="<?php echo htmlspecialchars($data["prescription_Type"]); ?>">

<label>Regulations</label>
<input type="text" name="regulations" value="<?php echo htmlspecialchars($data["prescription_Regulations"]); ?>">

<label>Authorization</label>
<select name="auth">
<option value="Approved" <?php echo $data["prescription_Auth"]==="Approved"?"selected":""; ?>>Approved</option>
<option value="Approved with Precaution" <?php echo $data["prescription_Auth"]==="Approved with Precaution"?"selected":""; ?>>Approved with Precaution</option>
</select>

<button type="submit">SAVE CHANGES</button>
<a href="management.php?section=prescriptions">CANCEL</a>
</form>

<?php elseif ($type === "report"): ?>

<h2>Edit Report</h2>

<form method="POST">
<label>Type</label>
<input type="text" name="report_Type" value="<?php echo htmlspecialchars($data["report_Type"]); ?>">

<label>Reason</label>
<input type="text" name="report_Reason" value="<?php echo htmlspecialchars($data["report_Reason"]); ?>">

<label>Description</label>
<textarea name="report_Desc"><?php echo htmlspecialchars($data["report_Desc"]); ?></textarea>

<button type="submit">SAVE CHANGES</button>
<a href="management.php?section=reports">CANCEL</a>
</form>

<?php elseif ($type === "note"): ?>

<h2>Edit Note</h2>

<form method="POST">
<label>Title</label>
<input type="text" name="title" value="<?php echo htmlspecialchars($data["notes_Title"]); ?>">

<label>Description</label>
<textarea name="description"><?php echo htmlspecialchars($data["notes_Desc"]); ?></textarea>

<button type="submit">SAVE CHANGES</button>
<a href="management.php?section=notes">CANCEL</a>
</form>

<?php elseif ($type === "authentication"): ?>

<h2>Edit Authentication</h2>

<form method="POST">
<label>Status</label>
<select name="status">
<option value="APPROVED" <?php echo $data["auth_Status"]==="APPROVED"?"selected":""; ?>>APPROVED</option>
<option value="NOT APPROVED" <?php echo $data["auth_Status"]==="NOT APPROVED"?"selected":""; ?>>NOT APPROVED</option>
</select>

<button type="submit">SAVE CHANGES</button>
<a href="management.php?section=authentication">CANCEL</a>
</form>

<?php endif; ?>

</div>
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