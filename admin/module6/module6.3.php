<?php
session_start();
// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// CREATE DATABASE TABLE IF IT DOESN'T EXIST
// =========================
$sql_create_table = "
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `duration` INT(11) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `location_lat` DECIMAL(10, 8) NULL,
    `location_lng` DECIMAL(11, 8) NULL,
    `facilitator` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `approval_status` ENUM('Pending','Approved') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_table)) {
    die("Error creating events table: " . $conn->error);
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
// ADD / UPDATE EVENT
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $title = $conn->real_escape_string($_POST['title']);
        $type = $conn->real_escape_string($_POST['type']);
        $date = $conn->real_escape_string($_POST['date']);
        $time = $conn->real_escape_string($_POST['time']);
        $duration = (int)$_POST['duration'];
        $location = $conn->real_escape_string($_POST['location']);
        $location_lat = floatval($_POST['location_lat']);
        $location_lng = floatval($_POST['location_lng']);
        $facilitator = $conn->real_escape_string($_POST['facilitator']);
        $notes = $conn->real_escape_string($_POST['notes']);

        if ($edit_mode) {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE events SET title=?, type=?, date=?, time=?, duration=?, location=?, location_lat=?, location_lng=?, facilitator=?, notes=? WHERE id=?");
            $stmt->bind_param("sssidsddssi", $title, $type, $date, $time, $duration, $location, $location_lat, $location_lng, $facilitator, $notes, $id);
            if ($stmt->execute()) {
                $success = "Event updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO events (title, type, date, time, duration, location, location_lat, location_lng, facilitator, notes, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("sssidsddss", $title, $type, $date, $time, $duration, $location, $location_lat, $location_lng, $facilitator, $notes);
            if ($stmt->execute()) {
                $success = "Event scheduled successfully! (Pending Approval)";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
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
// DELETE EVENT
// ----------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM events WHERE id=$id");
    header("Location: module2.1.php?deleted=1");
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simulation Event Planning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.maptiler.com/maptiler-sdk-js/v1.2.0/maptiler-sdk.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.maptiler.com/maptiler-sdk-js/v1.2.0/maptiler-sdk.css" />
    <style>
        .map-container { height: 300px; width: 100%; }
    </style>
</head>
<body class="h-screen flex overflow-hidden">

<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
</aside>

<nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
        <i data-lucide="calendar-days" class="w-8 h-8 text-blue-600"></i>
        Simulation Event Planning
    </h1>
</nav>

<main class="flex-1 h-full overflow-y-auto p-8 bg-gray-100 pt-20">

    <?php if (!empty($success)): ?>
        <div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="p-4 mb-4 text-red-800 bg-red-100 border border-red-300 rounded-lg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['approved'])): ?>
        <div class="p-4 mb-4 text-blue-800 bg-blue-100 border border-blue-300 rounded-lg">Event approved successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="p-4 mb-4 text-red-800 bg-red-100 border border-red-300 rounded-lg">Event deleted successfully.</div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-xl shadow mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4"><?= $edit_mode ? "Edit Event" : "Schedule New Event" ?></h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?= $edit_event['id'] ?>"><?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" required value="<?= $edit_mode ? htmlspecialchars($edit_event['title']) : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select name="type" required class="w-full border px-3 py-2 rounded-lg mt-1">
                    <option value="Program" <?= ($edit_mode && $edit_event['type'] == 'Program') ? 'selected' : '' ?>>Program</option>
                    <option value="Training" <?= ($edit_mode && $edit_event['type'] == 'Training') ? 'selected' : '' ?>>Training</option>
                    <option value="Scenario-Based" <?= ($edit_mode && $edit_event['type'] == 'Scenario-Based') ? 'selected' : '' ?>>Scenario-Based</option>
                </select>
            </div>

            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="date" required value="<?= $edit_mode ? $edit_event['date'] : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Time</label>
                    <input type="time" name="time" required value="<?= $edit_mode ? $edit_event['time'] : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Duration (hours)</label>
                    <input type="number" name="duration" min="1" required value="<?= $edit_mode ? $edit_event['duration'] : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Location</label>
                <input type="text" id="location_name" name="location" required value="<?= $edit_mode ? htmlspecialchars($edit_event['location']) : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
            </div>
            
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Latitude</label>
                    <input type="text" id="location_lat" name="location_lat" required readonly value="<?= $edit_mode ? $edit_event['location_lat'] : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1 bg-gray-100 cursor-not-allowed">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Longitude</label>
                    <input type="text" id="location_lng" name="location_lng" required readonly value="<?= $edit_mode ? $edit_event['location_lng'] : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1 bg-gray-100 cursor-not-allowed">
                </div>
            </div>

            <div id="map" class="map-container rounded-lg border-2 border-gray-300"></div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Facilitator(s)</label>
                <input type="text" name="facilitator" value="<?= $edit_mode ? htmlspecialchars($edit_event['facilitator']) : '' ?>" class="w-full border px-3 py-2 rounded-lg mt-1">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="3" class="w-full border px-3 py-2 rounded-lg mt-1"><?= $edit_mode ? htmlspecialchars($edit_event['notes']) : '' ?></textarea>
            </div>

            <button type="submit" name="save_event" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Event</button>
        </form>
    </div>

    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Scheduled Events</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-left">
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
                            <td class="p-3">
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['location']) ?>" target="_blank" class="text-blue-500 hover:underline">
                                    <?= htmlspecialchars($event['location']) ?>
                                    <i data-lucide="map-pin" class="inline-block w-4 h-4 ml-1"></i>
                                </a>
                            </td>
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
    </div>
</main>
<script>
    lucide.createIcons();
    maptiler.apiKey = 'yfboSZCNAu3e7LkIkLlS';
    const map = maptiler.createMap({
        container: 'map',
        style: maptiler.MapStyle.STREETS,
        center: [121.045, 14.65], // Quezon City, Philippines
        zoom: 12
    });

    const latInput = document.getElementById('location_lat');
    const lngInput = document.getElementById('location_lng');
    const locationNameInput = document.getElementById('location_name');
    let marker = null;

    // Add this code block right below your existing map initialization
      const searchBox = document.getElementById('location_name');

      // Use a debounce function to avoid excessive API calls while typing
      function debounce(func, delay) {
          let timeout;
          return function(...args) {
              clearTimeout(timeout);
              timeout = setTimeout(() => func.apply(this, args), delay);
          };
      }

      // Geocoding function
      const handleGeocode = debounce(async (query) => {
          if (query.length < 3) return; // Don't search for short queries
          const response = await fetch(`https://api.maptiler.com/geocoding/${query}.json?key=${maptiler.apiKey}&proximity=121.045,14.65`);
          const data = await response.json();
          
          if (data.features.length > 0) {
              const coords = data.features[0].geometry.coordinates;
              const [lng, lat] = coords;
              
              // Update inputs and map
              latInput.value = lat.toFixed(6);
              lngInput.value = lng.toFixed(6);
              
              map.setCenter([lng, lat]);
              if (marker) {
                  marker.remove();
              }
              marker = new maptiler.Marker({ color: '#FF0000' })
                  .setLngLat([lng, lat])
                  .addTo(map);
          }
      }, 500); // 500ms delay

      searchBox.addEventListener('input', (e) => {
          handleGeocode(e.target.value);
      });

    // Place initial marker if in edit mode
    <?php if ($edit_mode && $edit_event['location_lat'] && $edit_event['location_lng']): ?>
    const initialLat = <?= $edit_event['location_lat'] ?>;
    const initialLng = <?= $edit_event['location_lng'] ?>;
    marker = new maptiler.Marker({ color: '#FF0000' }).setLngLat([initialLng, initialLat]).addTo(map);
    map.setCenter([initialLng, initialLat]);
    <?php endif; ?>

    map.on('click', (e) => {
        const { lat, lng } = e.lngLat;
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);

        if (marker) {
            marker.remove();
        }
        marker = new maptiler.Marker({ color: '#FF0000' })
            .setLngLat([lng, lat])
            .addTo(map);
    });

    // Handle form submission
    const form = document.querySelector('form');
    form.addEventListener('submit', (e) => {
        if (!latInput.value || !lngInput.value) {
            alert('Please select a location on the map.');
            e.preventDefault();
        }
    });
    
</script>
</body>
</html>