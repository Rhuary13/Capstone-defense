<?php
session_start();

/**
 * module2.2.php
 * Admin interface: Upcoming Events / Announcements + Safety Procedures & Compliance
 *
 * - Requires: sidebar.php at ../sidebar.php (kept as include)
 * - DB: will create necessary tables if missing
 * - Security: prepared statements, csrf token, minimal validation
 */

/* ---------- Configuration ---------- */
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

$MAPTILER_KEY = "yfboSZCNAu3e7LkIkLlS"; // your MapTiler key

/* ---------- Connect DB ---------- */
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/* ---------- Create tables if not exists (safe defaults) ---------- */
$create_sqls = [

    // announcements (events)
    "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    location_id INT DEFAULT NULL,
    safety_procedure_id INT DEFAULT NULL,
    compliance ENUM('yes','no') DEFAULT 'no',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (safety_procedure_id) REFERENCES safety_procedures(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // locations (covered courts etc.)
    "CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        barangay VARCHAR(255) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        lat DECIMAL(10,7) DEFAULT NULL,
        lng DECIMAL(10,7) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // safety procedures (master procedures)
    "CREATE TABLE IF NOT EXISTS safety_procedures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        checklist JSON DEFAULT NULL, -- array of checklist items {label, required}
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // participants (simple)
    "CREATE TABLE IF NOT EXISTS participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        role VARCHAR(100) NOT NULL,
        program_id INT DEFAULT NULL,
        completion_percent INT DEFAULT 0,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // staff_suggestions (if used elsewhere)
    "CREATE TABLE IF NOT EXISTS staff_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type VARCHAR(100) DEFAULT NULL,
        description TEXT,
        suggested_by VARCHAR(150) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($create_sqls as $sql) {
    if (!$conn->query($sql)) {
        error_log("Table creation error: " . $conn->error);
    }
}

/* ---------- SAFE MIGRATION: add missing columns only (prevents duplicate column errors) ---------- */
/**
 * ensure_table_columns($conn, $dbName, $tableName, $columnsAssoc)
 *  - $columnsAssoc is ['colname' => 'SQL_DEFINITION', ...]
 *  - will add only the columns that are not present yet
 */
/* ---------- SAFE MIGRATION: add missing columns only (prevents duplicate column errors) ---------- */
function ensure_table_columns($conn, $dbName, $tableName, $columnsAssoc) {
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    if (!$stmt) {
        error_log("Migration prepare failed: " . $conn->error);
        return;
    }
    $stmt->bind_param("ss", $dbName, $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = [];
    while ($row = $res->fetch_assoc()) {
        $existing[$row['COLUMN_NAME']] = true;
    }
    $stmt->close();

    foreach ($columnsAssoc as $col => $definition) {
        if (!isset($existing[$col])) {
            $alter = "ALTER TABLE `{$tableName}` ADD COLUMN `{$col}` {$definition}";
            if (!$conn->query($alter)) {
                error_log("Migration: failed to add column {$col} to {$tableName}: " . $conn->error);
            } else {
                error_log("Migration: added column {$col} to {$tableName}");
            }
        }
    }
}

// Locations migration
ensure_table_columns($conn, $DB_NAME, 'locations', [
    'barangay' => 'VARCHAR(255) DEFAULT NULL',
    'lat' => 'DECIMAL(10,7) DEFAULT NULL',
    'lng' => 'DECIMAL(10,7) DEFAULT NULL'
]);

// Announcements migration (add missing fields required by queries and inserts)
ensure_table_columns($conn, $DB_NAME, 'announcements', [
    'audience' => 'VARCHAR(100) DEFAULT "General"',
    'event_date' => 'DATE DEFAULT NULL',
    'time' => 'TIME DEFAULT NULL',
    'location' => 'VARCHAR(255) DEFAULT NULL',
    'location_lat' => 'DECIMAL(10,7) DEFAULT NULL',
    'location_lng' => 'DECIMAL(10,7) DEFAULT NULL',
    'compliance_approved' => 'TINYINT(1) DEFAULT 0',
    'details' => 'TEXT DEFAULT NULL'
]);

// Safety procedures migration
ensure_table_columns($conn, $DB_NAME, 'safety_procedures', [
    'checklist' => 'JSON DEFAULT NULL'
]);
/* ---------- END SAFE MIGRATION ---------- */

/* ---------- END SAFE MIGRATION ---------- */

/* ---------- Session / Auth check ---------- */
/* NOTE: keep your existing auth logic. This file expects:
   $_SESSION['user_id'] and $_SESSION['role'] exists and role==='admin' for admin features.
*/
if (!isset($_SESSION['id'])) {
    // not logged in — redirect (adjust path as needed)
    header("Location: ../auth/login.php");
    exit;
}
$role = $_SESSION['role'] ?? 'User';

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ---------- Handle POST actions (admin only) ---------- */
$errors = [];
$success = null;

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Which action?
        if (isset($_POST['action']) && $_POST['action'] === 'add_safety_procedure') {
            // add safety procedure
            $title = trim($_POST['sp_title'] ?? '');
            $desc  = trim($_POST['sp_description'] ?? '');
            // checklist items come as array sp_check_label[]
            $check_labels = $_POST['sp_check_label'] ?? [];
            $check_required = $_POST['sp_check_required'] ?? []; // checkbox values '1' for required
            $checklist = [];
            for ($i = 0; $i < count($check_labels); $i++) {
                $label = trim($check_labels[$i]);
                if ($label === '') continue;
                $required = isset($check_required[$i]) && $check_required[$i] === '1' ? true : false;
                $checklist[] = ['label' => $label, 'required' => $required];
            }

            if ($title === '') $errors[] = "Procedure title is required.";

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO safety_procedures (title, description, checklist) VALUES (?, ?, ?)");
                $json_checklist = empty($checklist) ? null : json_encode($checklist, JSON_UNESCAPED_UNICODE);
                $stmt->bind_param("sss", $title, $desc, $json_checklist);
                if ($stmt->execute()) {
                    $success = "Safety procedure added.";
                    $stmt->close();
                } else {
                    $errors[] = "DB error: " . $stmt->error;
                }
            }

        } elseif (isset($_POST['action']) && $_POST['action'] === 'add_announcement') {
            // add announcement/event with safety selection + compliance flag
            $title = trim($_POST['title'] ?? '');
            $audience = $_POST['audience'] ?? 'General';
            $event_date = $_POST['event_date'] ?? '';
            $time = $_POST['time'] ?? null;
            $location = trim($_POST['location'] ?? '');
            $lat = isset($_POST['location_lat']) ? (float)$_POST['location_lat'] : null;
            $lng = isset($_POST['location_lng']) ? (float)$_POST['location_lng'] : null;
            $details = trim($_POST['details'] ?? '');
            $safety_procedure_id = !empty($_POST['safety_procedure_id']) ? (int)$_POST['safety_procedure_id'] : null;
            $compliance_approved = isset($_POST['compliance_approved']) && $_POST['compliance_approved'] === '1' ? 1 : 0;

            // basic validation
            if ($title === '') $errors[] = "Title is required.";
            if ($event_date === '') $errors[] = "Event date is required.";
            if ($location === '') $errors[] = "Location is required.";
            // If safety_procedure_id given, check it exists
            if ($safety_procedure_id) {
                $chk = $conn->prepare("SELECT id FROM safety_procedures WHERE id = ?");
                $chk->bind_param("i", $safety_procedure_id);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows === 0) $errors[] = "Selected safety procedure not found.";
                $chk->close();
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO announcements (title, audience, event_date, time, location, location_lat, location_lng, safety_procedure_id, compliance_approved, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                // bind (s = string, d = double not in mysqli bind types — use 's' and rely on casting)
                $latparam = $lat !== null ? (string)$lat : null;
                $lngparam = $lng !== null ? (string)$lng : null;
                $stmt->bind_param("ssssssssss", $title, $audience, $event_date, $time, $location, $latparam, $lngparam, $safety_procedure_id, $compliance_approved, $details);
                if ($stmt->execute()) {
                    $success = "Announcement saved.";
                    $stmt->close();
                } else {
                    $errors[] = "DB error: " . $stmt->error;
                }
            }

        } elseif (isset($_POST['action']) && $_POST['action'] === 'add_location') {
            // add location to locations table (name, barangay, address, lat, lng)
            $name = trim($_POST['loc_name'] ?? '');
            $barangay = trim($_POST['loc_barangay'] ?? '');
            $address = trim($_POST['loc_address'] ?? '');
            $lat = isset($_POST['loc_lat']) ? (float)$_POST['loc_lat'] : null;
            $lng = isset($_POST['loc_lng']) ? (float)$_POST['loc_lng'] : null;

            if ($name === '') $errors[] = "Location name is required.";
            if ($address === '') $errors[] = "Address is required.";

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO locations (name, barangay, address, lat, lng) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdd", $name, $barangay, $address, $lat, $lng);
                if ($stmt->execute()) {
                    $success = "Location added.";
                    $stmt->close();
                } else {
                    $errors[] = "DB error: " . $stmt->error;
                }
            }

        } // end action checks
    }
}

