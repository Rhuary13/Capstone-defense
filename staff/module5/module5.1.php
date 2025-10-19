<?php
session_start();

/**
 * criteria_scoring.php
 * Staff role: Score participants based on criteria from admin
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

// --- Create scores table if not exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        criteria_id INT NOT NULL,
        score INT NOT NULL,
        feedback TEXT,
        scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (criteria_id) REFERENCES criteria(id) ON DELETE CASCADE
    )
");

// --- Handle scoring submission ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['score_submit'])) {
    $participant = trim($_POST['participant']);
    $criteria_id = intval($_POST['criteria_id']);
    $score = intval($_POST['score']);
    $feedback = trim($_POST['feedback']);

    $stmt = $conn->prepare("INSERT INTO scores (participant_name, criteria_id, score, feedback) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siis", $participant, $criteria_id, $score, $feedback);

    if ($stmt->execute()) {
        $message = "✅ Score submitted successfully!";
    } else {
        $message = "❌ Error submitting score.";
    }
    $stmt->close();
}

// --- Fetch criteria from DB (using title, description, disaster_type) ---
$criteria = $conn->query("SELECT * FROM criteria ORDER BY created_at ASC");

// --- Fetch existing scores ---
$scores = $conn->query("
    SELECT s.*, c.title AS criteria_title, c.description, c.disaster_type
    FROM scores s
    JOIN criteria c ON s.criteria_id = c.id
    ORDER BY s.scored_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Criteria Scoring - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">🏆 Criteria Scoring</h1>
            <p class="text-gray-600 mb-6">Score participant actions based on admin-defined rules.</p>

            <!-- Feedback message -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Scoring Form -->
            <div class="bg-gray-50 p-4 rounded-lg shadow mb-6">
                <h2 class="text-lg font-semibold mb-3 text-gray-700">➕ New Score Entry</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 font-medium">Participant Name</label>
                        <input type="text" name="participant" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Criteria</label>
                        <select name="criteria_id" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                            <option value="">-- Select Criteria --</option>
                            <?php while ($c = $criteria->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['title']) ?> - <?= htmlspecialchars($c['disaster_type']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Score</label>
                        <input type="number" name="score" min="0" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Feedback</label>
                        <textarea name="feedback" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"></textarea>
                    </div>
                    <button type="submit" name="score_submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Submit Score
                    </button>
                </form>
            </div>

            <!-- Scores List -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📋 Submitted Scores</h2>
            <div class="space-y-3">
                <?php if ($scores->num_rows > 0): ?>
                    <?php while ($s = $scores->fetch_assoc()): ?>
                        <div class="bg-white border rounded-lg p-4 shadow-sm">
                            <p><span class="font-semibold">Participant:</span> <?= htmlspecialchars($s['participant_name']) ?></p>
                            <p><span class="font-semibold">Criteria:</span> <?= htmlspecialchars($s['criteria_title']) ?></p>
                            <p><span class="font-semibold">Disaster Type:</span> <?= htmlspecialchars($s['disaster_type']) ?></p>
                            <p><span class="font-semibold">Score:</span> <?= $s['score'] ?></p>
                            <?php if ($s['feedback']): ?>
                                <p><span class="font-semibold">Feedback:</span> <?= htmlspecialchars($s['feedback']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500">Scored at: <?= $s['scored_at'] ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No scores submitted yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
