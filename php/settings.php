<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION["user_ID"])) {
    if (isset($_GET["api"])) {
        http_response_code(401);
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["error" => "unauthorized"]);
        exit;
    }

    header("Location: ../html/LoginPage.html");
    exit;
}

$userID = (int)$_SESSION["user_ID"];

$palettes = [
    "default",
    "colors",
    "colors2",
    "color3",
    "color4",
    "color5"
];

$fonts = [
    "system",
    "poppins"
];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/* The shared theme script uses this endpoint to read preferences on each page. */
if (isset($_GET["api"]) && $_GET["api"] === "1") {
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store, no-cache, must-revalidate");

    try {
        $stmt = $conn->prepare(
            "SELECT theme_palette, dark_mode, font_family
             FROM user_preferences
             WHERE users_ID = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $preferences = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$preferences) {
            $preferences = [
                "theme_palette" => "default",
                "dark_mode" => 0,
                "font_family" => "system"
            ];
        }

        echo json_encode([
            "palette" => $preferences["theme_palette"],
            "darkMode" => (bool)$preferences["dark_mode"],
            "font" => $preferences["font_family"]
        ]);
    } catch (Throwable $error) {
        http_response_code(500);
        echo json_encode(["error" => "preferences_unavailable"]);
    }

    exit;
}

/* Save settings. */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    if (
        $submittedToken === "" ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        header("Location: settings.php?error=csrf");
        exit;
    }

    $palette = $_POST["palette"] ?? "default";
    $font = $_POST["font_family"] ?? "system";
    $darkMode = isset($_POST["dark_mode"]) ? 1 : 0;

    if (!in_array($palette, $palettes, true)) {
        $palette = "default";
    }

    if (!in_array($font, $fonts, true)) {
        $font = "system";
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO user_preferences
                (users_ID, theme_palette, dark_mode, font_family)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                theme_palette = VALUES(theme_palette),
                dark_mode = VALUES(dark_mode),
                font_family = VALUES(font_family)"
        );
        $stmt->bind_param("isis", $userID, $palette, $darkMode, $font);
        $stmt->execute();
        $stmt->close();

        header("Location: settings.php?saved=1");
        exit;
    } catch (Throwable $error) {
        header("Location: settings.php?error=save");
        exit;
    }
}

/* Load saved preferences for the settings form. */
$preferences = [
    "theme_palette" => "default",
    "dark_mode" => 0,
    "font_family" => "system"
];

