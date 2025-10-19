<?php
// history.php - Exercise History
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "simulation_event_planning";

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
// Fetch exercise history
// (Example: from a `exercise_history` table)
// =========================
$stmt = $pdo->query("
    SELECT id, exercise_name, disaster_type, conducted_on, participants_count, avg_score, notes
    FROM exercise_history
    ORDER BY conducted_on DESC
");
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Exercise History - Progress Tracking</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Exercise History & Progress Tracking</h1>

    <!-- History Records -->
    <div class="bg-white rounded-lg shadow-md p-6">
      <h2 class="text-xl font-semibold mb-4">Past Exercises</h2>
      <table class="w-full table-auto border-collapse">
        <thead>
          <tr class="bg-gray-200 text-left">
            <th class="p-2 border">ID</th>
            <th class="p-2 border">Exercise</th>
            <th class="p-2 border">Disaster Type</th>
            <th class="p-2 border">Date</th>
            <th class="p-2 border">Participants</th>
            <th class="p-2 border">Avg. Score</th>
            <th class="p-2 border">Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-2 border"><?= htmlspecialchars($h['id']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($h['exercise_name']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($h['disaster_type']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($h['conducted_on']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($h['participants_count']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($h['avg_score']) ?></td>
              <td class="p-2 border text-sm"><?= nl2br(htmlspecialchars($h['notes'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($history)): ?>
            <tr><td colspan="7" class="p-3 text-center text-gray-500">No past exercises recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
