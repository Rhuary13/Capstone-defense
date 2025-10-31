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
// Handle Prerequisite & Mandatory Updates (safer: prepared statements + transaction)
// =========================
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mandatory     = $_POST['mandatory'] ?? [];
    $prerequisites = $_POST['prerequisite'] ?? [];

    // Start transaction
    $conn->begin_transaction();
    try {
        // Reset all first
        $conn->query("UPDATE training_modules SET is_mandatory = 0, prerequisite_id = NULL");

        // Update mandatory using prepared statement
        if (!empty($mandatory)) {
            $stmt = $conn->prepare("UPDATE training_modules SET is_mandatory = 1 WHERE id = ?");
            foreach ($mandatory as $mid) {
                $stmt->bind_param("i", $mid);
                $stmt->execute();
            }
            $stmt->close();
        }

        // Update prerequisites using prepared statement
        if (!empty($prerequisites)) {
            $stmt2 = $conn->prepare("UPDATE training_modules SET prerequisite_id = ? WHERE id = ?");
            foreach ($prerequisites as $mid => $pid) {
                $mid_i = intval($mid);
                $pid_i = $pid === "" ? null : intval($pid);
                if ($pid_i === null) {
                    // If null, set prerequisite_id = NULL
                    $conn->query("UPDATE training_modules SET prerequisite_id = NULL WHERE id = " . intval($mid_i));
                } else {
                    $stmt2->bind_param("ii", $pid_i, $mid_i);
                    $stmt2->execute();
                }
            }
            $stmt2->close();
        }

        $conn->commit();
        $message = "✅ Completion rules updated.";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "❌ Failed to update rules: " . htmlspecialchars($e->getMessage());
    }
}

