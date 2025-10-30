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
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

$id = $_SESSION['id'];
$message = "";

// ----------------------
// CREATE feedback_debrief TABLE IF NOT EXISTS
// ----------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS feedback_debrief (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_id INT NOT NULL,
        reflection TEXT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ----------------------
// HANDLE SUBMISSION
// ----------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_reflection'])) {
    $reflection = $conn->real_escape_string($_POST['reflection']);

    if (!empty($reflection)) {
        $stmt = $conn->prepare("INSERT INTO feedback_debrief (participant_id, reflection) VALUES (?, ?)");
        $stmt->bind_param("is", $id, $reflection);
        if ($stmt->execute()) {
            $message = "✅ Thank you! Your reflection has been saved.";
        } else {
            $message = "❌ Error saving reflection. Please try again.";
        }
        $stmt->close();
    } else {
        $message = "⚠️ Please write your reflection before submitting.";
    }
}

// ----------------------
// FETCH PREVIOUS REFLECTIONS
// ----------------------
$reflections = [];
$sql = "SELECT reflection, submitted_at FROM feedback_debrief WHERE participant_id = ? ORDER BY submitted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reflections[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debriefing Feedback</title>
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
        <i data-lucide="clipboard-list" class="w-8 h-8 text-blue-600"></i>
        Debriefing Feedback
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow max-w-3xl mx-auto">

        <!-- Reflection Form -->
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Reflect on Your Performance</h2>
        <?php if ($message): ?>
            <div class="mb-4 p-4 rounded-lg <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-lg font-medium text-gray-700 mb-2">Your Reflection</label>
                <textarea name="reflection" rows="5" class="w-full border rounded-lg p-3 text-lg" placeholder="Write about what you learned and how you can improve..." required></textarea>
            </div>

            <button type="submit" name="submit_reflection" class="w-full py-3 bg-blue-600 text-white text-xl rounded-lg hover:bg-blue-700">
                Submit Reflection
            </button>
        </form>

        <!-- Previous Reflections -->
        <h2 class="text-xl font-semibold text-gray-800 mt-10 mb-4">Your Previous Reflections</h2>
        <?php if (empty($reflections)): ?>
            <p class="text-gray-600">You haven’t submitted any reflections yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($reflections as $r): ?>
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <p class="text-gray-800 text-lg"><?= nl2br(htmlspecialchars($r['reflection'])) ?></p>
                        <p class="text-sm text-gray-500 mt-2">Submitted: <?= date("M d, Y h:i A", strtotime($r['submitted_at'])) ?></p>
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
