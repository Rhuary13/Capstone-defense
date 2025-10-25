<?php
session_start();

/* ---------------------------
   CONFIGURATION - adjust if needed
   --------------------------- */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";
$uploadDirRelative = "uploads/"; // relative to this file
$allowedExtensions = ['pdf','doc','docx','jpg','jpeg','png'];
$itemsPerPage = 8; // pagination

/* Preset list of disaster preparedness types (admin dropdown) */
$presetDisasterTypes = [
    "All Disaster Type",
    "Earthquake",
    "Tsunami",
    "Volcanic Eruption",
    "Flood",
    "Typhoon / Hurricane / Cyclone",
    "Tornado",
    "Landslide",
    "Avalanche",
    "Wildfire",
    "Drought",
    "Heatwave",
    "Coldwave / Extreme Cold",
    "Pandemic / Epidemic",
    "Biological Incident",
    "Chemical Spill / Hazmat",
    "Radiological / Nuclear",
    "Power Outage / Blackout",
    "Infrastructure Collapse (structures, bridges)",
    "Mass Casualty Incident",
    "Transport Accident (road, rail, air, maritime)",
    "Oil Spill",
    "Water Contamination",
    "Food Shortage / Famine",
    "Sinkhole",
    "Cyberattack / ICT disruption",
    "Terrorism / Violent Attack",
    "Urban Fire",
    "Industrial Accident",
    "Extreme Storm / Hail",
    "Other"
];

/* ---------------------------
   DATABASE CONNECT
   --------------------------- */
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

/* ---------------------------
   AUTH CHECK
   --------------------------- */
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

/* Simple CSRF token (optional but helpful) */
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

/* ---------------------------
   HELPER FUNCTIONS
   --------------------------- */
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function make_unique_filename($orig) {
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $base = pathinfo($orig, PATHINFO_FILENAME);
    $uniq = time() . '_' . bin2hex(random_bytes(6));
    $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', substr($base, 0, 50));
    return $safeBase . '_' . $uniq . ($ext ? '.' . $ext : '');
}

/* Ensure upload directory exists (server path) */
$uploadDirServer = __DIR__ . DIRECTORY_SEPARATOR . $uploadDirRelative;
if (!is_dir($uploadDirServer)) {
    mkdir($uploadDirServer, 0755, true);
}

/* Compute absolute base URL for Google Docs viewer (for docs) */
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';

/* ---------------------------
   ACTIONS: ADD / UPDATE / DELETE
   --------------------------- */
