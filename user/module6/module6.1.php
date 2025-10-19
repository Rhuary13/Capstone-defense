<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ----------------------
// FETCH PARTICIPANT INFO
// ----------------------
$sql = "
    SELECT id, name, role, program_id, completion_percent
    FROM participants
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$participant = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Certification</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden bg-gray-100">

  <!-- Sidebar -->
  <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
  </aside>

  <!-- Navbar -->
  <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
      <i data-lucide="certificate" class="w-8 h-8 text-blue-600"></i>
      My Certification
    </h1>
  </nav>

  <!-- Main Content -->
  <main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">Certification Requirements</h2>
      <ul class="list-disc pl-6 space-y-2 text-gray-700">
        <li>✅ Completion of all <strong>Training Modules</strong></li>
        <li>✅ Active participation in <strong>Training Sessions</strong></li>
        <li>✅ Successful involvement in <strong>Simulation Exercises</strong></li>
        <li>✅ Completion of required <strong>Programs</strong></li>
      </ul>
      <div class="mt-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-800 rounded">
        <p><strong>Note:</strong> You must complete all requirements to qualify for certification.</p>
      </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">My Progress</h2>

      <?php if (!$participant): ?>
        <p class="text-gray-600">We could not find your participant record.</p>
      <?php else: ?>
        <div class="flex items-center justify-between mb-6">
          <p class="text-lg font-medium text-gray-700">
            Hello, <span class="font-bold"><?= htmlspecialchars($participant['name']) ?></span>
          </p>
          <p class="text-gray-600">
            Completion: 
            <span class="font-bold text-blue-600"><?= htmlspecialchars($participant['completion_percent']) ?>%</span>
          </p>
        </div>

        <?php if ($participant['completion_percent'] == 100): ?>
          <!-- Certificate Card -->
          <div class="border-2 border-green-600 rounded-xl p-6 text-center bg-green-50">
            <h3 class="text-2xl font-bold text-green-700 mb-4">🎉 Congratulations!</h3>
            <p class="text-gray-700 mb-2">
              You have successfully completed all requirements.
            </p>
            <p class="text-lg font-semibold text-gray-800">
              Certificate of Completion
            </p>
            <p class="text-sm text-gray-600 mt-2">
              Awarded to <span class="font-bold"><?= htmlspecialchars($participant['name']) ?></span><br>
              Program ID: <?= htmlspecialchars($participant['program_id']) ?>
            </p>
          </div>
        <?php else: ?>
          <!-- Progress Info -->
          <div class="p-6 border rounded-lg bg-yellow-50 text-yellow-800">
            <p class="font-semibold">You are still working towards your certification.</p>
            <p>Please complete all modules, training sessions, and simulations to earn your certificate.</p>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>
