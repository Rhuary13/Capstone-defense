<?php
session_start();

// =========================
// Security: Participant-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// ✅ Get participant name from session
$participantName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? '');
if (empty($participantName)) {
    die("Participant name not found in session. Please log in again.");
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
// Handle Actions (Confirm / Return)
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'], $_POST['checkout_id'])) {
    $checkoutId = intval($_POST['checkout_id']);
    $action     = $_POST['action'];

    if ($action === "confirm") {
        $update = $conn->prepare("UPDATE gear_checkout SET status = 'Approved' WHERE id = ? AND staff_name = ?");
        $update->bind_param("is", $checkoutId, $participantName);
        $update->execute();
    } elseif ($action === "return") {
        $update = $conn->prepare("UPDATE gear_checkout SET status = 'Returned' WHERE id = ? AND staff_name = ?");
        $update->bind_param("is", $checkoutId, $participantName);
        $update->execute();
    }
}

// =========================
// Fetch Gear Records
// =========================
$stmt = $conn->prepare("
    SELECT g.id, e.name AS item_name, e.notes AS description, g.qty, g.requested_at, g.status
    FROM gear_checkout g
    JOIN equipment e ON g.equipment_id = e.id
    WHERE g.staff_name = ?
    ORDER BY g.requested_at DESC
");
$stmt->bind_param("s", $participantName);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Gear Checkout</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function confirmAction(formId, message) {
        if (confirm(message)) {
            document.getElementById(formId).submit();
        }
    }
  </script>
</head>
<body class="flex h-screen bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6">🧰 My Gear Checkout</h1>

    <div class="bg-white shadow-lg rounded-xl p-6">
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full border border-gray-200 rounded-lg text-lg">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="p-4 text-left">Equipment</th>
                            <th class="p-4 text-left">Description</th>
                            <th class="p-4 text-center">Qty</th>
                            <th class="p-4 text-center">Requested Date</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-4 font-medium text-gray-900"><?= htmlspecialchars($row['item_name']) ?></td>
                            <td class="p-4 text-gray-700"><?= htmlspecialchars($row['description'] ?? 'N/A') ?></td>
                            <td class="p-4 text-center"><?= (int)$row['qty'] ?></td>
                            <td class="p-4 text-center"><?= htmlspecialchars($row['requested_at']) ?></td>
                            <td class="p-4 text-center">
                                <?php
                                    switch ($row['status']) {
                                        case "Returned":
                                            echo "<span class='bg-green-100 text-green-700 px-3 py-1 rounded-full text-base'>Returned</span>";
                                            break;
                                        case "Approved":
                                            echo "<span class='bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-base'>In Use</span>";
                                            break;
                                        case "Rejected":
                                            echo "<span class='bg-red-100 text-red-700 px-3 py-1 rounded-full text-base'>Rejected</span>";
                                            break;
                                        default:
                                            echo "<span class='bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-base'>Pending</span>";
                                            break;
                                    }
                                ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($row['status'] === "Pending"): ?>
                                    <form id="confirmForm<?= $row['id'] ?>" method="POST" class="inline">
                                        <input type="hidden" name="checkout_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="button" onclick="confirmAction('confirmForm<?= $row['id'] ?>', 'Confirm you have received this gear?')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-lg">
                                            ✅ Confirm Receipt
                                        </button>
                                    </form>
                                <?php elseif ($row['status'] === "Approved"): ?>
                                    <form id="returnForm<?= $row['id'] ?>" method="POST" class="inline">
                                        <input type="hidden" name="checkout_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="action" value="return">
                                        <button type="button" onclick="confirmAction('returnForm<?= $row['id'] ?>', 'Confirm you are returning this gear?')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-lg">
                                            🔄 Return Gear
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-gray-500">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-700 text-xl">No gear checkout records found.</p>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
