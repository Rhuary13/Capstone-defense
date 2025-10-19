<?php
// admin_gear_checkout.php
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

// Admin guard
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

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
// Approve / Reject Checkout
// =========================
if ($is_admin && isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $conn->query("UPDATE gear_checkout SET status='Approved', approved_at=NOW() WHERE id=$id");
        $_SESSION['flash'] = "Checkout request approved.";
    } elseif ($action === 'reject') {
        $conn->query("UPDATE gear_checkout SET status='Rejected', approved_at=NOW() WHERE id=$id");
        $_SESSION['flash'] = "Checkout request rejected.";
    } elseif ($action === 'return') {
        $conn->query("UPDATE gear_checkout SET status='Returned' WHERE id=$id");
        $_SESSION['flash'] = "Gear marked as returned.";
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Get requests
// =========================
$sql = "SELECT g.*, e.name AS equipment_name, e.category 
        FROM gear_checkout g 
        JOIN equipment e ON g.equipment_id = e.id 
        ORDER BY g.requested_at DESC";
$rs = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin — Gear Checkout</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Gear Checkout Management</h1>
      <div>
        <?php if ($is_admin): ?>
          <span class="text-sm text-green-600">Signed in as Admin</span>
        <?php else: ?>
          <span class="text-sm text-red-600">Not signed in</span>
        <?php endif; ?>
      </div>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="mb-4 p-3 rounded bg-white shadow text-sm">
        <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
      </div>
    <?php endif; ?>

    <!-- Checkout Requests -->
    <section>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-4">Staff Checkout Requests</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Staff</th>
                <th class="px-3 py-2">Equipment</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Qty</th>
                <th class="px-3 py-2">Purpose</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Requested</th>
                <th class="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $rs->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?= $r['id'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['staff_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['equipment_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['category']) ?></td>
                <td class="px-3 py-2"><?= $r['qty'] ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['purpose']) ?></td>
                <td class="px-3 py-2"><?= $r['status'] ?></td>
                <td class="px-3 py-2"><?= $r['requested_at'] ?></td>
                <td class="px-3 py-2 space-x-2">
                  <?php if ($is_admin && $r['status'] === 'Pending'): ?>
                    <a href="?action=approve&id=<?= $r['id'] ?>" class="text-green-600 text-xs hover:underline">Approve</a>
                    <a href="?action=reject&id=<?= $r['id'] ?>" class="text-red-600 text-xs hover:underline">Reject</a>
                  <?php elseif ($is_admin && $r['status'] === 'Approved'): ?>
                    <a href="?action=return&id=<?= $r['id'] ?>" class="text-blue-600 text-xs hover:underline">Mark Returned</a>
                  <?php else: ?>
                    <span class="text-xs text-gray-400">No action</span>
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
