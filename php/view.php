<?php
require_once "Admin_check.php";
require_once "connect.php";

$type = $_GET["type"] ?? "";
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Invalid record ID.");
}

/*
 * Keep table and column names in this fixed list.
 * Only the row ID comes from the URL, and it is passed as a prepared parameter.
 */
$views = [
    "booking" => [
        "title" => "Booking Details",
        "table" => "booking",
        "id_column" => "booking_ID",
        "section" => "bookings",
        "fields" => [
            "booking_ID" => "Booking ID",
            "patient_ID" => "Patient ID",
            "booking_Doctors" => "Doctor",
            "booking_Date" => "Date",
            "booking_Time" => "Time",
            "booking_Medium" => "Medium",
            "booking_Desc" => "Description",
        ],
    ],
    "prescription" => [
        "title" => "Prescription Details",
        "table" => "prescription",
        "id_column" => "prescription_ID",
        "section" => "prescriptions",
        "fields" => [
            "prescription_ID" => "Prescription ID",
            "patient_ID" => "Patient ID",
            "prescription_MedicationName" => "Medication",
            "prescription_Type" => "Type",
            "prescription_Regulations" => "Regulations",
            "prescription_Auth" => "Authorization",
        ],
    ],
    "report" => [
        "title" => "Report Details",
        "table" => "reports",
        "id_column" => "report_ID",
        "section" => "reports",
        "fields" => [
            "report_ID" => "Report ID",
            "report_Type" => "Type",
            "report_Reason" => "Reason",
            "report_Desc" => "Description",
        ],
    ],
    "request" => [
        "title" => "Request Details",
        "table" => "requests",
        "id_column" => "request_ID",
        "section" => "requests",
        "fields" => [
            "request_ID" => "request ID:",
            "users_ID" => "User ID",
            "users_Email" => "User",
            "request_Type" => "Type:",
            "request_Reason" => "Reason:",
            "request_Desc" => "Description:",
        ],
    ],
    "note" => [
        "title" => "Note Details",
        "table" => "notes",
        "id_column" => "notes_ID",
        "section" => "notes",
        "fields" => [
            "notes_ID" => "Note ID",
            "notes_Title" => "Title",
            "notes_Desc" => "Description",
        ],
    ],
    "authentication" => [
        "title" => "Authentication Details",
        "table" => "authentication",
        "id_column" => "auth_ID",
        "section" => "authentication",
        "fields" => [
            "auth_ID" => "Authentication ID",
            "admin_ID" => "Administrator ID",
            "auth_Status" => "Status",
            "auth_Date" => "Date",
        ],
    ],
    "patients" => [
    "title" => "Patient Details",
    "table" => "patients",
    "id_column" => "patient_ID",
    "section" => "patients",
    "fields" => [
        "patient_ID" => "Patient ID",
        "users_ID" => "User ID",
        "patient_Name" => "Name",
        "patient_BirthDate" => "Birth Date",
        "patient_Gender" => "Gender",
    ],
],
];

if (!isset($views[$type])) {
    http_response_code(404);
    exit("Unknown record type.");
}

$config = $views[$type];

if ($type === "request") {
    $stmt = $conn->prepare("
        SELECT r.request_ID, u.users_Email, u.users_ID, r.request_Type,
               r.request_Reason, r.request_Desc
        FROM requests r
        LEFT JOIN users u ON u.users_ID = r.users_ID
        WHERE r.request_ID = ?
    ");
} else {
    $columns = array_keys($config["fields"]);
    $columnList = implode(", ", array_map(
        static fn($column) => "`$column`",
        $columns
    ));

    $sql = "SELECT $columnList
            FROM `{$config["table"]}`
            WHERE `{$config["id_column"]}` = ?";
    $stmt = $conn->prepare($sql);
}

if (!$stmt) {
    http_response_code(500);
    exit("Could not prepare the record query.");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    http_response_code(404);
    exit("Record not found.");
}

function escapeValue($value): string
{
    if ($value === null || $value === "") {
        return "—";
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($config["title"], ENT_QUOTES, "UTF-8") ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles/styleAdmin.css">
<link rel="stylesheet" href="../styles/styleView.css">
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
<div class="report_button">
<button class="Report" style="background-color: red;">
<a href="../html/Report.html" style="font-size: larger;">REPORT</a>
</button>
</div>
<div class="us_button">
<button class="AboutUs" style="margin: auto;">
<a href="../html/AboutUs.html" style="font-size: larger;">ABOUT US</a>
</button>
</div>
<div class="neuro_button">
<button class="Neurodivergent">
<a href="../html/Requests.html" style="font-size: larger;">REQUESTS</a>
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
<a href="updates.php">Notifications</a>
<a href="settings.php">Settings</a>
<a href="logout.php">Log Out</a>
</div>
</div>
</nav>
</h1>
</header>

<body>

<main class="record-details">
    <h1><?= htmlspecialchars($config["title"], ENT_QUOTES, "UTF-8") ?></h1>

    <dl>
        <?php foreach ($config["fields"] as $column => $label): ?>
            <dt><?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?></dt>
            <dd><?= escapeValue($record[$column]) ?></dd>
        <?php endforeach; ?>
    </dl>

    <a href="management.php?section=<?= urlencode($config["section"]) ?>">
        Back to management
    </a>
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
</body>
<script src="../javascript/theme.js" defer></script>
</html>