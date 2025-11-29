<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Security check
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// Fetch Recent Modules (last 5 by created_at)
// =========================
$recentModules = [];
$resRecent = $conn->query("SELECT id, title, description, created_at FROM training_modules ORDER BY created_at DESC LIMIT 5");
while ($row = $resRecent->fetch_assoc()) $recentModules[] = $row;

// =========================
// Fetch All Modules
// =========================
$modules = [];
$res = $conn->query("SELECT id, title, description, is_mandatory, prerequisite_id, created_at FROM training_modules ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $modules[] = $row;

// Prepare a PHP array to be injected as JSON for client-side usage
$modules_for_js = [];
foreach (array_merge($recentModules, $modules) as $m) {
    // avoid duplicates by id
    $modules_for_js[intval($m['id'])] = [
        'id' => intval($m['id']),
        'title' => $m['title'],
        'description' => $m['description'] ?? '',
        'created_at' => $m['created_at'] ?? ''
    ];
}
include '../sidebar.php'; 

// =========================================================================================
// MOCK DATABASE CONNECTION & DATA SIMULATION (using simulation_event_planning DB concept)
// =========================================================================================

// Mock connection setup (In a real app, this connects to simulation_event_planning)
$dbName = "simulation_event_planning";

// Mock Module Data
$modules = [
    ['id' => 1, 'title' => 'Tsunami Protocol Basics', 'total_users' => 120],
    ['id' => 2, 'title' => 'Chemical Spill First Response', 'total_users' => 150],
    ['id' => 3, 'title' => 'Mass Casualty Triage', 'total_users' => 95],
    ['id' => 4, 'title' => 'Communications Hierarchy', 'total_users' => 120],
];

// Mock Participant Completion Data (Overall Progress Report)
$totalParticipants = 485;
$overallCompletionRate = 78.5;
$inactiveThresholdDays = 30;
$inactiveParticipants = 42;
$delayedParticipants = 85; 

// Mock Data: Completion per Barangay (Monitor module completion per barangay)
$barangayCompletion = [
    ['name' => 'Barangay Central', 'completion' => 92, 'active' => 150, 'total' => 163],
    ['name' => 'Barangay Coastal West', 'completion' => 75, 'active' => 120, 'total' => 160],
    ['name' => 'Barangay Mountain East', 'completion' => 55, 'active' => 45, 'total' => 82],
    ['name' => 'Barangay Industrial Zone', 'completion' => 88, 'active' => 75, 'total' => 80],
];

// Mock Data: Inactive/Delayed Participants (Identify inactive or delayed participants)
$inactiveList = [
    ['id' => 101, 'name' => 'Juan Dela Cruz', 'barangay' => 'Coastal West', 'last_activity' => 45],
    ['id' => 105, 'name' => 'Maria Santos', 'barangay' => 'Mountain East', 'last_activity' => 60],
    ['id' => 112, 'name' => 'Pedro Reyes', 'barangay' => 'Central', 'last_activity' => 32],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Progress & Completion Tracking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for independent scrolling and font */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Tailwind slate-50 */
        }
        /* Define the main content area for independent scrolling */
        .h-screen-main {
            min-height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .h-screen-main::-webkit-scrollbar {
            width: 8px;
        }
        .h-screen-main::-webkit-scrollbar-thumb {
            background-color: #e2e8f0; /* Tailwind slate-200 */
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-slate-50 flex">

    <!-- MAIN CONTENT -->
    <main class="flex-1 h-screen-main p-4 sm:p-8 space-y-10">

        <!-- PAGE HEADER -->
        <header class="pb-4 border-b-2 border-sky-200">
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">
                <span class="text-sky-600">Progress</span> & Completion Tracking
            </h1>
            <p class="text-lg text-gray-600 mt-2">
                Monitor training consistency and accountability across all participants and modules.
                <span class="font-mono text-xs bg-gray-200 text-gray-700 px-2 py-0.5 rounded ml-2"><?= $dbName ?></span>
            </p>
        </header>

        <!-- ============================================== -->
        <!-- SECTION 1: OVERALL PROGRESS REPORTS (KPIs) -->
        <!-- ============================================== -->
        <h2 class="text-2xl font-bold text-sky-700">Overall Training Performance</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Card 1: Total Participants -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-t-4 border-sky-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-sky-700 uppercase tracking-widest">Total Participants</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= $totalParticipants ?></p>
            </div>

            <!-- Card 2: Overall Completion Rate -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-t-4 border-green-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-green-700 uppercase tracking-widest">Completion Rate</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3">
                    <?= number_format($overallCompletionRate, 1) ?><span class="text-xl font-medium text-green-500">%</span>
                </p>
            </div>

            <!-- Card 3: Inactive Participants (Identify inactive) -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-t-4 border-red-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-red-700 uppercase tracking-widest">Inactive (> <?= $inactiveThresholdDays ?> Days)</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= $inactiveParticipants ?></p>
                <button class="text-xs text-red-600 mt-2 font-medium hover:underline">View List & Send Reminders</button>
            </div>

            <!-- Card 4: Modules Available -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-t-4 border-indigo-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-indigo-700 uppercase tracking-widest">Total Training Modules</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= count($modules) ?></p>
                <p class="text-xs text-gray-500 mt-2">Essential for knowledge consistency.</p>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- SECTION 2: COMPLETION BY BARANGAY (Monitoring) -->
        <!-- ============================================== -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-7 h-7 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243m10.606 0L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243m10.606 0L17.657 16.657M6.343 16.657L6.343 16.657"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0z"></path></svg>
                Module Completion Rate per Barangay
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4">
                <?php foreach ($barangayCompletion as $b): ?>
                    <?php
                        // Determine progress color
                        $progressColor = 'bg-red-400';
                        if ($b['completion'] >= 90) $progressColor = 'bg-green-500';
                        else if ($b['completion'] >= 70) $progressColor = 'bg-yellow-500';
                    ?>
                    <div class="p-4 rounded-xl border border-gray-100 shadow-md bg-white hover:border-sky-300 transition duration-300">
                        <p class="font-bold text-lg text-gray-800"><?= $b['name'] ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= $b['active'] ?> / <?= $b['total'] ?> Participants Tracked
                        </p>
                        
                        <div class="mt-4">
                            <div class="flex justify-between mb-1 text-sm font-medium">
                                <span class="text-gray-700">Completion</span>
                                <span class="font-extrabold text-lg" style="color: <?= $progressColor ?>;"><?= $b['completion'] ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="h-2.5 rounded-full <?= $progressColor ?>" style="width: <?= $b['completion'] ?>%"></div>
                            </div>
                        </div>
                        <button class="text-xs text-sky-600 mt-3 font-medium hover:underline">View Barangay Details</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- SECTION 3: INACTIVE PARTICIPANTS (Actionable List) -->
        <!-- ============================================== -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-xl border border-red-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-7 h-7 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                Delayed or Inactive Participants (Immediate Action Required)
            </h2>

            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-inner">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-red-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold text-red-700 uppercase tracking-wider rounded-tl-xl">ID</th>
                            <th class="p-4 text-left text-xs font-bold text-red-700 uppercase tracking-wider">Participant Name</th>
                            <th class="p-4 text-left text-xs font-bold text-red-700 uppercase tracking-wider">Barangay</th>
                            <th class="p-4 text-center text-xs font-bold text-red-700 uppercase tracking-wider">Last Activity (Days Ago)</th>
                            <th class="p-4 text-center text-xs font-bold text-red-700 uppercase tracking-wider rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($inactiveList as $p): ?>
                        <tr class="hover:bg-red-50/50 transition duration-150">
                            <td class="p-4 text-sm text-gray-500 font-mono"><?= $p['id'] ?></td>
                            <td class="p-4 text-sm font-semibold text-gray-900"><?= $p['name'] ?></td>
                            <td class="p-4 text-sm text-gray-700"><?= $p['barangay'] ?></td>
                            
                            <td class="p-4 text-center text-lg font-extrabold text-red-600">
                                <?= $p['last_activity'] ?>
                            </td>

                            <td class="p-4 text-center text-sm font-medium">
                                <button class="bg-red-500 text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-red-600 transition shadow-md shadow-red-300">
                                    Send Follow-up
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($inactiveList)): ?>
                <p class="mt-4 p-4 bg-green-50 text-green-700 rounded-lg border border-green-200 text-center font-medium">
                    All participants are currently active and compliant with the training schedule.
                </p>
            <?php endif; ?>
        </div>
        
    </main>

<script>
// Injected module data from server
const MODULES = <?= json_encode(array_values($modules_for_js), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>.reduce((acc, m) => { acc[m.id] = m; return acc; }, {});

function escapeHtml(s) {
  if (s === null || s === undefined) return '';
  return String(s)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#39;");
}

function openModuleModal(id) {
  const mid = parseInt(id, 10);
  const data = MODULES[mid];
  if (!data) {
    // As a fallback, try to fetch from server endpoint if available
    fetch('fetch_module.php?id=' + encodeURIComponent(mid))
      .then(r => r.ok ? r.json() : Promise.reject('no'))
      .then(d => showModuleInModal(d))
      .catch(() => {
        alert('Module data not available. Please ensure fetch_module.php exists or that the server returned module description.');
      });
    return;
  }
  showModuleInModal(data);
}

function showModuleInModal(data) {
  document.getElementById('moduleModalTitle').innerText = data.title || 'Module Details';
  const desc = data.description ? data.description : '— No description —';
  const created = data.created_at ? ('<div class="text-xs text-gray-500 mb-2">Created: ' + escapeHtml(data.created_at) + '</div>') : '';
  document.getElementById('moduleModalContent').innerHTML = created + '<div class="whitespace-pre-wrap">' + escapeHtml(desc) + '</div>';
  // update edit link
  document.getElementById('editLink').href = 'edit_module.php?id=' + encodeURIComponent(data.id);
  document.getElementById('moduleModal').classList.remove('hidden');
  document.getElementById('moduleModal').classList.add('flex');
}

function closeModuleModal() {
  document.getElementById('moduleModal').classList.add('hidden');
  document.getElementById('moduleModal').classList.remove('flex');
}

function dismissRecent(id) {
  const el = document.getElementById('recent-card-' + id);
  if (!el) return;
  el.classList.add('module-fade');
  setTimeout(() => el.remove(), 450);
}

function onRecentView(id) {
  // First hide the card smoothly, then open modal with the module data
  const el = document.getElementById('recent-card-' + id);
  if (el) {
    el.classList.add('module-fade');
    setTimeout(() => el.remove(), 450);
  }
  openModuleModal(id);
}

function filterTable() {
  const q = document.getElementById('searchModules').value.trim().toLowerCase();
  document.querySelectorAll('#modulesTableBody tr').forEach(tr => {
    const title = tr.children[1]?.innerText.toLowerCase() || '';
    const id = tr.getAttribute('data-id') || '';
    if (!q || title.includes(q) || id.includes(q)) tr.style.display = '';
    else tr.style.display = 'none';
  });
}
</script>

</body>
</html>