$feedback = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // basic CSRF check
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $feedback = ['type' => 'error', 'msg' => 'Invalid request token.'];
    } else {
        // ADD
        if (isset($_POST['add_module'])) {
            $title = trim($_POST['title'] ?? '');
            $objectives = trim($_POST['objectives'] ?? '');
            $disasterType = trim($_POST['disaster_type'] ?? '');

            // Validate basic
            if ($title === '' || $objectives === '' || $disasterType === '') {
                $feedback = ['type' => 'error', 'msg' => 'Please fill all required fields.'];
            } else {
                // handle file upload
                $uploaded_file = null;
                if (!empty($_FILES['content']['name']) && $_FILES['content']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['content']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions)) {
                        $feedback = ['type' => 'error', 'msg' => 'Invalid file type.'];
                    } else {
                        $newname = make_unique_filename($_FILES['content']['name']);
                        $target = $uploadDirServer . $newname;
                        if (move_uploaded_file($_FILES['content']['tmp_name'], $target)) {
                            $uploaded_file = $newname;
                        } else {
                            $feedback = ['type' => 'error', 'msg' => 'Failed to move uploaded file.'];
                        }
                    }
                }

                if ($feedback['type'] === '') {
                    $stmt = $conn->prepare("INSERT INTO training_modules (title, objectives, disaster_type, file_name, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssss", $title, $objectives, $disasterType, $uploaded_file);
                    if ($stmt->execute()) {
                        $feedback = ['type' => 'success', 'msg' => 'Module added successfully.'];
                    } else {
                        $feedback = ['type' => 'error', 'msg' => 'DB error on insert.'];
                    }
                    $stmt->close();
                }
            }
        }

        // UPDATE
        if (isset($_POST['update_module'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $objectives = trim($_POST['objectives'] ?? '');
            $disasterType = trim($_POST['disaster_type'] ?? '');
            $existing_file = $_POST['existing_file'] ?? null;

            if ($id <= 0 || $title === '' || $objectives === '' || $disasterType === '') {
                $feedback = ['type' => 'error', 'msg' => 'Invalid input for update.'];
            } else {
                $uploaded_file = $existing_file;
                if (!empty($_FILES['content']['name']) && $_FILES['content']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['content']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions)) {
                        $feedback = ['type' => 'error', 'msg' => 'Invalid file type.'];
                    } else {
                        $newname = make_unique_filename($_FILES['content']['name']);
                        $target = $uploadDirServer . $newname;
                        if (move_uploaded_file($_FILES['content']['tmp_name'], $target)) {
                            $uploaded_file = $newname;
                            // optionally remove old file
                            if ($existing_file) {
                                @unlink($uploadDirServer . $existing_file);
                            }
                        } else {
                            $feedback = ['type' => 'error', 'msg' => 'Failed to move uploaded file.'];
                        }
                    }
                }

                if ($feedback['type'] === '') {
                    $stmt = $conn->prepare("UPDATE training_modules SET title=?, objectives=?, disaster_type=?, file_name=? WHERE id=?");
                    $stmt->bind_param("ssssi", $title, $objectives, $disasterType, $uploaded_file, $id);
                    if ($stmt->execute()) {
                        $feedback = ['type' => 'success', 'msg' => 'Module updated successfully.'];
                    } else {
                        $feedback = ['type' => 'error', 'msg' => 'DB error on update.'];
                    }
                    $stmt->close();
                }
            }
        }
    }
}

/* DELETE action (GET) - with confirmation in UI
   Use prepared stmt for safety */
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    if ($delId > 0) {
        // fetch file for deletion
        $stmt = $conn->prepare("SELECT file_name FROM training_modules WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $delId);
        $stmt->execute();
        $res = $stmt->get_result();
        $fileToRemove = null;
        if ($r = $res->fetch_assoc()) {
            $fileToRemove = $r['file_name'];
        }
        $stmt->close();

        $stmt2 = $conn->prepare("DELETE FROM training_modules WHERE id=?");
        $stmt2->bind_param("i", $delId);
        if ($stmt2->execute()) {
            if ($fileToRemove) {
                @unlink($uploadDirServer . $fileToRemove);
            }
            $feedback = ['type' => 'success', 'msg' => 'Module deleted.'];
        } else {
            $feedback = ['type' => 'error', 'msg' => 'Failed to delete module.'];
        }
        $stmt2->close();
    }
}

/* ---------------------------
   FETCH single for edit
   --------------------------- */
$editData = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    if ($eid > 0) {
        $stmt = $conn->prepare("SELECT * FROM training_modules WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $eid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $editData = $res->fetch_assoc();
        }
        $stmt->close();
    }
}

/* ---------------------------
   SEARCH / FILTER / PAGINATION
   --------------------------- */
