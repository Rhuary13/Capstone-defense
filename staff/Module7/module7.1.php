<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../../auth/login.php");
    exit;
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$staffName = $_SESSION['full_name'] ?? $_SESSION['username'];

// =========================
// Handle assignment to participant
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to_participant'])) {
    $checkoutId      = intval($_POST['checkout_id']);
    $participantName = $conn->real_escape_string(trim($_POST['participant_name']));

    if ($checkoutId > 0 && !empty($participantName)) {
        $stmt = $conn->prepare("INSERT INTO gear_checkout (equipment_id, staff_name, role, status) VALUES (?, ?, 'staff', 'Approved')");
        $stmt->bind_param("is", $equipment_id, $staff_name);
        $stmt->execute();
        $_SESSION['flash'] = "✅ Equipment assigned to $participantName.";
    }
    header("Location: staff_inventory.php");
    exit;
}

// =========================
// Fetch Full Inventory
// =========================
$inventory = $conn->query("SELECT * FROM equipment ORDER BY created_at DESC");

// =========================
// Fetch Items staff holds (not yet given)
// =========================
$stmt = $conn->prepare("
    SELECT e.name, e.category, e.notes, g.requested_at, g.status
    FROM gear_checkout g
    JOIN equipment e ON g.equipment_id = e.id
    WHERE g.assigned_to = ?
    ORDER BY g.requested_at DESC
");
$stmt->bind_param("s", $participantName);
$stmt->execute();
$my_items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Inventory & Assignment</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main -->
<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
  <h1 class="text-3xl font-extrabold text-gray-800 mb-6">📦 Staff Inventory & 🎯 Assignment</h1>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="mb-4 p-3 bg-white rounded shadow text-green-700 font-medium">
      <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
    </div>
  <?php endif; ?>

  <!-- Section 1: Full Inventory -->
  <section class="mb-8">
    <div class="bg-white shadow-lg rounded-xl p-6">
      <h2 class="text-xl font-bold mb-4">🏭 Full Equipment Inventory</h2>
      <div class="overflow-x-auto">
        <table class="w-full border text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 text-left">ID</th>
              <th class="p-2 text-left">Name</th>
              <th class="p-2 text-left">Category</th>
              <th class="p-2">Quantity</th>
              <th class="p-2">Status</th>
              <th class="p-2 text-left">Notes</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php while ($row = $inventory->fetch_assoc()): ?>
              <tr>
                <td class="p-2"><?= htmlspecialchars($row['id']) ?></td>
                <td class="p-2 font-medium"><?= htmlspecialchars($row['name']) ?></td>
                <td class="p-2"><?= htmlspecialchars($row['category']) ?></td>
                <td class="p-2 text-center"><?= htmlspecialchars($row['quantity']) ?></td>
                <td class="p-2 text-center"><?= htmlspecialchars($row['status']) ?></td>
                <td class="p-2"><?= htmlspecialchars($row['notes'] ?? '-') ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Section 2: My Held Items + Assign -->
  <section>
    <div class="bg-white shadow-lg rounded-xl p-6">
      <h2 class="text-xl font-bold mb-4">🧰 My Held Equipment — Assign to Participants</h2>
      <?php if ($my_items->num_rows > 0): ?>
        <table class="w-full text-sm border">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2">Equipment</th>
              <th class="p-2">Category</th>
              <th class="p-2">Notes</th>
              <th class="p-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $my_items->fetch_assoc()): ?>
            <tr class="border-t">
              <td class="p-2 font-medium"><?= htmlspecialchars($row['name']) ?></td>
              <td class="p-2"><?= htmlspecialchars($row['category']) ?></td>
              <td class="p-2"><?= htmlspecialchars($row['notes']) ?></td>
              <td class="p-2">
                <form method="post" class="flex gap-2">
                  <input type="hidden" name="checkout_id" value="<?= $row['id'] ?>">
                  <input type="text" name="participant_name" placeholder="Participant Name" required class="border p-1 rounded">
                  <button name="assign_to_participant" class="bg-green-600 text-white px-3 py-1 rounded">Give</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-600">No equipment currently held by you.</p>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>
