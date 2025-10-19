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

$user_id = $_SESSION['user_id'];

// ----------------------
// FETCH DEBRIEFING MATERIALS ASSIGNED TO PARTICIPANT
// ----------------------
// Assume admin uploads debriefing per event after it ends
// Table: debriefings(id, event_id, feedback_text, resources, created_at)
$debriefs = [];
$sql = "
    SELECT d.id, e.title AS event_title, e.date, e.time, d.feedback_text, d.resources, d.created_at
    FROM debriefings d
    INNER JOIN events e ON d.event_id = e.id
    INNER JOIN attendance a ON a.event_id = e.id
    WHERE a.participant_id = ? 
    ORDER BY d.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $debriefs[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debriefing Materials - Participant</title>
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
        <i data-lucide="book-open" class="w-8 h-8 text-blue-600"></i>
        Debriefing Materials
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-6">Review Feedback & Reflections</h2>

        <?php if (empty($debriefs)): ?>
            <p class="text-gray-600">No debriefing materials available yet. Please check back after completing exercises.</p>
        <?php else: ?>
            <?php foreach ($debriefs as $d): ?>
                <div class="border rounded-lg p-6 mb-6 bg-gray-50 shadow-sm">
                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                        <?= htmlspecialchars($d['event_title']) ?>
                    </h3>
                    <p class="text-gray-600 mb-2">
                        <strong>Date:</strong> <?= htmlspecialchars($d['date']) ?> at <?= htmlspecialchars($d['time']) ?>
                    </p>
                    <p class="text-gray-600 mb-4">
                        <strong>Feedback:</strong><br>
                        <?= nl2br(htmlspecialchars($d['feedback_text'])) ?>
                    </p>

                    <?php if (!empty($d['resources'])): ?>
                        <p class="text-gray-600 mb-3"><strong>Resources:</strong></p>
                        <a href="<?= htmlspecialchars($d['resources']) ?>" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-2">
                            <i data-lucide="download" class="w-4 h-4"></i> View / Download
                        </a>
                    <?php endif; ?>

                    <p class="text-sm text-gray-400 mt-4">Added on <?= htmlspecialchars($d['created_at']) ?></p>
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
