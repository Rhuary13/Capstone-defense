<?php
// reports_analytics.php
session_start();

// --- Database Connection ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management"; // adjust if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Ensure table exists ---
$conn->query("CREATE TABLE IF NOT EXISTS records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id INT DEFAULT NULL,
  module_name VARCHAR(255) DEFAULT NULL,
  score DECIMAL(5,2) DEFAULT NULL,
  status ENUM('in_progress','completed','failed') DEFAULT 'in_progress',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- Fetch performance stats ---
$total_learners_res = $conn->query("SELECT COUNT(DISTINCT id) as total FROM records");
$total_learners = $total_learners_res && $total_learners_res->num_rows ? $total_learners_res->fetch_assoc()['total'] : 0;

$avg_score_res = $conn->query("SELECT AVG(score) as avg_score FROM records");
$avg_score = $avg_score_res && $avg_score_res->num_rows ? round($avg_score_res->fetch_assoc()['avg_score'], 2) : 0;

$completed_res = $conn->query("SELECT COUNT(*) as completed FROM records");
$completed = $completed_res->fetch_assoc()['completed'] ?? 0;

// --- Fetch chart data (monthly trend) ---
$chart_data_res = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, AVG(score) as avg_score
    FROM records
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

$months = [];
$scores = [];
if ($chart_data_res) {
    while ($row = $chart_data_res->fetch_assoc()) {
        $months[] = $row['month'];
        $scores[] = round($row['avg_score'], 2);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports & Analytics — Staff</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 h-screen font-sans text-slate-800 flex overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 lg:p-10 overflow-y-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Reports & Analytics</h1>
      <p class="text-sm text-slate-500 mt-1">Review learner performance trends and completion rates.</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
        <div class="p-3 bg-indigo-50 rounded-md"><i data-feather="users" class="w-6 h-6 text-indigo-600"></i></div>
        <div><div class="text-xs text-slate-400">Total Learners</div><div class="text-2xl font-semibold"><?php echo $total_learners; ?></div></div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
        <div class="p-3 bg-green-50 rounded-md"><i data-feather="bar-chart-2" class="w-6 h-6 text-green-600"></i></div>
        <div><div class="text-xs text-slate-400">Average Score</div><div class="text-2xl font-semibold"><?php echo $avg_score; ?>%</div></div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
        <div class="p-3 bg-amber-50 rounded-md"><i data-feather="check-circle" class="w-6 h-6 text-amber-600"></i></div>
        <div><div class="text-xs text-slate-400">Completed Modules</div><div class="text-2xl font-semibold"><?php echo $completed; ?></div></div>
      </div>
    </div>

    <!-- Chart -->
    <div class="bg-white p-6 rounded-lg shadow mb-6">
      <h2 class="text-lg font-medium mb-4">Average Scores Over Time</h2>
      <canvas id="performanceChart" height="120"></canvas>
    </div>

    <!-- Records Table -->
    <div class="bg-white rounded-lg shadow p-4">
      <h2 class="text-lg font-medium mb-4">Learner Records</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">User ID</th>
              <th class="p-3 border-b">Module</th>
              <th class="p-3 border-b">Score</th>
              <th class="p-3 border-b">Status</th>
              <th class="p-3 border-b">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $records_res = $conn->query("SELECT * FROM records ORDER BY created_at DESC LIMIT 20");
            if ($records_res && $records_res->num_rows > 0) {
              while ($r = $records_res->fetch_assoc()) {
                echo "<tr class='hover:bg-slate-50'>
                  <td class='p-3 border-b'>".htmlspecialchars($r['id'])."</td>
                  <td class='p-3 border-b'>".htmlspecialchars($r['module_name'])."</td>
                  <td class='p-3 border-b'>".htmlspecialchars($r['score'])."%</td>
                  <td class='p-3 border-b'>".ucfirst($r['status'])."</td>
                  <td class='p-3 border-b'>".date('Y-m-d', strtotime($r['created_at']))."</td>
                </tr>";
              }
            } else {
              echo "<tr><td colspan='5' class='p-4 text-center text-slate-500'>No records found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="https://unpkg.com/feather-icons"></script>
  <script>
    feather.replace();
    const ctx = document.getElementById('performanceChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
          label: 'Average Score (%)',
          data: <?php echo json_encode($scores); ?>,
          borderColor: '#4f46e5',
          backgroundColor: 'rgba(79,70,229,0.1)',
          tension: 0.3,
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true, position: 'top' } },
        scales: { y: { beginAtZero: true, max: 100 } }
      }
    });
  </script>
</body>
</html>
