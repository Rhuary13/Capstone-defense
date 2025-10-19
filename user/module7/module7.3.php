<?php
session_start();

// =========================
// Security: Participant-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// ✅ Get participant name from session
$participantName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? '');
if (empty($participantName)) {
    die("Participant name not found in session. Please log in again.");
}

// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Handle Issue Report Submission
// =========================
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['equipment_id'], $_POST['issue_description'])) {
    $equipmentId = intval($_POST['equipment_id']);
    $issue       = trim($_POST['issue_description']);

    if (!empty($issue)) {
        $stmt = $conn->prepare("INSERT INTO damage_reports (equipment_id, reported_by, issue_description, reported_at, status) VALUES (?, ?, ?, NOW(), 'Pending')");
        $stmt->bind_param("iss", $equipmentId, $participantName, $issue);
        if ($stmt->execute()) {
            $message = "✅ Your issue has been reported successfully!";
        } else {
            $message = "❌ Error: " . $conn->error;
        }
    } else {
        $message = "⚠️ Please describe the issue before submitting.";
    }
}

// =========================
// Fetch Equipment List
// =========================
$equipmentList = $conn->query("SELECT id, name FROM equipment ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Maintenance Tracker</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6">🛠️ Report Equipment Issue</h1>

    <div class="bg-white shadow-lg rounded-xl p-6 max-w-2xl">
        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 rounded-lg text-lg <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- Select Equipment -->
            <div>
                <label for="equipment_id" class="block text-lg font-semibold text-gray-700 mb-2">Select Equipment</label>
                <select id="equipment_id" name="equipment_id" required class="w-full p-3 border border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Choose Equipment --</option>
                    <?php while ($row = $equipmentList->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Issue Description -->
            <div>
                <label for="issue_description" class="block text-lg font-semibold text-gray-700 mb-2">Describe the Issue</label>
                <textarea id="issue_description" name="issue_description" rows="5" required
                    class="w-full p-3 border border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-red-500"
                    placeholder="Example: The radio is not turning on, the screen is cracked, missing parts, etc."></textarea>
            </div>

            <!-- Submit Button -->
            <div class="text-center">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-xl text-2xl font-bold shadow-md">
                    🚨 Report Issue
                </button>
            </div>
        </form>
    </div>
</main>

</body>
</html>
