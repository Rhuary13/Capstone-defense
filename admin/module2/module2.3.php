<?php
session_start();

// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

// =========================
// Auth Check
// =========================
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// CSRF Protection
// =========================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =========================
// Fetch Locations from Notifications
// =========================
$locations = [];
$notifResult = $conn->query("SELECT id, title, message, location_lat, location_lng FROM notifications ORDER BY id DESC LIMIT 50");
if ($notifResult) {
    while ($row = $notifResult->fetch_assoc()) {
        $locations[] = $row;
    }
}

// =========================
// Fetch Staff with Credentials
// Only staff with non-empty certification
// =========================
$staff = [];
$staffResult = $conn->query("SELECT id, name, role, certification FROM staff WHERE certification IS NOT NULL AND certification <> '' ORDER BY name ASC");
if ($staffResult) {
    while ($row = $staffResult->fetch_assoc()) {
        $staff[] = $row;
    }
}

// =========================
// Handle Form Submission
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== $_SESSION['csrf_token']) die("Invalid CSRF Token");

    $staff_id = $_POST['staff_id'];
    $notif_id = $_POST['location_id'];
    $role_name = $_POST['role_name'];

    $stmt = $conn->prepare("INSERT INTO role_assignments (staff_id, notification_id, role_name, assigned_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $staff_id, $notif_id, $role_name);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Role assigned successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
$notifResult = $conn->query("SELECT id, title, message, location_lat, location_lng FROM notifications ORDER BY id DESC LIMIT 50");
$locations = $notifResult->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Role Assignment</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<!-- MAPTILER SDK -->
<link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.1.0/maptiler-sdk.css" rel="stylesheet">
<script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.1.0/maptiler-sdk.umd.js"></script>

<style>
main {
    overflow-y: auto;
    height: calc(100vh - 64px);
    padding-right: 1rem;
}
</style>
</head>
<body class="h-screen flex bg-gray-100">

<!-- SIDEBAR -->
<aside class="w-64 bg-blue-700 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
</aside>

<!-- NAVBAR -->
<nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
        <i data-lucide="users" class="w-7 h-7 text-blue-600"></i>
        Role Assignment
    </h1>
</nav>

<!-- MAIN CONTENT -->
<main class="flex-1 px-8 pt-24 pb-10">

    <!-- Success Message -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- System Overview -->
    <div class="bg-white p-6 rounded-2xl shadow-md mb-8">
        <h2 class="text-xl font-bold text-gray-700 mb-2 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
            System Overview
        </h2>
        <p class="text-gray-600 leading-relaxed">
            Assign staff to organize participants for simulation exercises.
            Staff must have proper training and certification. Locations are pulled from Notification Reminders and visualized on MapTiler map.
        </p>
    </div>

    <!-- GRID: Role Assignment Form & Map -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- ROLE ASSIGNMENT FORM -->
        <div class="bg-white p-6 rounded-2xl shadow-md">
            <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                <i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i>
                Assign Staff Role
            </h3>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div>
                    <label class="font-medium text-gray-700">Staff</label>
                    <select name="staff_id" required class="w-full border rounded-xl px-4 py-3 mt-1">
                        <option value="">Select Staff</option>
                        <?php foreach($staff as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['certification']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="font-medium text-gray-700">Role Name</label>
                    <input type="text" name="role_name" placeholder="Event Organizer, Trainer, etc."
                        required class="w-full border rounded-xl px-4 py-3 mt-1">
                </div>

                <div>
                    <label class="font-medium text-gray-700">Simulation Location</label>
                    <select name="location_id" required class="w-full border rounded-xl px-4 py-3 mt-1">
                        <option value="">Select Location from Notifications</option>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>">
                                <?= htmlspecialchars($loc['title']) ?> - <?= htmlspecialchars($loc['message']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="assign_role"
                        class="bg-blue-600 text-white px-5 py-3 rounded-xl w-full">
                    Assign Role
                </button>
            </form>
        </div>

        <!-- MAPTILER MAP -->
        <div class="bg-white p-6 rounded-2xl shadow-md">
            <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                <i data-lucide="map-pin" class="w-5 h-5 text-blue-600"></i>
                Notification Locations
            </h3>
            <div id="map" class="w-full h-96 rounded-xl bg-gray-200"></div>
            <p class="text-gray-600 text-sm mt-3">
                Map shows all locations from notification reminders. Select a notification in the form to assign staff.
            </p>
        </div>

    </div>
</main>

<script>
lucide.createIcons();

// MAPTILER CONFIG
maptilersdk.config.apiKey = "yfboSZCNAu3e7LkIkLlS"; // replace with your MapTiler API key
const map = new maptilersdk.Map({
    container: "map",
    style: maptilersdk.MapStyle.STREETS,
    center: [121.03, 14.66],
    zoom: 12
});

// Add markers for each location
const locations = <?= json_encode($locations) ?>;
locations.forEach(loc => {
    if (loc.location_lat && loc.location_lng) {
        new maptilersdk.Marker({ color: "red" })
            .setLngLat([parseFloat(loc.location_lng), parseFloat(loc.location_lat)])
            .setPopup(new maptilersdk.Popup().setText(loc.title + ": " + loc.message))
            .addTo(map);
    }
});

// Optional: fly to first location
if (locations.length > 0) {
    map.flyTo({
        center: [parseFloat(locations[0].location_lng), parseFloat(locations[0].location_lat)],
        zoom: 13
    });
}
</script>
</body>
</html>
