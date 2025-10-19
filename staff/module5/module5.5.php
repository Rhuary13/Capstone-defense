<?php
session_start();

/**
 * feedback.php
 * Staff role: Lead debriefing sessions on strengths & weaknesses
 * Tech Stack: PHP + MySQL + TailwindCSS + JavaScript
 * DB: simulation_event_planning
 */

// --- DB Connection ---
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// --- Ensure feedback table exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        strengths TEXT,
        weaknesses TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- Handle submission ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['feedback_submit'])) {
    $participant = trim($_POST['participant']);
    $strengths = trim($_POST['strengths']);
    $weaknesses = trim($_POST['weaknesses']);
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("INSERT INTO feedback (participant_name, strengths, weaknesses, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $participant, $strengths, $weaknesses, $notes);

    if ($stmt->execute()) {
        $message = "✅ Feedback submitted successfully!";
    } else {
        $message = "❌ Error submitting feedback.";
    }
    $stmt->close();
}

// --- Fetch feedback records ---
$records = $conn->query("SELECT * FROM feedback ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feedback - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">💬 Feedback & Debriefing</h1>
            <p class="text-gray-600 mb-6">Lead debriefing sessions and provide feedback on participant strengths and weaknesses.</p>

            <!-- Feedback Message -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Feedback Form -->
            <div class="bg-gray-50 p-4 rounded-lg shadow mb-6">
                <h2 class="text-lg font-semibold mb-3 text-gray-700">➕ New Feedback</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 font-medium">Participant Name</label>
                        <input type="text" name="participant" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Strengths</label>
                        <textarea name="strengths" rows="3" required
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-green-200"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Weaknesses</label>
                        <textarea name="weaknesses" rows="3" required
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-red-200"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Additional Notes (Optional)</label>
                        <textarea name="notes" rows="2"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"></textarea>
                    </div>
                    <button type="submit" name="feedback_submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Submit Feedback
                    </button>
                </form>
            </div>

            <!-- Feedback Records -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📋 Submitted Feedback</h2>
            <div class="space-y-3">
                <?php if ($records->num_rows > 0): ?>
                    <?php while ($f = $records->fetch_assoc()): ?>
                        <div class="bg-white border rounded-lg p-4 shadow-sm">
                            <p><span class="font-semibold">Participant:</span> <?= htmlspecialchars($f['participant_name']) ?></p>
                            <p><span class="font-semibold text-green-600">Strengths:</span> <?= nl2br(htmlspecialchars($f['strengths'])) ?></p>
                            <p><span class="font-semibold text-red-600">Weaknesses:</span> <?= nl2br(htmlspecialchars($f['weaknesses'])) ?></p>
                            <?php if ($f['notes']): ?>
                                <p><span class="font-semibold">Notes:</span> <?= nl2br(htmlspecialchars($f['notes'])) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500">Submitted: <?= $f['created_at'] ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No feedback submitted yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
