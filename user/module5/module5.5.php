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
// FETCH PARTICIPANT HISTORY
// ----------------------
// We assume disaster-related progress is tracked in `attendance` + `events`
$history = [];
$sql = "
    SELECT e.title, e.date, e.time, e.location, a.status, a.attended_at
    FROM attendance a
    INNER JOIN events e ON a.event_id = e.id
    WHERE a.participant_id = ?
    ORDER BY e.date DESC, e.time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History - My Disaster Progress</title>
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
        <i data-lucide="clock-history" class="w-8 h-8 text-blue-600"></i>
        My Disaster Progress
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Your Participation History</h2>

        <?php if (empty($history)): ?>
            <p class="text-gray-600">You have not participated in any disaster-related exercises yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-lg">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700">
                            <th class="p-3 text-left">Event</th>
                            <th class="p-3 text-left">Date & Time</th>
                            <th class="p-3 text-left">Location</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">Attended At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= htmlspecialchars($h['title']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($h['date']) ?> <?= htmlspecialchars($h['time']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($h['location']) ?></td>
                                <td class="p-3">
                                    <?php if ($h['status'] === 'confirmed'): ?>
                                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm">Confirmed</span>
                                    <?php elseif ($h['status'] === 'pending'): ?>
                                        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-sm">Pending</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full bg-gray-200 text-gray-700 text-sm">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?= $h['attended_at'] ? date("M d, Y h:i A", strtotime($h['attended_at'])) : "Not yet" ?>
                                </td>
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
