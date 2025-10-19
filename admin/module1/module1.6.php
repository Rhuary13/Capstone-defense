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
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// Fetch Modules
// =========================
$modules = [];
$res = $conn->query("SELECT id, title FROM training_modules ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $modules[] = $row;
}

// =========================
// Handle Prerequisite & Mandatory Updates
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mandatory   = $_POST['mandatory'] ?? [];
    $prerequisites = $_POST['prerequisite'] ?? [];

    // Reset all first
    $conn->query("UPDATE training_modules SET is_mandatory = 0, prerequisite_id = NULL");

    foreach ($mandatory as $mid) {
        $conn->query("UPDATE training_modules SET is_mandatory = 1 WHERE id = " . intval($mid));
    }

    foreach ($prerequisites as $mid => $pid) {
        if (!empty($pid)) {
            $conn->query("UPDATE training_modules SET prerequisite_id = " . intval($pid) . " WHERE id = " . intval($mid));
        }
    }

    $message = "✅ Completion rules updated.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Completion Tracking</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      height: 100vh;
      display: flex;
      overflow: hidden; /* Sidebar + Content */
    }
    main {
      height: 100vh;
      overflow-y: auto;
      flex: 1;
    }
  </style>
</head>
<body class="bg-gray-100">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="p-6 bg-gray-50">

    <div class="max-w-5xl mx-auto bg-white p-6 rounded-2xl shadow-lg">
      <h1 class="text-2xl font-bold text-blue-700 mb-6">📌 Completion Tracking</h1>

      <?php if (!empty($message)): ?>
        <div class="p-4 mb-6 bg-green-50 text-green-700 border border-green-200 rounded-lg">
          <?= $message ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-6">
        <table class="w-full border-collapse border border-gray-200 text-sm">
          <thead>
            <tr class="bg-gray-100">
              <th class="border px-4 py-2">Module</th>
              <th class="border px-4 py-2">Mandatory</th>
              <th class="border px-4 py-2">Prerequisite</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($modules as $m): ?>
              <tr>
                <td class="border px-4 py-2 font-semibold"><?= htmlspecialchars($m['title']) ?></td>
                <td class="border px-4 py-2 text-center">
                  <input type="checkbox" name="mandatory[]" value="<?= $m['id'] ?>">
                </td>
                <td class="border px-4 py-2">
                  <select name="prerequisite[<?= $m['id'] ?>]" class="w-full border rounded p-2">
                    <option value="">-- None --</option>
                    <?php foreach ($modules as $p): ?>
                      <?php if ($p['id'] != $m['id']): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="flex justify-end mt-6">
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            💾 Save Rules
          </button>
        </div>
      </form>
    </div>

  </main>
</body>
</html>
