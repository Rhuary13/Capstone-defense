<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Export CSV
if (isset($_POST['export_csv'])) {
    $result = $conn->query("SELECT full_name, user_type, check_in, check_out, date FROM attendance ORDER BY date DESC");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report.csv');

    $output = fopen("php://output", "w");
    fputcsv($output, ['Full Name', 'User Type', 'Check In', 'Check Out', 'Date']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Handle Report Submission
if (isset($_POST['submit_report'])) {
    $title = $conn->real_escape_string($_POST['report_title']);
    $body  = $conn->real_escape_string($_POST['report_body']);
    $created_by = "staff"; // you can replace with session username if available

    $conn->query("INSERT INTO reports (report_title, report_body, created_by) VALUES ('$title', '$body', '$created_by')");
}

// Fetch attendance records
$records = $conn->query("
    SELECT full_name, user_type, check_in, check_out, date
    FROM attendance
    ORDER BY date DESC
");

// Fetch summary for staff reporting
$summary = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN user_type='participant' THEN 1 ELSE 0 END) AS participants,
        SUM(CASE WHEN user_type='staff' THEN 1 ELSE 0 END) AS staff
    FROM attendance
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reporting</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl lg:text-3xl font-semibold">Reporting</h1>
        <p class="text-sm text-slate-500 mt-1">Export attendance data and report outcomes to admin.</p>
      </div>
      <form method="post">
        <button type="submit" name="export_csv" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700">
          Export CSV
        </button>
      </form>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <div class="bg-white shadow rounded-lg p-6 text-center">
        <p class="text-2xl font-bold text-slate-800"><?php echo $summary['total']; ?></p>
        <p class="text-slate-500">Total Records</p>
      </div>
      <div class="bg-white shadow rounded-lg p-6 text-center">
        <p class="text-2xl font-bold text-indigo-600"><?php echo $summary['participants']; ?></p>
        <p class="text-slate-500">Participants</p>
      </div>
      <div class="bg-white shadow rounded-lg p-6 text-center">
        <p class="text-2xl font-bold text-emerald-600"><?php echo $summary['staff']; ?></p>
        <p class="text-slate-500">Staff</p>
      </div>
    </div>

    <!-- Attendance Records -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Attendance Records</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border">
          <thead class="bg-slate-100">
            <tr>
              <th class="p-3 border">Full Name</th>
              <th class="p-3 border">User Type</th>
              <th class="p-3 border">Check In</th>
              <th class="p-3 border">Check Out</th>
              <th class="p-3 border">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($records && $records->num_rows > 0): ?>
              <?php while ($row = $records->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border"><?php echo htmlspecialchars($row['full_name']); ?></td>
                  <td class="p-3 border"><?php echo htmlspecialchars($row['user_type']); ?></td>
                  <td class="p-3 border"><?php echo $row['check_in'] ? $row['check_in'] : '-'; ?></td>
                  <td class="p-3 border"><?php echo $row['check_out'] ? $row['check_out'] : '-'; ?></td>
                  <td class="p-3 border"><?php echo htmlspecialchars($row['date']); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="p-4 text-center text-slate-500">No attendance records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Staff Report Form -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Report Outcome to Admin</h2>
      <form method="post" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Report Title</label>
          <input type="text" name="report_title" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Report Details / Outcome</label>
          <textarea name="report_body" rows="4" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200"></textarea>
        </div>
        <div class="flex justify-end">
          <button type="submit" name="submit_report" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
            Submit Report
          </button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
