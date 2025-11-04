<?php
session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- DB CONNECTION ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";  // change to your DB name
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf'];

// --- Handle Form Submission ---
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? '');

        if ($title === '' || $type === '') {
            $errors[] = "Title and type are required.";
        }

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO debriefing_materials (title, type, status) VALUES (?, ?, 'Pending')");
            $stmt->execute([$title, $type]);
            $success = "New material added successfully.";
        }
    }
}

// --- Fetch Existing Materials ---
$stmt = $pdo->query("SELECT * FROM debriefing_materials ORDER BY id DESC");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

function s($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Debriefing Materials - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
  
  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-8 overflow-y-auto">
    <!-- Header -->
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Debriefing Materials</h1>
    <p class="text-gray-600 mb-10">
      Facilitate post-exercise reflection and learning. As an <strong>Admin</strong>, you can approve feedback tools and discussion guides.
    </p>

    <!-- Messages -->
    <?php if ($errors): ?>
      <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg">
        <?= implode("<br>", array_map("s", $errors)); ?>
      </div>
    <?php elseif ($success): ?>
      <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg">
        <?= s($success); ?>
      </div>
    <?php endif; ?>

    <!-- Create New Material -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4">Add New Material</h2>
      <form method="post" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= $CSRF; ?>">

        <div>
          <label class="block text-sm font-medium text-gray-700">Title</label>
          <input type="text" name="title" required
                 class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Type</label>
          <select name="type" required
                  class="mt-1 w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2">
            <option value="Participant Feedback Form">Participant Feedback Form</option>
            <option value="Facilitator Discussion Guide">Facilitator Discussion Guide</option>
            <option value="After-Action Report Template">After-Action Report Template</option>
            <option value="Improvement Plan Worksheet">Improvement Plan Worksheet</option>
            <option value="Observation Log Sheet">Observation Log Sheet</option>
            <option value="Hotwash Guide">Hotwash Guide</option>
            <option value="Feedback Tool">Feedback Tool</option>
            <option value="Discussion Guide">Discussion Guide</option>
          </select>
        </div>

        <button type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Add Material
        </button>
      </form>
    </div>

    <!-- Existing Materials -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-xl font-semibold mb-4">Existing Materials</h2>

      <div class="space-y-4">
        <?php if ($materials): ?>
          <?php foreach ($materials as $mat): ?>
            <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between">
              <div>
                <h3 class="font-medium text-gray-800"><?= s($mat['title']); ?></h3>
                <p class="text-sm text-gray-500"><?= s($mat['type']); ?></p>
                <span class="text-xs px-2 py-1 rounded-full 
                  <?= $mat['status'] === 'Approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                  <?= s($mat['status']); ?>
                </span>
              </div>
              <div class="flex gap-2">
                <?php if ($mat['status'] !== 'Approved'): ?>
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= (int)$mat['id']; ?>">
                    <button type="submit"
                            class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                      Approve
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this material?');">
                  <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$mat['id']; ?>">
                  <button type="submit"
                          class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                    Delete
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-gray-500">No materials added yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
