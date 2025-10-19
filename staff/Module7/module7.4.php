<?php
// staff_damage_reports.php
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

// Fake staff auth (replace with real login system)
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

$conn->query("CREATE TABLE IF NOT EXISTS damage_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    staff_name VARCHAR(255) NOT NULL,
    report TEXT NOT NULL,
    severity ENUM('Minor','Moderate','Severe','Lost') DEFAULT 'Minor',
    status ENUM('Pending','Reviewed','Resolved') DEFAULT 'Pending',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================
// Handle new damage report
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_report'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $report       = $conn->real_escape_string(trim($_POST['report']));
    $severity     = $conn->real_escape_string($_POST['severity']);

    $conn->query("INSERT INTO damage_reports (equipment_id, staff_name, report, severity) 
                  VALUES ($equipment_id, '$staff_name', '$report', '$severity')");

    $_SESSION['flash'] = "Damage report filed successfully.";
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Get available equipment
// =========================
$equip_rs = $conn->query("SELECT * FROM equipment ORDER BY name ASC");

// =========================
// Get staff's reports
// =========================
$sql = "SELECT d.*, e.name AS equipment_name, e.category 
        FROM damage_reports d 
        JOIN equipment e ON d.equipment_id = e.id 
        WHERE d.staff_name='$staff_name'
        ORDER BY d.reported_at DESC";
$rs = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff — Damage Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Damage / Loss Reports — Staff</h1>
      <span class="text-sm text-blue-600">Signed in as <?= htmlspecialchars($staff_name) ?></span>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="mb-4 p-3 rounded bg-green-100 border border-green-300 text-green-800 text-sm">
        <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
      </div>
    <?php endif; ?>

    <!-- File New Report -->
    <section class="mb-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-2">File a Damage / Loss Report</h2>
        <form method="post" class="grid gap-3 md:grid-cols-2">
          <select name="equipment_id" required class="p-2 border rounded">
            <option value="">Select Equipment</option>
            <?php while ($eq = $equip_rs->fetch_assoc()): ?>
              <option value="<?= $eq['id'] ?>">
                <?= htmlspecialchars($eq['name']) ?> (<?= $eq['category'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
          <select name="severity" class="p-2 border rounded">
            <option>Minor</option>
            <option>Moderate</option>
            <option>Severe</option>
            <option>Lost</option>
          </select>
          <textarea name="report" placeholder="Describe damage or loss..." required class="p-2 border rounded md:col-span-2"></textarea>
          <button name="file_report" class="bg-red-600 text-white px-4 py-2 rounded md:col-span-2">
            Submit Report
          </button>
        </form>
      </div>
    </section>

    <!-- My Reports -->
    <section>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-4">My Submitted Reports</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Equipment</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Severity</th>
                <th class="px-3 py-2">Report</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Reported At</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $rs->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?= $r['id'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['equipment_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['category']) ?></td>
                <td class="px-3 py-2"><?= $r['severity'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['report']) ?></td>
                <td class="px-3 py-2"><?= $r['status'] ?></td>
                <td class="px-3 py-2"><?= $r['reported_at'] ?></td>
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
