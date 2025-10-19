<?php
// scheduling.php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// =========================
// Ensure schedules table exists (based on your actual structure)
// =========================
$conn->query("CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// =========================
// Fetch schedules
// =========================
$sql = "SELECT * FROM schedules ORDER BY event_date ASC, start_time ASC";
$result = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>📅 Scheduling & Availability</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-8 overflow-y-auto">
    <header class="mb-8 text-center">
      <h1 class="text-3xl font-bold text-blue-800">📅 Training Schedule</h1>
      <p class="text-lg text-gray-600">Here are your upcoming sessions. Please take note of the time and venue.</p>
    </header>

    <!-- Schedule Table -->
    <section>
      <div class="bg-white shadow rounded-xl overflow-hidden">
        <table class="w-full border-collapse text-lg">
          <thead class="bg-blue-700 text-white text-xl">
            <tr>
              <th class="p-4 text-left">📌 Title</th>
              <th class="p-4 text-left">📝 Description</th>
              <th class="p-4 text-center">📅 Date</th>
              <th class="p-4 text-center">⏰ Time</th>
              <th class="p-4 text-left">📍 Venue</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="border-b hover:bg-blue-50">
                  <td class="p-4 font-semibold"><?= htmlspecialchars($row['title']) ?></td>
                  <td class="p-4"><?= htmlspecialchars($row['description']) ?></td>
                  <td class="p-4 text-center text-blue-700 font-bold">
                    <?= date("F d, Y", strtotime($row['event_date'])) ?>
                  </td>
                  <td class="p-4 text-center">
                    <?= date("h:i A", strtotime($row['start_time'])) ?> - <?= date("h:i A", strtotime($row['end_time'])) ?>
                  </td>
                  <td class="p-4"><?= htmlspecialchars($row['venue']) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="p-6 text-center text-gray-500 text-xl">No schedules available yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</body>
</html>
