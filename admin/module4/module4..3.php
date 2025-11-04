<?php
// module5.3.php - Scoring System
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
// Handle Weight Updates
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weights'])) {
    foreach ($_POST['weights'] as $id => $weight) {
        $stmt = $pdo->prepare("UPDATE data_criteria SET weight = ? WHERE id = ?");
        $stmt->execute([(int)$weight, $id]);
    }
    $message = "✅ Score weightings updated successfully!";
}

// =========================
// Fetch Criteria + Scores
// =========================
$stmt = $pdo->query("SELECT * FROM data_criteria ORDER BY scenario, id");
$criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Example: simulate raw participant scores (in real app, fetch from participant_scores table)
foreach ($criteria as &$c) {
    $c['raw_score'] = rand(50, 100); // demo purpose
}

// =========================
// Compute Final Score
// =========================
$totalWeighted = 0;
$totalWeight = 0;
foreach ($criteria as $c) {
    $weight = $c['weight'] ?? 0;
    $raw = $c['raw_score'] ?? 0;
    $totalWeighted += $raw * ($weight / 100);
    $totalWeight += $weight;
}
$finalScore = ($totalWeight > 0) ? round($totalWeighted, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scoring - Final Scores</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Scoring: Generate Final Scores</h1>

    <?php if (!empty($message)): ?>
      <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Scoring Table -->
    <form method="POST">
      <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Criteria Weighting & Scores</h2>
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="bg-gray-200 text-gray-700">
              <th class="p-3 text-left">Scenario</th>
              <th class="p-3 text-left">Criterion</th>
              <th class="p-3 text-left">Weight (%)</th>
              <th class="p-3 text-left">Raw Score</th>
              <th class="p-3 text-left">Weighted Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($criteria as $c): ?>
              <?php 
                $weight = $c['weight'] ?? 0;
                $raw = $c['raw_score'] ?? 0;
                $weightedScore = round($raw * ($weight / 100), 2);
              ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-3"><?= htmlspecialchars($c['scenario']) ?></td>
                <td class="p-3"><?= htmlspecialchars($c['criterion']) ?></td>
                <td class="p-3">
                  <input type="number" name="weights[<?= $c['id'] ?>]" 
                         value="<?= $weight ?>" min="0" max="100"
                         class="w-20 border rounded-lg p-1 text-center">
                </td>
                <td class="p-3"><?= $raw ?></td>
                <td class="p-3"><?= $weightedScore ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="mt-4 flex items-center justify-between">
          <button type="submit" 
                  class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            Save Weights
          </button>
          <div class="text-lg font-semibold text-gray-800">
            Final Score: <span class="text-blue-600"><?= $finalScore ?></span>
          </div>
        </div>
      </div>
    </form>
  </main>
</body>
</html>
