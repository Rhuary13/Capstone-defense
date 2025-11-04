<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";  // change to your DB name
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// ============================
// AUTH CHECK (Admin only)
// ============================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ============================
// ADD CRITERIA
// ============================
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if (!empty($title) && !empty($description)) {
        $stmt = $pdo->prepare("INSERT INTO criteria (title, description) VALUES (:title, :description)");
        if ($stmt->execute(['title' => $title, 'description' => $description])) {
            $message = "✅ New criteria added successfully!";
        } else {
            $message = "❌ Error adding criteria.";
        }
    } else {
        $message = "⚠️ Please fill in all fields.";
    }
}

// ============================
// FETCH EXISTING CRITERIA
// ============================
$stmt = $pdo->query("SELECT * FROM criteria ORDER BY id DESC");
$criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Criteria - Standards for Disaster Response</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen overflow-hidden bg-gray-100">

  <!-- Sidebar -->
  <?php include __DIR__ . "/../sidebar.php"; ?>

  <!-- Main Content -->
  <main class="flex-1 p-8 overflow-y-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Criteria: Standards for Disaster Response</h1>

    <!-- Message -->
    <?php if ($message): ?>
      <div class="mb-4 p-3 rounded bg-blue-100 text-blue-800">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Add Criteria Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4">Add New Criteria</h2>
      <form method="POST" class="space-y-4">
        <div>
          <label class="block font-medium text-gray-700">Title</label>
          <input type="text" name="title" class="w-full px-3 py-2 border rounded-lg focus:ring focus:ring-blue-300" required>
        </div>
        <div>
          <label class="block font-medium text-gray-700">Description</label>
          <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring focus:ring-blue-300" required></textarea>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">Add Criteria</button>
      </form>
    </div>

    <!-- Existing Criteria -->
    <div class="bg-white rounded-lg shadow-md p-6">
      <h2 class="text-xl font-semibold mb-4">Existing Criteria</h2>
      <div class="overflow-x-auto">
        <table class="w-full table-auto border-collapse">
          <thead>
            <tr class="bg-gray-200 text-left">
              <th class="p-2 border">ID</th>
              <th class="p-2 border">Title</th>
              <th class="p-2 border">Description</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($criteria as $c): ?>
              <tr class="hover:bg-gray-50">
                <td class="p-2 border"><?= htmlspecialchars($c['id']) ?></td>
                <td class="p-2 border"><?= htmlspecialchars($c['title']) ?></td>
                <td class="p-2 border"><?= htmlspecialchars($c['description']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>

</html>