/* ---------- Fetch data for display ---------- */
// Safety procedures
$safety_procedures_res = $conn->query("SELECT id, title, description, checklist FROM safety_procedures ORDER BY created_at DESC");
if (!$safety_procedures_res) $errors[] = "Safety procedures fetch error: " . $conn->error;

// Only fetch locations in Quezon City
$locations_res = $conn->query("
    SELECT id, name, barangay, address, lat, lng 
    FROM locations 
    WHERE address LIKE '%Quezon City%' OR barangay IS NOT NULL
    ORDER BY barangay ASC, name ASC
");

if (!$locations_res) $errors[] = "Locations fetch error: " . $conn->error;

// Announcements (upcoming)
$announcements = [];
$ann_res = $conn->query("SELECT a.*, sp.title AS sp_title FROM announcements a LEFT JOIN safety_procedures sp ON a.safety_procedure_id=sp.id WHERE a.event_date >= CURDATE() ORDER BY a.event_date ASC, a.time ASC");
if ($ann_res) {
    while ($r = $ann_res->fetch_assoc()) $announcements[] = $r;
} else {
    $errors[] = "Announcements fetch error: " . $conn->error;
}

// Optional: sample participants & suggestions queries (if tables exist)
$suggestions_res = $conn->query("SELECT title, type, description, suggested_by, created_at FROM staff_suggestions ORDER BY created_at DESC LIMIT 20");
$participants_res = $conn->query("SELECT id, name, role, program_id, completion_percent, last_activity FROM participants ORDER BY last_activity DESC LIMIT 50");

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin — Announcements & Safety Procedures</title>
<!-- Tailwind CDN (development) -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide icons -->
<script src="https://unpkg.com/lucide@latest"></script>
<!-- Leaflet (MapTiler) -->

<style>
  #map {
    width: 100%;
    height: 500px;
    border-radius: 0.5rem;
    position: relative;
    z-index: 1;
  }
  #mapPlaceholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(243,244,246,0.9); /* gray-100 with opacity */
    color: #4b5563; /* gray-600 */
    border-radius: 0.5rem;
    z-index: 2;
  }
