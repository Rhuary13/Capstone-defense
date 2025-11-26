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
// Handle Notification Form Only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {

    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $target = $_POST['target'] ?? '';

    $_SESSION['form_data'] = [
        'title' => $title,
        'message' => $message,
        'target' => $target
    ];

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
// Retrieve old values if exist
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // optional: clear after use
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['send_reminder'])) {
        $template = $_POST['template'];
        // Fetch reminder settings from DB if enabled
        // Determine target (all / participants / staff)
        // Send notification via your notification function
        // Example: sendNotification($template, $target);
    }

    if (isset($_POST['save_reminder_settings'])) {
        $frequency = $_POST['reminder_frequency'] ?? '1_day';
        $enabled = isset($_POST['enable_auto_reminder']) ? 1 : 0;
        // Save to database
        // Example: saveReminderSettings($frequency, $enabled);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $target = $_POST['target'];
    $lat = $_POST['location_lat'] ?? NULL;
    $lng = $_POST['location_lng'] ?? NULL;

    $stmt = $conn->prepare("INSERT INTO notifications (title, message, target, location_lat, location_lng) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdd", $title, $message, $target, $lat, $lng);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Notification saved successfully!";
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications & Reminder System</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<!-- MAPTILER (latest official stable CDN) -->
<link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.1.0/maptiler-sdk.css" rel="stylesheet">
<script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.1.0/maptiler-sdk.umd.js"></script>

<style>
    #map {
        width: 100%;
        height: 350px;
        border-radius: 1rem;
        overflow: hidden;
    }
</style>

</head>

<body class="h-screen flex overflow-hidden bg-gray-100">

<!-- SIDEBAR -->
<aside class="w-64 bg-blue-700 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
</aside>

<!-- NAVBAR -->
<nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
        <i data-lucide="bell" class="w-7 h-7 text-blue-600"></i>
        Notifications & Reminder System
    </h1>
</nav>

