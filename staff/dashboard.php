<?php
// dashboard.php (Staff Dashboard)
// Later: fetch staff data from session/db
$staffName = "John Doe";
$staffRole = "Training Staff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex">

  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col min-h-screen">

    <!-- Header -->
    <header class="bg-white shadow px-6 py-4 flex items-center justify-between">
      <h1 class="text-xl font-bold text-blue-700">Staff Dashboard</h1>
      <div class="flex items-center gap-4">
        <div class="text-right">
          <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($staffName); ?></p>
          <p class="text-sm text-gray-500"><?php echo htmlspecialchars($staffRole); ?></p>
        </div>
        <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold">
          <?php echo strtoupper(substr($staffName, 0, 1)); ?>
        </div>
      </div>
    </header>

    <!-- Dashboard Content -->
    <main class="p-6 flex-1">

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white shadow rounded-2xl p-4">
          <h2 class="text-sm text-gray-500">Assigned Modules</h2>
          <p class="text-2xl font-bold text-blue-700">5</p>
        </div>
        <div class="bg-white shadow rounded-2xl p-4">
          <h2 class="text-sm text-gray-500">Upcoming Events</h2>
          <p class="text-2xl font-bold text-green-600">3</p>
        </div>
        <div class="bg-white shadow rounded-2xl p-4">
          <h2 class="text-sm text-gray-500">Attendance Rate</h2>
          <p class="text-2xl font-bold text-yellow-600">92%</p>
        </div>
        <div class="bg-white shadow rounded-2xl p-4">
          <h2 class="text-sm text-gray-500">Certificates Issued</h2>
          <p class="text-2xl font-bold text-purple-600">12</p>
        </div>
      </div>

      <!-- Placeholder for More Content -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Recent Activity -->
        <div class="bg-white shadow rounded-2xl p-6">
          <h2 class="text-lg font-bold text-gray-700 mb-4">Recent Activity</h2>
          <ul class="space-y-3 text-sm text-gray-600">
            <li>✔️ Completed training on <strong>Fire Safety</strong></li>
            <li>📅 Scheduled event for <strong>Earthquake Drill</strong></li>
            <li>👥 Registered 20 new participants</li>
            <li>🏅 Issued certificate to <strong>Batch 3</strong></li>
          </ul>
        </div>

        <!-- Announcements -->
        <div class="bg-white shadow rounded-2xl p-6">
          <h2 class="text-lg font-bold text-gray-700 mb-4">Announcements</h2>
          <ul class="space-y-3 text-sm text-gray-600">
            <li>📢 Staff meeting on Friday, 3 PM</li>
            <li>🚨 New disaster type training available</li>
            <li>📝 Submit reports before end of the week</li>
          </ul>
        </div>
      </div>

    </main>
  </div>
</body>
</html>
