<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// ----------------------
// CREATE FEEDBACK TABLE IF NOT EXISTS
// ----------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_id INT NOT NULL,
        category VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ----------------------
// ENSURE ALL REQUIRED COLUMNS EXIST
// ----------------------
$columnsRes = $conn->query("SHOW COLUMNS FROM feedback");
$columns = [];
while ($col = $columnsRes->fetch_assoc()) {
    $columns[] = $col['Field'];
}

if (!in_array('participant_id', $columns)) {
    $conn->query("ALTER TABLE feedback ADD COLUMN participant_id INT NOT NULL AFTER id");
}
if (!in_array('category', $columns)) {
    $conn->query("ALTER TABLE feedback ADD COLUMN category VARCHAR(100) NOT NULL AFTER participant_id");
}
if (!in_array('message', $columns)) {
    $conn->query("ALTER TABLE feedback ADD COLUMN message TEXT NOT NULL AFTER category");
}
if (!in_array('submitted_at', $columns)) {
    $conn->query("ALTER TABLE feedback ADD COLUMN submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER message");
}

// ----------------------
// HANDLE FEEDBACK SUBMISSION
// ----------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_feedback'])) {
    $category = $_POST['category'];
    $feedback = $conn->real_escape_string($_POST['feedback']);

    if (!empty($feedback)) {
        $stmt = $conn->prepare("INSERT INTO feedback (participant_id, category, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $category, $feedback);
        if ($stmt->execute()) {
            $message = "✅ Thank you! Your feedback has been submitted.";
        } else {
            $message = "❌ Error submitting feedback. Please try again.";
        }
        $stmt->close();
    } else {
        $message = "⚠️ Please write your feedback before submitting.";
    }
}

// ----------------------
// FETCH PREVIOUS FEEDBACK
// ----------------------
$feedbacks = [];
$sql = "SELECT category, message, submitted_at FROM feedback WHERE participant_id = ? ORDER BY submitted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data - Feedback & Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden bg-gray-100">

<!-- Sidebar -->
<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
</aside>

<!-- Navbar -->
<nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
        <i data-lucide="message-square" class="w-8 h-8 text-blue-600"></i>
        Data - Feedback & Reports
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow max-w-3xl mx-auto">

        <!-- Feedback Form -->
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Share Your Experience</h2>
        <?php if ($message): ?>
            <div class="mb-4 p-4 rounded-lg <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-lg font-medium text-gray-700 mb-1">Category</label>
                <select name="category" class="w-full border rounded-lg p-3 text-lg" required>
                    <option value="Training Module">Training Module</option>
                    <option value="Event Program">Event Program</option>
                    <option value="Simulation">Simulation</option>
                </select>
            </div>

            <div>
                <label class="block text-lg font-medium text-gray-700 mb-1">Your Feedback</label>
                <textarea name="feedback" rows="4" class="w-full border rounded-lg p-3 text-lg" placeholder="Write your experience here..." required></textarea>
            </div>

            <button type="submit" name="submit_feedback" class="w-full py-3 bg-blue-600 text-white text-xl rounded-lg hover:bg-blue-700">
                Submit Feedback
            </button>
        </form>

        <!-- Previous Feedback -->
        <h2 class="text-xl font-semibold text-gray-800 mt-10 mb-4">Your Previous Feedback</h2>
        <?php if (empty($feedbacks)): ?>
            <p class="text-gray-600">You haven’t submitted any feedback yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($feedbacks as $fb): ?>
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <p class="font-semibold text-gray-700">Category: <?= htmlspecialchars($fb['category']) ?></p>
                        <p class="text-gray-800 mt-2"><?= nl2br(htmlspecialchars($fb['message'])) ?></p>
                        <p class="text-sm text-gray-500 mt-2">Submitted: <?= date("M d, Y h:i A", strtotime($fb['submitted_at'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    lucide.createIcons();
</script>
</body>
</html>
