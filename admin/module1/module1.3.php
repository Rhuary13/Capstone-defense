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
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// VARIABLES
// ----------------------
$edit_mode = false;
$edit_drill = null;
$success = "";
$error = "";

// ----------------------
// FETCH DRILL FOR EDIT
// ----------------------
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM drills WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_drill = $result->fetch_assoc();
    $stmt->close();
    if (!$edit_drill) {
        $edit_mode = false;
    }
}

// ----------------------
// ADD DRILL
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_drill'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $title   = $conn->real_escape_string($_POST['title']);
        $date    = $conn->real_escape_string($_POST['date']);
        $details = $conn->real_escape_string($_POST['details']);

        $sql = "INSERT INTO drills (title, `date`, details, created_at) 
                VALUES ('$title', '$date', '$details', NOW())";
        if ($conn->query($sql)) {
            $success = "Drill added successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    } else {
        $error = "Invalid CSRF token.";
    }
}

// ----------------------
// UPDATE DRILL
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_drill'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $id      = (int)$_POST['id'];
        $title   = $conn->real_escape_string($_POST['title']);
        $date    = $conn->real_escape_string($_POST['date']);
        $details = $conn->real_escape_string($_POST['details']);

        $sql = "UPDATE drills SET title='$title', `date`='$date', details='$details' WHERE id=$id";
        if ($conn->query($sql)) {
            header("Location: module1.3.php?updated=1");
            exit;
        } else {
            $error = "Error updating drill: " . $conn->error;
        }
    } else {
        $error = "Invalid CSRF token.";
    }
}

// ----------------------
// DELETE DRILL
// ----------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM drills WHERE id=$id");
    header("Location: module1.3.php");
    exit;
}

// ----------------------
// FETCH DRILLS
// ----------------------
$drills = [];
$res = $conn->query("SELECT * FROM drills ORDER BY `date` DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $drills[] = $row;
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Educational Drills</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden">
  
  <!-- Sidebar -->
  <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
  </aside>

  <!-- Top Navigation -->
  <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
      <i data-lucide="book-open-check" class="w-8 h-8 text-blue-600"></i>
      Educational Drills
    </h1>
  </nav>

  <!-- Main Section -->
<main class="flex-1 h-full overflow-y-auto p-8 bg-gray-100 pt-20">
  
  <!-- Alerts -->
  <?php if (!empty($success)): ?>
    <div class="flex items-center gap-2 p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg">
      <i data-lucide="check-circle" class="w-5 h-5"></i>
      <span><?= htmlspecialchars($success) ?></span>
    </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="flex items-center gap-2 p-4 mb-4 text-red-800 bg-red-100 border border-red-300 rounded-lg">
      <i data-lucide="alert-triangle" class="w-5 h-5"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['updated'])): ?>
    <div class="flex items-center gap-2 p-4 mb-4 text-blue-800 bg-blue-100 border border-blue-300 rounded-lg">
      <i data-lucide="info" class="w-5 h-5"></i>
      <span>Drill updated successfully!</span>
    </div>
  <?php endif; ?>

  <!-- Add / Edit Drill Form -->
  <div class="bg-white p-6 rounded-xl shadow mb-8">
    <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
      <i data-lucide="<?= $edit_mode ? 'pencil' : 'plus-circle' ?>" class="w-5 h-5 text-blue-600"></i>
      <?= $edit_mode ? "Edit Drill" : "Add New Drill" ?>
    </h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <?php if ($edit_mode): ?>
        <input type="hidden" name="id" value="<?= $edit_drill['id'] ?>">
      <?php endif; ?>

      <div>
        <label class="block font-medium text-gray-700 mb-1">Title</label>
        <input type="text" name="title" required 
               value="<?= $edit_mode ? htmlspecialchars($edit_drill['title']) : '' ?>"
               class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300">
      </div>
      <div>
        <label class="block font-medium text-gray-700 mb-1">Date</label>
        <input type="date" name="date" required 
               value="<?= $edit_mode ? htmlspecialchars($edit_drill['date']) : '' ?>"
               class="w-48 border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300">
      </div>
      <div>
        <label class="block font-medium text-gray-700 mb-1">Details</label>
        <textarea name="details" rows="3" required 
                  class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300"><?= $edit_mode ? htmlspecialchars($edit_drill['details']) : '' ?></textarea>
      </div>
      <div class="flex gap-3">
        <?php if ($edit_mode): ?>
          <a href="module1.3.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</a>
          <button type="submit" name="update_drill" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i data-lucide="save" class="w-5 h-5"></i> Update Drill
          </button>
        <?php else: ?>
          <button type="submit" name="add_drill" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i data-lucide="save" class="w-5 h-5"></i> Save Drill
          </button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Drill List -->
<div class="bg-white p-6 rounded-xl shadow">
  <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
    <i data-lucide="list-checks" class="w-5 h-5 text-blue-600"></i>
    Drill List
  </h2>
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-left rounded-lg overflow-hidden">
      <thead>
        <tr class="bg-gray-100 text-gray-700">
          <th class="p-3">Title</th>
          <th class="p-3">Description</th>
          <th class="p-3">Type</th>
          <th class="p-3">File</th>
          <th class="p-3">Date</th>
          <th class="p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($drills) > 0): ?>
          <?php foreach ($drills as $drill): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($drill['title']) ?></td>
              <td class="p-3 text-gray-600"><?= htmlspecialchars($drill['details']) ?></td>
              <td class="p-3 text-gray-700">Drill</td>
              <td class="p-3 text-gray-500">No file</td>
              <td class="p-3 text-gray-600"><?= htmlspecialchars($drill['created_at']) ?></td>
              <td class="p-3 flex gap-4">
                <a href="?edit=<?= $drill['id'] ?>" class="text-blue-600 hover:underline">Edit</a>
                <a href="?delete=<?= $drill['id'] ?>" 
                   class="text-red-600 hover:underline"
                   onclick="return confirm('Delete this drill?')">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="p-4 text-center text-gray-500">No drills found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</main>

<script>
  lucide.createIcons();
</script>
</body>
</html>
