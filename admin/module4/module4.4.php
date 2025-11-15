<?php
// module5.4.php - Reports System
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";  // change to your DB name
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// =========================
// Only Admin Access
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// =========================
// Fetch Scores (Example Table: participant_scores)
// =========================
$stmt = $pdo->query("SELECT * FROM participant_scores ORDER BY participant_name");
$scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
// Calculate Summary
// =========================
$total = count($scores);
$averageScore = $total > 0 ? round(array_sum(array_column($scores, 'final_score')) / $total, 2) : 0;
$passed = count(array_filter($scores, fn($s) => $s['final_score'] >= 75));
$failed = $total - $passed;

// =========================
// Export CSV
// =========================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="training_report.csv"');
    $output = fopen("php://output", "w");
    fputcsv($output, ["Participant", "Scenario", "Final Score", "Remarks"]);
    foreach ($scores as $row) {
        fputcsv($output, [
            $row['participant_name'],
            $row['scenario'],
            $row['final_score'],
            $row['final_score'] >= 75 ? "Pass" : "Fail"
        ]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports - Performance Results</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Reports: Performance Results</h1>

    <!-- Summary Section -->
    <div class="grid grid-cols-3 gap-6 mb-6">
      <div class="bg-white shadow rounded-lg p-4 text-center">
        <h2 class="text-lg font-semibold text-gray-600">Average Score</h2>
        <p class="text-2xl font-bold text-blue-600"><?= $averageScore ?></p>
      </div>
      <div class="bg-white shadow rounded-lg p-4 text-center">
        <h2 class="text-lg font-semibold text-gray-600">Passed</h2>
        <p class="text-2xl font-bold text-green-600"><?= $passed ?></p>
      </div>
      <div class="bg-white shadow rounded-lg p-4 text-center">
        <h2 class="text-lg font-semibold text-gray-600">Failed</h2>
        <p class="text-2xl font-bold text-red-600"><?= $failed ?></p>
      </div>
    </div>

    <!-- Participant Results -->
    <div class="bg-white shadow-md rounded-lg p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Participant Performance</h2>
        <a href="?export=csv" 
           class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
          Export CSV
        </a>
      </div>
      <table class="w-full border-collapse text-sm">
        <thead>
          <tr class="bg-gray-200 text-gray-700">
            <th class="p-3 text-left">Participant</th>
            <th class="p-3 text-left">Scenario</th>
            <th class="p-3 text-left">Final Score</th>
            <th class="p-3 text-left">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scores as $s): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="p-3"><?= htmlspecialchars($s['participant_name']) ?></td>
              <td class="p-3"><?= htmlspecialchars($s['scenario']) ?></td>
              <td class="p-3"><?= $s['final_score'] ?></td>
              <td class="p-3">
                <span class="<?= $s['final_score'] >= 75 ? 'text-green-600' : 'text-red-600' ?>">
                  <?= $s['final_score'] >= 75 ? 'Pass' : 'Fail' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($scores)): ?>
            <tr><td colspan="4" class="p-3 text-center text-gray-500">No results available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
