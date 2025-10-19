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
$role = $_SESSION['role'];

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// VARIABLES
// ----------------------
$edit_mode = false;
$edit_announcement = null;

// ----------------------
// FETCH ANNOUNCEMENT FOR EDIT
// ----------------------
if ($role === 'admin' && isset($_GET['edit'])) {
    $edit_mode = true;
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_announcement = $result->fetch_assoc();
    $stmt->close();
    if (!$edit_announcement) $edit_mode = false;
}

// ----------------------
// ADD ANNOUNCEMENT
// ----------------------
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $title      = $conn->real_escape_string($_POST['title']);
        $audience   = $conn->real_escape_string($_POST['audience']);
        $event_date = $conn->real_escape_string($_POST['event_date']);
        $time       = $conn->real_escape_string($_POST['time']);
        $location   = $conn->real_escape_string($_POST['location']);
        $details    = $conn->real_escape_string($_POST['details']);

        $sql = "INSERT INTO announcements (title, audience, event_date, time, location, details, created_at)
                VALUES ('$title','$audience','$event_date','$time','$location','$details',NOW())";
        $conn->query($sql);
        header("Location: module2.2.php?success=1");
        exit;
    }
}

// ----------------------
// UPDATE ANNOUNCEMENT
// ----------------------
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $id         = (int)$_POST['id'];
        $title      = $conn->real_escape_string($_POST['title']);
        $audience   = $conn->real_escape_string($_POST['audience']);
        $event_date = $conn->real_escape_string($_POST['event_date']);
        $time       = $conn->real_escape_string($_POST['time']);
        $location   = $conn->real_escape_string($_POST['location']);
        $details    = $conn->real_escape_string($_POST['details']);

        $sql = "UPDATE announcements 
                SET title='$title', audience='$audience', event_date='$event_date', time='$time', 
                    location='$location', details='$details'
                WHERE id=$id";
        $conn->query($sql);
        header("Location: module2.2.php?updated=1");
        exit;
    }
}

// ----------------------
// DELETE ANNOUNCEMENT
// ----------------------
if ($role === 'admin' && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM announcements WHERE id=$id");
    header("Location: module2.2.php?deleted=1");
    exit;
}

// ----------------------
// FETCH ANNOUNCEMENTS (Upcoming Only)
// ----------------------
$announcements = [];
$sql = "SELECT * FROM announcements WHERE event_date >= CURDATE() ORDER BY event_date ASC";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upcoming Events</title>
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Leaflet + MapTiler -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    #map { height: 300px; border-radius: 0.5rem; }
  </style>
