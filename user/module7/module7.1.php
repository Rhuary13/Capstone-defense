<?php
session_start();

// =========================
// Security: Participant-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// ✅ Participant identity
$participantName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? '');
if (empty($participantName)) {
    die("Participant name not found. Please log in again.");
}

// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Fetch Equipment Assigned by Admin
// =========================
$stmt = $conn->prepare("UPDATE gear_checkout 
                        SET assigned_to=?, status='Approved' 
                        WHERE id=? AND staff_name=?");
$stmt->bind_param("sis", $participantName, $checkoutId, $staffName);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Equipment</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
  <h1 class="text-3xl font-extrabold text-gray-800 mb-6">🧰 My Equipment Assigned by Admin</h1>

  <div class="bg-white shadow-lg rounded-xl p-6">
    <?php if ($result && $result->num_rows > 0): ?>
      <div class="overflow-x-auto">
        <table class="w-full border border-gray-200 rounded-lg text-sm">
          <thead class="bg-gray-100 text-gray-700">
            <tr>
              <th class="p-3 text-left">Equipment</th>
              <th class="p-3 text-left">Category</th>
              <th class="p-3 text-left">Description</th>
              <th class="p-3">Assigned Date</th>
              <th class="p-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($row['item_name']) ?></td>
                <td class="p-3 text-gray-600"><?= htmlspecialchars($row['category']) ?></td>
                <td class="p-3 text-gray-600"><?= htmlspecialchars($row['description'] ?? 'N/A') ?></td>
                <td class="p-3"><?= htmlspecialchars($row['assigned_date']) ?></td>
                <td class="p-3">
                  <?php
                    switch ($row['status']) {
                      case "Returned":
                        echo "<span class='bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm'>Returned</span>";
                        break;
                      case "Rejected":
                        echo "<span class='bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm'>Rejected</span>";
                        break;
                      case "Approved":
                        echo "<span class='bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm'>Approved</span>";
                        break;
                      case "Pending":
                      default:
                        echo "<span class='bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-sm'>Pending</span>";
                        break;
                    }
                  ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-600">No equipment has been assigned to you yet.</p>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
