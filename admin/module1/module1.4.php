<?php
session_start();

// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = ""; // your MySQL password if any
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// FETCH RECORDS
// ----------------------
$records = [];
$sql = "
    SELECT 
        p.id AS participant_id,
        p.name AS full_name,
        pr.score,
        pr.status
    FROM participant_records pr
    LEFT JOIN participants p ON pr.participant_id = p.id
    ORDER BY pr.id DESC
";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $records[] = $row;
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Training & Simulation Records</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">
  <!-- Sidebar -->
  <?php include "../sidebar.php"; ?>

  <!-- Main Content -->
  <div class="flex-1 p-6">
    <h1 class="text-2xl font-bold mb-6">📊 Training & Simulation Records</h1>

    <div class="bg-white shadow-md rounded-lg p-4 overflow-x-auto">
      <table class="w-full border-collapse text-left">
        <thead>
          <tr class="bg-blue-600 text-white">
            <th class="p-3">Participant</th>
            <th class="p-3">Training Module</th>
            <th class="p-3">Program</th>
            <th class="p-3">Simulation</th>
            <th class="p-3">Score</th>
            <th class="p-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($records)): ?>
            <?php foreach ($records as $rec): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($rec['full_name']) ?></td>
                <td class="p-3"><?= isset($rec['training_module']) ? htmlspecialchars($rec['training_module']) : '-' ?></td>
                <td class="p-3"><?= isset($rec['program_name']) ? htmlspecialchars($rec['program_name']) : '-' ?></td>
                <td class="p-3"><?= isset($rec['simulation_title']) ? htmlspecialchars($rec['simulation_title']) : '-' ?></td>
                <td class="p-3"><?= htmlspecialchars($rec['score']) ?>%</td>
                <td class="p-3">
                  <span class="px-2 py-1 rounded text-white 
                    <?= $rec['status'] === 'completed' ? 'bg-green-500' : 
                        ($rec['status'] === 'in-progress' ? 'bg-yellow-500' : 'bg-red-500') ?>">
                    <?= ucfirst($rec['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center p-4 text-gray-500">No records found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
