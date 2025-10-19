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
// CREATE ATTENDANCE TABLE IF NOT EXISTS
// ----------------------
$conn->query("
    CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT NOT NULL,
        status ENUM('Registered','Present','Absent') DEFAULT 'Registered',
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_event (user_id, event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ----------------------
// HANDLE PARTICIPATION REQUEST
// ----------------------
if (isset($_GET['participate'])) {
    $event_id = (int) $_GET['participate'];

    // Insert participation (ignore if already exists)
    $stmt = $conn->prepare("INSERT IGNORE INTO attendance (user_id, event_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $stmt->close();

    header("Location: module3.5.php?joined=1");
    exit;
}

// ----------------------
// FETCH ASSIGNED SCENARIO-BASED EVENTS
// ----------------------
$events = [];
$sql = "
    SELECT e.id, e.title, e.date, e.time, e.duration, e.location, e.facilitator, e.notes,
           (SELECT COUNT(*) FROM attendance a WHERE a.event_id = e.id) AS participants_count
    FROM events e
    WHERE e.type = 'Scenario-Based' AND e.approval_status = 'Approved'
    ORDER BY e.date DESC, e.time DESC
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }
    $res->free();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scenario Templates - Participate</title>
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
        <i data-lucide="clipboard-list" class="w-8 h-8 text-blue-600"></i>
        Scenario Templates
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Participate in Scenario-Based Exercises</h2>

        <?php if (isset($_GET['joined'])): ?>
            <div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg">
                You have successfully joined the exercise!
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <p class="text-gray-600">No scenario-based exercises are available right now.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3">Title</th>
                            <th class="p-3">Date</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Duration</th>
                            <th class="p-3">Location</th>
                            <th class="p-3">Facilitator</th>
                            <th class="p-3">Participants</th>
                            <th class="p-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= htmlspecialchars($event['title']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($event['date']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($event['time']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($event['duration']) ?> hrs</td>
                                <td class="p-3">
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['location']) ?>" 
                                       target="_blank" 
                                       class="text-blue-500 hover:underline flex items-center gap-1">
                                        <?= htmlspecialchars($event['location']) ?>
                                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                                    </a>
                                </td>
                                <td class="p-3"><?= htmlspecialchars($event['facilitator']) ?></td>
                                <td class="p-3"><?= $event['participants_count'] ?></td>
                                <td class="p-3">
                                    <a href="?participate=<?= $event['id'] ?>" 
                                       class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                       Participate
                                    </a>
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
