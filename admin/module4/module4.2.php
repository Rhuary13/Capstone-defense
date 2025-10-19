<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// AUTH CHECK
// =========================
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// CREATE `variables` TABLE IF IT DOESN'T EXIST
// ----------------------
$sql_create_variables_table = "
CREATE TABLE IF NOT EXISTS `variables` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `scenario_id` INT(11) NOT NULL,
    `weather` VARCHAR(255) NOT NULL,
    `damage_level` ENUM('Minimal', 'Moderate', 'Severe', 'Catastrophic') NOT NULL,
    `casualty_level` ENUM('Low', 'Medium', 'High') NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`scenario_id`) REFERENCES `scenarios`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `scenario_id_unique` (`scenario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_variables_table)) {
    die("Error creating variables table: " . $conn->error);
}

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// HANDLE FORM SUBMISSION (SAVE VARIABLES)
// ----------------------
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_variables'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } else {
        $scenario_id = (int)$_POST['scenario_id'];
        $weather = $_POST['weather'];
        $damage_level = $_POST['damage_level'];
        $casualty_level = $_POST['casualty_level'];

        // Use INSERT...ON DUPLICATE KEY UPDATE to handle both create and update
        $stmt = $conn->prepare("
            INSERT INTO `variables` (`scenario_id`, `weather`, `damage_level`, `casualty_level`) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            `weather` = ?, `damage_level` = ?, `casualty_level` = ?
        ");
        $stmt->bind_param("issssss", $scenario_id, $weather, $damage_level, $casualty_level, $weather, $damage_level, $casualty_level);
        
        if ($stmt->execute()) {
            $message = "Scenario variables saved successfully!";
            $message_type = "success";
        } else {
            $message = "Error saving variables: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// ----------------------
// FETCH APPROVED SCENARIOS FOR DROPDOWN
// ----------------------
$scenarios = [];
$res = $conn->query("SELECT id, title FROM scenarios WHERE approval_status = 'Approved' ORDER BY title ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $scenarios[] = $row;
    }
    $res->free();
}

// ----------------------
// FETCH EXISTING VARIABLES FOR SELECTED SCENARIO (for edit)
// ----------------------
$selected_scenario_id = isset($_GET['scenario_id']) ? (int)$_GET['scenario_id'] : (isset($_POST['scenario_id']) ? (int)$_POST['scenario_id'] : null);
$existing_variables = null;

if ($selected_scenario_id) {
    $stmt = $conn->prepare("SELECT * FROM `variables` WHERE scenario_id = ?");
    $stmt->bind_param("i", $selected_scenario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_variables = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Variable Configuration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden">

<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
</aside>

<main class="flex-1 h-full overflow-y-auto p-8 bg-gray-100 pt-20">

    <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="sliders" class="w-8 h-8 text-blue-600"></i>
            Scenario Variable Configuration
        </h1>
    </nav>
    
    <?php if (!empty($message)): ?>
        <div class="p-4 mb-4 text-<?= $message_type ?>-800 bg-<?= $message_type ?>-100 border border-<?= $message_type ?>-300 rounded-lg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-xl shadow mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Select a Scenario</h2>
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label for="scenario_select" class="block text-sm font-medium text-gray-700">Approved Scenarios</label>
                <select id="scenario_select" name="scenario_id" required class="w-full border px-3 py-2 rounded-lg mt-1">
                    <option value="">-- Select a Scenario --</option>
                    <?php foreach ($scenarios as $scenario): ?>
                        <option value="<?= $scenario['id'] ?>" <?= ($selected_scenario_id == $scenario['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($scenario['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Load</button>
        </form>
    </div>

    <?php if ($selected_scenario_id): ?>
        <div class="bg-white p-6 rounded-xl shadow">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Configure Variables for "<?= htmlspecialchars($existing_variables['title'] ?? ($scenarios[array_search($selected_scenario_id, array_column($scenarios, 'id'))]['title'] ?? '')) ?>"</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="scenario_id" value="<?= $selected_scenario_id ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Weather Condition</label>
                    <input type="text" name="weather" required value="<?= htmlspecialchars($existing_variables['weather'] ?? '') ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Damage Level</label>
                    <select name="damage_level" required class="w-full border px-3 py-2 rounded-lg mt-1">
                        <option value="Minimal" <?= ($existing_variables['damage_level'] ?? '') == 'Minimal' ? 'selected' : '' ?>>Minimal</option>
                        <option value="Moderate" <?= ($existing_variables['damage_level'] ?? '') == 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                        <option value="Severe" <?= ($existing_variables['damage_level'] ?? '') == 'Severe' ? 'selected' : '' ?>>Severe</option>
                        <option value="Catastrophic" <?= ($existing_variables['damage_level'] ?? '') == 'Catastrophic' ? 'selected' : '' ?>>Catastrophic</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Casualty Level</label>
                    <select name="casualty_level" required class="w-full border px-3 py-2 rounded-lg mt-1">
                        <option value="Low" <?= ($existing_variables['casualty_level'] ?? '') == 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($existing_variables['casualty_level'] ?? '') == 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($existing_variables['casualty_level'] ?? '') == 'High' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                
                <button type="submit" name="save_variables" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Variables</button>
            </form>
        </div>
    <?php elseif (isset($_GET['scenario_id']) && !$selected_scenario_id): ?>
        <div class="p-4 mb-4 text-red-800 bg-red-100 border border-red-300 rounded-lg">Scenario not found or not approved.</div>
    <?php endif; ?>

</main>
<script>
    lucide.createIcons();
</script>
</body>
</html>