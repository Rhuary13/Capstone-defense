<?php
session_start();
// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = ""; // your MySQL password if any
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// ----------------------
// CSRF TOKEN
// ----------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----------------------
// Helpers
// ----------------------
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ----------------------
// Simple Export CSV for drills
// ----------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=drills.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['id','title','details','date','type','file','created_at']);
    $r = $conn->query("SELECT id,title,details,`date`,type,file_path,created_at FROM drills ORDER BY `date` DESC");
    while ($row = $r->fetch_assoc()) fputcsv($out, [$row['id'],$row['title'],$row['details'],$row['date'],$row['type'],$row['file_path'],$row['created_at']]);
    fclose($out); exit;
}

// ----------------------
// Handle uploads directory
// ----------------------
$uploadDir = __DIR__ . '/uploads/drills/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ----------------------
// Post handling: add / update
// (left unchanged from your original — only UI changed)
// ----------------------
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
        $errors[] = 'Invalid CSRF token.';
    } else {
        // common fields
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $type = trim($_POST['type'] ?? 'Drill');

        if ($title === '') $errors[] = 'Title is required.';
        if ($date === '') $errors[] = 'Date is required.';
        if ($details === '') $errors[] = 'Details are required.';

        // handle file upload optionally
        $filePath = null;
        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK){
            $name = preg_replace('/[^A-Za-z0-9.\-_]/','_', basename($_FILES['file']['name']));
            $target = $uploadDir . time() . '_' . $name;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target)){
                // store relative path for link
                $filePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $target);
            } else {
                $errors[] = 'Failed to save uploaded file.';
            }
        }

        // CREATE
        if (isset($_POST['add_drill']) && empty($errors)){
            $stmt = $conn->prepare("INSERT INTO drills (title, `date`, details, type, file_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssss', $title, $date, $details, $type, $filePath);
            if ($stmt->execute()){
                $success = 'Drill added successfully.';
            } else {
                $errors[] = 'Insert failed: ' . $stmt->error;
            }
            $stmt->close();
        }

        // UPDATE
        if (isset($_POST['update_drill']) && empty($errors)){
            $id = (int)($_POST['id'] ?? 0);
            // if new file uploaded, store path, otherwise keep existing
            if ($filePath){
                $stmt = $conn->prepare("UPDATE drills SET title=?, `date`=?, details=?, type=?, file_path=? WHERE id=?");
                $stmt->bind_param('sssssi', $title, $date, $details, $type, $filePath, $id);
            } else {
                $stmt = $conn->prepare("UPDATE drills SET title=?, `date`=?, details=?, type=? WHERE id=?");
                $stmt->bind_param('ssssi', $title, $date, $details, $type, $id);
            }
            if ($stmt->execute()){
                header('Location: module1.3.php?updated=1'); exit;
            } else {
                $errors[] = 'Update failed: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ----------------------
// Delete (with prepared)
// ----------------------
if (isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    // fetch file path to delete if exists
    $stmt = $conn->prepare("SELECT file_path FROM drills WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close();
    if ($row && !empty($row['file_path'])){
        $fp = $_SERVER['DOCUMENT_ROOT'] . $row['file_path']; if (file_exists($fp)) @unlink($fp);
    }
    $stmt = $conn->prepare("DELETE FROM drills WHERE id=?"); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    header('Location: module1.3.php?deleted=1'); exit;
}

// ----------------------
// Edit fetch
// ----------------------
$edit_mode = false; $edit_drill = null;
if (isset($_GET['edit'])){
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM drills WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$id); $stmt->execute(); $res = $stmt->get_result(); $edit_drill = $res->fetch_assoc(); $stmt->close();
    if ($edit_drill) $edit_mode = true;
}

// ----------------------
// Fetch drills with pagination + search + filter by type/date
// ----------------------
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12; $offset = ($page-1)*$perPage;
$search = trim($_GET['q'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');

$whereParts = [];
$params = [];
$typesForBind = '';
if ($search !== ''){
    $whereParts[] = "(title LIKE ? OR details LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
    $typesForBind .= 'ss';
}
if ($filterType !== ''){
    $whereParts[] = "type = ?";
    $params[] = $filterType;
    $typesForBind .= 's';
}
if ($fromDate !== ''){
    $whereParts[] = "`date` >= ?";
    $params[] = $fromDate;
    $typesForBind .= 's';
}
if ($toDate !== ''){
    $whereParts[] = "`date` <= ?";
    $params[] = $toDate;
    $typesForBind .= 's';
}
$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$countSql = "SELECT COUNT(*) AS c FROM drills " . $where;
$stmt = $conn->prepare($countSql);
if ($params){
    $stmt->bind_param($typesForBind, ...$params);
}
$stmt->execute(); $total = $stmt->get_result()->fetch_assoc()['c'] ?? 0; $stmt->close();
$pages = max(1, (int)ceil($total / $perPage));

$listSql = "SELECT * FROM drills " . $where . " ORDER BY `date` DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($listSql);
if ($params){
    // append two ints for LIMIT/OFFSET
    $bindTypes = $typesForBind . 'ii';
    $stmt->bind_param($bindTypes, ...array_merge($params, [$perPage, $offset]));
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute(); $dr = $stmt->get_result();
$drills = $dr->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Prepare upcoming drills (next 6) for sidebar/calendar preview
$upcoming = $conn->query("SELECT id,title,`date`,type FROM drills WHERE `date` >= CURDATE() ORDER BY `date` ASC LIMIT 6");
$upcoming_list = $upcoming ? $upcoming->fetch_all(MYSQLI_ASSOC) : [];

// Gather distinct types for filter dropdown
$typeRes = $conn->query("SELECT DISTINCT type FROM drills ORDER BY type ASC");
$types = $typeRes ? array_filter(array_map(function($r){ return $r['type'];}, $typeRes->fetch_all(MYSQLI_ASSOC))) : [];

// ----------------------
// (end server logic) — UI only below
// ----------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Training & Scheduled Lessons (Admin)</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden}
    .main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
    .main-scroll{flex:1;overflow:auto;min-height:0;padding:1.25rem;background:#f8fafc}
    .truncate-cell{max-width:20rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .card-scroll{max-height:42vh;overflow:auto;padding-right:8px}
    .card-scroll::-webkit-scrollbar{width:10px}
    .card-scroll::-webkit-scrollbar-thumb{background-color:rgba(2,6,23,0.06);border-radius:8px}
    .pill{display:inline-block;padding:.25rem .5rem;border-radius:9999px;font-size:.75rem}
  </style>
</head>
<body class="bg-slate-50 font-sans">
  <div class="app">
    <!-- Sidebar -->
    <?php if (file_exists(__DIR__ . '/../sidebar.php')): ?>
      <?php include __DIR__ . '/../sidebar.php'; ?>
    <?php else: ?>
      <aside style="width:16rem;background:#fff;border-right:1px solid #edf2f7;padding:1rem;"> <div class="font-bold">Admin</div></aside>
    <?php endif; ?>

    <div class="main-wrap">
      <!-- Topbar -->
      <header class="bg-white border-b h-16 flex items-center justify-between px-6">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">Training & Lessons</h1>
          <div class="text-sm text-slate-500">Build lessons, schedule training deadlines, and publish to participants/staff</div>
        </div>
        <div class="flex items-center gap-3">
          <a href="?export=csv" class="px-3 py-2 bg-gray-100 rounded text-sm">Export CSV</a>
          <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'Admin') ?></strong></div>
        </div>
      </header>

      <!-- Main scrollable content (independent scrolling for layout stability) -->
      <main class="main-scroll" role="main">
        <div class="max-w-7xl mx-auto">

          <!-- Alerts -->
          <?php if (!empty($success)): ?>
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-100">✅ <?= esc($success) ?></div>
          <?php endif; ?>
          <?php if (!empty($errors)): ?>
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-100">
              <strong>Errors:</strong>
              <ul class="mt-1"><?php foreach($errors as $er) echo '<li>'.esc($er).'</li>'; ?></ul>
            </div>
          <?php endif; ?>
          <?php if (isset($_GET['updated'])): ?><div class="mb-4 p-3 rounded-lg bg-blue-50 text-blue-800">Drill updated successfully.</div><?php endif; ?>
          <?php if (isset($_GET['deleted'])): ?><div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800">Drill deleted.</div><?php endif; ?>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left column: Form -->
            <section class="bg-white p-6 rounded-2xl shadow">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                  <div class="p-2 rounded bg-indigo-50"><i data-lucide="plus-circle" class="w-5 h-5 text-indigo-600"></i></div>
                  <div>
                    <h2 class="text-lg font-semibold"><?= $edit_mode ? 'Edit Lesson / Training' : 'New Lesson / Training' ?></h2>
                    <div class="text-sm text-slate-500">Create scheduled lessons that will be posted to participants & staff</div>
                  </div>
                </div>
                <?php if ($edit_mode): ?><a href="module1.3.php" class="text-sm text-slate-500">New</a><?php endif; ?>
              </div>

              <!-- Form (unchanged handlers) -->
              <form method="POST" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
                <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?= (int)$edit_drill['id'] ?>"><?php endif; ?>

                <div>
                  <label class="text-sm font-medium block mb-1">Lesson / Training Title</label>
                  <input name="title" required value="<?= esc($edit_drill['title'] ?? '') ?>" class="mt-1 w-full px-3 py-2 border rounded-lg" placeholder="E.g. Community Evacuation Drill — Flood" />
                </div>

                <div>
                  <label class="text-sm font-medium block mb-1">Training Date (deadline / scheduled)</label>
                  <input type="date" name="date" required value="<?= esc($edit_drill['date'] ?? '') ?>" class="mt-1 px-3 py-2 border rounded-lg w-48" />
                  <div class="text-xs text-slate-400 mt-1">Date participants will be scheduled for this lesson/training.</div>
                </div>

                <div>
                  <label class="text-sm font-medium block mb-1">Details / Lesson Content</label>
                  <textarea name="details" rows="5" class="mt-1 w-full px-3 py-2 border rounded-lg"><?= esc($edit_drill['details'] ?? '') ?></textarea>
                </div>

                <div>
                  <label class="text-sm font-medium block mb-1">Category / Disaster Type</label>
                  <select name="type" class="mt-1 px-3 py-2 border rounded-lg w-full">
                    <?php
                      $preset = ['Flood','Earthquake','Fire','Storm','Tsunami','Landslide','Drill','Workshop','Tabletop','Simulation'];
                      foreach($preset as $p): ?>
                      <option value="<?= esc($p) ?>" <?= isset($edit_drill['type']) && $edit_drill['type']==$p ? 'selected' : '' ?>><?= esc($p) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="text-xs text-slate-400 mt-1">This helps categorize the lesson for participants (disaster type).</div>
                </div>

                <div>
                  <label class="text-sm font-medium block mb-1">Attach file (agenda, PPT, PDF) — optional</label>
                  <input type="file" name="file" class="mt-1" />
                  <?php if (!empty($edit_drill['file_path'])): ?>
                    <div class="text-sm text-slate-500 mt-1">Current file: <a href="<?= esc($edit_drill['file_path']) ?>" target="_blank" class="text-indigo-600">Open</a></div>
                  <?php endif; ?>
                </div>

                <div class="flex gap-2 justify-end">
                  <?php if ($edit_mode): ?>
                    <a href="module1.3.php" class="px-4 py-2 bg-gray-100 rounded-lg">Cancel</a>
                    <button type="submit" name="update_drill" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Update</button>
                  <?php else: ?>
                    <button type="submit" name="add_drill" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Publish Lesson</button>
                  <?php endif; ?>
                </div>
              </form>

              <div class="mt-4 text-xs text-slate-400">When published, this lesson will be visible to participants and staff. Use the category to tag by disaster type.</div>
            </section>

            <!-- Middle + Right column combined -->
            <div class="lg:col-span-2 space-y-6">
              <!-- Controls & filters -->
              <div class="bg-white p-4 rounded-2xl shadow flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                  <input id="q" name="q" placeholder="Search title or details" value="<?= esc($search) ?>" class="px-3 py-2 border rounded-lg w-64" oninput="debouncedSearch()" />
                  <select id="typeFilter" class="px-3 py-2 border rounded-lg" onchange="applyFilters()">
                    <option value="">All categories</option>
                    <?php foreach ($types as $t): ?>
                      <option value="<?= esc($t) ?>" <?= $t === $filterType ? 'selected' : '' ?>><?= esc($t) ?></option>
                    <?php endforeach;?>
                  </select>

                  <div class="flex items-center gap-2">
                    <input type="date" id="from" name="from" value="<?= esc($fromDate) ?>" class="px-3 py-2 border rounded-lg" onchange="applyFilters()" />
                    <span class="text-sm text-slate-400">to</span>
                    <input type="date" id="to" name="to" value="<?= esc($toDate) ?>" class="px-3 py-2 border rounded-lg" onchange="applyFilters()" />
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <button class="px-3 py-2 bg-sky-600 text-white rounded-lg" onclick="window.location='module1.3.php'">Reset</button>
                  <div class="text-sm text-slate-500">Showing <?= (int)$total ?> results</div>
                </div>
              </div>

              <!-- List + upcoming sidebar -->
              <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <section class="lg:col-span-2 bg-white p-4 rounded-2xl shadow">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold">Lessons & Trainings</h3>
                    <div class="text-sm text-slate-500">Page <?= $page ?> / <?= $pages ?></div>
                  </div>

                  <div class="overflow-auto rounded border border-slate-100 card-scroll">
                    <table class="min-w-full text-sm">
                      <thead class="bg-slate-50 text-slate-700 text-xs uppercase sticky top-0">
                        <tr>
                          <th class="px-3 py-2 text-left">Title</th>
                          <th class="px-3 py-2 text-left">Category</th>
                          <th class="px-3 py-2 text-left">Date</th>
                          <th class="px-3 py-2 text-left">File</th>
                          <th class="px-3 py-2 text-left">Created</th>
                          <th class="px-3 py-2 text-left">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="drillTable" class="divide-y">
                        <?php if (count($drills)): foreach ($drills as $d): ?>
                          <tr class="hover:bg-slate-50">
                            <td class="px-3 py-3 font-medium truncate-cell" title="<?= esc($d['title']) ?>"><?= esc($d['title']) ?></td>
                            <td class="px-3 py-3">
                              <span class="pill bg-slate-100 text-slate-700"><?= esc($d['type']) ?></span>
                            </td>
                            <td class="px-3 py-3"><?= esc($d['date']) ?></td>
                            <td class="px-3 py-3"><?php if (!empty($d['file_path'])): ?><a href="<?= esc($d['file_path']) ?>" target="_blank" class="text-indigo-600">Open</a><?php else: ?>—<?php endif; ?></td>
                            <td class="px-3 py-3"><?= esc($d['created_at']) ?></td>
                            <td class="px-3 py-3 flex gap-3">
                              <a class="text-indigo-600" href="?edit=<?= (int)$d['id'] ?>">Edit</a>
                              <a class="text-rose-600" href="#" onclick="confirmDelete(<?= (int)$d['id'] ?>)">Delete</a>
                              <a class="text-sky-600" href="#" onclick="previewAs(<?= (int)$d['id'] ?>,'participant')">Preview</a>
                            </td>
                          </tr>
                        <?php endforeach; else: ?>
                          <tr><td colspan="6" class="p-4 text-center text-slate-500">No drills found.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Pagination controls -->
                  <div class="mt-4 flex items-center justify-between">
                    <div>
                      <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>" class="px-3 py-1 bg-gray-100 rounded">Prev</a><?php endif; ?>
                    </div>
                    <div class="text-sm text-slate-500">Page <?= $page ?> of <?= $pages ?></div>
                    <div>
                      <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>" class="px-3 py-1 bg-gray-100 rounded">Next</a><?php endif; ?>
                    </div>
                  </div>
                </section>

                <!-- Upcoming / Preview Sidebar -->
                <aside class="bg-white p-4 rounded-2xl shadow">
                  <div class="flex items-center justify-between mb-3">
                    <h4 class="text-md font-semibold">Upcoming Schedule</h4>
                    <a href="#" class="text-sm text-slate-500">More</a>
                  </div>

                  <?php if (count($upcoming_list)): ?>
                    <div class="space-y-3">
                      <?php foreach ($upcoming_list as $u): ?>
                        <div class="p-3 border rounded-lg">
                          <div class="flex items-start justify-between gap-3">
                            <div>
                              <div class="font-medium"><?= esc($u['title']) ?></div>
                              <div class="text-xs text-slate-500"><?= esc($u['type']) ?> • <?= date('M d, Y', strtotime($u['date'])) ?></div>
                            </div>
                            <div class="text-xs text-slate-400"><?= date('D', strtotime($u['date'])) ?></div>
                          </div>
                          <div class="mt-2 text-xs text-slate-500">Posted to: <strong>Participants & Staff</strong></div>
                        </div>
                      <?php endforeach;?>
                    </div>
                  <?php else: ?>
                    <div class="text-sm text-slate-500">No upcoming trainings scheduled.</div>
                  <?php endif; ?>

                  <hr class="my-3" />
                  <div>
                    <h5 class="text-sm font-semibold">Preview as:</h5>
                    <div class="mt-2 flex gap-2">
                      <button class="px-3 py-2 bg-slate-100 rounded" onclick="window.location='module1.3_preview_participant.php'">Participant</button>
                      <button class="px-3 py-2 bg-slate-100 rounded" onclick="window.location='module1.3_preview_staff.php'">Staff</button>
                    </div>
                    <div class="text-xs text-slate-400 mt-3">Click preview to see how participants and staff view scheduled lessons (opens the preview page).</div>
                  </div>
                </aside>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

<script>
  lucide.createIcons();

  function confirmDelete(id){
    if (confirm('Delete this lesson/training? This action cannot be undone.')){
      window.location.href = '?delete='+id;
    }
  }

  // Debounced search + filters (client -> reload with query params)
  let debounceTimer = null;
  function debouncedSearch(){
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(()=> applyFilters(), 350);
  }
  function applyFilters(){
    const q = document.getElementById('q').value || '';
    const type = document.getElementById('typeFilter').value || '';
    const from = document.getElementById('from').value || '';
    const to = document.getElementById('to').value || '';
    const url = new URL(window.location.href);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (type) url.searchParams.set('type', type); else url.searchParams.delete('type');
    if (from) url.searchParams.set('from', from); else url.searchParams.delete('from');
    if (to) url.searchParams.set('to', to); else url.searchParams.delete('to');
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
  }

  // preview logic — opens a small modal-like window showing how the participant would see it
  function previewAs(id, as){
    // simple client-side preview by opening a new window to a preview view route (you can implement preview pages)
    window.open('module1.3_preview.php?id='+id+'&as='+as, '_blank', 'width=900,height=800');
  }
</script>
</body>
</html>
