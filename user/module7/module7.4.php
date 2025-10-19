<?php
session_start();

// -------------------------
// Damage Reports - Participant UI
// Role: user (participant)
// -------------------------

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

$participantName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? '');
if (empty($participantName)) {
    die("Participant name not found in session. Please log in again.");
}

// DB Connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$message = "";
$messageClass = "";

// Handle submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $equipmentId  = intval($_POST['equipment_id']);
    $itemName     = trim($_POST['item_name'] ?? '');
    $damageType   = trim($_POST['damage_type'] ?? '');
    $incidentDate = trim($_POST['incident_date'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $severity     = trim($_POST['severity'] ?? 'Low');

    if ($equipmentId <= 0 || empty($itemName) || empty($damageType) || empty($incidentDate)) {
        $message = "⚠️ Please complete all required fields.";
        $messageClass = "bg-yellow-100 text-yellow-800";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO damage_reports (equipment_id, staff_name, item_name, damage_type, incident_date, description, severity, reported_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending', NOW())
        ");
        if ($stmt) {
            $stmt->bind_param("issssss", $equipmentId, $participantName, $itemName, $damageType, $incidentDate, $description, $severity);
            if ($stmt->execute()) {
                $message = "✅ Damage report submitted successfully!";
                $messageClass = "bg-green-100 text-green-800";
                $_POST = []; // clear form
            } else {
                $message = "❌ Error: " . htmlspecialchars($stmt->error);
                $messageClass = "bg-red-100 text-red-800";
            }
            $stmt->close();
        } else {
            $message = "❌ DB Error: " . htmlspecialchars($conn->error);
            $messageClass = "bg-red-100 text-red-800";
        }
    }
}

// Fetch equipment list
$equipmentList = $conn->query("SELECT id, name FROM equipment ORDER BY name ASC");

// Fetch participant’s reports
$reportsStmt = $conn->prepare("
    SELECT dr.item_name, dr.damage_type, dr.incident_date, dr.description, dr.severity, dr.reported_at, dr.status
    FROM damage_reports dr
    WHERE dr.staff_name = ?
    ORDER BY dr.reported_at DESC
");
$reportsResult = null;
if ($reportsStmt) {
    $reportsStmt->bind_param("s", $participantName);
    $reportsStmt->execute();
    $reportsResult = $reportsStmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Damage Reports</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">

<?php include '../sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
  <div class="max-w-4xl mx-auto">

    <h1 class="text-3xl font-extrabold mb-6">🚨 Report Damaged or Missing Equipment</h1>

    <?php if ($message): ?>
      <div class="mb-6 p-4 rounded-lg text-lg <?= $messageClass ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Report Form -->
    <div class="bg-white shadow-lg rounded-xl p-6 mb-10">
      <form method="POST" class="space-y-6">
        
        <!-- Equipment Select -->
        <div>
          <label class="block text-lg font-semibold mb-2">Select Equipment</label>
          <select name="equipment_id" required
            class="w-full p-3 border rounded-lg text-lg focus:ring-2 focus:ring-red-500">
            <option value="">-- Choose Equipment --</option>
            <?php while ($eq = $equipmentList->fetch_assoc()): ?>
              <option value="<?= $eq['id'] ?>" <?= ($_POST['equipment_id'] ?? '') == $eq['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($eq['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Item Name -->
        <div>
          <label class="block text-lg font-semibold mb-2">Item Name</label>
          <input type="text" name="item_name" required
            value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>"
            class="w-full p-3 border rounded-lg text-lg focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- Damage Type -->
        <div>
          <label class="block text-lg font-semibold mb-2">Damage Type</label>
          <select name="damage_type" required
            class="w-full p-3 border rounded-lg text-lg focus:ring-2 focus:ring-red-500">
            <option value="">-- Select Type --</option>
            <?php foreach (['Physical','Missing','Malfunction','Other'] as $type): ?>
              <option value="<?= $type ?>" <?= ($_POST['damage_type'] ?? '') == $type ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Incident Date -->
        <div>
          <label class="block text-lg font-semibold mb-2">Date of Incident</label>
          <input type="date" name="incident_date" required
            value="<?= htmlspecialchars($_POST['incident_date'] ?? '') ?>"
            class="w-full p-3 border rounded-lg text-lg focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- Description -->
        <div>
          <label class="block text-lg font-semibold mb-2">Description</label>
          <textarea name="description" rows="5" class="w-full p-3 border rounded-lg text-lg focus:ring-2 focus:ring-red-500"
            placeholder="Explain what happened..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Severity -->
        <div>
          <label class="block text-lg font-semibold mb-2">Severity</label>
          <select name="severity"
            class="w-full p-3 border rounded-lg text-lg focus:ring-2 focus:ring-red-500">
            <?php foreach (['Low','Medium','High','Critical'] as $sev): ?>
              <option value="<?= $sev ?>" <?= ($_POST['severity'] ?? 'Low') == $sev ? 'selected' : '' ?>><?= $sev ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Submit -->
        <div class="text-center">
          <button type="submit"
            class="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-xl text-2xl font-bold shadow-md">
            🚨 Submit Damage Report
          </button>
        </div>

      </form>
    </div>

    <!-- Past Reports -->
    <div class="bg-white shadow-lg rounded-xl p-6">
      <h2 class="text-2xl font-bold mb-4">📋 My Submitted Reports</h2>
      <?php if ($reportsResult && $reportsResult->num_rows > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full border border-gray-200 rounded-lg text-lg">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="p-3 text-left">Item</th>
                <th class="p-3">Type</th>
                <th class="p-3">Date</th>
                <th class="p-3">Severity</th>
                <th class="p-3">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php while ($r = $reportsResult->fetch_assoc()): ?>
              <tr>
                <td class="p-3"><?= htmlspecialchars($r['item_name']) ?></td>
                <td class="p-3 text-center"><?= htmlspecialchars($r['damage_type']) ?></td>
                <td class="p-3 text-center"><?= htmlspecialchars($r['incident_date']) ?></td>
                <td class="p-3 text-center"><?= htmlspecialchars($r['severity']) ?></td>
                <td class="p-3 text-center">
                  <?php
                    if ($r['status'] === 'Closed') {
                      echo "<span class='bg-green-100 text-green-700 px-3 py-1 rounded-full'>Closed</span>";
                    } elseif ($r['status'] === 'Investigating') {
                      echo "<span class='bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full'>Investigating</span>";
                    } else {
                      echo "<span class='bg-gray-200 text-gray-700 px-3 py-1 rounded-full'>Pending</span>";
                    }
                  ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-lg text-gray-600">No reports submitted yet.</p>
      <?php endif; ?>
    </div>

  </div>
</main>

</body>
</html>
