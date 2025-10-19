<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; // adjust if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $participant = $conn->real_escape_string($_POST['participant_name']);
    $module      = $conn->real_escape_string($_POST['module']);
    $status      = $conn->real_escape_string($_POST['status']);
    $date        = $conn->real_escape_string($_POST['date']);

    $conn->query("INSERT INTO attendance (participant_name, module, status, date)
                  VALUES ('$participant', '$module', '$status', '$date')");
}

// Fetch attendance records (removed created_at because not in your table)
$records = $conn->query("SELECT * FROM attendance ORDER BY date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Tracking</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Attendance Tracking</h1>
      <p class="text-sm text-slate-500 mt-1">Record participant attendance for each session.</p>
    </div>

    <!-- Add Attendance Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Record Attendance</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Participant Name</label>
          <input type="text" name="participant_name" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Module</label>
          <input type="text" name="module" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select name="status" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
            <option value="Present">Present</option>
            <option value="Absent">Absent</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Date</label>
          <input type="date" name="date" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Save Attendance
          </button>
        </div>
      </form>
    </div>

    <!-- Attendance Records -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Attendance Records</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100">
            <tr>
              <th class="p-3 border-b">Participant</th>
              <th class="p-3 border-b">Module</th>
              <th class="p-3 border-b">Status</th>
              <th class="p-3 border-b">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($records && $records->num_rows > 0): ?>
              <?php while ($row = $records->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?php echo htmlspecialchars($row['participant_name']); ?></td>
                  <td class="p-3 border-b"><?php echo htmlspecialchars($row['module']); ?></td>
                  <td class="p-3 border-b">
                    <span class="px-2 py-1 rounded text-xs
                      <?php echo $row['status'] === 'Present' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                      <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                  </td>
                  <td class="p-3 border-b"><?php echo htmlspecialchars($row['date']); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="p-4 text-center text-slate-500">No attendance records yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
