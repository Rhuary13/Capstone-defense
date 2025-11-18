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

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin - Completion Tracking</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    html, body { height: 100%; }
    body { display: flex; overflow: hidden; background-color: #f3f4f6; }
    main { flex: 1; height: 100vh; overflow-y: auto; }
    .module-fade { animation: fadeOut 0.45s forwards; }

    @keyframes fadeOut {
      to { opacity: 0; transform: translateY(-6px); height: 0; margin: 0; padding: 0; }
    }

    /* make recent badges clearly interactive */
    .recent-badge { cursor: pointer; user-select: none; }

    /* responsive tweaks */
    @media (max-width: 768px) {
      main { padding: 1rem; }
    }
  </style>
</head>
<body class="font-sans text-gray-800">

<?php include '../sidebar.php'; ?>

<main class="p-6 space-y-8">

  <!-- DASHBOARD HEADER WITH RECENT BADGES -->
  <section>
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-3xl font-bold text-sky-700 mb-2">Completion Tracking</h1>
        <p class="text-gray-600 text-sm">Manage mandatory and prerequisite modules.</p>
      </div>
      <div class="text-right text-sm text-gray-500">
        Admin: <span class="font-medium"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin' ?></span><br>
        <?= date('F j, Y, g:i A') ?>
      </div>
    </div>

    <?php if (!empty($recentModules)): ?>
      <div class="flex flex-wrap gap-2 mt-4">
        <?php foreach ($recentModules as $rm): ?>
          <span data-id="<?= intval($rm['id']) ?>" class="recent-badge px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs hover:bg-green-200" onclick="openModuleModal(<?= intval($rm['id']) ?>)">
            NEW: <?= htmlspecialchars($rm['title']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- FLOATING PANEL: RECENT MODULES -->
  <section class="bg-white p-5 rounded-xl shadow-md border relative">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Recently Added Modules</h2>
      <div class="text-xs text-gray-500">(Click <strong>View Details</strong> to open — the card will disappear after you open it)</div>
    </div>

    <div id="recentModulesPanel" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
      <?php foreach ($recentModules as $rm): ?>
        <div id="recent-card-<?= intval($rm['id']) ?>" class="bg-slate-50 p-4 rounded-lg shadow-sm border">
          <h3 class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($rm['title']) ?></h3>
          <p class="text-xs text-gray-500 mt-1">Added: <?= htmlspecialchars($rm['created_at']) ?></p>
          <p class="text-xs text-gray-600 mt-2"><?php $d = strip_tags($rm['description'] ?? ''); echo htmlspecialchars(strlen($d) > 120 ? substr($d,0,120).'...' : ($d ?: '— No description —')); ?></p>
          <div class="mt-3 flex items-center gap-2">
            <button onclick="onRecentView(<?= intval($rm['id']) ?>)" class="text-xs px-3 py-1 bg-sky-600 text-white rounded hover:bg-sky-700">View Details</button>
            <button onclick="dismissRecent(<?= intval($rm['id']) ?>)" class="text-xs px-3 py-1 bg-white border rounded">Dismiss</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($recentModules)): ?>
      <p class="text-sm text-gray-500">No recently added modules.</p>
    <?php endif; ?>
  </section>

  <!-- MAIN MODULE TABLE -->
  <section class="bg-white p-6 rounded-xl shadow-md border">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">All Modules</h2>
      <div class="flex items-center gap-3">
        <input id="searchModules" type="search" placeholder="Search modules..." class="border rounded px-3 py-2 text-sm" oninput="filterTable()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm border">
        <thead class="bg-slate-100">
          <tr>
            <th class="px-4 py-2 border">ID</th>
            <th class="px-4 py-2 border">Module</th>
            <th class="px-4 py-2 border">Created</th>
            <th class="px-4 py-2 border">Actions</th>
          </tr>
        </thead>
        <tbody id="modulesTableBody">
          <?php foreach ($modules as $m): ?>
            <tr data-id="<?= intval($m['id']) ?>">
              <td class="px-4 py-2 border text-center"><?= intval($m['id']) ?></td>
              <td class="px-4 py-2 border"><?= htmlspecialchars($m['title']) ?></td>
              <td class="px-4 py-2 border"><?= htmlspecialchars($m['created_at']) ?></td>
              <td class="px-4 py-2 border text-center">
                <button onclick="openModuleModal(<?= intval($m['id']) ?>)" class="text-xs px-3 py-1 bg-white border rounded">View Details</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

</main>

<!-- UNIVERSAL MODULE MODAL -->
<div id="moduleModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
  <div class="bg-white w-11/12 md:w-2/3 lg:w-1/2 p-6 rounded-xl shadow-xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="moduleModalTitle" class="font-bold text-lg">Module Details</h3>
      <button onclick="closeModuleModal()" class="text-gray-500">✕</button>
    </div>
    <div id="moduleModalContent" class="text-sm text-gray-700 max-h-[60vh] overflow-y-auto"></div>
    <div class="mt-4 flex justify-end gap-2">
      <a id="editLink" href="#" class="px-4 py-2 border rounded text-sm">Edit</a>
      <button onclick="closeModuleModal()" class="px-4 py-2 bg-slate-100 rounded text-sm">Close</button>
    </div>
  </div>
</div>

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