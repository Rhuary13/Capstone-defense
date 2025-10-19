<?php
// reports.php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Ensure `progress` table exists
// =========================
$conn->query("CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('not started','in progress','completed') DEFAULT 'not started',
    score DECIMAL(5,2) DEFAULT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_progress (participant_id, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================
// Assume participant login session
// =========================
$participant_id = $_SESSION['user_id'] ?? 1;

// =========================
// Fetch participant summary
// =========================
$progress = $conn->query("
    SELECT 
        COUNT(*) AS total_modules,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_modules,
        AVG(score) AS avg_score
    FROM progress
    WHERE participant_id = $participant_id
")->fetch_assoc();

$total_modules     = $progress['total_modules'] ?? 0;
$completed_modules = $progress['completed_modules'] ?? 0;
$avg_score         = $progress['avg_score'] ? round($progress['avg_score'], 1) : 0;

$completion_rate = $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>📊 Reports & Analytics</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-8 overflow-y-auto">
    <header class="mb-8 text-center">
      <h1 class="text-3xl font-bold text-blue-800">📊 Reports & Analytics</h1>
      <p class="text-lg text-gray-600">This summary helps you understand your learning journey. The program continuously improves based on results.</p>
    </header>

    <!-- Overview Cards -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white shadow-lg rounded-xl p-6 text-center">
        <p class="text-2xl font-bold text-blue-700"><?= $completed_modules ?>/<?= $total_modules ?></p>
        <p class="text-gray-600">Modules Completed</p>
      </div>
      <div class="bg-white shadow-lg rounded-xl p-6 text-center">
        <p class="text-2xl font-bold text-green-600"><?= $completion_rate ?>%</p>
        <p class="text-gray-600">Completion Rate</p>
      </div>
      <div class="bg-white shadow-lg rounded-xl p-6 text-center">
        <p class="text-2xl font-bold text-purple-600"><?= $avg_score ?>%</p>
        <p class="text-gray-600">Average Score</p>
      </div>
    </section>

    <!-- Chart Section -->
    <section class="bg-white shadow-lg rounded-xl p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4 text-blue-800">Your Learning Analytics</h2>
      <canvas id="progressChart" height="100"></canvas>
    </section>

    <!-- Explanation for elderly users -->
    <section class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-xl">
      <h2 class="text-xl font-semibold text-yellow-700 mb-2">ℹ️ What does this mean?</h2>
      <p class="text-gray-700 leading-relaxed">
        These numbers show how much you’ve learned and completed. 
        The training program uses this information to improve future sessions, 
        so you get clearer lessons and better support.
      </p>
    </section>
  </main>

  <script>
    // Chart.js: Simple Completion vs Remaining
    const ctx = document.getElementById('progressChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Completed', 'Remaining'],
        datasets: [{
          data: [<?= $completed_modules ?>, <?= $total_modules - $completed_modules ?>],
          backgroundColor: ['#2563eb', '#e5e7eb'],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  </script>
</body>
</html>
