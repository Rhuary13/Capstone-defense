<?php
session_start();

/**
 * certification_criteria.php
 * Staff role: Verify participant eligibility
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

// --- Create tables if not exist ---
$conn->query("
    CREATE TABLE IF NOT EXISTS certification_criteria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        min_score INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        criteria_id INT,
        score INT NOT NULL,
        feedback TEXT,
        scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- Handle new certification criteria creation ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $min_score = intval($_POST['min_score']);

    $stmt = $conn->prepare("INSERT INTO certification_criteria (title, description, min_score) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $description, $min_score);
    if ($stmt->execute()) {
        $message = "✅ Certification criteria added successfully!";
    } else {
        $message = "❌ Error adding criteria.";
    }
    $stmt->close();
}

// --- Fetch criteria & participant scores ---
$criteria = $conn->query("SELECT * FROM certification_criteria ORDER BY created_at DESC");
$participants = $conn->query("SELECT participant_name, AVG(score) as avg_score FROM scores GROUP BY participant_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certification Criteria - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-gray-100">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">🎓 Certification Criteria</h1>
            <p class="text-gray-600 mb-6">Verify participant eligibility based on certification rules.</p>

            <!-- Feedback message -->
            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- New Criteria Form -->
            <div class="bg-gray-50 p-4 rounded-lg shadow mb-6">
                <h2 class="text-lg font-semibold mb-3 text-gray-700">➕ Add Certification Criteria</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 font-medium">Title</label>
                        <input type="text" name="title" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-medium">Minimum Score Required</label>
                        <input type="number" name="min_score" min="0" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                    </div>
                    <button type="submit" name="create"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Save Criteria
                    </button>
                </form>
            </div>

            <!-- Existing Criteria -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📋 Current Certification Criteria</h2>
            <div class="space-y-3 mb-6">
                <?php if ($criteria->num_rows > 0): ?>
                    <?php while ($c = $criteria->fetch_assoc()): ?>
                        <div class="bg-white border rounded-lg p-4 shadow-sm">
                            <p><span class="font-semibold">Title:</span> <?= htmlspecialchars($c['title']) ?></p>
                            <p><span class="font-semibold">Description:</span> <?= htmlspecialchars($c['description']) ?></p>
                            <p><span class="font-semibold">Minimum Score:</span> <?= $c['min_score'] ?></p>
                            <p class="text-xs text-gray-500">Created at: <?= $c['created_at'] ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No certification criteria defined yet.</p>
                <?php endif; ?>
            </div>

            <!-- Eligibility Check -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">✅ Participant Eligibility</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 rounded-lg shadow">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 border text-left">Participant</th>
                            <th class="px-4 py-2 border text-left">Average Score</th>
                            <th class="px-4 py-2 border text-left">Eligibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($participants->num_rows > 0): ?>
                            <?php while ($p = $participants->fetch_assoc()): ?>
                                <?php
                                    // Check against highest criteria min_score
                                    $criteria_check = $conn->query("SELECT MAX(min_score) as required FROM certification_criteria");
                                    $required = ($criteria_check && $criteria_check->num_rows > 0) ? $criteria_check->fetch_assoc()['required'] : 0;
                                    $eligible = $p['avg_score'] >= $required;
                                ?>
                                <tr class="border-t">
                                    <td class="px-4 py-2 border"><?= htmlspecialchars($p['participant_name']) ?></td>
                                    <td class="px-4 py-2 border"><?= round($p['avg_score'], 2) ?></td>
                                    <td class="px-4 py-2 border font-semibold 
                                        <?= $eligible ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $eligible ? 'Eligible ✅' : 'Not Eligible ❌' ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="px-4 py-2 text-center text-gray-500">No participants found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
