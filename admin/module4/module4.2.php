<?php
// module5.2.php - Data Collection & Mapping
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
// Handle Form Submission
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scenario = $_POST['scenario'];
    $criterion = $_POST['criterion'];
    $objective = $_POST['objective'];

    $stmt = $pdo->prepare("INSERT INTO data_criteria (scenario, criterion, objective) VALUES (?, ?, ?)");
    $stmt->execute([$scenario, $criterion, $objective]);

    $message = "New Data Criterion Added Successfully!";
}

// =========================
// Fetch Existing Records
// =========================
$criteria = $pdo->query("SELECT * FROM data_criteria ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Data Collection - Disaster Preparedness</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Data Collection & Mapping</h1>

    <?php if (!empty($message)): ?>
      <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Data Input Form -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Add Data Criterion</h2>
      <form method="POST" class="space-y-4">

        <!-- Scenario Type -->
        <div>
          <label class="block text-gray-700 font-medium mb-2">Disaster Scenario</label>
          <select name="scenario" required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-300">
            <option value="">-- Select Scenario --</option>
            <option value="Flood">Flood</option>
            <option value="Earthquake">Earthquake</option>
            <option value="Typhoon">Typhoon</option>
            <option value="Fire">Fire</option>
            <option value="Landslide">Landslide</option>
          </select>
        </div>

        <!-- Criterion -->
        <div>
          <label class="block text-gray-700 font-medium mb-2">Criterion</label>
          <input type="text" name="criterion" placeholder="e.g., Time to issue evacuation order"
                 required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-300">
        </div>

        <!-- Learning Objective -->
        <div>
          <label class="block text-gray-700 font-medium mb-2">Learning Objective</label>
          <input type="text" name="objective" placeholder="e.g., Improve inter-agency communication protocols"
                 required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-300">
        </div>

        <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
          Save Criterion
        </button>
      </form>
    </div>

    <!-- Existing Criteria Table -->
    <div class="bg-white shadow-md rounded-lg p-6">
      <h2 class="text-xl font-semibold mb-4">Configured Data Criteria</h2>
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-gray-200 text-gray-700">
            <th class="p-3 text-left">Scenario</th>
            <th class="p-3 text-left">Criterion</th>
            <th class="p-3 text-left">Learning Objective</th>
            <th class="p-3 text-left">Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($criteria as $row): ?>
            <tr class="border-b">
              <td class="p-3"><?= htmlspecialchars($row['scenario']) ?></td>
              <td class="p-3"><?= htmlspecialchars($row['criterion']) ?></td>
              <td class="p-3"><?= htmlspecialchars($row['objective']) ?></td>
              <td class="p-3"><?= htmlspecialchars($row['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
