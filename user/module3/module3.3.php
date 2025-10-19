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

// ----------------------
// FETCH APPROVED EVENTS + CAPACITY + CURRENT COUNT
// ----------------------
$events = [];
$sql = "
    SELECT e.id, e.title, e.type, e.date, e.time, e.duration, e.location, e.facilitator, e.notes, e.capacity,
           COUNT(a.id) AS registered
    FROM events e
    LEFT JOIN attendance a ON e.id = a.event_id AND a.status = 'confirmed'
    WHERE e.approval_status = 'Approved'
    GROUP BY e.id
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
    <title>Capacity Management - View Availability</title>
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
        <i data-lucide="users" class="w-8 h-8 text-blue-600"></i>
        Capacity Management
    </h1>
</nav>

<!-- Main Content -->
<main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Available Events</h2>

        <?php if (empty($events)): ?>
            <p class="text-gray-600">No approved events available yet. Please check back later.</p>
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
                            <th class="p-3">Capacity</th>
                            <th class="p-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-semibold"><?= htmlspecialchars($event['title']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($event['type']) ?></td>
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
                                <td class="p-3">
                                    <?php 
                                        $registered = (int)$event['registered'];
                                        $capacity = (int)$event['capacity'];
                                        $remaining = $capacity - $registered;
                                    ?>
                                    <div class="flex flex-col">
                                        <span class="font-medium"><?= $registered ?> / <?= $capacity ?> registered</span>
                                        <?php if ($remaining > 0): ?>
                                            <span class="text-green-600 text-sm">Seats Available (<?= $remaining ?>)</span>
                                        <?php else: ?>
                                            <span class="text-red-600 text-sm font-semibold">Full</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3 text-gray-600"><?= nl2br(htmlspecialchars($event['notes'])) ?></td>
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