</style>
        <link href="https://cdn.maptiler.com/maptiler-sdk-js/v1.4.0/maptiler-sdk.css" rel="stylesheet">
        <script src="https://cdn.maptiler.com/maptiler-sdk-js/v1.4.0/maptiler-sdk.umd.js"></script>

</head>
<body class="h-screen flex overflow-hidden bg-gray-100">

  <!-- Sidebar (kept as include) -->
  <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
  </aside>

  <main class="flex-1 p-8 overflow-y-auto pt-20">
    <nav class="fixed top-0 left-64 right-0 bg-white shadow px-6 py-4 z-10">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
        <i data-lucide="megaphone" class="w-7 h-7 text-blue-600"></i>
        Announcements & Safety Procedures
      </h1>
    </nav>

    <!-- messages -->
    <div class="max-w-6xl mx-auto space-y-4">
      <?php if ($success): ?>
        <div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded"><?= h($success) ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded"><?= h($err) ?></div>
      <?php endforeach; ?>

      <!-- Announcements form -->
      <?php if ($role === 'admin'): ?>
      <section class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-lg font-semibold mb-3">Create Announcement / Simulation Event</h2>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
          <input type="hidden" name="action" value="add_announcement">

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Title</label>
              <input name="title" required class="mt-1 w-full border px-3 py-2 rounded" value="">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Audience</label>
              <select name="audience" class="mt-1 w-full border px-3 py-2 rounded">
                <option>General</option>
                <option>Staff</option>
                <option>User</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Safety Procedure</label>
              <select name="safety_procedure_id" id="safetyProcedureSelect" class="mt-1 w-full border px-3 py-2 rounded">
                <option value="">-- None / Select Procedure --</option>
                <?php if ($safety_procedures_res && $safety_procedures_res->num_rows): ?>
                  <?php while($sp = $safety_procedures_res->fetch_assoc()): ?>
                    <option value="<?= (int)$sp['id'] ?>" data-checklist='<?= h($sp['checklist']) ?>'><?= h($sp['title']) ?></option>
                  <?php endwhile; ?>
                <?php endif; ?>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4 mt-2">
            <div>
              <label class="block text-sm font-medium text-gray-700">Date</label>
              <input type="date" name="event_date" required class="mt-1 w-full border px-3 py-2 rounded">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Time</label>
              <input type="time" name="time" class="mt-1 w-full border px-3 py-2 rounded">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Location (Quezon City)</label>
                <select id="locationSelect" name="location" class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">-- Select Barangay --</option>
                    <option value="Apolonio Samson" data-lat="14.6575" data-lng="121.0215">Apolonio Samson</option>
                    <option value="Amihan" data-lat="14.6335" data-lng="121.0742">Amihan</option>
                    <option value="Apolonio Samson" data-lat="14.6586" data-lng="121.0046">Apolonio Samson</option>
                    <option value="Baesa" data-lat="14.6827" data-lng="121.0153">Baesa</option>
                    <option value="Bagbag" data-lat="14.7053" data-lng="121.0375">Bagbag</option>
                    <option value="Bagong Pag-asa" data-lat="14.6540" data-lng="121.0315">Bagong Pag-asa</option>
                    <option value="Bagong Silangan" data-lat="14.7114" data-lng="121.1019">Bagong Silangan</option>
                    <option value="Bagumbayan" data-lat="14.5799" data-lng="121.0595">Bagumbayan</option>
                    <option value="Bagumbuhay" data-lat="14.6327" data-lng="121.0653">Bagumbuhay</option>
                    <option value="Bahay Toro" data-lat="14.6682" data-lng="121.0182">Bahay Toro</option>
                    <!-- … continue all the way until Barangay West Triangle -->
                    <option value="West Triangle" data-lat="14.6435" data-lng="121.0312">West Triangle</option>
                </select>

            </div>
          </div>

          <!-- Map with placeholder -->
            <div class="relative mt-3">
            <div id="map" class="rounded w-full h-80"></div>
            
            <div id="mapPlaceholder" class="absolute inset-0 flex items-center justify-center bg-gray-100 text-gray-600 rounded">
            <iframe width="1000" height="500" src="https://api.maptiler.com/maps/basic-v2/?key=yfboSZCNAu3e7LkIkLlS#1.0/0.00000/0.00000">Select a location to preview map</iframe>
            </div>
            </div>

          <div class="grid grid-cols-3 gap-4 mt-3">
            <input type="hidden" id="location_lat" name="location_lat">
            <input type="hidden" id="location_lng" name="location_lng">
            <div class="col-span-3">
              <label class="block text-sm font-medium text-gray-700">Details / Notes</label>
              <textarea name="details" rows="3" class="mt-1 w-full border px-3 py-2 rounded"></textarea>
            </div>
          </div>

          <div class="flex items-center gap-4 mt-3">
            <label class="inline-flex items-center">
              <input type="checkbox" name="compliance_approved" value="1" class="form-checkbox h-4 w-4 text-blue-600">
              <span class="ml-2 text-sm">Compliance Approved (Admin confirms event meets safety standards)</span>
            </label>
            <button type="submit" class="ml-auto px-4 py-2 bg-blue-600 text-white rounded">Save Announcement</button>
          </div>
        </form>
      </section>

      <!-- Safety Procedures manager -->
      <section class="bg-white p-6 rounded-lg shadow mt-6">
        <h2 class="text-lg font-semibold mb-3">Safety Procedures (Master)</h2>
        <div class="grid grid-cols-2 gap-6">
          <div>
            <form method="POST" class="space-y-3">
              <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
              <input type="hidden" name="action" value="add_safety_procedure">

              <label class="block text-sm font-medium text-gray-700">Title</label>
              <input name="sp_title" class="w-full border px-3 py-2 rounded" required>

              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea name="sp_description" rows="3" class="w-full border px-3 py-2 rounded"></textarea>

              <label class="block text-sm font-medium text-gray-700">Checklist (one item per field)</label>
              <div id="checklistContainer" class="space-y-2">
                <div class="flex gap-2">
                  <input name="sp_check_label[]" placeholder="e.g. Evacuation routes posted" class="flex-1 border px-2 py-1 rounded">
                  <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="sp_check_required[0]" value="1"> required</label>
                </div>
              </div>
              <div class="flex gap-2">
                <button type="button" id="addChecklistBtn" class="px-3 py-1 border rounded">Add checklist item</button>
                <button type="submit" class="ml-auto px-4 py-1 bg-green-600 text-white rounded">Save Procedure</button>
              </div>
            </form>
          </div>

          <div>
            <h3 class="text-sm font-semibold">Existing Procedures</h3>
            <div class="mt-3 space-y-3 max-h-64 overflow-y-auto">
              <?php
                // re-query safety procedures for display
                $spq = $conn->query("SELECT id, title, description, checklist, created_at FROM safety_procedures ORDER BY created_at DESC");
                if ($spq && $spq->num_rows) {
                  while ($spRow = $spq->fetch_assoc()):
                    $checklistJson = $spRow['checklist'];
                    $checklistArr = $checklistJson ? json_decode($checklistJson, true) : [];
              ?>
                <div class="p-3 border rounded">
                  <div class="flex justify-between">
                    <div>
                      <div class="font-semibold"><?= h($spRow['title']) ?></div>
                      <div class="text-xs text-gray-600"><?= h($spRow['description']) ?></div>
                    </div>
                    <div class="text-xs text-gray-400"><?= date('M j, Y', strtotime($spRow['created_at'])) ?></div>
                  </div>
                  <?php if (!empty($checklistArr)): ?>
                    <ul class="mt-2 text-sm list-disc list-inside text-gray-700">
                      <?php foreach ($checklistArr as $ci): ?>
                        <li><?= h($ci['label']) ?> <?= !empty($ci['required']) ? "<strong class='text-red-600'>(required)</strong>" : "" ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              <?php
                  endwhile;
                } else {
                  echo "<div class='text-sm text-gray-500'>No procedures yet.</div>";
                }
              ?>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Upcoming announcements list -->
      <section class="bg-white p-6 rounded-lg shadow mt-6">
        <h2 class="text-lg font-semibold mb-3">Upcoming Events</h2>
        <div class="space-y-3">
          <?php if (count($announcements) === 0): ?>
            <div class="text-gray-500">No upcoming events.</div>
          <?php else: foreach ($announcements as $ev): ?>
            <div class="p-3 border rounded flex flex-col md:flex-row md:items-center md:justify-between">
              <div>
                <div class="font-semibold"><?= h($ev['title']) ?></div>
                <div class="text-sm text-gray-600"><?= h($ev['event_date']) ?> <?= h($ev['time']) ? " • " . h($ev['time']) : "" ?> — <?= h($ev['location']) ?></div>
                <div class="text-sm text-gray-700 mt-1"><?= h(substr($ev['details'],0,240)) ?><?= strlen($ev['details'])>240 ? '…' : '' ?></div>
                <div class="mt-2 text-xs">
                  Safety Procedure: <?= h($ev['sp_title'] ?? '—') ?> |
                  Compliance: <?= $ev['compliance_approved'] ? "<span class='text-green-700 font-semibold'>Approved</span>" : "<span class='text-red-600 font-medium'>Not Approved</span>" ?>
                </div>
              </div>
              <div class="mt-3 md:mt-0 flex gap-2">
                <a href="?edit=<?= (int)$ev['id'] ?>" class="px-3 py-1 border rounded text-sm">Edit</a>
                <a href="?delete=<?= (int)$ev['id'] ?>" onclick="return confirm('Delete this announcement?')" class="px-3 py-1 border rounded text-sm text-red-600">Delete</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    </div> <!-- end container -->
  </main>

  <script>
  maptilersdk.config.apiKey = "yfboSZCNAu3e7LkIkLlS";

  const map = new maptilersdk.Map({
    container: "map",
    style: "https://api.maptiler.com/maps/basic-v2/style.json?key=yfboSZCNAu3e7LkIkLlS",
    center: [121.0437, 14.6760], // Quezon City center
    zoom: 12,
  });

  let marker = null;
  const placeholder = document.getElementById("mapPlaceholder");

  function showPlaceholder() {
    placeholder.style.display = "flex";
    if (marker) { marker.remove(); marker = null; }
  }

  function hidePlaceholder() {
    placeholder.style.display = "none";
  }

  map.on("load", () => { showPlaceholder(); });

  // 🔹 When a location is selected, pinpoint on map
  document.getElementById("locationSelect").addEventListener("change", function () {
    const opt = this.options[this.selectedIndex];
    const lat = opt.dataset.lat;
    const lng = opt.dataset.lng;

    if (lat && lng) {
      const coords = [parseFloat(lng), parseFloat(lat)];
      map.flyTo({ center: coords, zoom: 15 });

      if (marker) marker.remove();
      marker = new maptilersdk.Marker({ color: "red" }).setLngLat(coords).addTo(map);

      // ✅ Save coordinates to hidden inputs
      document.getElementById("location_lat").value = lat;
      document.getElementById("location_lng").value = lng;

      hidePlaceholder();
    } else {
      // Reset hidden fields
      document.getElementById("location_lat").value = "";
      document.getElementById("location_lng").value = "";
      showPlaceholder();
    }
  });
</script>
</body>
</html>
