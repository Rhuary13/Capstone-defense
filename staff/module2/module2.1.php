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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = $conn->real_escape_string($_POST['title']);
    $type       = $conn->real_escape_string($_POST['type']);
    $location   = $conn->real_escape_string($_POST['location']);
    $date       = $conn->real_escape_string($_POST['date']);
    $time       = $conn->real_escape_string($_POST['time']);
    $duration   = (int) $_POST['duration'];
    $facilitator= $conn->real_escape_string($_POST['facilitator']);
    $audience   = $conn->real_escape_string($_POST['audience']);
    $notes      = $conn->real_escape_string($_POST['notes']);

    $conn->query("INSERT INTO events (title, type, location, date, time, duration, facilitator, audience, notes, created_at, approval_status) 
                  VALUES ('$title', '$type', '$location', '$date', '$time', '$duration', '$facilitator', '$audience', '$notes', NOW(), 'Pending')");
}

// Fetch events
$events = $conn->query("SELECT * FROM events ORDER BY date ASC, time ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Event Scheduling</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Event Scheduling</h1>
      <p class="text-sm text-slate-500 mt-1">Coordinate logistics and propose event schedules.</p>
    </div>

    <!-- Add Event Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Add New Event</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Event Title</label>
          <input type="text" name="title" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Type</label>
          <select name="type" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
            <option value="Program">Program</option>
            <option value="Training">Training</option>
            <option value="Scenario-Based">Scenario-Based</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Date</label>
          <input type="date" name="date" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Time</label>
          <input type="time" name="time" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Duration (minutes)</label>
          <input type="number" name="duration" min="0" class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Location</label>
          <input type="text" name="location" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Facilitator</label>
          <input type="text" name="facilitator" class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Audience</label>
          <input type="text" name="audience" class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Notes</label>
          <textarea name="notes" rows="3" class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200"></textarea>
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Save Event
          </button>
        </div>
      </form>
    </div>

    <!-- Events List -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Upcoming Events</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">Title</th>
              <th class="p-3 border-b">Type</th>
              <th class="p-3 border-b">Date</th>
              <th class="p-3 border-b">Time</th>
              <th class="p-3 border-b">Duration</th>
              <th class="p-3 border-b">Location</th>
              <th class="p-3 border-b">Facilitator</th>
              <th class="p-3 border-b">Audience</th>
              <th class="p-3 border-b">Approval</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($events->num_rows > 0): ?>
              <?php while ($e = $events->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?= htmlspecialchars($e['title']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($e['type']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($e['date']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($e['time']); ?></td>
                  <td class="p-3 border-b"><?= $e['duration'] ? $e['duration'].' min' : '—'; ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($e['location']); ?></td>
                  <td class="p-3 border-b"><?= $e['facilitator'] ? htmlspecialchars($e['facilitator']) : '—'; ?></td>
                  <td class="p-3 border-b"><?= $e['audience'] ? htmlspecialchars($e['audience']) : '—'; ?></td>
                  <td class="p-3 border-b">
                    <span class="px-2 py-1 rounded text-xs 
                      <?= $e['approval_status'] === 'Approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                      <?= htmlspecialchars($e['approval_status']); ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="p-4 text-center text-slate-500">No events scheduled yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
