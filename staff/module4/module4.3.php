<?php
session_start();

/**
 * injects_decision_points.php
 * Staff role: Deliver injects & monitor participant responses
 * Tech Stack: PHP + MySQL + TailwindCSS + JavaScript
 * DB: simulation_event_planning
 */

// --- Configuration ---
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

// --- Connect to DB ---
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Create tables if not exist ---
$conn->query("
    CREATE TABLE IF NOT EXISTS injects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('pending','delivered') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inject_id INT NOT NULL,
        participant_name VARCHAR(100) NOT NULL,
        response TEXT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (inject_id) REFERENCES injects(id) ON DELETE CASCADE
    )
");

// --- Handle inject delivery ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['deliver'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE injects SET status='delivered' WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "✅ Inject delivered successfully!";
    } else {
        $message = "❌ Error delivering inject.";
    }
    $stmt->close();
}

// --- Handle new inject creation ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    $stmt = $conn->prepare("INSERT INTO injects (title, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $title, $description);
    if ($stmt->execute()) {
        $message = "✅ New inject created!";
    } else {
        $message = "❌ Error creating inject.";
    }
    $stmt->close();
}

// --- Fetch injects with responses ---
$injects = $conn->query("SELECT * FROM injects ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Injects & Decision Points - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    
    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">🎯 Injects & Decision Points</h1>
            <p class="text-gray-600 mb-6">Deliver injects and monitor participant responses in real-time.</p>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Create Inject -->
            <div class="mb-6">
                <button onclick="document.getElementById('injectModal').classList.remove('hidden')" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                    ➕ New Inject
                </button>
            </div>

            <!-- Inject List -->
            <div class="space-y-4">
                <?php while ($inject = $injects->fetch_assoc()): ?>
                    <?php $status = isset($inject['status']) ? $inject['status'] : 'pending'; ?>
                    <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($inject['title']) ?></h2>
                        <p class="text-gray-600 mb-2"><?= nl2br(htmlspecialchars($inject['description'])) ?></p>
                        <p class="text-sm <?= $status=='delivered' ? 'text-green-600' : 'text-yellow-600' ?>">
                            Status: <?= ucfirst($status) ?>
                        </p>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="id" value="<?= $inject['id'] ?>">
                            <button type="submit" name="deliver" 
                                    class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded-lg shadow"
                                    <?= $status=='delivered' ? 'disabled' : '' ?>>
                                Deliver Inject
                            </button>
                        </form>

                        <!-- Responses -->
                        <?php
                        $inject_id = $inject['id'];
                        $responses = $conn->query("SELECT * FROM responses WHERE inject_id=$inject_id ORDER BY submitted_at ASC");
                        ?>
                        <div class="mt-3">
                            <h3 class="font-medium text-gray-700">Participant Responses:</h3>
                            <?php if ($responses->num_rows > 0): ?>
                                <ul class="list-disc list-inside text-gray-600 mt-2 space-y-1">
                                    <?php while ($resp = $responses->fetch_assoc()): ?>
                                        <li>
                                            <span class="font-semibold"><?= htmlspecialchars($resp['participant_name']) ?>:</span> 
                                            <?= htmlspecialchars($resp['response']) ?> 
                                            <span class="text-xs text-gray-500">(<?= $resp['submitted_at'] ?>)</span>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm">No responses yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <!-- New Inject Modal -->
    <div id="injectModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-lg">
            <h2 class="text-xl font-bold mb-4">➕ Create New Inject</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-medium">Title</label>
                    <input type="text" name="title" required
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium">Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('injectModal').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-gray-300">Cancel</button>
                    <button type="submit" name="create"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Escape modal with ESC key
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                document.getElementById("injectModal").classList.add("hidden");
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
