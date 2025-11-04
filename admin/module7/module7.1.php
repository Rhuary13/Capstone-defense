<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; // adjust if different
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// Only Admin Access
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// =========================
// Fetch participants who completed ALL modules
// (Assuming we have a `participants` table and a `completion` table/field)
// =========================
$sql = "
    SELECT p.id, p.name, p.role, p.program_id, p.completion_percent
    FROM participants p
    WHERE p.completion_percent = 100
    ORDER BY p.name ASC
";
$result = $conn->query($sql);
$completed_participants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Certification Criteria</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Certification Criteria</h1>

    <!-- Criteria Card -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
      <p class="text-gray-700 mb-4">
        To earn a <strong>Certificate</strong>, the participant must successfully meet the following requirements:
      </p>

      <ul class="list-disc pl-6 space-y-2 text-gray-800">
        <li>✅ Completion of all <strong>Training Modules</strong></li>
        <li>✅ Active participation in <strong>Training Sessions</strong></li>
        <li>✅ Successful involvement in <strong>Simulation Exercises</strong></li>
        <li>✅ Completion of required <strong>Programs</strong></li>
      </ul>

      <div class="mt-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-800 rounded">
        <p><strong>Note:</strong> All requirements must be fulfilled to qualify for certification.</p>
      </div>
    </div>

    <!-- Completed Participants -->
    <div class="bg-white shadow-md rounded-lg p-6">
      <h2 class="text-xl font-semibold mb-4">Participants Who Completed All Requirements</h2>

      <table class="w-full table-auto border-collapse mb-4">
        <thead>
          <tr class="bg-gray-200 text-left">
            <th class="p-2 border">ID</th>
            <th class="p-2 border">Name</th>
            <th class="p-2 border">Role</th>
            <th class="p-2 border">Program ID</th>
            <th class="p-2 border">Completion %</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($completed_participants)): ?>
            <?php foreach ($completed_participants as $cp): ?>
              <tr class="hover:bg-gray-50">
                <td class="p-2 border"><?= htmlspecialchars($cp['id']) ?></td>
                <td class="p-2 border"><?= htmlspecialchars($cp['name']) ?></td>
                <td class="p-2 border"><?= htmlspecialchars($cp['role']) ?></td>
                <td class="p-2 border"><?= htmlspecialchars($cp['program_id']) ?></td>
                <td class="p-2 border text-green-600 font-bold"><?= htmlspecialchars($cp['completion_percent']) ?>%</td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="p-3 text-center text-gray-500">No participants have completed all requirements yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="p-4 bg-green-50 border-l-4 border-green-600 text-green-800 rounded">
        <p>
          These are the participants who complete each <strong>Training Module, Training, Simulation, Program</strong>.<br>
          <strong>The Certificate will be issued and will be generated momentarily.</strong>
        </p>
      </div>
    </div>
  </main>
</body>
</html>
