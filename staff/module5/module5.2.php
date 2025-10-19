<?php
session_start();

/**
 * data_entry.php
 * Staff role: Enter scores and notes
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
    CREATE TABLE IF NOT EXISTS data_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        score INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- Handle form submission ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['data_submit'])) {
    $participant = trim($_POST['participant']);
    $score = intval($_POST['score']);
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("INSERT INTO data_entries (participant_name, score, notes) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $participant, $score, $notes);

    if ($stmt->execute()) {
        $message = "✅ Data entry saved successfully!";
    } else {
        $message = "❌ Error saving data entry.";
    }
    $stmt->close();
}

// --- Fetch existing entries ---
$entries = $conn->query("SELECT * FROM data_entries ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Entry - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">📝 Data Entry</h1>
            <p class="text-gray-600 mb-6">Enter participant scores and notes for tracking.</p>

            <!-- Feedback message -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Data Entry Form -->
            <div class="bg-gray-50 p-4 rounded-lg shadow mb-6">
                <h2 class="text-lg font-semibold mb-3 text-gray-700">➕ New Entry</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 font-medium">Participant Name</label>
                        <input type="text" name="participant" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Score</label>
                        <input type="number" name="score" min="0" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Notes</label>
                        <textarea name="notes" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"></textarea>
                    </div>
                    <button type="submit" name="data_submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Save Entry
                    </button>
                </form>
            </div>

            <!-- Entries List -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📋 Saved Entries</h2>
            <div class="space-y-3">
                <?php if ($entries->num_rows > 0): ?>
                    <?php while ($e = $entries->fetch_assoc()): ?>
                        <div class="bg-white border rounded-lg p-4 shadow-sm">
                            <p><span class="font-semibold">Participant:</span> <?= htmlspecialchars($e['participant_name']) ?></p>
                            <p><span class="font-semibold">Score:</span> <?= $e['score'] ?></p>
                            <?php if ($e['notes']): ?>
                                <p><span class="font-semibold">Notes:</span> <?= htmlspecialchars($e['notes']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500">Entered at: <?= $e['created_at'] ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No entries recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
