<?php
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
// Security check
// =========================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// =========================
// Get total modules
// =========================
$totalModulesQuery = $conn->query("SELECT COUNT(*) AS total FROM training_modules");
$totalModulesRow   = $totalModulesQuery->fetch_assoc();
$totalModules      = $totalModulesRow['total'] ?? 0;

// =========================
// Get completed modules by this participant
// =========================
$completedQuery = $conn->prepare("SELECT COUNT(DISTINCT lesson_id) AS completed 
                                  FROM quiz_results 
                                  WHERE participant_id = ? AND status = 'Passed'");
$completedQuery->bind_param("i", $user_id);
$completedQuery->execute();
$completedResult = $completedQuery->get_result()->fetch_assoc();
$completedModules = $completedResult['completed'] ?? 0;
$completedQuery->close();

// =========================
// Find next module not completed
// =========================
$nextStep = "🎉 You have finished all available training modules!";
if ($completedModules < $totalModules) {
    $nextModuleQuery = $conn->prepare("
        SELECT id, title 
        FROM training_modules 
        WHERE id NOT IN (
            SELECT lesson_id 
            FROM quiz_results 
            WHERE participant_id = ? AND status = 'Passed'
        )
        ORDER BY id ASC 
        LIMIT 1
    ");
    $nextModuleQuery->bind_param("i", $user_id);
    $nextModuleQuery->execute();
    $nextModuleResult = $nextModuleQuery->get_result()->fetch_assoc();
    if ($nextModuleResult) {
        $nextStep = "📌 Your next step: <strong>" . htmlspecialchars($nextModuleResult['title']) . "</strong>";
    }
    $nextModuleQuery->close();
}

// =========================
// Calculate progress percentage
// =========================
$progressPercent = ($totalModules > 0) ? round(($completedModules / $totalModules) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Progress & Completion Tracking</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto h-screen">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-2xl shadow-lg">
      <h1 class="text-3xl font-bold text-blue-700 mb-6 text-center">📊 Progress & Completion Tracking</h1>

      <!-- Progress Overview -->
      <div class="mb-6">
        <p class="text-lg mb-2">Modules Completed: 
          <strong class="text-green-700"><?= $completedModules ?></strong> / 
          <strong><?= $totalModules ?></strong>
        </p>

        <!-- Progress Bar -->
        <div class="w-full bg-gray-200 rounded-full h-6">
          <div class="bg-green-500 h-6 rounded-full text-right pr-2 text-white font-bold"
               style="width: <?= $progressPercent ?>%">
            <?= $progressPercent ?>%
          </div>
        </div>
      </div>

      <!-- Next Step -->
      <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-lg text-lg">
        <?= $nextStep ?>
      </div>

      <!-- Simple Tips -->
      <div class="mt-6 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded-lg">
        <h2 class="font-bold text-yellow-700 mb-2">💡 Tip</h2>
        <p>Click on the lesson in the sidebar to continue your training. Take your time — one step at a time!</p>
      </div>
    </div>
  </main>
</body>
</html>