</head>
<body class="h-screen flex overflow-hidden">

  <!-- Sidebar -->
  <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
  </aside>

  <!-- Top Navigation -->
  <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
      <i data-lucide="megaphone" class="w-8 h-8 text-blue-600"></i>
      Upcoming Events Announcements
    </h1>
  </nav>

  <!-- Main Section -->
  <main class="flex-1 h-full overflow-y-auto p-8 bg-gray-100 pt-20">

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
      <div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg flex items-center gap-2">
        <i data-lucide="check-circle" class="w-5 h-5"></i>
        Announcement added successfully!
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
      <div class="p-4 mb-4 text-blue-800 bg-blue-100 border border-blue-300 rounded-lg flex items-center gap-2">
        <i data-lucide="info" class="w-5 h-5"></i>
        Announcement updated successfully!
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="p-4 mb-4 text-red-800 bg-red-100 border border-red-300 rounded-lg flex items-center gap-2">
        <i data-lucide="trash-2" class="w-5 h-5"></i>
        Announcement deleted.
      </div>
    <?php endif; ?>

    <!-- Only Admin can Add/Edit -->
    <?php if ($role === 'admin'): ?>
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <i data-lucide="<?= $edit_mode ? 'pencil' : 'plus-circle' ?>" class="w-5 h-5 text-blue-600"></i>
        <?= $edit_mode ? "Edit Announcement" : "Create New Announcement" ?>
      </h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <?php if ($edit_mode): ?>
          <input type="hidden" name="id" value="<?= $edit_announcement['id'] ?>">
        <?php endif; ?>

        <div>
          <label class="block font-medium text-gray-700 mb-1">Title</label>
          <input type="text" name="title" required 
                 value="<?= $edit_mode ? htmlspecialchars($edit_announcement['title']) : '' ?>"
                 class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300">
        </div>

        <div class="flex gap-6">
          <!-- Audience -->
          <div class="w-48">
              <label class="block font-medium text-gray-700 mb-1">Audience</label>
              <select name="audience" class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300">
                  <option value="General">General</option>
                  <option value="Staff">Staff</option>
                  <option value="User">User</option>
              </select>
          </div>

          <!-- Location -->
          <div class="w-64">
              <label class="block font-medium text-gray-700 mb-1">Location</label>
              <select 
                  name="location" 
                  id="locationSelect"
                  class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300"
                  required
              >
                  <option value="">-- Select Location --</option>
                  <?php
                  $sql = "SELECT id, name, address, lat, lng FROM locations ORDER BY name ASC";
                  $result = $conn->query($sql);
                  if ($result && $result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                          $value = htmlspecialchars($row['name'] . " - " . $row['address']);
                          $selected = ($edit_mode && $edit_announcement['location'] === $value) ? "selected" : "";
                          echo "<option data-lat='{$row['lat']}' data-lng='{$row['lng']}' value='" . $value . "' $selected>" . $value . "</option>";
                      }
                  }
                  ?>
              </select>
          </div>
        </div>

        <!-- Map Preview with Placeholder -->
        <div class="relative mt-4">
          <div id="map" class="h-[300px] rounded-lg"></div>
          <div id="mapPlaceholder" 
               class="absolute inset-0 flex items-center justify-center bg-gray-100 text-gray-500 text-lg font-medium rounded-lg">
            Select a location to preview map
          </div>
        </div>

        <!-- Date -->
        <div>
          <label class="block font-medium text-gray-700 mb-1">Date</label>
          <input type="date" name="event_date" required 
                 value="<?= $edit_mode ? htmlspecialchars($edit_announcement['event_date']) : '' ?>"
                 class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300">
        </div>

        <!-- Time -->
        <div>
          <label class="block font-medium text-gray-700 mb-1">Time</label>
          <input type="time" name="time" required 
                 value="<?= $edit_mode ? htmlspecialchars($edit_announcement['time']) : '' ?>"
                 class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300">
        </div>

        <!-- Details -->
        <div>
          <label class="block font-medium text-gray-700 mb-1">Details</label>
          <textarea name="details" rows="3" class="w-full border px-3 py-2 rounded-lg focus:ring focus:ring-blue-300"><?= $edit_mode ? htmlspecialchars($edit_announcement['details']) : '' ?></textarea>
        </div>

        <!-- Buttons -->
        <div class="flex gap-3">
          <?php if ($edit_mode): ?>
            <a href="module2.2.php" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400">Cancel</a>
            <button type="submit" name="update_announcement" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
              <i data-lucide="save" class="w-5 h-5"></i> Update
            </button>
          <?php else: ?>
            <button type="submit" name="add_announcement" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
              <i data-lucide="save" class="w-5 h-5"></i> Save
            </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Announcements List -->
    <div class="bg-white p-6 rounded-xl shadow">
      <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <i data-lucide="list" class="w-5 h-5 text-blue-600"></i>
        Upcoming Events
      </h2>
      <div class="space-y-4">
        <?php if (count($announcements) > 0): ?>
          <?php foreach ($announcements as $a): ?>
            <?php if ($a['audience'] === 'General' || $a['audience'] === $role): ?>
            <div class="p-4 border rounded-lg shadow-sm bg-gray-50 hover:bg-gray-100 transition">
              <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($a['title']) ?></h3>
              <p class="text-gray-600 mt-1"><?= htmlspecialchars($a['details']) ?></p>
              <div class="mt-2 flex flex-wrap gap-4 text-sm text-gray-700">
                <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-4 h-4"></i> <?= htmlspecialchars($a['event_date']) ?></span>
                <span class="flex items-center gap-1"><i data-lucide="clock" class="w-4 h-4"></i> <?= htmlspecialchars($a['time']) ?></span>
                <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-4 h-4"></i> <?= htmlspecialchars($a['location']) ?></span>
                <span class="flex items-center gap-1"><i data-lucide="users" class="w-4 h-4"></i> Audience: <?= htmlspecialchars($a['audience']) ?></span>
              </div>
              <?php if ($role === 'admin'): ?>
              <div class="mt-2 flex gap-4 text-sm">
                <a href="?edit=<?= $a['id'] ?>" class="text-blue-600 hover:underline">Edit</a>
                <a href="?delete=<?= $a['id'] ?>" onclick="return confirm('Delete this announcement?')" class="text-red-600 hover:underline">Delete</a>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-gray-500">No upcoming events yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <script> lucide.createIcons(); </script>

  <!-- MapTiler Script -->
  <script>
    const key = "yfboSZCNAu3e7LkIkLlS";
    const map = L.map('map').setView([14.676, 121.0437], 12);

    L.tileLayer(`https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=${key}`, {
      attribution: '&copy; <a href="https://www.maptiler.com/">MapTiler</a>',
    }).addTo(map);

    const marker = L.marker([14.676, 121.0437]).addTo(map);
    const placeholder = document.getElementById('mapPlaceholder');

    // Update marker when selecting a location
    document.getElementById('locationSelect').addEventListener('change', function() {
      const option = this.options[this.selectedIndex];
      const lat = option.getAttribute('data-lat');
      const lng = option.getAttribute('data-lng');
      if (lat && lng) {
        map.setView([lat, lng], 15);
        marker.setLatLng([lat, lng]);
        placeholder.style.display = "none";
      } else {
        placeholder.style.display = "flex";
      }
    });
  </script>
</body>
</html>
