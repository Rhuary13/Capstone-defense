<?php
// progress_tracking.php
session_start();

// --- Database Connection ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management"; // change if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Ensure table exists (safety) ---
$conn->query("CREATE TABLE IF NOT EXISTS progress_tracking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_name VARCHAR(255) NOT NULL,
  is_mandatory TINYINT(1) DEFAULT 0,
  prerequisite VARCHAR(255) DEFAULT NULL
)");

// --- Ensure created_at column exists ---
$col_check = $conn->query("SHOW COLUMNS FROM progress_tracking LIKE 'created_at'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE progress_tracking ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// --- Handle Form Submission securely ---
$errors = [];
$success = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_module') {
    $module_name = trim($_POST['module_name'] ?? '');
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $prerequisite = trim($_POST['prerequisite'] ?? '');

    if ($module_name === '') {
        $errors[] = "Module name is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO progress_tracking (module_name, is_mandatory, prerequisite) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $module_name, $is_mandatory, $prerequisite);
        if ($stmt->execute()) {
            $success = "Module added successfully.";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Handle Delete ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $conn->query("DELETE FROM progress_tracking WHERE id = $delete_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Fetch modules & stats ---
$modules_res = $conn->query("SELECT * FROM progress_tracking ORDER BY created_at DESC");
$total_modules = 0;
$mandatory_count = 0;
$modules = [];
if ($modules_res) {
    while ($r = $modules_res->fetch_assoc()) {
        $modules[] = $r;
        $total_modules++;
        if ($r['is_mandatory']) $mandatory_count++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Progress & Completion Tracking — Staff</title>

  <!-- TailwindCSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/feather-icons"></script>

  <style>.table-container{max-height:60vh;overflow:auto;}</style>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?> <!-- adjust path if needed -->

  <!-- Main Content -->
  <main class="flex-1 p-6 lg:p-10 overflow-y-auto">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl lg:text-3xl font-semibold">Progress & Completion Tracking</h1>
        <p class="text-sm text-slate-500 mt-1">Set mandatory modules, prerequisites, and track completion requirements.</p>
      </div>

      <div class="flex items-center gap-3">
        <button id="openModalBtn" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md shadow">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Add Module
        </button>

        <div class="flex items-center bg-white border rounded-md px-3 py-1 shadow-sm">
          <input id="searchInput" type="search" placeholder="Search modules..." class="w-64 focus:outline-none text-sm" />
          <button id="clearSearch" class="ml-2 text-slate-400 hover:text-slate-600 text-sm hidden">Clear</button>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
        <div class="p-3 bg-indigo-50 rounded-md">
          <i data-feather="list" class="w-6 h-6 text-indigo-600"></i>
        </div>
        <div>
          <div class="text-xs text-slate-400">Total Modules</div>
          <div class="text-2xl font-semibold"><?php echo $total_modules; ?></div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
        <div class="p-3 bg-amber-50 rounded-md">
          <i data-feather="check-circle" class="w-6 h-6 text-amber-600"></i>
        </div>
        <div>
          <div class="text-xs text-slate-400">Mandatory Modules</div>
          <div class="text-2xl font-semibold"><?php echo $mandatory_count; ?></div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
        <div class="p-3 bg-green-50 rounded-md">
          <i data-feather="clock" class="w-6 h-6 text-green-600"></i>
        </div>
        <div>
          <div class="text-xs text-slate-400">Last added</div>
          <div class="text-sm"><?php echo $total_modules ? htmlspecialchars($modules[0]['module_name']) : '—'; ?></div>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
      <div class="mb-4 p-3 rounded-md bg-green-50 border border-green-200 text-green-800">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="mb-4 p-3 rounded-md bg-red-50 border border-red-200 text-red-800">
        <?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
      </div>
    <?php endif; ?>

    <!-- Modules Table -->
    <div class="bg-white rounded-lg shadow p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-medium">Modules</h3>
        <div class="text-sm text-slate-500">Showing <?php echo $total_modules; ?> module<?php echo $total_modules !== 1 ? 's' : ''; ?></div>
      </div>

      <div class="table-container border rounded-md">
        <table id="modulesTable" class="min-w-full text-sm">
          <thead class="sticky top-0 bg-white">
            <tr class="text-left">
              <th class="p-3 border-b">Module Name</th>
              <th class="p-3 border-b">Mandatory</th>
              <th class="p-3 border-b">Prerequisite</th>
              <th class="p-3 border-b">Created</th>
              <th class="p-3 border-b">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($modules) === 0): ?>
              <tr>
                <td colspan="5" class="p-4 text-center text-slate-500">
                  No modules yet. Add one with the <strong>Add Module</strong> button.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($modules as $m): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?php echo htmlspecialchars($m['module_name']); ?></td>
                  <td class="p-3 border-b"><?php echo $m['is_mandatory'] ? 'Yes' : 'No'; ?></td>
                  <td class="p-3 border-b"><?php echo $m['prerequisite'] ?: '—'; ?></td>
                  <td class="p-3 border-b"><?php echo htmlspecialchars(date("Y-m-d", strtotime($m['created_at']))); ?></td>
                  <td class="p-3 border-b">
                    <div class="flex gap-2">
                      <button class="editBtn px-3 py-1 text-xs bg-white border rounded-md">Edit</button>
                      <form method="POST" class="inline">
                        <input type="hidden" name="delete_id" value="<?php echo $m['id']; ?>">
                        <button type="button" class="deleteBtn px-3 py-1 text-xs bg-red-50 text-red-700 border border-red-100" data-id="<?php echo $m['id']; ?>">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script>
    if (typeof feather !== 'undefined') feather.replace();
  </script>
</body>

</html>
