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
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// FETCH EVENT FOR EDIT
// ----------------------
$edit_mode = false;
$edit_event = null;

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_event = $result->fetch_assoc();
    $stmt->close();
    if (!$edit_event) $edit_mode = false;
}

// ----------------------
// ADD EVENT
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $title       = $conn->real_escape_string($_POST['title']);
        $type        = $conn->real_escape_string($_POST['type']);
        $date        = $conn->real_escape_string($_POST['date']);
        $time        = $conn->real_escape_string($_POST['time']);
        $duration    = (int)$_POST['duration'];
        $location    = $conn->real_escape_string($_POST['location']);
        $facilitator = $conn->real_escape_string($_POST['facilitator']);
        $notes       = $conn->real_escape_string($_POST['notes']);

        $sql = "INSERT INTO events 
                (title, type, date, time, duration, location, facilitator, notes, approval_status, created_at) 
                VALUES ('$title', '$type', '$date', '$time', $duration, '$location', '$facilitator', '$notes', 'Pending', NOW())";
        if ($conn->query($sql)) {
            $success = "Event scheduled successfully! (Pending Approval)";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// ----------------------
// APPROVE EVENT
// ----------------------
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $conn->query("UPDATE events SET approval_status='Approved', approved_at=NOW() WHERE id=$id");
    header("Location: module2.1.php?approved=1");
    exit;
}

// ----------------------
// FETCH EVENTS
// ----------------------
$events = [];
$res = $conn->query("SELECT * FROM events ORDER BY date DESC, time DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Simulation Event Planning</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden">

<!-- Sidebar -->
<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
  <?php include '../sidebar.php'; ?>
</aside>

<!-- Top Navigation -->
<nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
  <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
    <i data-lucide="calendar-days" class="w-8 h-8 text-blue-600"></i>
    Simulation Event Planning
  </h1>
</nav>

<!-- Main Section -->
<main class="flex-1 h-full overflow-y-auto p-8 bg-gray-100 pt-20">

  <!-- Alerts -->
  <?php if (!empty($success)): ?>
    <div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['approved'])): ?>
    <div class="p-4 mb-4 text-blue-800 bg-blue-100 border border-blue-300 rounded-lg">Event approved successfully!</div>
  <?php endif; ?>

  <!-- Add / Edit Event Form -->
  <div class="bg-white p-6 rounded-xl shadow mb-8">
    <h2 class="text-lg font-semibold text-gray-700 mb-4"><?= $edit_mode ? "Edit Event" : "Schedule New Event" ?></h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?= $edit_event['id'] ?>"><?php endif; ?>

      <div>
        <label>Title</label>
        <input type="text" name="title" required value="<?= $edit_mode ? htmlspecialchars($edit_event['title']) : '' ?>" class="w-full border px-3 py-2 rounded-lg">
      </div>

      <div>
        <label>Type</label>
        <select name="type" required class="w-full border px-3 py-2 rounded-lg">
          <option value="Program">Program</option>
          <option value="Training">Training</option>
          <option value="Scenario-Based">Scenario-Based</option>
        </select>
      </div>

      <div class="flex gap-4">
        <div>
          <label>Date</label>
          <input type="date" name="date" required value="<?= $edit_mode ? $edit_event['date'] : '' ?>" class="border px-3 py-2 rounded-lg">
        </div>
        <div>
          <label>Time</label>
          <input type="time" name="time" required value="<?= $edit_mode ? $edit_event['time'] : '' ?>" class="border px-3 py-2 rounded-lg">
        </div>
        <div>
          <label>Duration (hours)</label>
          <input type="number" name="duration" min="1" required value="<?= $edit_mode ? $edit_event['duration'] : '' ?>" class="border px-3 py-2 rounded-lg">
        </div>
      </div>

      <div>
        <label>Location</label>
        <input type="text" name="location" required value="<?= $edit_mode ? htmlspecialchars($edit_event['location']) : '' ?>" class="w-full border px-3 py-2 rounded-lg">
      </div>

      <div>
        <label>Facilitator(s)</label>
        <input type="text" name="facilitator" value="<?= $edit_mode ? htmlspecialchars($edit_event['facilitator']) : '' ?>" class="w-full border px-3 py-2 rounded-lg">
      </div>

      <div>
        <label>Notes</label>
        <textarea name="notes" rows="3" class="w-full border px-3 py-2 rounded-lg"><?= $edit_mode ? htmlspecialchars($edit_event['notes']) : '' ?></textarea>
      </div>

      <button type="submit" name="add_event" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save Event</button>
    </form>
  </div>

  <!-- Event List -->
  <div class="bg-white p-6 rounded-xl shadow">
    <h2 class="text-lg font-semibold text-gray-700 mb-4">Scheduled Events</h2>
    <table class="w-full border-collapse text-left">
      <thead>
        <tr class="bg-gray-100">
          <th class="p-3">Title</th>
          <th class="p-3">Date</th>
          <th class="p-3">Time</th>
          <th class="p-3">Duration</th>
          <th class="p-3">Location</th>
          <th class="p-3">Status</th>
          <th class="p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $event): ?>
          <tr class="border-b">
            <td class="p-3"><?= htmlspecialchars($event['title']) ?></td>
            <td class="p-3"><?= htmlspecialchars($event['date']) ?></td>
            <td class="p-3"><?= htmlspecialchars($event['time']) ?></td>
            <td class="p-3"><?= htmlspecialchars($event['duration']) ?> hrs</td>
            <td class="p-3"><?= htmlspecialchars($event['location']) ?></td>
            <td class="p-3">
              <?= $event['approval_status'] == 'Approved' ? '<span class="text-green-600">Approved</span>' : '<span class="text-yellow-600">Pending</span>' ?>
            </td>
            <td class="p-3 flex gap-2">
              <?php if ($event['approval_status'] == 'Pending'): ?>
                <a href="?approve=<?= $event['id'] ?>" class="text-blue-600 hover:underline">Approve</a>
              <?php endif; ?>
              <a href="?edit=<?= $event['id'] ?>" class="text-gray-600 hover:underline">Edit</a>
              <a href="?delete=<?= $event['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Delete this event?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</main>
<script>lucide.createIcons();</script>
</body>
</html>
