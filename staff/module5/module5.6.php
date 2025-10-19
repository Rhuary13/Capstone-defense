<?php
session_start();

/**
 * history.php
 * Staff role: Compare current performance to past simulations
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

// --- Create history table if not exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS history_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        simulation_name VARCHAR(150) NOT NULL,
        score INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- Fetch grouped history (latest & past scores) ---
$records = $conn->query("
    SELECT participant_name, simulation_name, score, created_at
    FROM history_records
    ORDER BY participant_name, created_at DESC
");

$history_data = [];
if ($records->num_rows > 0) {
    while ($row = $records->fetch_assoc()) {
        $history_data[$row['participant_name']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">📊 Performance History</h1>
            <p class="text-gray-600 mb-6">Compare current participant performance with past simulations.</p>

            <?php if (empty($history_data)): ?>
                <p class="text-gray-500">No history records yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200 rounded-lg shadow">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-700 font-semibold border">Participant</th>
                                <th class="px-4 py-2 text-left text-gray-700 font-semibold border">Simulation</th>
                                <th class="px-4 py-2 text-left text-gray-700 font-semibold border">Score</th>
                                <th class="px-4 py-2 text-left text-gray-700 font-semibold border">Date</th>
                                <th class="px-4 py-2 text-left text-gray-700 font-semibold border">Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_data as $participant => $entries): ?>
                                <?php 
                                    $latest = $entries[0]; 
                                    $previous = $entries[1] ?? null;
                                    $trend = "";
                                    if ($previous) {
                                        if ($latest['score'] > $previous['score']) {
                                            $trend = "⬆️ Improved";
                                        } elseif ($latest['score'] < $previous['score']) {
                                            $trend = "⬇️ Declined";
                                        } else {
                                            $trend = "➡️ No Change";
                                        }
                                    } else {
                                        $trend = "🆕 First Record";
                                    }
                                ?>
                                <tr class="border-t">
                                    <td class="px-4 py-2 border"><?= htmlspecialchars($participant) ?></td>
                                    <td class="px-4 py-2 border"><?= htmlspecialchars($latest['simulation_name']) ?></td>
                                    <td class="px-4 py-2 border"><?= $latest['score'] ?></td>
                                    <td class="px-4 py-2 border text-sm text-gray-500"><?= $latest['created_at'] ?></td>
                                    <td class="px-4 py-2 border font-semibold 
                                        <?= strpos($trend, '⬆️') !== false ? 'text-green-600' : (strpos($trend, '⬇️') !== false ? 'text-red-600' : 'text-gray-600') ?>">
                                        <?= $trend ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php $conn->close(); ?>
