<?php
// staff_gear_checkout.php
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

$conn->query("CREATE TABLE IF NOT EXISTS gear_checkout (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    staff_name VARCHAR(255) NOT NULL,
    purpose TEXT,
    qty INT DEFAULT 1,
    status ENUM('Pending','Approved','Rejected','Returned') DEFAULT 'Pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================
// Handle new checkout request
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_checkout'])) {
    $equipment_id = intval($_POST['equipment_id']);
    $qty          = intval($_POST['qty']);
    $purpose      = $conn->real_escape_string(trim($_POST['purpose']));

    $conn->query("INSERT INTO gear_checkout (equipment_id, staff_name, purpose, qty) 
                  VALUES ($equipment_id, '$staff_name', '$purpose', $qty)");

    $_SESSION['flash'] = "Checkout request submitted to admin.";
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Mark as returned (staff initiates)
// =========================
if (isset($_GET['return'])) {
    $id = intval($_GET['return']);
    $conn->query("UPDATE gear_checkout SET status='Returned' WHERE id=$id AND staff_name='$staff_name'");
    $_SESSION['flash'] = "Marked as returned (Admin will be notified).";
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Get available equipment
// =========================
$equip_rs = $conn->query("SELECT * FROM equipment WHERE status='Available' ORDER BY name ASC");

// =========================
// Get staff requests
// =========================
$sql = "SELECT g.*, e.name AS equipment_name, e.category 
        FROM gear_checkout g 
        JOIN equipment e ON g.equipment_id = e.id 
        WHERE g.staff_name='$staff_name'
        ORDER BY g.requested_at DESC";
$rs = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff — Gear Checkout</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Gear Checkout — Staff</h1>
      <span class="text-sm text-blue-600">Signed in as <?= htmlspecialchars($staff_name) ?></span>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="mb-4 p-3 rounded bg-green-100 border border-green-300 text-green-800 text-sm">
        <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
      </div>
    <?php endif; ?>

    <!-- Request Gear -->
    <section class="mb-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-2">Request Gear Checkout</h2>
        <form method="post" class="grid gap-3 md:grid-cols-2">
          <select name="equipment_id" required class="p-2 border rounded">
            <option value="">Select Equipment</option>
            <?php while ($eq = $equip_rs->fetch_assoc()): ?>
              <option value="<?= $eq['id'] ?>">
                <?= htmlspecialchars($eq['name']) ?> (<?= $eq['category'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
          <input type="number" name="qty" min="1" value="1" class="p-2 border rounded" required>
          <textarea name="purpose" placeholder="Purpose of use" required class="p-2 border rounded md:col-span-2"></textarea>
          <button name="request_checkout" class="bg-blue-600 text-white px-4 py-2 rounded md:col-span-2">
            Submit Request
          </button>
        </form>
      </div>
    </section>

    <!-- My Checkout Requests -->
    <section>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-4">My Checkout Logs</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Equipment</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Qty</th>
                <th class="px-3 py-2">Purpose</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Requested</th>
                <th class="px-3 py-2">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $rs->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?= $r['id'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['equipment_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['category']) ?></td>
                <td class="px-3 py-2"><?= $r['qty'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['purpose']) ?></td>
                <td class="px-3 py-2"><?= $r['status'] ?></td>
                <td class="px-3 py-2"><?= $r['requested_at'] ?></td>
                <td class="px-3 py-2">
                  <?php if ($r['status'] === 'Approved'): ?>
                    <a href="?return=<?= $r['id'] ?>" class="text-blue-600 text-xs hover:underline">Mark Returned</a>
                  <?php else: ?>
                    <span class="text-xs text-gray-400">—</span>
                  <?php endif; ?>
                </td>
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
