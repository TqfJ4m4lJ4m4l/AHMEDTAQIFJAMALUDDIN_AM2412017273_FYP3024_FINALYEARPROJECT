<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION["user_ID"])) {
    header("Location: ../html/LoginPage.html");
    exit();
}

$user_ID = $_SESSION["user_ID"];

$stmt = $conn->prepare("
    SELECT
        patient_ID,
        patient_Name,
        users_ID
    FROM patients
    WHERE users_ID = ?
");

if (!$stmt) {
    die("Patient Query Error: " . $conn->error);
}

$stmt->bind_param("i", $user_ID);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Patient record not found.");
}

$patient = $result->fetch_assoc();

$patient_ID = $patient["patient_ID"];
$patient_Name = $patient["patient_Name"];

$_SESSION["patient_ID"] = $patient_ID;

$stmt = $conn->prepare("
    SELECT users_Email
    FROM users
    WHERE users_ID = ?
");

if (!$stmt) {
    die("User Query Error: " . $conn->error);
}

$stmt->bind_param("i", $user_ID);
$stmt->execute();

$emailResult = $stmt->get_result();
$user = $emailResult->fetch_assoc();

$user_Email = $user["users_Email"] ?? "";

$doctors = $conn->query("
    SELECT
        doctors_ID,
        doctor_Name
    FROM doctors
    ORDER BY doctor_Name
");

if ($_SERVER["REQUEST_METHOD"] === "POST"){
    $doctor = trim($_POST["doctor"]??"");
    $date = trim($_POST["date"]??"");
    $time = trim($_POST["time"]??"");
    $medium = trim($_POST["medium"]??"");
    $description = trim($_POST["description"]??"");
        $validMedia = ["Digital", "One on One", "Non-vocal"];

    if ($doctor === "" || $date === "" || $time === "" ||
        !in_array($medium, $validMedia, true)) {
        die("Please complete all required booking fields.");
}
$stmt = $conn->prepare("
        INSERT INTO booking
            (patient_ID, booking_Doctors, booking_Date, booking_Time, booking_Medium, booking_Desc)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Booking insert error: " . $conn->error);
    }

    $stmt->bind_param(
        "isssss",
        $patient_ID,
        $doctor,
        $date,
        $time,
        $medium,
        $description
    );

    if (!$stmt->execute()) {
        die("Could not save booking: " . $stmt->error);
    }

    header("Location: booking.php?booked=1");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bookings</title>

    <link rel="stylesheet" href="../styles/styleBook.css?v=4">
            <link rel="stylesheet" href="../styles/styleNav.css?v=2">
            <link rel="stylesheet" href="../styles/styleTheme.css">
</head>

<body>

<header>
<h1>
<a href="HomePage.html">
    <img src="../img/neurodivergence.png" alt="Login Page" width="250">
</a>



<nav>
<div class="report_button">
    <button class="Report" style="background-color: red;">
      <a href="reports.php" style="font-size: larger;">REPORT</a>
    </button>
  </div>
  <div class="report_button">
    <button class="Request" style="background-color: rgb(255, 192, 65);">
      <a href="requests.php" style="font-size: larger;">REQUESTS</a>
    </button>
  </div>
 <div class="us_button">
  <button class="AboutUs" style="margin: auto;">
   <a href="appointments.php" style="font-size: larger;">APPOINTMENTS</a>
  </button>
 </div>

<div class="neuro_button">
  <button class="Neurodivergent">
   <a href="booking.php" style="font-size: larger;">BOOK AN APPOINTMENT</a>
  </button>
 </div>

 <div class="appointment_button">
  <button class="appointments">
<a href="notes.php" style="font-size: larger;">YOUR NOTES</a>
</button>
</div>

<div class="dropdown">
<button class="dropdown-btn">
     <a href= "../php/account.php"> <img src="../img/login.png" alt="Login Page" width="70" height="70"></a>
</button>
<div class="dropdown-content">
      <a href="updates.php">Inbox</a>
      <a href="settings.php">Settings</a>
      <a href="prescription.php">Your Prescriptions</a>
      <a href="logout.php">Log Out</a>
    </div>
  </div>
</a>
</nav>
</h1>
</header>
<?php if (isset($_GET["booked"]) && $_GET["booked"] === "1"): ?>
    <div
        id="booking-message"
        class="auth-toast auth-toast--success"
        role="status"
        aria-live="polite"
    >
        <span class="auth-toast-message">SUCCESSFULLY BOOKED!</span>
        <button
            class="auth-toast-close"
            type="button"
            aria-label="Dismiss notification"
        >&times;</button>
    </div>
<?php endif; ?>
<main>
<form class="book_Disp" id="accountForm" action="booking.php" method="POST">
<div class="book-header">
<div class="booking-bigtext">BOOKING APPOINTMENT:</div>
<input type="hidden" name="patient_ID" value="<?php echo htmlspecialchars($patient_ID); ?>">
</div>
<div class="book-content">
<div class="book-column">
<div class="user_ID">
<div class="form-row">
<label for="patientID">ID:</label>
<input type="text" id="patientID" value="<?php echo htmlspecialchars($patient_ID); ?>" readonly>
</div>
</div>
<div class="user_NameAndEmail">
<div class="form-row">
<label for="patientName">NAME:</label>
<input type="text" id="patientName" value="<?php echo htmlspecialchars($patient_Name); ?>" readonly>
</div>
<div class="form-row">
<label for="patientEmail">EMAIL:</label>
<input type="email" id="patientEmail" value="<?php echo htmlspecialchars($user_Email); ?>" readonly>
</div>
</div>
<div class="book_Doctor">
<div class="form-row">
<label for="doctor">DOCTOR:</label>
<select id="doctor" name="doctor" required>
<option value="" disabled selected>Select Doctor</option>
<?php while ($doctor = $doctors->fetch_assoc()): ?>
<option value="<?php echo htmlspecialchars($doctor["doctor_Name"]); ?>">
<?php echo htmlspecialchars($doctor["doctor_Name"]); ?>
</option>
<?php endwhile; ?>
</select>
</div>
</div>
<div class="book_DateAndMedia">
<div class="form-row">
<label for="bookingDate">DATE:</label>
<input type="date" id="bookingDate" name="date" required>
</div>
<div class="form-row">
<label for="bookingTime">TIME:</label>
<input type="time" id="bookingTime" name="time" required>
</div>
<div class="form-row">
<label for="media">MEDIUM:</label>
<select id="media" name="medium" required>
<option value="" disabled selected>Select Medium</option>
<option value="Digital">Digital</option>
<option value="One on One">One on One</option>
<option value="Non-vocal">Non-vocal</option>
</select>
</div>
</div>
<div class="descBook">
<div class="form-row">
<label for="descriptionBooking">DESCRIPTION:</label>
<textarea id="descriptionBooking" name="description" placeholder="Enter the reason or description for your booking..."></textarea>
</div>
</div>
<div class="confirm-container">
<button type="submit" id="confirmButton">CONFIRM</button>
</div>
</div>
</div>
</form>
</main>
<footer>
<div class="footer-contact">
<p>CONTACT US AT:</p>
<p>EMAIL: prohukm@hctm.ukm.edu.my</p>
<p>PHONE NUMBER: 03-9145 5555(OFFICE HOURS: 8:30AM - 6:00PM)</p>
</div>
<div class="footer-version">
<p>
VERSION: 1.0.2
</p>
</div>
<div class="footer-logo">

<img src="../img/hctm.png"
     alt="HCTM Logo">

</div>
</footer>
<script>
const toast = document.getElementById("booking-message");

if (toast) {
    toast.querySelector(".auth-toast-close").addEventListener("click", () => {
        toast.remove();

        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete("booked");
        window.history.replaceState({}, document.title, cleanUrl);
    });
}
</script>
<script src="../javascript/theme.js" defer></script>
</body>
</html>