<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Auto-create tables if missing (with error reporting)
// =========================
$queries = [

"CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

"CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    participant_id INT NOT NULL,
    status ENUM('confirmed','pending') DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL,
    UNIQUE KEY unique_attendance (event_id, participant_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        die("Table creation failed: " . $conn->error);
    }
}

// =========================
// Mock participant login
// =========================
$participant_id = $_SESSION['user_id'] ?? 1;

// =========================
// Handle attendance confirmation
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
    $stmt = $conn->prepare("INSERT INTO attendance (event_id, participant_id, status, confirmed_at)
                            VALUES (?, ?, 'confirmed', NOW())
                            ON DUPLICATE KEY UPDATE status='confirmed', confirmed_at=NOW()");
    $stmt->bind_param("ii", $event_id, $participant_id);
    if ($stmt->execute()) {
        $message = "✅ Your attendance has been confirmed!";
    } else {
        $message = "❌ Error: " . $stmt->error;
    }
    $stmt->close();
}

// =========================
// Fetch events with attendance status
// =========================
$sql = "SELECT e.*, 
               IF(a.status='confirmed','Yes','No') AS attending
        FROM events e
        LEFT JOIN attendance a 
        ON e.id = a.event_id AND a.participant_id = $participant_id
        ORDER BY e.event_date ASC, e.start_time ASC";
$events = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>📅 Event Schedule</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden">

  <!-- Sidebar (from sidebar.php) -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <div class="max-w-5xl mx-auto bg-white p-6 rounded-2xl shadow-lg">
      <h1 class="text-3xl font-bold text-blue-700 mb-6">📅 Event Schedule</h1>

      <?php if (!empty($message)): ?>
        <div class="p-4 mb-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
          <?= $message ?>
        </div>
      <?php endif; ?>

      <?php if ($events && $events->num_rows > 0): ?>
        <div class="space-y-6">
          <?php while ($row = $events->fetch_assoc()): ?>
            <div class="p-6 border rounded-xl shadow-sm bg-gray-50">
              <h2 class="text-2xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($row['title']); ?></h2>
              <p class="text-gray-600 mb-2"><?= htmlspecialchars($row['description']); ?></p>
              <p class="text-lg">📍 <strong><?= htmlspecialchars($row['location']); ?></strong></p>
              <p class="text-lg">🗓 <?= date("F j, Y", strtotime($row['event_date'])); ?></p>
              <p class="text-lg">⏰ <?= date("g:i A", strtotime($row['start_time'])); ?> - <?= date("g:i A", strtotime($row['end_time'])); ?></p>

              <div class="mt-4 flex items-center justify-between">
                <span class="text-gray-700 text-lg">
                  Attendance: <strong class="<?= $row['attending'] === 'Yes' ? 'text-green-600' : 'text-red-600'; ?>">
                    <?= $row['attending'] ?>
                  </strong>
                </span>

                <?php if ($row['attending'] !== 'Yes'): ?>
                  <form method="POST">
                    <input type="hidden" name="event_id" value="<?= $row['id']; ?>">
                    <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-lg hover:bg-blue-700">
                      ✅ Yes, I'll attend
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-green-700 font-bold">✔️ Confirmed</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p class="text-red-600 font-semibold">❌ No events scheduled yet. Please check back later.</p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
