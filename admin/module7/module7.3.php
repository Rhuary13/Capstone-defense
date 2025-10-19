<?php
// module7.3.php
session_start();

// --- Database Connection ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; // make sure this DB has your maintenance table

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Create table if missing (safety) ---
$conn->query("CREATE TABLE IF NOT EXISTS maintenance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  equipment VARCHAR(255) NOT NULL,
  task_type VARCHAR(50) NOT NULL,
  schedule_date DATE NOT NULL,
  notes TEXT
)");

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment     = $_POST['equipment'];
    $task_type     = $_POST['task_type'];
    $schedule_date = $_POST['schedule_date'];
    $notes         = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO maintenance (equipment, task_type, schedule_date, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $equipment, $task_type, $schedule_date, $notes);
    $stmt->execute();
    $stmt->close();
}

// --- Fetch Maintenance Tasks ---
$result = $conn->query("SELECT * FROM maintenance ORDER BY schedule_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Maintenance Tracker</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?> 

  <!-- Main Content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="bg-blue-700 text-white p-4 rounded-lg shadow mb-6 text-xl font-bold">
      Maintenance Tracker - Admin Dashboard
    </header>

    <!-- Schedule Form -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Schedule Inspection or Repair</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
          <label class="block text-sm font-medium mb-1">Equipment</label>
          <input type="text" name="equipment" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Task Type</label>
          <select name="task_type" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
            <option value="Inspection">Inspection</option>
            <option value="Repair">Repair</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Schedule Date</label>
          <input type="date" name="schedule_date" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Notes</label>
          <textarea name="notes" rows="3" class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300"></textarea>
        </div>

        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
            Save Task
          </button>
        </div>
      </form>
    </div>

    <!-- Scheduled Tasks -->
    <div class="bg-white rounded-2xl shadow-md p-6">
      <h2 class="text-lg font-semibold mb-4">Scheduled Maintenance</h2>
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-gray-200 text-left text-sm">
            <th class="p-2 border">Equipment</th>
            <th class="p-2 border">Task</th>
            <th class="p-2 border">Date</th>
            <th class="p-2 border">Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()) : ?>
            <tr class="hover:bg-gray-100 text-sm">
              <td class="p-2 border"><?php echo htmlspecialchars($row['equipment']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['task_type']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['schedule_date']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['notes']); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

  </div>
</body>
</html>
<?php $conn->close(); ?>
