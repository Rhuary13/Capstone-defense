<?php
// admin_equipment_list.php
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

// Admin guard (replace with real auth)
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// =========================
// Ensure required tables exist
// =========================
$conn->query("CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 1,
    available_qty INT DEFAULT 1,
    status ENUM('Available','In Use','Damaged','Maintenance') DEFAULT 'Available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS gear_checkout (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    staff_name VARCHAR(255) NULL,
    participant_name VARCHAR(255) NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Approved','Rejected','Returned','Damaged') DEFAULT 'Pending',
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================
// Add equipment
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment']) && $is_admin) {
    $name     = $conn->real_escape_string(trim($_POST['name']));
    $category = $conn->real_escape_string(trim($_POST['category']));
    $quantity = intval($_POST['quantity']);
    $status   = $conn->real_escape_string(trim($_POST['status']));
    $notes    = $conn->real_escape_string(trim($_POST['notes']));

    $conn->query("INSERT INTO equipment (name, category, quantity, available_qty, status, notes) 
                  VALUES ('$name', '$category', '$quantity', '$quantity', '$status', '$notes')");
    $_SESSION['flash'] = "✅ Equipment added successfully.";
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Assign equipment
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_equipment']) && $is_admin) {
    $equipment_id = intval($_POST['equipment_id']);
    $staff_name   = !empty($_POST['staff_name']) ? $conn->real_escape_string(trim($_POST['staff_name'])) : null;
    $participant  = !empty($_POST['participant_name']) ? $conn->real_escape_string(trim($_POST['participant_name'])) : null;

    if ($equipment_id > 0 && ($staff_name || $participant)) {
        // Check available qty
        $check = $conn->query("SELECT available_qty FROM equipment WHERE id=$equipment_id");
        $eq = $check->fetch_assoc();
        if ($eq['available_qty'] > 0) {
            $stmt = $conn->prepare("INSERT INTO gear_checkout (equipment_id, staff_name, participant_name, status) VALUES (?, ?, ?, 'Approved')");
            $stmt->bind_param("iss", $equipment_id, $staff_name, $participant);
            $stmt->execute();
            $stmt->close();

            // Decrease available quantity
            $conn->query("UPDATE equipment SET available_qty = available_qty - 1, status='In Use' WHERE id=$equipment_id");

            $_SESSION['flash'] = "✅ Equipment assigned successfully.";
        } else {
            $_SESSION['flash'] = "⚠️ No available stock.";
        }
    } else {
        $_SESSION['flash'] = "⚠️ Invalid assignment data.";
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Return / Update equipment
// =========================
if (isset($_GET['return']) && $is_admin) {
    $id = intval($_GET['return']);
    // Find checkout record
    $checkout = $conn->query("SELECT equipment_id FROM gear_checkout WHERE id=$id")->fetch_assoc();
    if ($checkout) {
        $eid = $checkout['equipment_id'];
        $conn->query("UPDATE gear_checkout SET status='Returned' WHERE id=$id");
        $conn->query("UPDATE equipment SET available_qty = available_qty + 1, status='Available' WHERE id=$eid");
        $_SESSION['flash'] = "↩️ Equipment returned to inventory.";
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Delete equipment
// =========================
if (isset($_GET['delete']) && $is_admin) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM equipment WHERE id=$id");
    $_SESSION['flash'] = "🗑️ Equipment deleted.";
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Filtering & Search
// =========================
$where = [];
if (!empty($_GET['category'])) {
    $cat = $conn->real_escape_string($_GET['category']);
    $where[] = "category = '$cat'";
}
if (!empty($_GET['status'])) {
    $stat = $conn->real_escape_string($_GET['status']);
    $where[] = "status = '$stat'";
}
if (!empty($_GET['search'])) {
    $s = $conn->real_escape_string($_GET['search']);
    $where[] = "(name LIKE '%$s%' OR notes LIKE '%$s%' OR category LIKE '%$s%')";
}
$sql = "SELECT * FROM equipment";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY created_at DESC";
$rs = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin — Equipment List</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { height: 100vh; overflow: hidden; }
    main { overflow-y: auto; }
  </style>
</head>
<body class="bg-gray-100 text-gray-900 flex h-screen">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <main class="flex-1 p-8 bg-gray-50 flex-1 h-full overflow-y-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Equipment List — Admin</h1>
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

    <!-- Add Equipment -->
    <section class="mb-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-2">➕ Add Equipment</h2>
        <?php if ($is_admin): ?>
        <form method="post" class="grid gap-3 md:grid-cols-2">
          <input type="text" name="name" placeholder="Equipment Name" required class="p-2 border rounded">
          <input type="text" name="category" placeholder="Category (e.g., Medical, Comms)" required class="p-2 border rounded">
          <input type="number" name="quantity" placeholder="Quantity" value="1" min="1" class="p-2 border rounded">
          <select name="status" class="p-2 border rounded">
            <option>Available</option>
            <option>In Use</option>
            <option>Damaged</option>
            <option>Maintenance</option>
          </select>
          <textarea name="notes" placeholder="Notes" class="p-2 border rounded md:col-span-2"></textarea>
          <button name="add_equipment" class="bg-blue-600 text-white px-4 py-2 rounded md:col-span-2">Add</button>
        </form>
        <?php else: ?>
          <p class="text-sm text-red-500">You must be an admin to add equipment.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Assign Equipment -->
    <section class="mb-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-2">🎯 Assign Equipment</h2>
        <?php if ($is_admin): ?>
        <form method="post" class="grid gap-3 md:grid-cols-2">
          <!-- Select Equipment -->
          <select name="equipment_id" required class="p-2 border rounded">
            <option value="">-- Select Equipment --</option>
            <?php
              $eq_rs = $conn->query("SELECT id, name FROM equipment WHERE status='Available'");
              while ($eq = $eq_rs->fetch_assoc()):
            ?>
              <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['name']) ?></option>
            <?php endwhile; ?>
          </select>

          <!-- Select Staff -->
          <select name="staff_name" class="p-2 border rounded">
            <option value="">-- Assign to Staff (optional) --</option>
            <?php
              if ($conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0) {
                $staff_rs = $conn->query("SELECT full_name, username FROM users WHERE role='staff'");
                while ($st = $staff_rs->fetch_assoc()):
                  $staffDisplay = $st['full_name'] ?: $st['username'];
            ?>
              <option value="<?= htmlspecialchars($staffDisplay) ?>"><?= htmlspecialchars($staffDisplay) ?></option>
            <?php endwhile; } ?>
          </select>

          <!-- Or Enter Participant Name -->
          <input type="text" name="participant_name" placeholder="Or type Participant Full Name" class="p-2 border rounded md:col-span-2">

          <!-- Assign Button -->
          <button name="assign_equipment" class="bg-green-600 text-white px-4 py-2 rounded md:col-span-2">
            Assign
          </button>
        </form>
        <?php else: ?>
          <p class="text-sm text-red-500">You must be an admin to assign equipment.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Filters -->
    <section class="mb-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-2">🔎 Filter & Search</h2>
        <form method="get" class="flex flex-wrap gap-3 items-center">
          <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search by keyword" class="p-2 border rounded flex-1">
          <select name="category" class="p-2 border rounded">
            <option value="">All Categories</option>
            <option <?= (($_GET['category'] ?? '')==='Medical')?'selected':'' ?>>Medical</option>
            <option <?= (($_GET['category'] ?? '')==='Comms')?'selected':'' ?>>Comms</option>
            <option <?= (($_GET['category'] ?? '')==='Protective')?'selected':'' ?>>Protective</option>
            <option <?= (($_GET['category'] ?? '')==='Logistics')?'selected':'' ?>>Logistics</option>
          </select>
          <select name="status" class="p-2 border rounded">
            <option value="">All Status</option>
            <option <?= (($_GET['status'] ?? '')==='Available')?'selected':'' ?>>Available</option>
            <option <?= (($_GET['status'] ?? '')==='In Use')?'selected':'' ?>>In Use</option>
            <option <?= (($_GET['status'] ?? '')==='Damaged')?'selected':'' ?>>Damaged</option>
            <option <?= (($_GET['status'] ?? '')==='Maintenance')?'selected':'' ?>>Maintenance</option>
          </select>
          <button class="bg-gray-700 text-white px-4 py-2 rounded">Apply</button>
          <a href="admin_equipment_list.php" class="px-4 py-2 border rounded">Reset</a>
        </form>
      </div>
    </section>

    <!-- Equipment List -->
    <section>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-4">📋 All Equipment</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Qty</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Notes</th>
                <th class="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $rs->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?= htmlspecialchars($r['id']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['category']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['quantity']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['status']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($r['notes']) ?></td>
                <td class="px-3 py-2">
                  <?php if ($is_admin): ?>
                    <a href="?delete=<?= $r['id'] ?>" class="text-red-600 text-xs hover:underline" onclick="return confirm('Delete this equipment?')">Delete</a>
                  <?php else: ?>
                    <span class="text-xs text-gray-400">No permission</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Inventory / Checkout Records -->
    <section class="mt-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-medium mb-4">📦 Equipment Inventory / Checkout Records</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="px-3 py-2">Checkout ID</th>
                <th class="px-3 py-2">Equipment</th>
                <th class="px-3 py-2">Staff</th>
                <th class="px-3 py-2">Participant</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Requested At</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $checkout_rs = $conn->query("
                  SELECT g.id, e.name AS equipment_name, g.staff_name, g.participant_name, g.status, g.requested_at
                  FROM gear_checkout g
                  INNER JOIN equipment e ON g.equipment_id = e.id
                  ORDER BY g.requested_at DESC
                ");
                if ($checkout_rs->num_rows > 0):
                  while ($c = $checkout_rs->fetch_assoc()):
              ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?= htmlspecialchars($c['id']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($c['equipment_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($c['staff_name'] ?? '-') ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($c['participant_name'] ?? '-') ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($c['status']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($c['requested_at']) ?></td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="6" class="px-3 py-2 text-center text-gray-500">No checkout records found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>
</body>
</html>