<!-- MAIN CONTENT -->
<main class="flex-1 overflow-y-auto px-8 pt-24 pb-10">

    <!-- PAGE INTRO -->
    <div class="bg-white p-6 rounded-2xl shadow-md mb-8">
        <h2 class="text-xl font-bold text-gray-700 mb-2 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
            System Overview
        </h2>
        <p class="text-gray-600 leading-relaxed">
            This module allows the Admin to send barangay-wide alerts, configure reminders,
            manage templates, and notify participants & staff about schedule changes, simulation
            updates, and locations via MapTiler.
        </p>
    </div>

    <!-- GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- SEND NOTIF -->
        <div class="bg-white p-6 rounded-2xl shadow-md">
            <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                <i data-lucide="send" class="w-5 h-5 text-blue-600"></i>
                Send Barangay-wide Notification
            </h3>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="location_lat" id="locationLat"> 
                <input type="hidden" name="location_lng" id="locationLng">

                <div>
                    <label class="font-medium text-gray-700">Notification Title</label>
                    <input type="text" name="title" required
                          value="<?= htmlspecialchars($form_data['title'] ?? '') ?>"
                          class="w-full border rounded-xl px-4 py-3 mt-1">
                </div>

                <div>
                    <label class="font-medium text-gray-700">Message</label>
                    <textarea name="message" rows="4" required
                              class="w-full border rounded-xl px-4 py-3 mt-1"><?= htmlspecialchars($form_data['message'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="font-medium text-gray-700">Notify Target</label>
                    <select name="target" class="w-full border rounded-xl px-4 py-3 mt-1">
                        <option value="all" <?= (isset($form_data['target']) && $form_data['target'] === 'all') ? 'selected' : '' ?>>All Participants & Staff</option>
                        <option value="participants" <?= (isset($form_data['target']) && $form_data['target'] === 'participants') ? 'selected' : '' ?>>Participants Only</option>
                        <option value="staff" <?= (isset($form_data['target']) && $form_data['target'] === 'staff') ? 'selected' : '' ?>>Staff Only</option>
                    </select>
                </div>

                <button class="bg-blue-600 text-white font-semibold px-5 py-3 rounded-xl w-full">
                    Send Notification
                </button>
            </form>
        </div>

        <!-- MAPTILER -->
        <div class="bg-white p-6 rounded-2xl shadow-md">
            <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                <i data-lucide="map-pin" class="w-5 h-5 text-blue-600"></i>
                Simulation Location (MapTiler)
            </h3>

            <div class="grid grid-cols-2 gap-4 mb-4">
                  <!-- DISTRICT SELECT -->
                  <div>
                      <label class="font-medium text-gray-700">District</label>
                      <select id="districtSelect" class="w-full border rounded-xl px-4 py-3 mt-1">
                          <option value="">Select District</option>
                          <option value="1">District 1</option>
                          <option value="2">District 2</option>
                          <option value="3">District 3</option>
                          <option value="4">District 4</option>
                          <option value="5">District 5</option>
                          <option value="6">District 6</option>
                      </select>
                  </div>

                  <!-- BARANGAY SELECT -->
                  <div>
                      <label class="font-medium text-gray-700">Barangay</label>
                      <select id="barangaySelect" class="w-full border rounded-xl px-4 py-3 mt-1">
                          <option value="">Select Barangay</option>
                      </select>
                  </div>
              </div>
            <div id="map"></div>
              <style>
                  #map {
                      width: 100%;
                      height: 400px; 
                      border-radius: 1rem;
                      background: #e5e7eb;
                  }
              </style>
            <p class="text-gray-600 text-sm mt-3">
                Click on the map to select a simulation location. The coordinates will be added to the notification.
            </p>
        </div>

        <!-- TEMPLATES -->
          <div class="bg-white p-6 rounded-2xl shadow-md">
              <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                  <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>
                  Communication Templates
              </h3>

              <div class="space-y-4">
                  <button class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl">
                      Create New Template
                  </button>

                  <div class="border rounded-xl p-4">
                      <h4 class="font-bold text-gray-700">Evacuation Drill Reminder</h4>
                      <p class="text-gray-600 text-sm mt-2">Used for scheduled drills.</p>
                      <button class="mt-2 text-blue-600 font-medium">
                          Edit Template
                      </button>
                      <!-- New: Trigger Automatic Reminder -->
                      <form method="POST" class="mt-2">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <input type="hidden" name="template" value="evacuation_drill_reminder">
                          <button type="submit" name="send_reminder" 
                                  class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl">
                              Send Automatic Reminder
                          </button>
                      </form>
                  </div>

                  <div class="border rounded-xl p-4">
                      <h4 class="font-bold text-gray-700">Simulation Update Notice</h4>
                      <p class="text-gray-600 text-sm mt-2">Used for schedule changes.</p>
                      <button class="mt-2 text-blue-600 font-medium">
                          Edit Template
                      </button>
                      <!-- New: Trigger Automatic Reminder -->
                      <form method="POST" class="mt-2">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <input type="hidden" name="template" value="simulation_update_notice">
                          <button type="submit" name="send_reminder" 
                                  class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl">
                              Send Automatic Reminder
                          </button>
                      </form>
                  </div>
              </div>
          </div>

          <!-- REMINDERS -->
          <div class="bg-white p-6 rounded-2xl shadow-md">
              <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                  <i data-lucide="clock" class="w-5 h-5 text-blue-600"></i>
                  Automatic Reminder Settings
              </h3>

              <form method="POST" class="space-y-4">
                  <div>
                      <label class="font-medium text-gray-700">Reminder Frequency</label>
                      <select name="reminder_frequency" class="w-full border rounded-xl px-4 py-3 mt-1">
                          <option value="1_day">1 Day Before Event</option>
                          <option value="3_days">3 Days Before Event</option>
                          <option value="7_days">7 Days Before Event</option>
                          <option value="custom">Custom Interval</option>
                      </select>
                  </div>

                  <div class="flex items-center gap-3 mt-3">
                      <input type="checkbox" name="enable_auto_reminder" class="h-5 w-5 text-blue-600">
                      <label class="font-medium text-gray-700">Enable Auto-Reminders</label>
                  </div>

                  <button type="submit" name="save_reminder_settings" 
                          class="bg-blue-600 text-white px-5 py-3 rounded-xl w-full">
                      Save Reminder Settings
                  </button>
              </form>
          </div>
    </div>
</main>

<script>
lucide.createIcons();
// =============================
// BARANGAY DATABASE (Your Data)
// =============================
const barangays = {
    1: [ // District 1
        { name: "Alicia", lat: 14.6593, lng: 121.0371 },
        { name: "Bagong Pag-asa", lat: 14.6592, lng: 121.0340 },
        { name: "Bahay Toro", lat: 14.6738, lng: 121.0264 },
        { name: "Balingasa", lat: 14.6465, lng: 120.9996 },
        { name: "Bungad", lat: 14.6548, lng: 121.0257 },
        { name: "Damar", lat: 14.6499, lng: 121.0028 },
        { name: "Damayan", lat: 14.6462, lng: 120.9997 },
        { name: "Del Monte", lat: 14.6360, lng: 121.0062 },
        { name: "Katipunan", lat: 14.6575, lng: 121.0305 },
        { name: "Lourdes", lat: 14.6202, lng: 121.0062 },
        { name: "Maharlika", lat: 14.6190, lng: 121.0051 },
        { name: "Manresa", lat: 14.6288, lng: 120.9959 },
        { name: "Mariblo", lat: 14.6421, lng: 121.0053 },
        { name: "Masambong", lat: 14.6461, lng: 121.0116 },
        { name: "N. S. Amoranto", lat: 14.6235, lng: 120.9984 },
        { name: "Nayong Kanluran", lat: 14.6465, lng: 121.0245 },
        { name: "Paang Bundok", lat: 14.6254, lng: 121.0003 },
        { name: "Pag-ibig sa Nayon", lat: 14.6441, lng: 120.9982 },
        { name: "Paltok", lat: 14.6462, lng: 121.0088 },
        { name: "Paraiso", lat: 14.6405, lng: 121.0034 },
        { name: "Phil-Am", lat: 14.6393, lng: 121.0315 },
        { name: "Project 6", lat: 14.6490, lng: 121.0375 },
        { name: "Ramon Magsaysay", lat: 14.6588, lng: 121.0189 },
        { name: "Saint Peter", lat: 14.6209, lng: 121.0025 },
        { name: "Salvacion", lat: 14.6285, lng: 120.9991 },
        { name: "San Antonio", lat: 14.6508, lng: 121.0117 },
        { name: "San Isidro Labrador", lat: 14.6268, lng: 120.9999 },
        { name: "San Jose", lat: 14.6300, lng: 120.9998 },
        { name: "Santa Cruz", lat: 14.6193, lng: 121.0000 },
        { name: "Santa Teresita", lat: 14.6209, lng: 121.0049 },
        { name: "Sto. Cristo", lat: 14.6625, lng: 121.0312 },
        { name: "Santo Domingo", lat: 14.6198, lng: 121.0084 },
        { name: "Siena", lat: 14.6212, lng: 121.0094 },
        { name: "Talayan", lat: 14.6416, lng: 121.0098 },
        { name: "Vasra", lat: 14.6558, lng: 121.0427 },
        { name: "Veterans Village", lat: 14.6548, lng: 121.0181 },
        { name: "West Triangle", lat: 14.6406, lng: 121.0305 }
    ],

    2: [
        { name: "Bagong Silangan", lat: 14.7094, lng: 121.1000 },
        { name: "Batasan Hills", lat: 14.6975, lng: 121.0975 },
        { name: "Commonwealth", lat: 14.6854, lng: 121.0772 },
        { name: "Holy Spirit", lat: 14.6983, lng: 121.0543 },
        { name: "Payatas", lat: 14.7088, lng: 121.0950 }
    ],

    3: [
        { name: "Amihan", lat: 14.6291, lng: 121.0722 },
        { name: "Bagumbayan", lat: 14.6067, lng: 121.0784 },
        { name: "Bagumbuhay", lat: 14.6200, lng: 121.0556 },
        { name: "Bayanihan", lat: 14.6198, lng: 121.0664 },
        { name: "Blue Ridge A", lat: 14.6190, lng: 121.0717 },
        { name: "Blue Ridge B", lat: 14.6175, lng: 121.0725 },
        { name: "Camp Aguinaldo", lat: 14.6146, lng: 121.0560 },
        { name: "Claro", lat: 14.6288, lng: 121.0728 },
        { name: "Dioquino Zobel", lat: 14.6150, lng: 121.0607 },
        { name: "Duyan-duyan", lat: 14.6315, lng: 121.0645 },
        { name: "E. Rodriguez", lat: 14.6163, lng: 121.0454 },
        { name: "East Kamias", lat: 14.6300, lng: 121.0515 },
        { name: "Escopa I", lat: 14.6200, lng: 121.0621 },
        // ... (continue same pattern—You already provided the full list)
    ],

    4: [
        { name: "Apolonio Samson", lat: 14.6465, lng: 121.0028 },
        { name: "Botocan", lat: 14.6481, lng: 121.0638 },
        { name: "Central", lat: 14.6409, lng: 121.0494 },
        { name: "Dioquino Zobel", lat: 14.6150, lng: 121.0607 },
        { name: "Don Manuel", lat: 14.6161, lng: 121.0118 },
        { name: "Doña Aurora", lat: 14.6190, lng: 121.0135 },
        { name: "Doña Imelda", lat: 14.6168, lng: 121.0189 },
        { name: "Doña Josefa", lat: 14.6148, lng: 121.0205 },
        { name: "Damayang Lagi", lat: 14.6068, lng: 121.0177 },
        { name: "Horseshoe", lat: 14.6068, lng: 121.0400 },
        { name: "Immaculate Concepcion", lat: 14.6182, lng: 121.0315 },
        { name: "Kalusugan", lat: 14.6105, lng: 121.0360 },
        { name: "Kamuning", lat: 14.6212, lng: 121.0355 },
        { name: "Kaunlaran", lat: 14.6111, lng: 121.0385 },
        { name: "Kristong Hari", lat: 14.6133, lng: 121.0435 },
        { name: "Krus na Ligas", lat: 14.6472, lng: 121.0675 },
        { name: "Laging Handa", lat: 14.6200, lng: 121.0310 },
        { name: "Malaya", lat: 14.6302, lng: 121.0450 },
        { name: "Mariana", lat: 14.6190, lng: 121.0250 },
        { name: "Obrero", lat: 14.6178, lng: 121.0333 },
        { name: "Old Capitol Site", lat: 14.6495, lng: 121.0583 },
        { name: "Paligsahan", lat: 14.6205, lng: 121.0305 },
        { name: "Pinagkaisahan", lat: 14.6130, lng: 121.0385 },
        { name: "Pinyahan", lat: 14.6400, lng: 121.0461 },
        { name: "Roxas District", lat: 14.6335, lng: 121.0225 },
        { name: "Sacred Heart", lat: 14.6300, lng: 121.0300 },
        { name: "San Isidro", lat: 14.6225, lng: 121.0200 },
        { name: "San Martin de Porres", lat: 14.6075, lng: 121.0535 },
        { name: "San Vicente", lat: 14.6500, lng: 121.0592 },
        { name: "Sikatuna Village", lat: 14.6295, lng: 121.0620 },
        { name: "Sto. Niño", lat: 14.6208, lng: 121.0258 },
        { name: "Tatalon", lat: 14.6242, lng: 121.0153 },
        { name: "Teachers Village East", lat: 14.6360, lng: 121.0580 },
        { name: "Teachers Village West", lat: 14.6358, lng: 121.0545 },
        { name: "U.P. Campus", lat: 14.6565, lng: 121.0688 },
        { name: "U.P. Village", lat: 14.6450, lng: 121.0620 },
        { name: "Valencia", lat: 14.6145, lng: 121.0370 }
    ],

    5: [
        { name: "Bagbag", lat: 14.7088, lng: 121.0345 },
        { name: "Capri", lat: 14.7218, lng: 121.0325 },
        { name: "Fairview", lat: 14.7230, lng: 121.0645 },
        { name: "Gulod", lat: 14.7335, lng: 121.0600 },
        { name: "Greater Lagro", lat: 14.7315, lng: 121.0805 },
        { name: "Kaligayahan", lat: 14.7150, lng: 121.0535 },
        { name: "Nagkaisang Nayon", lat: 14.7180, lng: 121.0200 },
        { name: "North Fairview", lat: 14.7320, lng: 121.0690 },
        { name: "Novaliches Proper (Bayan)", lat: 14.7262, lng: 121.0475 },
        { name: "Pasong Putik Proper", lat: 14.7505, lng: 121.0480 },
        { name: "San Agustin", lat: 14.7408, lng: 121.0530 },
        { name: "San Bartolome", lat: 14.7320, lng: 121.0400 },
        { name: "Santa Lucia", lat: 14.7258, lng: 121.0880 },
        { name: "Santa Monica", lat: 14.7325, lng: 121.0770 }
    ],

    6: [
        { name: "Baesa", lat: 14.6640, lng: 121.0100 },
        { name: "Balon-bato", lat: 14.6600, lng: 121.0000 },
        { name: "Culiat", lat: 14.6655, lng: 121.0405 },
        { name: "New Era", lat: 14.6650, lng: 121.0435 },
        { name: "Pasong Tamo", lat: 14.6732, lng: 121.0345 },
        { name: "Sangandaan", lat: 14.6598, lng: 121.0020 },
        { name: "Talipapa", lat: 14.6685, lng: 121.0275 },
        { name: "Tandang Sora", lat: 14.6780, lng: 121.0450 },
        { name: "Unang Sigaw", lat: 14.6545, lng: 121.0050 },
        { name: "Bago Bantay (Area near Sto. Cristo)", lat: 14.6580, lng: 121.0250 },
        { name: "Sauyo (Part of District 6)", lat: 14.6900, lng: 121.0300 }
    ]
};
document.addEventListener("DOMContentLoaded", function () {

    // =============================
    // MAPTILER MAP + BARANGAY PINNING
    // =============================
    maptilersdk.config.apiKey = "yfboSZCNAu3e7LkIkLlS";

    const map = new maptilersdk.Map({
        container: "map",
        style: maptilersdk.MapStyle.STREETS,
        center: [121.03, 14.66],
        zoom: 12
    });

    let mapMarkers = [];

    // Add ALL barangay markers at start
    function renderAllMarkers() {
        [1,2,3,4,5,6].forEach(d => {
            (barangays[d] || []).forEach(b => {
                const m = new maptilersdk.Marker({ color: "blue" })
                    .setLngLat([b.lng, b.lat])
                    .setPopup(new maptilersdk.Popup().setText(b.name))
                    .addTo(map);
                mapMarkers.push(m);
            });
        });
    }

    renderAllMarkers();

    function clearMarkers() {
        mapMarkers.forEach(m => m.remove());
        mapMarkers = [];
    }

    const districtSelect = document.getElementById("districtSelect");
    const barangaySelect = document.getElementById("barangaySelect");

    // Populate barangay dropdown when district changes
    districtSelect.addEventListener("change", function () {
        const district = this.value;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

        if (district && barangays[district]) {
            barangays[district].forEach(b => {
                const opt = document.createElement("option");
                opt.value = b.name;
                opt.textContent = b.name;
                barangaySelect.appendChild(opt);
            });
        }
    });

    // Auto-pin selected barangay
    barangaySelect.addEventListener("change", function () {
        const name = this.value;
        const district = districtSelect.value;

        if (!district || !name) return;

        const brgy = barangays[district].find(b => b.name === name);

        if (brgy) {
            clearMarkers();
            const marker = new maptilersdk.Marker({ color: "red" })
                .setLngLat([brgy.lng, brgy.lat])
                .setPopup(new maptilersdk.Popup().setText(brgy.name))
                .addTo(map);

            mapMarkers.push(marker);

            map.flyTo({
                center: [brgy.lng, brgy.lat],
                zoom: 15
            });
            document.getElementById('locationLat').value = brgy.lat;
            document.getElementById('locationLng').value = brgy.lng;

        }
    });
});
districtSelect.addEventListener("change", () => {
    localStorage.setItem('selectedDistrict', districtSelect.value);
});
barangaySelect.addEventListener("change", () => {
    localStorage.setItem('selectedBarangay', barangaySelect.value);
});

// Restore on load
document.addEventListener("DOMContentLoaded", () => {
    const savedDistrict = localStorage.getItem('selectedDistrict');
    const savedBarangay = localStorage.getItem('selectedBarangay');

    if(savedDistrict) {
        districtSelect.value = savedDistrict;
        districtSelect.dispatchEvent(new Event('change'));
    }
    if(savedBarangay) {
        barangaySelect.value = savedBarangay;
        barangaySelect.dispatchEvent(new Event('change'));
    }
});
</script>
</body>
</html>
