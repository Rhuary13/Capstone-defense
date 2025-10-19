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
// FETCH PERSONAL HISTORY
// ----------------------
$history = [];
$sql = "
    SELECT e.title, e.type, e.date, e.time, e.duration, e.location, e.facilitator, e.notes, a.status, a.confirmed_at
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
    <title>Reporting - Personal History</title>
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
        <i data-lucide="file-text" class="w-8 h-8 text-blue-600"></i>
        Reporting - Personal History
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Your Event History</h2>

        <?php if (empty($history)): ?>
            <p class="text-gray-600">You have no event history yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Date</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Duration</th>
                            <th class="p-3">Location</th>
                            <th class="p-3">Facilitator</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Confirmed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= htmlspecialchars($row['title']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['type']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['date']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['time']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['duration']) ?> hrs</td>
                                <td class="p-3"><?= htmlspecialchars($row['location']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['facilitator']) ?></td>
                                <td class="p-3">
                                    <?php if ($row['status'] === 'confirmed'): ?>
                                        <span class="text-green-600 font-medium">Confirmed</span>
                                    <?php elseif ($row['status'] === 'pending'): ?>
                                        <span class="text-yellow-600 font-medium">Pending</span>
                                    <?php else: ?>
                                        <span class="text-gray-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3"><?= $row['confirmed_at'] ? htmlspecialchars($row['confirmed_at']) : '-' ?></td>
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
