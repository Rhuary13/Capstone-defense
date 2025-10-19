<?php
session_start();

/**
 * reports.php
 * Staff role: Create & Show performance results, share reports
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

// --- Ensure table exists (same as scoring) ---
$conn->query("
    CREATE TABLE IF NOT EXISTS final_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(100) NOT NULL,
        exercise_title VARCHAR(200) NOT NULL,
        final_score INT NOT NULL,
        remarks TEXT,
        validated ENUM('yes','no') DEFAULT 'yes',
        finalized ENUM('yes','no') DEFAULT 'yes',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- Fetch performance results ---
$results = $conn->query("
    SELECT participant_name, exercise_title, final_score, remarks, created_at 
    FROM final_scores
    ORDER BY created_at DESC
");

// --- Aggregate statistics ---
$stats = $conn->query("
    SELECT participant_name, AVG(final_score) as avg_score, COUNT(*) as exercises
    FROM final_scores
    GROUP BY participant_name
    ORDER BY avg_score DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Reports - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">📊 Performance Reports</h1>
            <p class="text-gray-600 mb-6">View participant results, generate summaries, and share reports.</p>

            <!-- Export Buttons -->
            <div class="mb-6 flex space-x-3">
                <button onclick="exportTableToCSV('performance_report.csv')"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                    ⬇️ Export CSV
                </button>
                <button onclick="window.print()"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg shadow">
                    🖨️ Print Report
                </button>
            </div>

            <!-- Aggregated Stats -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">🏅 Average Scores by Participant</h2>
            <div class="overflow-x-auto mb-8">
                <table id="reportTable" class="w-full border-collapse bg-white shadow rounded-lg">
                    <thead class="bg-gray-200 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 border">Participant</th>
                            <th class="px-4 py-2 border">Exercises Attended</th>
                            <th class="px-4 py-2 border">Average Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stats->num_rows > 0): ?>
                            <?php while ($s = $stats->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-100">
                                    <td class="px-4 py-2 border"><?= htmlspecialchars($s['participant_name']) ?></td>
                                    <td class="px-4 py-2 border text-center"><?= $s['exercises'] ?></td>
                                    <td class="px-4 py-2 border text-center font-semibold"><?= round($s['avg_score'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-gray-500">No performance data yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Chart -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📈 Performance Chart</h2>
            <canvas id="performanceChart" height="100" class="mb-8"></canvas>

            <!-- Detailed Results -->
            <h2 class="text-lg font-semibold mb-3 text-gray-700">📋 Detailed Results</h2>
            <div class="space-y-3">
                <?php if ($results->num_rows > 0): ?>
                    <?php while ($r = $results->fetch_assoc()): ?>
                        <div class="bg-gray-50 border rounded-lg p-4 shadow-sm">
                            <p><span class="font-semibold">Participant:</span> <?= htmlspecialchars($r['participant_name']) ?></p>
                            <p><span class="font-semibold">Exercise:</span> <?= htmlspecialchars($r['exercise_title']) ?></p>
                            <p><span class="font-semibold">Score:</span> <?= $r['final_score'] ?></p>
                            <?php if ($r['remarks']): ?>
                                <p><span class="font-semibold">Remarks:</span> <?= htmlspecialchars($r['remarks']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500">Finalized: <?= $r['created_at'] ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No results available.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- CSV Export Script -->
    <script>
        function exportTableToCSV(filename) {
            let csv = [];
            let rows = document.querySelectorAll("table tr");
            for (let row of rows) {
                let cols = row.querySelectorAll("td, th");
                let rowData = [];
                for (let col of cols) {
                    rowData.push(col.innerText.replace(/,/g, ""));
                }
                csv.push(rowData.join(","));
            }
            let csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
            let downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();
        }
    </script>

    <!-- Chart Script -->
    <script>
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const chartData = {
            labels: [<?php
                $stats->data_seek(0);
                $labels = [];
                while ($row = $stats->fetch_assoc()) {
                    $labels[] = "'" . $row['participant_name'] . "'";
                }
                echo implode(",", $labels);
            ?>],
            datasets: [{
                label: 'Average Score',
                data: [<?php
                    $stats->data_seek(0);
                    $values = [];
                    while ($row = $stats->fetch_assoc()) {
                        $values[] = round($row['avg_score'], 2);
                    }
                    echo implode(",", $values);
                ?>],
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        };
        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

</body>
</html>
<?php $conn->close(); ?>
