<?php
session_start();

// =========================
// Database connection: Simulation Event Planning
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Database connection: Training Management (for modules)
// =========================
$tm_conn = new mysqli($host, $user, $pass, "training_management");
if ($tm_conn->connect_error) die("Connection failed: " . $tm_conn->connect_error);

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ----------------------
// Handle Approve / Delete / Edit
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    
    // Approve
    if(isset($_POST['approve_id'])){
        $id = (int)$_POST['approve_id'];
        $conn->query("UPDATE events SET approval_status='Completed' WHERE id=$id");
        exit; // stop here for fetch
    }

    // Delete
    if(isset($_POST['delete_id'])){
        $id = (int)$_POST['delete_id'];
        $conn->query("DELETE FROM events WHERE id=$id");
        exit; // stop here for fetch
    }

    // Edit / Save
    if(isset($_POST['edit_id'])){
        $id = (int)$_POST['edit_id'];
        $title = $conn->real_escape_string($_POST['title']);
        $disaster = $conn->real_escape_string($_POST['disaster_type']);
        $date = $conn->real_escape_string($_POST['date']);
        $time = $conn->real_escape_string($_POST['time']);
        $duration = (int)$_POST['duration'];
        $location = $conn->real_escape_string($_POST['location']);
        $facilitator = $conn->real_escape_string($_POST['facilitator']);
        $notes = $conn->real_escape_string($_POST['notes']);
        $status = $conn->real_escape_string($_POST['status']);

        $stmt = $conn->prepare("UPDATE events SET title=?, disaster_type=?, date=?, time=?, duration=?, location=?, facilitator=?, notes=?, approval_status=? WHERE id=?");
        $stmt->bind_param("ssssissssi", $title, $disaster, $date, $time, $duration, $location, $facilitator, $notes, $status, $id);
        $stmt->execute();
        $stmt->close();
        exit; // stop here for fetch
    }

    // NEW event creation
    if(isset($_POST['title'])){
        $title = $conn->real_escape_string($_POST['title']);
        $disaster = $conn->real_escape_string($_POST['disaster_type']);
        $date = $conn->real_escape_string($_POST['date']);
        $time = $conn->real_escape_string($_POST['time']);
        $duration = (int)$_POST['duration'];
        $location = $conn->real_escape_string($_POST['location']);
        $facilitator = $conn->real_escape_string($_POST['facilitator']);
        $notes = $conn->real_escape_string($_POST['notes']);
        $module_id = (int)$_POST['selected_module'] ?? 0;

        $sql = "INSERT INTO events (title, disaster_type, date, time, duration, location, facilitator, notes, approval_status, created_at, type) 
                VALUES ('$title', '$disaster', '$date', '$time', $duration, '$location', '$facilitator', '$notes', 'Pending', NOW(), 'Training')";
        if ($conn->query($sql)) {
            $_SESSION['successMsg'] = "Event successfully scheduled.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// ----------------------
// FETCH MODULES
// ----------------------
$modules = [];
$res = $tm_conn->query("SELECT id, title, disaster_type, created_by FROM training_modules ORDER BY title ASC");
if ($res) { while($row = $res->fetch_assoc()) $modules[] = $row; $res->free(); }

// ----------------------
// FETCH EVENTS
// ----------------------
$events = [];
$res = $conn->query("SELECT *, title as module_title, approval_status as status FROM events ORDER BY date ASC, time ASC");
if ($res) { while ($row = $res->fetch_assoc()) $events[] = $row; $res->free(); }

// ----------------------
// Handle POST submission (new event)
// ----------------------
$successMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $title = $conn->real_escape_string($_POST['title']);
    $disaster = $conn->real_escape_string($_POST['disaster_type']);
    $date = $conn->real_escape_string($_POST['date']);
    $time = $conn->real_escape_string($_POST['time']);
    $duration = (int)$_POST['duration'];
    $location = $conn->real_escape_string($_POST['location']);
    $facilitator = $conn->real_escape_string($_POST['facilitator']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $module_id = (int)$_POST['selected_module'];

    $sql = "INSERT INTO events (title, disaster_type, date, time, duration, location, facilitator, notes, approval_status, created_at, type) 
            VALUES ('$title', '$disaster', '$date', '$time', $duration, '$location', '$facilitator', '$notes', 'Pending', NOW(), 'Training')";

    if ($conn->query($sql)) {
        // After successful insert, redirect to avoid duplicate insertion on refresh
        $_SESSION['successMsg'] = "Event successfully scheduled.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $successMsg = "Failed to schedule event: " . $conn->error;
    }
}

// Display message from session (after redirect)
if (isset($_SESSION['successMsg'])) {
    $successMsg = $_SESSION['successMsg'];
    unset($_SESSION['successMsg']);
}

// ----------------------
// FETCH EVENTS
// ----------------------
$events = [];
$res = $conn->query("SELECT *, title as module_title FROM events ORDER BY date ASC, time ASC");
if ($res) { while ($row = $res->fetch_assoc()) $events[] = $row; $res->free(); }

// ----------------------
// COLLECT UNIQUE DISASTER TYPES FROM MODULES
// ----------------------
$disasterTypes = array_unique(array_map(fn($m) => $m['disaster_type'], $modules));
$philippineDisasters = [
    'Flood', 'Fire', 'Earthquake', 'Storm', 'Typhoon', 'Landslide', 'Volcanic Eruption', 'Drought', 'Tsunami'
];
$allDisasters = array_unique(array_merge($philippineDisasters, $disasterTypes));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Event Scheduling</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
body { display: flex; height: 100vh; font-family: sans-serif; overflow: hidden; }
main { flex: 1; overflow-y: auto; padding: 2rem; background-color: #f3f4f6; }
.table-scroll { max-height: 60vh; overflow-y: auto; }
.badge { @apply px-2 py-1 rounded text-sm font-semibold; }
.badge-Program { @apply bg-blue-100 text-blue-800; }
.badge-Training { @apply bg-green-100 text-green-800; }
.badge-Scenario { @apply bg-orange-100 text-orange-800; }
.modal-bg { @apply fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden; }
.modal-content { @apply bg-white rounded-xl p-6 w-11/12 md:w-1/2 max-h-[80vh] overflow-y-auto shadow-lg; }
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="w-64 bg-blue-700 text-white flex-shrink-0 h-full overflow-y-auto">
<?php include '../sidebar.php'; ?>
</aside>

<!-- Top Nav -->
<nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
<h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
<i data-lucide="calendar" class="w-8 h-8 text-blue-600"></i> Event Scheduling
</h1>
<div class="flex items-center gap-4">
<input type="text" id="search" placeholder="Search events..." class="border rounded px-3 py-2" oninput="filterEvents()">
</div>
</nav>

<main class="pt-20">

<!-- Alerts -->
<?php if($successMsg): ?>
<div class="p-4 mb-4 text-green-800 bg-green-100 border border-green-300 rounded-lg"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<!-- Event Form -->
<div class="bg-white rounded-2xl shadow-lg p-8 mb-8 max-w-4xl mx-auto transform transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
  <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
    <i data-lucide="plus-circle" class="w-6 h-6 text-blue-600"></i>
    Create / Schedule Event
  </h2>

  <form method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <!-- Select Module -->
    <div class="relative group">
      <select id="selectModule" name="selected_module" class="peer w-full border rounded-lg px-4 py-3 appearance-none transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <option value="" hidden></option>
        <?php foreach($modules as $m): ?>
          <option value="<?= $m['id'] ?>"
            data-title="<?= htmlspecialchars($m['title']) ?>"
            data-disaster="<?= htmlspecialchars($m['disaster_type']) ?>"
            data-facilitator="<?= htmlspecialchars($m['created_by']) ?>">
            <?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['disaster_type']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
        Select Module
      </label>
      <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
        <i data-lucide="chevron-down" class="w-4 h-4"></i>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Event Module / Topic -->
      <div class="relative group">
        <input type="text" id="eventTitle" name="title" placeholder=" " required
               class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Event Module / Topic
        </label>
      </div>

      <!-- Disaster Type -->
      <div class="relative group">
        <select id="disasterType" name="disaster_type" required
                class="peer w-full border rounded-lg px-4 py-3 appearance-none transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
          <option value="" hidden></option>
          <?php foreach($allDisasters as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Disaster Type
        </label>
        <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
          <i data-lucide="chevron-down" class="w-4 h-4"></i>
        </div>
      </div>

      <!-- Date -->
      <div class="relative group">
        <input type="date" name="date" placeholder=" " required
               class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Date
        </label>
      </div>

      <!-- Time -->
      <div class="relative group">
        <input type="time" name="time" placeholder=" " required
               class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Time
        </label>
      </div>

      <!-- Duration -->
      <div class="relative group">
        <input type="number" name="duration" min="1" placeholder=" " required
               class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Duration (hours)
        </label>
      </div>

      <!-- Location -->
      <div class="relative group">
        <input type="text" name="location" placeholder=" " required
               class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Location
        </label>
      </div>

      <!-- Facilitator(s) -->
      <div class="relative group md:col-span-2">
        <input type="text" id="facilitator" name="facilitator" placeholder=" "
               class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md">
        <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
          Facilitator(s)
        </label>
      </div>
    </div>

    <!-- Notes -->
    <div class="relative group">
      <textarea name="notes" rows="4" placeholder=" "
                class="peer w-full border rounded-lg px-4 py-3 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 group-hover:shadow-md"></textarea>
      <label class="absolute left-4 top-3 text-gray-400 text-sm transition-all duration-200 peer-focus:-top-2 peer-focus:text-blue-500 peer-focus:text-sm">
        Notes
      </label>
    </div>

    <button type="submit"
            class="w-full md:w-auto px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow hover:bg-blue-700 hover:shadow-xl transition-all duration-300">
      Schedule Event
    </button>
  </form>
</div>

<script>
document.getElementById('selectModule').addEventListener('change', function() {
  const selected = this.selectedOptions[0];
  if (!selected) return;
  document.getElementById('eventTitle').value = selected.dataset.title || '';
  document.getElementById('disasterType').value = selected.dataset.disaster || '';
  document.getElementById('facilitator').value = selected.dataset.facilitator || '';
});
</script>

<!-- Timeline and Event Table -->
<div class="bg-white p-6 rounded-xl shadow mb-8">
<h2 class="text-lg font-semibold text-gray-700 mb-4">Event Timeline</h2>
<ul class="space-y-4">
<?php foreach($events as $e):
$color = $e['type'] === 'Training' ? 'badge-Training' : ($e['type']==='Scenario-Based'?'badge-Scenario':'badge-Program'); ?>
<li class="flex justify-between items-center p-4 border rounded-lg hover:shadow-sm transition cursor-pointer" onclick="openModal(<?= $e['id'] ?>)">
<div>
<div class="font-semibold text-gray-800"><?= htmlspecialchars($e['module_title']) ?></div>
<div class="text-sm text-gray-500"><?= htmlspecialchars($e['date']) ?> at <?= htmlspecialchars($e['time']) ?></div>
</div>
<div class="badge <?= $color ?>"><?= htmlspecialchars($e['disaster_type']) ?></div>
</li>
<?php endforeach; ?>
</ul>
</div>

<div class="bg-white p-6 rounded-xl shadow">
  <h2 class="text-lg font-semibold text-gray-700 mb-4">Scheduled Events</h2>
  <div class="overflow-x-auto max-h-[500px]">
    <table class="w-full border-collapse text-left text-sm">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-3">Module / Topic</th>
          <th class="p-3">Disaster Type</th>
          <th class="p-3">Date</th>
          <th class="p-3">Time</th>
          <th class="p-3">Duration</th>
          <th class="p-3">Location</th>
          <th class="p-3">Facilitator</th>
          <th class="p-3">Status</th>
          <th class="p-3">Actions</th>
        </tr>
      </thead>
      <tbody id="event-table-body">
        <?php foreach($events as $e): ?>
        <tr data-id="<?= $e['id'] ?>" class="border-b hover:bg-gray-50">
          <td class="p-3"><?= htmlspecialchars($e['module_title']) ?></td>
          <td class="p-3"><?= htmlspecialchars($e['disaster_type']) ?></td>
          <td class="p-3"><?= htmlspecialchars($e['date']) ?></td>
          <td class="p-3"><?= htmlspecialchars($e['time']) ?></td>
          <td class="p-3"><?= htmlspecialchars($e['duration']) ?> hrs</td>
          <td class="p-3"><?= htmlspecialchars($e['location']) ?></td>
          <td class="p-3"><?= htmlspecialchars($e['facilitator']) ?></td>
          <td class="p-3">
            <span class="status-span px-2 py-1 rounded-full text-sm font-semibold <?= $e['status']==='Completed'?'bg-green-100 text-green-800':'bg-yellow-100 text-yellow-800' ?>">
              <?= $e['status'] ?>
            </span>
          </td>
          <td class="p-3 flex gap-2">
            <button onclick="approveEvent(event, <?= $e['id'] ?>)" class="text-blue-600 hover:underline">Approve</button>
            <button onclick="viewPending(event, <?= $e['id'] ?>)" class="text-yellow-600 hover:underline">Pending</button>
            <button onclick="editEvent(event, <?= $e['id'] ?>)" class="text-gray-600 hover:underline">Edit</button>
            <button onclick="deleteEvent(event, <?= $e['id'] ?>)" class="text-red-600 hover:underline">Delete</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const EVENTS = <?php echo json_encode($events); ?>;

// --- Modal Functions ---
function openModal(id, editable=false){
  const ev = EVENTS.find(e => e.id == id);
  if(!ev) return;

  const modalTitle = document.getElementById('modalTitle');
  const modalBody = document.getElementById('modalBody');
  const modalFooter = document.getElementById('modalFooter');

  modalTitle.innerText = editable ? "Edit Event" : ev.module_title;

  if(editable){
    modalBody.innerHTML = `
      <div class="space-y-3">
        <label class="block font-semibold text-gray-700">Title</label>
        <input id="editTitle" class="w-full border rounded px-3 py-2" value="${ev.module_title}">

        <label class="block font-semibold text-gray-700">Disaster Type</label>
        <input id="editDisaster" class="w-full border rounded px-3 py-2" value="${ev.disaster_type}">

        <label class="block font-semibold text-gray-700">Date</label>
        <input type="date" id="editDate" class="w-full border rounded px-3 py-2" value="${ev.date}">

        <label class="block font-semibold text-gray-700">Time</label>
        <input type="time" id="editTime" class="w-full border rounded px-3 py-2" value="${ev.time}">

        <label class="block font-semibold text-gray-700">Duration (hrs)</label>
        <input type="number" id="editDuration" class="w-full border rounded px-3 py-2" value="${ev.duration}">

        <label class="block font-semibold text-gray-700">Location</label>
        <input id="editLocation" class="w-full border rounded px-3 py-2" value="${ev.location}">

        <label class="block font-semibold text-gray-700">Facilitator</label>
        <input id="editFacilitator" class="w-full border rounded px-3 py-2" value="${ev.facilitator}">

        <label class="block font-semibold text-gray-700">Notes</label>
        <textarea id="editNotes" class="w-full border rounded px-3 py-2">${ev.notes || ''}</textarea>

        <label class="block font-semibold text-gray-700">Status</label>
        <select id="editStatus" class="w-full border rounded px-3 py-2">
          <option ${ev.status==='Pending'?'selected':''}>Pending</option>
          <option ${ev.status==='In Progress'?'selected':''}>In Progress</option>
          <option ${ev.status==='Completed'?'selected':''}>Completed</option>
        </select>
      </div>
    `;
    modalFooter.innerHTML = `
      <button onclick="saveEdit(${id})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Save</button>
      <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
    `;
  } else {
    modalBody.innerHTML = `
      <p><strong>Disaster Type:</strong> ${ev.disaster_type}</p>
      <p><strong>Date:</strong> ${ev.date}</p>
      <p><strong>Time:</strong> ${ev.time}</p>
      <p><strong>Duration:</strong> ${ev.duration} hrs</p>
      <p><strong>Location:</strong> ${ev.location}</p>
      <p><strong>Facilitator:</strong> ${ev.facilitator}</p>
      <p><strong>Notes:</strong><br>${ev.notes || '—'}</p>
      <p><strong>Status:</strong> ${ev.status}</p>
    `;
    modalFooter.innerHTML = `
      <button onclick="openModal(${id}, true)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Edit</button>
      <button onclick="deleteEvent(event, ${id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Delete</button>
      <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Close</button>
    `;
  }

  document.getElementById('eventModal').classList.remove('hidden');
}

function closeModal(){ 
  document.getElementById('eventModal').classList.add('hidden'); 
}

// --- Button Actions ---
function approveEvent(e, id){
  e.stopPropagation();
  fetch(window.location.href, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`approve_id=${id}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
  }).then(() => {
    const row = document.querySelector(`tr[data-id='${id}']`);
    if(row){
      const span = row.querySelector('.status-span');
      span.innerText = 'Completed';
      span.className = 'status-span px-2 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800';
    }
  });
}

function viewPending(e, id){
  e.stopPropagation();
  openModal(id, false);
}

function editEvent(e, id){
  e.stopPropagation();
  openModal(id, true);
}

function saveEdit(id){
  const data = new URLSearchParams();
  data.append('edit_id', id);
  data.append('title', document.getElementById('editTitle').value);
  data.append('disaster_type', document.getElementById('editDisaster').value);
  data.append('date', document.getElementById('editDate').value);
  data.append('time', document.getElementById('editTime').value);
  data.append('duration', document.getElementById('editDuration').value);
  data.append('location', document.getElementById('editLocation').value);
  data.append('facilitator', document.getElementById('editFacilitator').value);
  data.append('notes', document.getElementById('editNotes').value);
  data.append('status', document.getElementById('editStatus').value);
  data.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

  fetch(window.location.href, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:data.toString()
  }).then(() => location.reload());
}

function deleteEvent(e, id){
  e.stopPropagation();
  if(confirm('Delete this event?')){
    fetch(window.location.href, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`delete_id=${id}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
    }).then(() => {
      const row = document.querySelector(`tr[data-id='${id}']`);
      if(row) row.remove();
    });
  }
}


// --- Edit ---
function editEvent(e, id){
  e.stopPropagation();
  openEditModal(id);
}

function openEditModal(id){
  const ev = EVENTS.find(ev => ev.id == id);
  if(!ev) return;

  document.getElementById('modalTitle').innerText = "Edit Event";
  document.getElementById('modalBody').innerHTML = `
    <div class="space-y-3">
      <label class="block font-semibold text-gray-700">Title</label>
      <input id="editTitle" class="w-full border rounded px-3 py-2" value="${ev.module_title}">

      <label class="block font-semibold text-gray-700">Disaster Type</label>
      <input id="editDisaster" class="w-full border rounded px-3 py-2" value="${ev.disaster_type}">

      <label class="block font-semibold text-gray-700">Date</label>
      <input type="date" id="editDate" class="w-full border rounded px-3 py-2" value="${ev.date}">

      <label class="block font-semibold text-gray-700">Time</label>
      <input type="time" id="editTime" class="w-full border rounded px-3 py-2" value="${ev.time}">

      <label class="block font-semibold text-gray-700">Duration (hrs)</label>
      <input type="number" id="editDuration" class="w-full border rounded px-3 py-2" value="${ev.duration}">

      <label class="block font-semibold text-gray-700">Location</label>
      <input id="editLocation" class="w-full border rounded px-3 py-2" value="${ev.location}">

      <label class="block font-semibold text-gray-700">Facilitator</label>
      <input id="editFacilitator" class="w-full border rounded px-3 py-2" value="${ev.facilitator}">

      <label class="block font-semibold text-gray-700">Notes</label>
      <textarea id="editNotes" class="w-full border rounded px-3 py-2">${ev.notes}</textarea>

      <label class="block font-semibold text-gray-700">Status</label>
      <select id="editStatus" class="w-full border rounded px-3 py-2">
        <option ${ev.status==='Pending'?'selected':''}>Pending</option>
        <option ${ev.status==='In Progress'?'selected':''}>In Progress</option>
        <option ${ev.status==='Completed'?'selected':''}>Completed</option>
      </select>
    </div>
  `;

  document.getElementById('modalFooter').innerHTML = `
    <button onclick="saveEdit(${id})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Save</button>
    <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
  `;

  document.getElementById('eventModal').classList.remove('hidden');
}

// --- Save Edit ---
function saveEdit(id){
  const data = new URLSearchParams();
  data.append('edit_id', id);
  data.append('title', document.getElementById('editTitle').value);
  data.append('disaster_type', document.getElementById('editDisaster').value);
  data.append('date', document.getElementById('editDate').value);
  data.append('time', document.getElementById('editTime').value);
  data.append('duration', document.getElementById('editDuration').value);
  data.append('location', document.getElementById('editLocation').value);
  data.append('facilitator', document.getElementById('editFacilitator').value);
  data.append('notes', document.getElementById('editNotes').value);
  data.append('status', document.getElementById('editStatus').value);
  data.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

  fetch(window.location.href, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:data.toString()
  }).then(() => location.reload());
}
</script>
</body>
</html>
