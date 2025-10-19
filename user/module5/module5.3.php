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

// ----------------------
// FETCH PARTICIPANT NAME FROM USERS TABLE
// ----------------------
// 🔹 Replace `name` with the correct column in your `users` table!
$participant_name = "";
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($participant_name);
$stmt->fetch();
$stmt->close();

// ----------------------
// FETCH SCORES FOR USER
// ----------------------
$scores = [];
$sql = "
    SELECT s.criteria_id, s.score, s.feedback, s.scored_at
    FROM scores s
    WHERE s.participant_name = ?
    ORDER BY s.scored_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $participant_name);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $scores[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scoring - My Scores</title>
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
        <i data-lucide="award" class="w-8 h-8 text-blue-600"></i>
        My Scores
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Final Scores for <?= htmlspecialchars($participant_name) ?>
        </h2>

        <?php if (empty($scores)): ?>
            <p class="text-gray-600">You don’t have any scores yet. Please check back after evaluation.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3">Criteria</th>
                            <th class="p-3">Score</th>
                            <th class="p-3">Feedback</th>
                            <th class="p-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scores as $s): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3">Criteria #<?= htmlspecialchars($s['criteria_id']) ?></td>
                                <td class="p-3 font-semibold"><?= htmlspecialchars($s['score']) ?></td>
                                <td class="p-3 text-gray-600"><?= nl2br(htmlspecialchars($s['feedback'])) ?></td>
                                <td class="p-3"><?= date("M d, Y h:i A", strtotime($s['scored_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    lucide.createIcons();
</script>
</body>
</html>