$search = trim($_GET['q'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

/* Build WHERE clause with parameters */
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(title LIKE ? OR objectives LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}
if ($filterType !== '' && $filterType !== 'All Disaster Type') {
    $where[] = "disaster_type = ?";
    $params[] = $filterType;
    $types .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* Count total */
$countSql = "SELECT COUNT(*) as cnt FROM training_modules $whereSQL";
$stmt = $conn->prepare($countSql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cntRes = $stmt->get_result()->fetch_assoc();
$totalItems = (int)($cntRes['cnt'] ?? 0);
$stmt->close();

$totalPages = (int)ceil($totalItems / $itemsPerPage);

/* Fetch paginated rows */
$selectSql = "SELECT * FROM training_modules $whereSQL ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($selectSql);
if ($params) {
    // add limit and offset types
    $bindTypes = $types . 'ii';
    $stmt->bind_param($bindTypes, ...array_merge($params, [$itemsPerPage, $offset]));
} else {
    $stmt->bind_param('ii', $itemsPerPage, $offset);
}
$stmt->execute();
$modulesResult = $stmt->get_result();
$stmt->close();

/* Get distinct disaster types for filter dropdown
   Merge preset list with DB types (preset order preserved), remove duplicates */
$typesFromDb = [];
$rt = $conn->query("SELECT DISTINCT disaster_type FROM training_modules WHERE disaster_type IS NOT NULL AND disaster_type <> ''");
while ($tr = $rt->fetch_assoc()) { $typesFromDb[] = $tr['disaster_type']; }

// Merge preset + DB unique
$merged = array_unique(array_merge($presetDisasterTypes, $typesFromDb));

// Ensure "All Disaster Type" is first
if (($key = array_search("All Disaster Type", $merged)) !== false) {
    unset($merged[$key]);
}
$typesArr = array_values(array_merge(["All Disaster Type"], $merged));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Module Creation & Setup — Admin</title>

<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<style>
  /* Ensure the main panel scrolls independently and doesn't overlap the sidebar */
  .main-wrapper { margin-left: 16rem; /* 64 */ }
  .top-nav-height { height: 4rem; } /* match nav height (4rem) */
  .main-scroll { height: calc(100vh - 4rem); overflow-y: auto; -webkit-overflow-scrolling: touch; }
  .table-scroll { max-height: 60vh; overflow: auto; }
  /* modal background */
  .modal-backdrop { background: rgba(0,0,0,0.45); }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex font-sans">

  <!-- Sidebar (kept as-is) -->
  <?php include '../sidebar.php'; ?>
  
  <!-- Main Area (to the right of sidebar) -->
  <div class="flex-1 flex flex-col min-h-screen">

    <!-- Top nav (sticky) -->
    <nav class="bg-white shadow-sm border-b px-6 py-4 flex items-center justify-between sticky top-0 z-20 top-nav-height">
      <div class="flex items-center gap-3">
        <i data-lucide="layers" class="w-6 h-6 text-sky-600"></i>
        <div>
          <h1 class="text-lg font-semibold text-slate-800">Training Modules</h1>
          <span class="text-sm text-slate-500">Manage learning units & resources</span>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2" aria-label="Search modules">
          <input type="hidden" name="type" value="<?= e($filterType) ?>">
          <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search title or objectives"
                 class="px-3 py-2 border rounded-md bg-white text-sm w-64 focus:ring-2 focus:ring-sky-500" />
          <button type="submit" class="px-3 py-2 bg-sky-600 text-white rounded-md text-sm">Search</button>
        </form>

        <button id="toggleFormBtn" class="px-3 py-2 bg-emerald-600 text-white rounded-md text-sm flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Add Module
        </button>
      </div>
    </nav>

    <!-- Independent scrolling main content -->
    <main class="p-6 main-scroll">
      <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Form column -->
        <section id="formColumn" class="lg:col-span-1">
          <div class="bg-white p-6 rounded-xl shadow-sm border">
            <div class="flex items-center justify-between gap-3 mb-4">
              <h2 class="text-lg font-medium text-slate-800">
                <?= $editData ? 'Edit Module' : 'Create Module' ?>
              </h2>
              <?php if ($editData): ?>
                <a href="module1.1.php" class="text-sm text-slate-500 hover:underline">New Module</a>
              <?php endif; ?>
            </div>

            <!-- Feedback -->
            <?php if ($feedback['type'] === 'success'): ?>
              <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-md border border-green-100"><?= e($feedback['msg']) ?></div>
            <?php elseif ($feedback['type'] === 'error'): ?>
              <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-md border border-red-100"><?= e($feedback['msg']) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-3">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
              <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">
              <input type="hidden" name="existing_file" value="<?= e($editData['file_name'] ?? '') ?>">

              <label class="block text-sm font-medium text-slate-700">Module Title</label>
              <input name="title" required value="<?= e($editData['title'] ?? '') ?>"
                     class="w-full px-3 py-2 border rounded-md bg-white text-sm focus:ring-2 focus:ring-sky-500">

              <label class="block text-sm font-medium text-slate-700">Objectives</label>
              <textarea name="objectives" rows="4" required class="w-full px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-sky-500"><?= e($editData['objectives'] ?? '') ?></textarea>

              <label class="block text-sm font-medium text-slate-700">Disaster Type</label>
              <select name="disaster_type" required class="w-full px-3 py-2 border rounded-md text-sm focus:ring-2 focus:ring-sky-500">
                <?php foreach ($typesArr as $t): ?>
                  <option value="<?= e($t) ?>" <?= (isset($editData['disaster_type']) && $editData['disaster_type'] === $t) ? 'selected' : '' ?>>
                    <?= e($t) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <label class="block text-sm font-medium text-slate-700">Upload Content (pdf/doc/img)</label>
              <input type="file" name="content" class="w-full text-sm">

              <?php if (!empty($editData['file_name'])): 
                $fp = $uploadDirRelative . e($editData['file_name']);
              ?>
                <div class="text-xs text-slate-500">Current file: <a href="<?= $fp ?>" target="_blank" class="text-sky-600 hover:underline"><?= e($editData['file_name']) ?></a></div>
              <?php endif; ?>

              <div class="flex items-center gap-3 pt-3">
                <?php if ($editData): ?>
                  <button type="submit" name="update_module" class="px-4 py-2 bg-sky-600 text-white rounded-md">Update Module</button>
                  <a href="module1.1.php" class="px-4 py-2 bg-gray-100 border rounded-md text-sm">Cancel</a>
                <?php else: ?>
                  <button type="submit" name="add_module" class="px-4 py-2 bg-emerald-600 text-white rounded-md">Save Module</button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </section>

        <!-- List column -->
        <section class="lg:col-span-2">
          <div class="bg-white p-4 rounded-xl shadow-sm border">
            <div class="flex items-center justify-between gap-3 mb-4">
              <div>
                <h3 class="text-lg font-medium text-slate-800">Existing Modules</h3>
                <p class="text-sm text-slate-500"><?= $totalItems ?> total</p>
              </div>

              <div class="flex items-center gap-3">
                <form method="GET" class="flex items-center gap-2">
                  <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search..."
                         class="px-3 py-2 border rounded-md text-sm w-48">
                  <select name="type" class="px-3 py-2 border rounded-md text-sm">
                    <?php foreach ($typesArr as $t): ?>
                      <option value="<?= e($t) ?>" <?= ($filterType === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="px-3 py-2 bg-sky-600 text-white rounded-md text-sm">Apply</button>
                </form>
              </div>
            </div>

            <!-- modules table -->
            <div class="table-scroll">
              <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 sticky top-0">
                  <tr>
                    <th class="px-3 py-2 text-left font-medium text-slate-700">Title</th>
                    <th class="px-3 py-2 text-left font-medium text-slate-700">Objectives</th>
                    <th class="px-3 py-2 text-left font-medium text-slate-700">Type</th>
                    <th class="px-3 py-2 text-left font-medium text-slate-700">File</th>
                    <th class="px-3 py-2 text-left font-medium text-slate-700">Created</th>
                    <th class="px-3 py-2 text-left font-medium text-slate-700">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <?php if ($modulesResult->num_rows === 0): ?>
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No modules found.</td></tr>
                  <?php else: ?>
                    <?php while ($row = $modulesResult->fetch_assoc()): 
                      $file = $row['file_name'];
                      $filePath = $file ? $uploadDirRelative . $file : null;
                      $ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
                      // data attributes for details modal (escape JSON-friendly values)
                      $dataAttrs = [
                        'title' => $row['title'],
                        'objectives' => $row['objectives'],
                        'type' => $row['disaster_type'],
                        'file' => $filePath,
                        'ext' => $ext,
                        'created' => $row['created_at'] ?? '',
                        'filename' => $file ?? ''
                      ];
                      $dataJson = e(json_encode($dataAttrs));
                      ?>
                      <tr>
                        <td class="px-3 py-3 align-top"><?= e($row['title']) ?></td>
                        <td class="px-3 py-3 align-top"><div class="max-w-xl text-xs text-slate-700"><?= e($row['objectives']) ?></div></td>
                        <td class="px-3 py-3 align-top"><?= e($row['disaster_type']) ?></td>
                        <td class="px-3 py-3 align-top">
                          <?php if ($filePath): ?>
                            <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
                              <a href="<?= e($filePath) ?>" target="_blank" class="text-sky-600 hover:underline">Image</a>
                            <?php elseif ($ext === 'pdf'): ?>
                              <a href="<?= e($filePath) ?>" target="_blank" class="text-sky-600 hover:underline">PDF</a>
                            <?php elseif (in_array($ext, ['doc','docx'])): ?>
                              <a href="https://docs.google.com/viewer?url=<?= urlencode($baseUrl . $filePath) ?>&embedded=true" target="_blank" class="text-emerald-600 hover:underline">View (Docs)</a>
                            <?php else: ?>
                              <a href="<?= e($filePath) ?>" download class="text-slate-600 hover:underline">Download</a>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-slate-400">None</span>
                          <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 align-top"><?= e($row['created_at'] ?? '') ?></td>
                        <td class="px-3 py-3 align-top flex gap-2">
                          <button 
                            class="detailsBtn inline-flex items-center gap-2 px-2 py-1 text-indigo-600 hover:underline"
                            data-info='<?= $dataJson ?>'>
                            <i data-lucide="info" class="w-4 h-4"></i>Details
                          </button>
                          <a href="?edit=<?= (int)$row['id'] ?>" class="inline-flex items-center gap-2 px-2 py-1 text-sky-600 hover:underline">
                            <i data-lucide="edit-2" class="w-4 h-4"></i>Edit
                          </a>
                          <a href="?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this module?')" class="inline-flex items-center gap-2 px-2 py-1 text-red-600 hover:underline">
                            <i data-lucide="trash" class="w-4 h-4"></i>Delete
                          </a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4 flex items-center justify-between">
              <div class="text-sm text-slate-500">Showing page <?= $page ?> of <?= max(1, $totalPages) ?></div>
              <div class="flex items-center gap-2">
                <?php for ($p = 1; $p <= max(1, $totalPages); $p++): ?>
                  <a href="?q=<?= urlencode($search) ?>&type=<?= urlencode($filterType) ?>&page=<?= $p ?>"
                     class="px-3 py-1 rounded-md text-sm <?= ($p === $page) ? 'bg-sky-600 text-white' : 'bg-white border' ?>">
                    <?= $p ?>
                  </a>
                <?php endfor; ?>
              </div>
            </div>

          </div>
        </section>

      </div>
    </main>

  </div>

<!-- DETAILS MODAL -->
<div id="detailsModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="modal-backdrop absolute inset-0"></div>
  <div class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4 md:mx-0 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 id="modalTitle" class="text-lg font-semibold text-slate-800">Module Details</h3>
      <div class="flex items-center gap-2">
        <button id="modalClose" class="text-slate-600 hover:text-slate-900 px-2 py-1">Close</button>
      </div>
    </div>

    <div class="p-4 max-h-[70vh] overflow-y-auto">
      <div class="mb-4">
        <h4 class="text-sm font-medium text-slate-700">Title</h4>
        <div id="modalTitleText" class="text-base text-slate-900"></div>
      </div>

      <div class="mb-4">
        <h4 class="text-sm font-medium text-slate-700">Objectives</h4>
        <div id="modalObjectives" class="text-sm text-slate-800 whitespace-pre-wrap"></div>
      </div>

      <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <h4 class="text-sm font-medium text-slate-700">Disaster Type</h4>
          <div id="modalType" class="text-sm text-slate-800"></div>
        </div>
        <div>
          <h4 class="text-sm font-medium text-slate-700">Created</h4>
          <div id="modalCreated" class="text-sm text-slate-800"></div>
        </div>
        <div>
          <h4 class="text-sm font-medium text-slate-700">File</h4>
          <div id="modalFile" class="text-sm text-slate-800"></div>
        </div>
      </div>

      <div id="modalPreviewWrapper" class="mt-3">
        <!-- dynamic preview injected here -->
      </div>
    </div>

    <div class="px-4 py-3 border-t flex justify-end gap-2">
      <a id="modalOpen" target="_blank" class="px-3 py-2 bg-sky-600 text-white rounded-md hidden">Open Full</a>
      <button id="modalClose2" class="px-3 py-2 bg-gray-100 rounded-md">Close</button>
    </div>
  </div>
</div>

<script>lucide.createIcons();</script>

<!-- Simple JS: toggle form + details modal -->
<script>
(function(){
  // Toggle form visibility for small screens
  const btn = document.getElementById('toggleFormBtn');
  const formCol = document.getElementById('formColumn');
  if (btn && formCol) {
    btn.addEventListener('click', () => {
      formCol.classList.toggle('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    if (!<?= $editData ? 'true' : 'false' ?> && window.innerWidth < 1024) {
      formCol.classList.add('hidden');
    }
  }

  // DETAILS MODAL
  const modal = document.getElementById('detailsModal');
  const modalTitle = document.getElementById('modalTitleText');
  const modalObjectives = document.getElementById('modalObjectives');
  const modalType = document.getElementById('modalType');
  const modalCreated = document.getElementById('modalCreated');
  const modalFile = document.getElementById('modalFile');
  const modalPreviewWrapper = document.getElementById('modalPreviewWrapper');
  const modalOpen = document.getElementById('modalOpen');
  const modalClose = document.getElementById('modalClose');
  const modalClose2 = document.getElementById('modalClose2');

  function openModal() { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
  function closeModal() { modal.classList.add('hidden'); document.body.style.overflow = ''; modalPreviewWrapper.innerHTML = ''; modalOpen.classList.add('hidden'); }

  modalClose.addEventListener('click', closeModal);
  modalClose2.addEventListener('click', closeModal);
  modal.addEventListener('click', (ev) => {
    if (ev.target === modal) closeModal();
  });

  // Attach to each details button
  document.querySelectorAll('.detailsBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const dataRaw = btn.getAttribute('data-info');
      if (!dataRaw) return;
      let info = {};
      try { info = JSON.parse(dataRaw); } catch(e) { console.error(e); }

      // populate modal
      document.getElementById('modalTitle').textContent = 'Module Details';
      modalTitle.textContent = info.title || '';
      modalObjectives.textContent = info.objectives || '';
      modalType.textContent = info.type || '';
      modalCreated.textContent = info.created || '';
      modalFile.textContent = info.filename || 'None';

      modalPreviewWrapper.innerHTML = ''; // reset

      if (info.file) {
        const ext = (info.ext || '').toLowerCase();
        const fileUrl = info.file;

        // show Open Full button
        modalOpen.href = fileUrl;
        modalOpen.classList.remove('hidden');

        if (['jpg','jpeg','png'].includes(ext)) {
          const img = document.createElement('img');
          img.src = fileUrl;
          img.alt = info.title || 'Image';
          img.className = 'max-w-full rounded-md shadow-sm';
          modalPreviewWrapper.appendChild(img);
        } else if (ext === 'pdf') {
          // embed PDF in iframe (if browser supports) and provide link
          const iframe = document.createElement('iframe');
          iframe.src = fileUrl;
          iframe.className = 'w-full h-[60vh] border rounded-md';
          modalPreviewWrapper.appendChild(iframe);
        } else if (['doc','docx'].includes(ext)) {
          // use Google docs viewer link for preview in new tab
          const viewLink = 'https://docs.google.com/viewer?url=' + encodeURIComponent("<?= $baseUrl ?>" + fileUrl) + '&embedded=true';
          const p = document.createElement('p');
          p.className = 'text-sm';
          p.innerHTML = 'Preview not available inline. <a target="_blank" href="'+ viewLink +'" class="text-sky-600 underline">Open in Google Docs Viewer</a>';
          modalPreviewWrapper.appendChild(p);
          // set Open Full to docs viewer
          modalOpen.href = viewLink;
        } else {
          const p = document.createElement('p');
          p.className = 'text-sm';
          p.innerHTML = 'No inline preview. <a href="'+ fileUrl +'" class="text-sky-600 underline" target="_blank">Download / Open</a>';
          modalPreviewWrapper.appendChild(p);
        }
      } else {
        modalOpen.classList.add('hidden');
        const p = document.createElement('p');
        p.className = 'text-sm text-slate-500';
        p.textContent = 'No file attached for this module.';
        modalPreviewWrapper.appendChild(p);
      }

      openModal();
    });
  });

})();
</script>

</body>
</html>
