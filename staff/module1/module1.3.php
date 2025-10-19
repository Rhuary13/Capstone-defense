  <?php
  // staff_scheduling.php
  session_start();

  // --- Database Connection ---
  $host = "localhost";
  $user = "root";
  $pass = "";
  $db   = "training_management"; 

  $conn = new mysqli($host, $user, $pass, $db);
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }

  // --- Create table if missing (safety net) ---
  $conn->query("CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");

  // --- Handle Form Submission ---
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $title       = $_POST['title'];
      $description = $_POST['description'];
      $event_date  = $_POST['event_date'];
      $start_time  = $_POST['start_time'];
      $end_time    = $_POST['end_time'];
      $venue       = $_POST['venue'];

      $stmt = $conn->prepare("INSERT INTO schedules (title, description, event_date, start_time, end_time, venue) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssss", $title, $description, $event_date, $start_time, $end_time, $venue);
      $stmt->execute();
      $stmt->close();
  }

  // --- Fetch All Schedules ---
  $result = $conn->query("SELECT * FROM schedules ORDER BY event_date ASC, start_time ASC");
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Staff Scheduling</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?> 

    <!-- Main Content -->
    <div class="flex-1 p-6 overflow-y-auto">
      <header class="bg-blue-700 text-white p-4 rounded-lg shadow mb-6 text-xl font-bold">
        Staff Scheduling - Inform Participants
      </header>

      <!-- Schedule Form -->
      <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Add New Schedule</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

          <div>
            <label class="block text-sm font-medium mb-1">Event Title</label>
            <input type="text" name="title" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Venue</label>
            <input type="text" name="venue" class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Event Date</label>
            <input type="date" name="event_date" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Start Time</label>
            <input type="time" name="start_time" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">End Time</label>
            <input type="time" name="end_time" required class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300">
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Description / Notes</label>
            <textarea name="description" rows="3" class="w-full p-2 border rounded-lg focus:ring focus:ring-blue-300"></textarea>
          </div>

          <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
              Save Schedule
            </button>
          </div>
        </form>
      </div>

      <!-- Schedule List -->
      <div class="bg-white rounded-2xl shadow-md p-6">
        <h2 class="text-lg font-semibold mb-4">Upcoming Schedules</h2>
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="bg-gray-200 text-left text-sm">
              <th class="p-2 border">Title</th>
              <th class="p-2 border">Date</th>
              <th class="p-2 border">Time</th>
              <th class="p-2 border">Venue</th>
              <th class="p-2 border">Description</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()) : ?>
              <tr class="hover:bg-gray-50 text-sm">
                <td class="p-2 border font-medium"><?php echo htmlspecialchars($row['title']); ?></td>
                <td class="p-2 border"><?php echo htmlspecialchars($row['event_date']); ?></td>
                <td class="p-2 border">
                  <?php echo htmlspecialchars(substr($row['start_time'],0,5)); ?> - 
                  <?php echo htmlspecialchars(substr($row['end_time'],0,5)); ?>
                </td>
                <td class="p-2 border"><?php echo htmlspecialchars($row['venue']); ?></td>
                <td class="p-2 border"><?php echo htmlspecialchars($row['description']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </body>
  </html>
  <?php $conn->close(); ?>
