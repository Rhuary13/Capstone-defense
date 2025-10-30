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

$participant_id = $_SESSION['id'];

// ----------------------
// CREATE decisions TABLE IF NOT EXISTS
// ----------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS decisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_id INT NOT NULL,
        inject_id INT NOT NULL,
        decision_text TEXT NOT NULL,
        decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ----------------------
// HANDLE DECISION SUBMISSION
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_decision'])) {
    $inject_id = (int) $_POST['inject_id'];
    $decision_text = $conn->real_escape_string($_POST['decision_text']);

    $stmt = $conn->prepare("INSERT INTO decisions (participant_id, inject_id, decision_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $participant_id, $inject_id, $decision_text);
    $stmt->execute();
    $stmt->close();

    header("Location: module4.3.php?submitted=1");
    exit;
}

// ----------------------
// FETCH ACTIVE INJECTS
// ----------------------
// (Assume `injects` table exists with id, title, description, event_id, created_at)
$injects = [];
$sql = "
    SELECT i.id, i.title, i.description, i.created_at, e.title AS event_title
    FROM injects i
    INNER JOIN events e ON i.event_id = e.id
    WHERE e.approval_status = 'Approved'
    ORDER BY i.created_at DESC
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $injects[] = $row;
    }
    $res->free();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Injects & Decision Points</title>
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
        <i data-lucide="alert-triangle" class="w-8 h-8 text-red-600"></i>
        Injects & Decision Points
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Make Your Decisions</h2>

        <?php if (isset($_GET['submitted'])): ?>
            <div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg text-lg">
                ✅ Your decision has been submitted successfully!
            </div>
        <?php endif; ?>

        <?php if (empty($injects)): ?>
            <p class="text-gray-600 text-lg">No injects available at the moment. Please wait for updates.</p>
        <?php else: ?>
            <?php foreach ($injects as $inject): ?>
                <div class="border rounded-xl p-6 mb-6 bg-gray-50 shadow-sm">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">
                        <?= htmlspecialchars($inject['title']) ?>
                    </h3>
                    <p class="text-gray-700 mb-2"><strong>Event:</strong> <?= htmlspecialchars($inject['event_title']) ?></p>
                    <p class="text-gray-600 mb-4"><?= nl2br(htmlspecialchars($inject['description'])) ?></p>

                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="inject_id" value="<?= $inject['id'] ?>">

                        <label class="block text-lg font-medium text-gray-700">Your Decision:</label>
                        <textarea name="decision_text" rows="3" required
                            class="w-full border px-3 py-2 rounded-lg text-lg"></textarea>

                        <button type="submit" name="submit_decision"
                            class="w-full px-6 py-3 text-xl bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Submit Decision
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
