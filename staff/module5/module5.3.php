<?php
session_start();

/**
 * scoring.php
 * Staff role: Validate participant and finalize scores
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

// --- Create table if not exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS final_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        exercise_title VARCHAR(200) NOT NULL,
        final_score INT NOT NULL,
        remarks TEXT,
        validated ENUM('yes','no') DEFAULT 'yes',
        finalized ENUM('yes','no') DEFAULT 'yes',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- Handle form submission ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['finalize_score'])) {
    $participant = trim($_POST['participant']);
    $exercise = trim($_POST['exercise']);
    $score = intval($_POST['score']);
    $remarks = trim($_POST['remarks']);

    $stmt = $conn->prepare("INSERT INTO final_scores (participant_name, exercise_title, final_score, remarks, validated, finalized) VALUES (?, ?, ?, ?, 'yes', 'yes')");
    $stmt->bind_param("ssis", $participant, $exercise, $score, $remarks);

    if ($stmt->execute()) {
        $message = "✅ Final score saved successfully!";
    } else {
        $message = "❌ Error saving final score.";
    }
    $stmt->close();
}

// --- Fetch finalized scores ---
$final_scores = $conn->query("SELECT * FROM final_scores ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Scoring - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">🏅 Final Scoring</h1>
            <p class="text-gray-600 mb-6">Validate participants and finalize their exercise scores.</p>

            <!-- Feedback message -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Finalize Score Form -->
            <div class="bg-gray-50 p-4 rounded-lg shadow mb-6">
                <h2 class="text-lg font-semibold mb-3 text-gray-700">➕ Finalize Score</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 font-medium">Participant Name</label>
                        <input type="text" name="participant" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Exercise Title</label>
                        <input type="text" name="exercise" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Final Score</label>
                        <input type="number" name="score" min="0" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Remarks</label>
                        <textarea name="remarks" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"></textarea>
                    </div>
                    <button type="submit" name="finalize_score"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Finalize Score
                    </button>
                </form>
            </div>

            <!-- Final Scores List -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📋 Finalized Scores</h2>
            <div class="space-y-3">
                <?php if ($final_scores->num_rows > 0): ?>
                    <?php while ($f = $final_scores->fetch_assoc()): ?>
                        <div class="bg-white border rounded-lg p-4 shadow-sm flex justify-between items-center">
                            <div>
                                <p><span class="font-semibold">Participant:</span> <?= htmlspecialchars($f['participant_name']) ?></p>
                                <p><span class="font-semibold">Exercise:</span> <?= htmlspecialchars($f['exercise_title']) ?></p>
                                <p><span class="font-semibold">Score:</span> <?= $f['final_score'] ?></p>
                                <?php if ($f['remarks']): ?>
                                    <p><span class="font-semibold">Remarks:</span> <?= htmlspecialchars($f['remarks']) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500">Finalized at: <?= $f['created_at'] ?></p>
                            </div>
                            <span class="text-green-600 font-bold text-lg">✅ Finalized</span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No finalized scores yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