try {
    $stmt = $conn->prepare(
        "SELECT theme_palette, dark_mode, font_family
         FROM user_preferences
         WHERE users_ID = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $savedPreferences = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($savedPreferences) {
        $preferences = $savedPreferences;
    }
} catch (Throwable $error) {
    $pageError = "Settings could not be loaded. Check that the preferences table was created.";
}

$paletteLabels = [
    "default" => "Current site colors",
    "colors" => "Muted mauve and blue",
    "colors2" => "Navy, cream, and coral",
    "color3" => "Soft gray and blue",
    "color4" => "Soft green and slate",
    "color5" => "Warm earth colors"
];

$csrfToken = $_SESSION["csrf_token"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessibility Settings</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../styles/styleNav.css">
    <link rel="stylesheet" href="../styles/styleTheme.css">
    <link rel="stylesheet" href="../styles/styleSettings.css">

    <style>
        body.settings-page {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .settings-main {
            flex: 1;
            width: min(1000px, calc(100% - 32px));
            margin: 0 auto;
            padding: 36px 0 56px;
        }

        .settings-heading {
            margin: 0 0 24px;
            text-align: center;
        }

        .settings-heading h2 {
            margin: 0 0 6px;
            font-size: clamp(28px, 4vw, 40px);
        }

        .settings-heading p {
            margin: 0;
        }

        .settings-card {
            padding: clamp(20px, 4vw, 36px);
            border: 1px solid var(--app-border);
            border-radius: 18px;
            background: var(--app-panel);
            color: var(--app-panel-text);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
        }

        .settings-card h3 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .settings-help {
            margin: 0 0 18px;
            opacity: 0.9;
        }

        .settings-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 30px;
            padding: 0;
            border: 0;
        }

        .palette-option {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            padding: 14px;
            border: 1px solid var(--app-border);
            border-radius: 12px;
            background: var(--app-surface);
            color: var(--app-text);
            cursor: pointer;
        }

        .palette-option input {
            flex: 0 0 auto;
            width: 20px;
            height: 20px;
            accent-color: var(--app-accent);
        }

        .palette-swatches {
            display: flex;
            flex: 0 0 auto;
            gap: 4px;
        }

        .palette-swatches span {
            width: 20px;
            height: 28px;
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 5px;
        }

        .settings-control {
            display: grid;
            grid-template-columns: minmax(150px, 230px) minmax(0, 1fr);
            align-items: center;
            gap: 16px;
            margin: 18px 0;
        }

        .settings-control label {
            font-weight: 700;
        }

        .settings-control select {
            min-height: 48px;
            padding: 10px 12px;
            border: 1px solid var(--app-border);
            border-radius: 9px;
            background: var(--app-control-bg);
            color: var(--app-control-text);
            font: inherit;
        }

        .dark-mode-control {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            font-weight: 700;
        }

        .dark-mode-control input {
            width: 22px;
            height: 22px;
            accent-color: var(--app-accent);
        }

        .settings-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .settings-actions button,
        .settings-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 11px 18px;
            border: 1px solid var(--app-accent);
            border-radius: 9px;
            background: var(--app-accent);
            color: #222;
            font: 700 16px Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }

        .settings-back {
            background: var(--app-surface);
            color: var(--app-text);
        }

        .settings-notice {
            margin: 0 0 18px;
            padding: 12px 15px;
            border: 1px solid #6d9b78;
            border-radius: 9px;
            background: #edf7ef;
            color: #23432b;
        }

        @media (max-width: 650px) {
            .settings-options {
                grid-template-columns: 1fr;
            }

            .settings-control {
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }
    </style>
</head>

<body class="settings-page">
    <header>
        <h1>
            <a href="../php/account.php">
                <img src="../img/neurodivergence.png" alt="Neurodivergence" width="250">
            </a>
            <nav>
                <a class="settings-back" href="account.php">Back to account</a>
            </nav>
        </h1>
    </header>

    <main class="settings-main">
        <div class="settings-heading">
            <h2>DISPLAY SETTINGS</h2>
            <p>Choose the colors and text style that feel comfortable for you.</p>
        </div>

        <?php if (!empty($_GET["saved"])): ?>
            <div class="settings-notice" role="status">
                Your display settings have been saved.
            </div>
        <?php elseif (!empty($_GET["error"])): ?>
            <div class="settings-notice" role="alert">
                <?php if ($_GET["error"] === "csrf"): ?>
                    The page expired. Please try saving your settings again.
                <?php else: ?>
                    Settings could not be saved. Please try again.
                <?php endif; ?>
            </div>
        <?php elseif (!empty($pageError)): ?>
            <div class="settings-notice" role="alert">
                <?= htmlspecialchars($pageError, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form class="settings-card" method="post" action="settings.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>"
            >

            <h3>Color palette</h3>
            <p class="settings-help">
                These palette colors are approximate matches to the images you provided.
            </p>

            <fieldset class="settings-options">
                <legend class="visually-hidden">Choose a color palette</legend>

                <?php foreach ($paletteLabels as $paletteKey => $paletteLabel): ?>
                    <?php
                    $swatches = [
                        "default" => ["#dedcee", "#393e41", "#c3a03e"],
                        "colors" => ["#b7d2e8", "#887c84", "#f7f7f4"],
                        "colors2" => ["#1c286e", "#f4e4ca", "#d4877b", "#b51b20"],
                        "color3" => ["#e6e9e9", "#3c4142", "#66828b"],
                        "color4" => ["#e4f5ec", "#b9c7bf", "#493e3a", "#cbd9ef"],
                        "color5" => ["#5c2416", "#e6301e", "#f0ae47", "#ffe27b"]
                    ][$paletteKey];
                    ?>
                    <label class="palette-option">
                        <input
                            type="radio"
                            name="palette"
                            value="<?= htmlspecialchars($paletteKey, ENT_QUOTES, "UTF-8") ?>"
                            <?= $preferences["theme_palette"] === $paletteKey ? "checked" : "" ?>
                        >
                        <span class="palette-swatches" aria-hidden="true">
                            <?php foreach ($swatches as $swatch): ?>
                                <span style="background-color: <?= htmlspecialchars($swatch, ENT_QUOTES, "UTF-8") ?>"></span>
                            <?php endforeach; ?>
                        </span>
                        <span><?= htmlspecialchars($paletteLabel, ENT_QUOTES, "UTF-8") ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <h3>Appearance</h3>

            <label class="dark-mode-control">
                <input
                    type="checkbox"
                    name="dark_mode"
                    value="1"
                    <?= (int)$preferences["dark_mode"] === 1 ? "checked" : "" ?>
                >
                <span>Use dark mode (EXPERIMENTAL, SOME TEXTS MAY BLEND IN)</span>
            </label>

            <div class="settings-control">
                <label for="font-family">Font</label>
                <select id="font-family" name="font_family">
                    <option value="system" <?= $preferences["font_family"] === "system" ? "selected" : "" ?>>
                        Standard system font
                    </option>
                    <option value="poppins" <?= $preferences["font_family"] === "poppins" ? "selected" : "" ?>>
                        Poppins
                    </option>
                </select>
            </div>

            <div class="settings-actions">
                <button type="submit">Save settings</button>
                <a class="settings-back" href="account.php">Back to account</a>
            </div>
        </form>
    </main>

    <footer>
        <div class="footer-contact">
            <p>CONTACT US AT:</p>
            <p>EMAIL: prohukm@hctm.ukm.edu.my</p>
            <p>PHONE NUMBER: 03-9145 5555 (OFFICE HOURS: 8:30AM - 6:00PM)</p>
        </div>
        <div class="footer-version"><p>VERSION: 1.0.2</p></div>
        <div class="footer-logo">
            <img src="../img/hctm.png" alt="HCTM Logo">
        </div>
    </footer>

    <script src="../javascript/theme.js" defer></script>
    <script>
/* PHP gives JavaScript the user's role */
const userRole = <?php echo json_encode($role_Type); ?>;
const userName = <?php echo json_encode($name); ?>;
/* Dashboard elements */
const dashboard =
    document.getElementById("dashboard");
const navBar =
    document.getElementById("navBar");
/* Displays Welcome Message without interfering with da rest*/
const roleKey = userRole.toLowerCase();

const welcomeHeading = document.createElement("h2");
welcomeHeading.textContent = `Welcome, ${userName}`;
dashboard.replaceChildren(welcomeHeading);

const navItems = {
    patient: [
        ["REPORT", "reports.php", "report_button", "Report"],
        ["REQUESTS", "requests.php", "request_button", "Request"],
        ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
        ["BOOK AN APPOINTMENT", "booking.php", "appointment_button", "appointments"],
        ["YOUR NOTES", "notes.php", "appointment_button", "appointments"]
    ],
    doctor: [
        ["REPORT", "reports.php", "report_button", "Report"],
        ["REQUESTS", "requests.php", "request_button", "Request"],
        ["SCHEDULE", "schedule.php", "neuro_button", "Neurodivergent"],
        ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
        ["NOTES", "notes.php", "appointment_button", "appointments"]
    ],
    admin: [
        ["APPOINTMENTS", "appointments.php", "appointment_button", "appointments"],
        ["SCHEDULE", "schedule.php", "neuro_button", "Neurodivergent"],
        ["MANAGEMENT", "management.php", "appointment_button", "appointments"]
    ]
};

if (navBar && navItems[roleKey]) {
    navBar.innerHTML = navItems[roleKey]
        .map(([label, href, containerClass, buttonClass]) => {
            let buttonStyle = "";

            if (buttonClass === "Report") {
                buttonStyle = "background-color: red;";
            } else if (buttonClass === "Request") {
                buttonStyle = "background-color: rgb(255, 192, 65);";
            }

            return `
                <div class="${containerClass}">
                    <button
                        class="${buttonClass}"
                        type="button"
                        style="${buttonStyle}"
                    >
                        <a href="${href}">${label}</a>
                    </button>
                </div>
            `;
        })
        .join("") + `
            <div class="dropdown">
    <button class="dropdown-btn" type="button">
        <a href="account.php">
            <img src="../img/login.png" alt="Account" width="45" height="45">
        </a>
    </button>
    <div class="dropdown-content">
        <a href="updates.php">Inbox</a>
        <a href="settings.php">Settings</a>
        ${roleKey !== "admin"
            ? '<a href="prescription.php">Your Prescriptions</a>'
            : ""}
        <a href="logout.php">Log Out</a>
    </div>
</div>
        `;
}
</script>

</body>
</html>