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

/* -------------------------
   Attendance Totals
-------------------------- */
$checkStatus = $conn->query("SHOW COLUMNS FROM attendance LIKE 'status'");
if ($checkStatus && $checkStatus->num_rows > 0) {
    $totals = $conn->query("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent
        FROM attendance
    ")->fetch_assoc();
} else {
    $totals = $conn->query("SELECT COUNT(*) AS total FROM attendance")->fetch_assoc();
    $totals['present'] = null;
    $totals['absent']  = null;
}

/* -------------------------
   Attendance by Module
-------------------------- */
$byModule = null;
$checkModule = $conn->query("SHOW COLUMNS FROM attendance LIKE 'module'");
if ($checkModule && $checkModule->num_rows > 0) {
    if ($checkStatus && $checkStatus->num_rows > 0) {
        // With module + status
        $byModule = $conn->query("
            SELECT module,
                   COUNT(*) AS total,
                   SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent
            FROM attendance
            GROUP BY module
        ");
    } else {
        // Only module, no status
        $byModule = $conn->query("
            SELECT module, COUNT(*) AS total
            FROM attendance
            GROUP BY module
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Capacity Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Capacity Management</h1>
      <p class="text-sm text-slate-500 mt-1">Monitor headcount and attendance capacity.</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
      <div class="bg-white shadow rounded-lg p-6 text-center">
        <p class="text-2xl font-bold text-slate-800"><?php echo $totals['total'] ?? 0; ?></p>
        <p class="text-slate-500">Total Records</p>
      </div>
      <?php if ($totals['present'] !== null): ?>
        <div class="bg-white shadow rounded-lg p-6 text-center">
          <p class="text-2xl font-bold text-green-600"><?php echo $totals['present']; ?></p>
          <p class="text-slate-500">Present</p>
        </div>
        <div class="bg-white shadow rounded-lg p-6 text-center">
          <p class="text-2xl font-bold text-red-600"><?php echo $totals['absent']; ?></p>
          <p class="text-slate-500">Absent</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Attendance Breakdown -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Attendance by Module</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-100">
            <tr>
              <th class="p-3 border-b">Module</th>
              <th class="p-3 border-b">Total</th>
              <?php if ($totals['present'] !== null): ?>
                <th class="p-3 border-b">Present</th>
                <th class="p-3 border-b">Absent</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($byModule && $byModule->num_rows > 0): ?>
              <?php while ($row = $byModule->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?php echo htmlspecialchars($row['module']); ?></td>
                  <td class="p-3 border-b font-semibold"><?php echo $row['total']; ?></td>
                  <?php if ($totals['present'] !== null): ?>
                    <td class="p-3 border-b text-green-600"><?php echo $row['present']; ?></td>
                    <td class="p-3 border-b text-red-600"><?php echo $row['absent']; ?></td>
                  <?php endif; ?>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?php echo ($totals['present'] !== null) ? 4 : 2; ?>" class="p-4 text-center text-slate-500">
                  No attendance data available.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Chart -->
    <?php if ($totals['present'] !== null): ?>
      <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-medium mb-4">Overall Attendance Distribution</h2>
        <canvas id="attendanceChart" height="120"></canvas>
      </div>
    <?php endif; ?>
  </main>

  <script>
    <?php if ($totals['present'] !== null): ?>
      const ctx = document.getElementById('attendanceChart');
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Present', 'Absent'],
          datasets: [{
            data: [<?php echo $totals['present']; ?>, <?php echo $totals['absent']; ?>],
            backgroundColor: ['#16a34a', '#dc2626'],
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } }
        }
      });
    <?php endif; ?>
  </script>
</body>
</html>