// =========================
// Fetch Modules + current values
// =========================
$modules = [];
$res = $conn->query("SELECT id, title, description, is_mandatory, prerequisite_id, created_at FROM training_modules ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $modules[] = $row;
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
    main { flex: 1; height: 100vh; overflow: hidden; } /* main will contain its own scroll areas */
    /* independent scrolling for the table area */
    .table-scroll { max-height: 60vh; overflow-y: auto; }
    /* sticky header for the table */
    .sticky-header th { position: sticky; top: 0; background: white; z-index: 10; }
    /* small responsive tweaks */
    @media (max-width: 768px) {
      .max-w-5xl { padding: 1rem; border-radius: 0.75rem; }
      .table-scroll { max-height: 50vh; }
    }
  </style>
</head>
<body class="font-sans text-gray-800">

  <!-- Sidebar (existing) -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="p-6">
    <div class="max-w-5xl mx-auto bg-white p-6 rounded-2xl shadow-lg border">
      <div class="flex items-start justify-between gap-6">
        <div>
          <h1 class="text-2xl font-bold text-sky-700 mb-1">📌 Completion Tracking</h1>
          <p class="text-sm text-gray-600">Manage which modules are mandatory and set module prerequisites to control learner flow.</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="text-sm text-gray-500">Admin: <span class="font-medium"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin' ?></span></div>
          <div class="text-sm text-gray-400">|</div>
          <div class="text-sm text-gray-500"><?= date('F j, Y, g:i A') ?></div>
        </div>
      </div>

      <?php if (!empty($message)): ?>
        <div class="p-4 mt-4 rounded-lg <?= strpos($message, '✅') === 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
          <?= $message ?>
        </div>
      <?php endif; ?>

      <!-- Controls: search, sort, bulk actions -->
      <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3 w-full md:w-2/3">
          <input id="search" type="search" placeholder="Search modules..." class="w-full md:w-2/3 border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-200" oninput="filterModules()">
          <select id="sort" class="border rounded-lg px-3 py-2" onchange="sortModules()">
            <option value="id_asc">Sort: ID ↑</option>
            <option value="id_desc">Sort: ID ↓</option>
            <option value="title_asc">Sort: Title A→Z</option>
            <option value="title_desc">Sort: Title Z→A</option>
          </select>
        </div>

        <div class="flex items-center gap-3">
          <button type="button" onclick="bulkSelect(true)" class="px-3 py-2 rounded-md border bg-sky-50 text-sky-700">Select all</button>
          <button type="button" onclick="bulkSelect(false)" class="px-3 py-2 rounded-md border bg-slate-50 text-gray-700">Deselect all</button>
          <button type="button" onclick="openHelp()" class="px-3 py-2 rounded-md border bg-white text-gray-600">Help</button>
        </div>
      </div>

      <!-- Form & Table -->
      <form method="POST" class="space-y-6 mt-6">
        <div class="rounded-lg border overflow-hidden">
          <div class="p-4 bg-white">
            <div class="text-sm text-gray-500">Tip: Use prerequisites to require completion of another module first. Selecting a module as mandatory requires learners to complete it before finishing a program.</div>
          </div>

          <!-- scrollable table container (independent scrolling) -->
          <div class="table-scroll border-t">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="sticky-header bg-white">
                <tr class="text-left">
                  <th class="px-4 py-3 border-b">#</th>
                  <th class="px-4 py-3 border-b">Module</th>
                  <th class="px-4 py-3 border-b">Description</th>
                  <th class="px-4 py-3 border-b text-center">Mandatory</th>
                  <th class="px-4 py-3 border-b">Prerequisite</th>
                  <th class="px-4 py-3 border-b">Actions</th>
                </tr>
              </thead>
              <tbody id="modules-table-body" class="bg-white divide-y divide-gray-100">
                <?php foreach ($modules as $m): ?>
                  <tr data-title="<?= strtolower(htmlspecialchars($m['title'])) ?>" data-id="<?= intval($m['id']) ?>">
                    <td class="px-4 py-3 align-top border-r font-mono text-xs text-gray-500"><?= intval($m['id']) ?></td>
                    <td class="px-4 py-3 align-top font-semibold"><?= htmlspecialchars($m['title']) ?>
                      <div class="text-xs text-gray-400 mt-1">Created: <?= htmlspecialchars($m['created_at']) ?></div>
                    </td>
                    <td class="px-4 py-3 align-top text-sm text-gray-600">
                      <?php
                        $desc = strip_tags($m['description'] ?? '');
                        if (strlen($desc) > 180) echo htmlspecialchars(substr($desc,0,180)) . '...';
                        else echo htmlspecialchars($desc ?: '— No description —');
                      ?>
                    </td>
                    <td class="px-4 py-3 align-top text-center">
                      <input class="h-4 w-4" type="checkbox" name="mandatory[]" value="<?= intval($m['id']) ?>" <?= $m['is_mandatory'] ? 'checked' : '' ?>>
                    </td>
                    <td class="px-4 py-3 align-top">
                      <select name="prerequisite[<?= intval($m['id']) ?>]" class="w-full border rounded px-3 py-2">
                        <option value="">-- None --</option>
                        <?php foreach ($modules as $p): ?>
                          <?php if ($p['id'] != $m['id']): ?>
                            <option value="<?= intval($p['id']) ?>" <?= ($p['id'] == $m['prerequisite_id']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($p['title']) ?>
                            </option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="px-4 py-3 align-top">
                      <div class="flex items-center gap-2">
                        <button type="button" onclick="previewModule(<?= intval($m['id']) ?>)" class="text-xs px-3 py-1 rounded-md border bg-white">Preview</button>
                        <a href="edit_module.php?id=<?= intval($m['id']) ?>" class="text-xs px-3 py-1 rounded-md border bg-white">Edit</a>
                        <button type="button" onclick="confirmRemove(<?= intval($m['id']) ?>, '<?= htmlspecialchars(addslashes($m['title'])) ?>')" class="text-xs px-3 py-1 rounded-md border bg-red-50 text-red-600">Remove</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>

                <?php if (empty($modules)): ?>
                  <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No modules found. Add modules in the Training Modules section.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex items-center justify-between gap-4">
          <div class="text-sm text-gray-500">
            <strong><?= count($modules) ?></strong> modules loaded.
          </div>

          <div class="flex items-center gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">💾 Save Rules</button>
            <a href="create_module.php" class="px-4 py-2 rounded-lg border bg-white text-gray-700">➕ Create module</a>
          </div>
        </div>
      </form>

      <!-- Hidden modal: Preview -->
      <div id="previewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg w-11/12 md:w-3/4 p-6 shadow-lg">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Module Preview</h3>
            <button onclick="closePreview()" class="text-gray-500">✕</button>
          </div>
          <div id="previewContent" class="max-h-[60vh] overflow-y-auto text-sm text-gray-700"></div>
        </div>
      </div>

    </div>
  </main>

  <script>
    // simple client-side helper functions
    function filterModules() {
      const q = document.getElementById('search').value.trim().toLowerCase();
      const rows = document.querySelectorAll('#modules-table-body tr[data-title]');
      rows.forEach(r => {
        const title = r.getAttribute('data-title') || '';
        const id = r.getAttribute('data-id') || '';
        if (!q || title.includes(q) || id.includes(q)) r.style.display = '';
        else r.style.display = 'none';
      });
    }

    function sortModules() {
      const mode = document.getElementById('sort').value;
      const tbody = document.getElementById('modules-table-body');
      Array.from(tbody.querySelectorAll('tr')).sort((a,b) => {
        const aId = parseInt(a.getAttribute('data-id')||0,10);
        const bId = parseInt(b.getAttribute('data-id')||0,10);
        const aTitle = (a.getAttribute('data-title')||'');
        const bTitle = (b.getAttribute('data-title')||'');
        if (mode === 'id_asc') return aId - bId;
        if (mode === 'id_desc') return bId - aId;
        if (mode === 'title_asc') return aTitle.localeCompare(bTitle);
        if (mode === 'title_desc') return bTitle.localeCompare(aTitle);
        return 0;
      }).forEach(node => tbody.appendChild(node));
    }

    function bulkSelect(flag) {
      document.querySelectorAll('input[type="checkbox"][name="mandatory[]"]').forEach(cb => cb.checked = flag);
    }

    function openHelp() {
      alert("Help:\n- Make modules mandatory to require completion.\n- Set a prerequisite to require completion of another module first.\n- Save rules to apply changes.");
    }

    // preview: simple fetch to a preview endpoint (or inline content if available)
    function previewModule(id) {
      // If you have an endpoint returning module JSON, call it. Fallback: grab cells from table.
      const row = document.querySelector('#modules-table-body tr[data-id="'+id+'"]');
      if (!row) return;
      const title = row.querySelector('td:nth-child(2)').innerText.trim();
      const desc = row.querySelector('td:nth-child(3)').innerText.trim();
      document.getElementById('previewContent').innerHTML = "<h4 class='font-semibold mb-2'>"+escapeHtml(title)+"</h4><div class='text-sm whitespace-pre-wrap'>"+escapeHtml(desc)+"</div>";
      document.getElementById('previewModal').classList.remove('hidden');
      document.getElementById('previewModal').classList.add('flex');
    }
    function closePreview() {
      document.getElementById('previewModal').classList.add('hidden');
      document.getElementById('previewModal').classList.remove('flex');
    }
    function escapeHtml(s){ return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'); }

    // remove action: confirms then posts to a delete endpoint (simple UX)
    function confirmRemove(id, title) {
      if (!confirm("Remove module ID " + id + " — " + title + " ? This action cannot be undone via this UI.")) return;
      // Redirect to delete endpoint (implement server-side)
      window.location.href = "delete_module.php?id=" + encodeURIComponent(id);
    }
  </script>
</body>
</html>
