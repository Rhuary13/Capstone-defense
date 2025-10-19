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
// CREATE REQUIRED TABLES IF THEY DON'T EXIST
// =========================

// Create 'staff' table
$sql_create_staff_table = "
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_staff_table)) {
    die("Error creating staff table: " . $conn->error);
}

// Create 'scenarios' table
$sql_create_scenarios_table = "
CREATE TABLE IF NOT EXISTS `scenarios` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `difficulty` ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL,
    `creator_id` INT(11) NOT NULL,
    `approval_status` ENUM('Pending','Approved') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`creator_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_scenarios_table)) {
    die("Error creating scenarios table: " . $conn->error);
}

// =========================
// SEED DATABASE WITH A DEFAULT ADMIN USER
// =========================
$email = 'admin@example.com';
$password_hash = password_hash('admin123', PASSWORD_DEFAULT);

$stmt_check = $conn->prepare("SELECT id FROM staff WHERE email = ?");
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    $stmt_insert = $conn->prepare("INSERT INTO staff (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $name = 'Default Admin';
    $stmt_insert->bind_param("sss", $name, $email, $password_hash);
    $stmt_insert->execute();
    $stmt_insert->close();
}
$stmt_check->close();

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// ADD / UPDATE SCENARIO
// ----------------------
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scenario'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid CSRF token.";
        $message_type = "error";
    } else {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $type = $_POST['type'];
        $difficulty = $_POST['difficulty'];
        $creator_id = $_SESSION['id'];
        $edit_mode = isset($_POST['id']) && !empty($_POST['id']);

        if ($edit_mode) {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE scenarios SET title=?, description=?, type=?, difficulty=? WHERE id=?");
            $stmt->bind_param("ssssi", $title, $description, $type, $difficulty, $id);
            if ($stmt->execute()) {
                $message = "Scenario updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating scenario: " . $stmt->error;
                $message_type = "error";
            }
        } else {
            // ======================================
            // UPDATED: Admin adds a new scenario, it gets approved immediately.
            // We set approval_status to 'Approved' and approved_at to NOW().
            // ======================================
            $stmt = $conn->prepare("INSERT INTO scenarios (title, description, type, difficulty, creator_id, approval_status, approved_at) VALUES (?, ?, ?, ?, ?, 'Approved', NOW())");
            $stmt->bind_param("ssssi", $title, $description, $type, $difficulty, $creator_id);
            if ($stmt->execute()) {
                $message = "Scenario added successfully and has been **approved**!";
                $message_type = "success";
            } else {
                $message = "Error adding scenario: " . $stmt->error;
                $message_type = "error";
            }
        }
        $stmt->close();
    }
}

// ----------------------
// APPROVE SCENARIO
// ----------------------
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE scenarios SET approval_status='Approved', approved_at=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=approved");
    exit;
}

// ----------------------
// DELETE SCENARIO
// ----------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scenarios WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
    exit;
}

// ----------------------
// FETCH SCENARIOS
// ----------------------
$scenarios = [];
$res = $conn->query("SELECT s.*, st.name as creator_name FROM scenarios s JOIN staff st ON s.creator_id = st.id ORDER BY created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $scenarios[] = $row;
    }
    $res->free();
}

// ----------------------
// FETCH SINGLE SCENARIO FOR EDIT
// ----------------------
$edit_mode = false;
$edit_scenario = null;
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scenarios WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_scenario = $result->fetch_assoc();
    $stmt->close();
    if (!$edit_scenario) $edit_mode = false;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disaster Simulation Frameworks</title>
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
            <i data-lucide="building-2" class="w-8 h-8 text-blue-600"></i>
            Disaster Simulation Frameworks
        </h1>
    </nav>
    
    <?php if (isset($_GET['msg'])): ?>
        <?php 
            $message_map = [
                'approved' => ['text' => 'Scenario approved successfully!', 'color' => 'green'],
                'deleted' => ['text' => 'Scenario deleted successfully.', 'color' => 'red'],
                'updated' => ['text' => 'Scenario updated successfully!', 'color' => 'green'],
            ];
            $msg = $message_map[$_GET['msg']] ?? ['text' => 'Action successful.', 'color' => 'blue'];
        ?>
        <div class="p-4 mb-4 text-<?= $msg['color'] ?>-800 bg-<?= $msg['color'] ?>-100 border border-<?= $msg['color'] ?>-300 rounded-lg"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <div class="p-4 mb-4 text-<?= $message_type ?>-800 bg-<?= $message_type ?>-100 border border-<?= $message_type ?>-300 rounded-lg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-xl shadow mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4"><?= $edit_mode ? "Edit Scenario" : "Add New Scenario" ?></h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?= $edit_scenario['id'] ?>"><?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" required value="<?= $edit_mode ? htmlspecialchars($edit_scenario['title']) : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="4" required class="w-full border px-3 py-2 rounded-lg mt-1"><?= $edit_mode ? htmlspecialchars($edit_scenario['description']) : '' ?></textarea>
            </div>
            
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Type</label>
                    <input type="text" name="type" required value="<?= $edit_mode ? htmlspecialchars($edit_scenario['type']) : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Difficulty</label>
                    <select name="difficulty" required class="w-full border px-3 py-2 rounded-lg mt-1">
                        <option value="Beginner" <?= ($edit_mode && $edit_scenario['difficulty'] == 'Beginner') ? 'selected' : '' ?>>Beginner</option>
                        <option value="Intermediate" <?= ($edit_mode && $edit_scenario['difficulty'] == 'Intermediate') ? 'selected' : '' ?>>Intermediate</option>
                        <option value="Advanced" <?= ($edit_mode && $edit_scenario['difficulty'] == 'Advanced') ? 'selected' : '' ?>>Advanced</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="save_scenario" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Scenario</button>
            <?php if ($edit_mode): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Scenario Templates</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-left">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-3">Title</th>
                        <th class="p-3">Type</th>
                        <th class="p-3">Difficulty</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Creator</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scenarios)): ?>
                        <tr>
                            <td colspan="6" class="p-3 text-center text-gray-500">No scenarios found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scenarios as $scenario): ?>
                            <tr class="border-b">
                                <td class="p-3"><?= htmlspecialchars($scenario['title']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($scenario['type']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($scenario['difficulty']) ?></td>
                                <td class="p-3">
                                    <?= $scenario['approval_status'] == 'Approved' 
                                        ? '<span class="text-green-600">Approved</span>' 
                                        : '<span class="text-yellow-600">Pending</span>' ?>
                                </td>
                                <td class="p-3"><?= htmlspecialchars($scenario['creator_name']) ?></td>
                                <td class="p-3 flex gap-2">
                                    <?php if ($scenario['approval_status'] == 'Pending'): ?>
                                        <a href="?approve=<?= $scenario['id'] ?>" class="text-blue-600 hover:underline">Approve</a>
                                    <?php endif; ?>
                                    <a href="?edit=<?= $scenario['id'] ?>" class="text-gray-600 hover:underline">Edit</a>
                                    <a href="?delete=<?= $scenario['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this scenario?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
<script>
    lucide.createIcons();
</script>
</body>
</html>