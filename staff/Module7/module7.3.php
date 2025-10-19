<?php
// staff_maintenance_tracker.php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fake staff auth (replace with real system)
$staff_name = $_SESSION['staff_name'] ?? "StaffUser";

// =========================
// Ensure tables exist
// =========================
$conn->query("CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('Available','In Use','Damaged','Maintenance') DEFAULT 'Available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    staff_name VARCHAR(255) NOT NULL,
    activity TEXT NOT NULL,
    status ENUM('Completed','Ongoing','Needs Follow-up') DEFAULT 'Ongoing',
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================
// Handle new maintenance entry
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_maintenance'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $activity     = $conn->real_escape_string(trim($_POST['activity']));
    $status       = $conn->real_escape_string($_POST['status']);

    $conn->query("INSERT INTO maintenance_logs (equipment_id, staff_name, activity, status) 
                  VALUES ($equipment_id, '$staff_name', '$activity', '$status')");

    $_SESSION['flash'] = "Maintenance log recorded successfully.";
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Get available equipment
// =========================
$equip_rs = $conn->query("SELECT * FROM equipment ORDER BY name ASC");

// =========================
// Get staff maintenance logs
// =========================
$sql = "SELECT m.*, e.name AS equipment_name, e.category 
        FROM maintenance_logs m 
        JOIN equipment e ON m.equipment_id = e.id 
        WHERE m.staff_name='$staff_name'
        ORDER BY m.performed_at DESC";
$rs = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff — Maintenance Tracker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Maintenance Tracker — Staff</h1>
      <span class="text-sm text-blue-600">Signed in as <?= htmlspecialchars($staff_name) ?></span>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="mb-4 p-3 rounded bg-green-100 border border-green-300 text-green-800 text-sm">
        <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
      </div>
    <?php endif; ?>

    <!-- Record Maintenance -->
    <section class="mb-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-2">Record Maintenance Activity</h2>
        <form method="post" class="grid gap-3 md:grid-cols-2">
          <select name="equipment_id" required class="p-2 border rounded">
            <option value="">Select Equipment</option>
            <?php while ($eq = $equip_rs->fetch_assoc()): ?>
              <option value="<?= $eq['id'] ?>">
                <?= htmlspecialchars($eq['name']) ?> (<?= $eq['category'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
          <select name="status" class="p-2 border rounded">
            <option>Ongoing</option>
            <option>Completed</option>
            <option>Needs Follow-up</option>
          </select>
          <textarea name="activity" placeholder="Describe maintenance activity..." required class="p-2 border rounded md:col-span-2"></textarea>
          <button name="log_maintenance" class="bg-blue-600 text-white px-4 py-2 rounded md:col-span-2">
            Save Log
          </button>
        </form>
      </div>
    </section>

    <!-- My Maintenance Logs -->
    <section>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-4">My Maintenance Logs</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Equipment</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Activity</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Performed At</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $rs->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?= $r['id'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['equipment_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['category']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['activity']) ?></td>
                <td class="px-3 py-2"><?= $r['status'] ?></td>
                <td class="px-3 py-2"><?= $r['performed_at'] ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
