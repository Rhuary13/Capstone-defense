<?php
// module5.5.php - Feedback System (Participant + Staff Reflections)
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
// FILTER by module type
// =========================
$filter = $_GET['module'] ?? 'all';
$query = "
    SELECT pf.id, pf.module_type, pf.comments, pf.created_at, u.name AS participant_name, u.role AS user_role
    FROM participant_feedback pf
    JOIN users u ON pf.user_id = u.id
";
if ($filter !== 'all') {
    $stmt = $pdo->prepare($query . " WHERE pf.module_type = ? ORDER BY pf.created_at DESC");
    $stmt->execute([$filter]);
} else {
    $stmt = $pdo->query($query . " ORDER BY pf.created_at DESC");
}
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Feedback - Participant & Staff Reflections</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Feedback & Reflections</h1>

    <!-- Filter -->
    <div class="mb-6">
      <form method="get" class="flex items-center space-x-3">
        <label class="font-medium">Filter by Module:</label>
        <select name="module" class="border px-3 py-2 rounded">
          <option value="all" <?= $filter==='all' ? 'selected' : '' ?>>All</option>
          <option value="Training" <?= $filter==='Training' ? 'selected' : '' ?>>Training</option>
          <option value="Program" <?= $filter==='Program' ? 'selected' : '' ?>>Program</option>
          <option value="Simulation" <?= $filter==='Simulation' ? 'selected' : '' ?>>Simulation</option>
        </select>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Apply</button>
      </form>
    </div>

    <!-- Participant & Staff Feedback -->
    <div class="bg-white rounded-lg shadow-md p-6">
      <h2 class="text-xl font-semibold mb-4">What Participants & Staff Think Should Improve</h2>
      <table class="w-full table-auto border-collapse">
        <thead>
          <tr class="bg-gray-200 text-left">
            <th class="p-2 border">Name</th>
            <th class="p-2 border">Role</th>
            <th class="p-2 border">Module</th>
            <th class="p-2 border">Feedback</th>
            <th class="p-2 border">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feedbacks as $fb): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-2 border"><?= htmlspecialchars($fb['participant_name']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars(ucfirst($fb['user_role'])) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($fb['module_type']) ?></td>
              <td class="p-2 border"><?= nl2br(htmlspecialchars($fb['comments'])) ?></td>
              <td class="p-2 border text-sm text-gray-500"><?= htmlspecialchars($fb['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($feedbacks)): ?>
            <tr><td colspan="5" class="p-3 text-center text-gray-500">No feedback submitted yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
