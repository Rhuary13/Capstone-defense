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

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

// This will map your logged-in user to participant_id in attendance
$participant_id = $_SESSION['user_id'];

// ----------------------
// CREATE RESPONSES TABLE IF NOT EXISTS
// ----------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_id INT NOT NULL,
        event_id INT NOT NULL,
        condition_text TEXT NOT NULL,
        response_text TEXT,
        responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ----------------------
// HANDLE RESPONSE SUBMISSION
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_response'])) {
    $event_id = (int) $_POST['event_id'];
    $condition_text = $conn->real_escape_string($_POST['condition_text']);
    $response_text = $conn->real_escape_string($_POST['response_text']);

    $stmt = $conn->prepare("INSERT INTO responses (participant_id, event_id, condition_text, response_text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $participant_id, $event_id, $condition_text, $response_text);
    $stmt->execute();
    $stmt->close();

    header("Location: module4.2.php?submitted=1");
    exit;
}

// ----------------------
// FETCH ASSIGNED SCENARIO EVENTS WITH CONDITIONS
// ----------------------
$events = [];
$sql = "
    SELECT e.id, e.title, e.date, e.time, e.duration, e.location, e.facilitator, e.notes
    FROM events e
    INNER JOIN attendance a ON e.id = a.event_id
    WHERE a.participant_id = ? 
      AND e.type = 'Scenario-Based' 
      AND e.approval_status = 'Approved'
    ORDER BY e.date DESC, e.time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $participant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Variable Configuration - Respond to Conditions</title>
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
        <i data-lucide="sliders" class="w-8 h-8 text-blue-600"></i>
        Variable Configuration
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Respond to Scenario Conditions</h2>

        <?php if (isset($_GET['submitted'])): ?>
            <div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg">
                Your response has been submitted successfully!
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <p class="text-gray-600">You are not assigned to any scenario exercises yet.</p>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="border rounded-lg p-4 mb-6 bg-gray-50">
                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                        <?= htmlspecialchars($event['title']) ?>
                    </h3>
                    <p class="text-gray-600 mb-2"><strong>Date:</strong> <?= htmlspecialchars($event['date']) ?> at <?= htmlspecialchars($event['time']) ?></p>
                    <p class="text-gray-600 mb-2"><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
                    <p class="text-gray-600 mb-4"><strong>Condition:</strong> <?= nl2br(htmlspecialchars($event['notes'])) ?></p>

                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <input type="hidden" name="condition_text" value="<?= htmlspecialchars($event['notes']) ?>">

                        <label class="block text-sm font-medium text-gray-700">Your Response:</label>
                        <textarea name="response_text" rows="3" required class="w-full border px-3 py-2 rounded-lg"></textarea>

                        <button type="submit" name="submit_response" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Submit Response
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    lucide.createIcons();
</script>
</body>
</html>
