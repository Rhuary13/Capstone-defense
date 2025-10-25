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
// Fetch drills with pagination + search
// ----------------------
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12; $offset = ($page-1)*$perPage;
$search = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($search !== ''){
    $where = "WHERE title LIKE ? OR details LIKE ?";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}
$countSql = "SELECT COUNT(*) AS c FROM drills " . $where;
$stmt = $conn->prepare($countSql);
if ($search !== '') $stmt->bind_param('ss', $params[0], $params[1]);
$stmt->execute(); $total = $stmt->get_result()->fetch_assoc()['c'] ?? 0; $stmt->close();
$pages = max(1, (int)ceil($total / $perPage));

$listSql = "SELECT * FROM drills " . $where . " ORDER BY `date` DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($listSql);
if ($search !== '') $stmt->bind_param('ssii', $params[0], $params[1], $perPage, $offset);
else $stmt->bind_param('ii', $perPage, $offset);
$stmt->execute(); $dr = $stmt->get_result();
$drills = $dr->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Drills — Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden}
    .main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
    .main-scroll{flex:1;overflow:auto;min-height:0;padding:1.5rem}
    .truncate-cell{max-width:18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
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
      <header class="bg-white border-b h-16 flex items-center justify-between px-6">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">Educational Drills</h1>
          <div class="text-sm text-slate-500">Create, manage and schedule drills</div>
        </div>
        <div class="flex items-center gap-3">
          <a href="?export=csv" class="px-3 py-2 bg-gray-100 rounded text-sm">Export CSV</a>
          <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'User') ?></strong></div>
        </div>
      </header>

      <main class="main-scroll" role="main">
        <div class="max-w-7xl mx-auto">

          <!-- Alerts -->
          <?php if (!empty($success)): ?>
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">✅ <?= esc($success) ?></div>
          <?php endif; ?>
          <?php if (!empty($errors)): ?>
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-100"><strong>Errors:</strong><ul class="mt-1"><?php foreach($errors as $er) echo '<li>'.esc($er).'</li>'; ?></ul></div>
          <?php endif; ?>
          <?php if (isset($_GET['updated'])): ?><div class="mb-4 p-3 rounded-lg bg-blue-50 text-blue-800">Drill updated successfully.</div><?php endif; ?>
          <?php if (isset($_GET['deleted'])): ?><div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800">Drill deleted.</div><?php endif; ?>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form -->
            <section class="bg-white p-6 rounded-2xl shadow">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                  <div class="p-2 rounded bg-indigo-50"><i data-lucide="plus-circle" class="w-5 h-5 text-indigo-600"></i></div>
                  <div>
                    <h2 class="text-lg font-semibold"><?= $edit_mode ? 'Edit Drill' : 'Add Drill' ?></h2>
                    <div class="text-sm text-slate-500">Create or modify drill schedules</div>
                  </div>
                </div>
                <?php if ($edit_mode): ?><a href="module1.3.php" class="text-sm text-slate-500">New</a><?php endif; ?>
              </div>

              <form method="POST" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
                <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?= (int)$edit_drill['id'] ?>"><?php endif; ?>

                <div>
                  <label class="text-sm font-medium">Title</label>
                  <input name="title" required value="<?= esc($edit_drill['title'] ?? '') ?>" class="mt-1 w-full px-3 py-2 border rounded-lg" />
                </div>

                <div>
                  <label class="text-sm font-medium">Date</label>
                  <input type="date" name="date" required value="<?= esc($edit_drill['date'] ?? '') ?>" class="mt-1 px-3 py-2 border rounded-lg w-48" />
                </div>

                <div>
                  <label class="text-sm font-medium">Details</label>
                  <textarea name="details" rows="4" class="mt-1 w-full px-3 py-2 border rounded-lg"><?= esc($edit_drill['details'] ?? '') ?></textarea>
                </div>

                <div>
                  <label class="text-sm font-medium">Drill Type</label>
                  <select name="type" class="mt-1 px-3 py-2 border rounded-lg">
                    <?php $types = ['Drill','Simulation','Tabletop','Workshop']; foreach($types as $t): ?>
                      <option value="<?= esc($t) ?>" <?= isset($edit_drill['type']) && $edit_drill['type']==$t ? 'selected' : '' ?>><?= esc($t) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label class="text-sm font-medium">Attach File (optional)</label>
                  <input type="file" name="file" class="mt-1" />
                  <?php if (!empty($edit_drill['file_path'])): ?>
                    <div class="text-sm text-slate-500 mt-1">Current file: <a href="<?= esc($edit_drill['file_path']) ?>" target="_blank" class="text-indigo-600">Open</a></div>
                  <?php endif; ?>
                </div>

                <div class="flex gap-2 justify-end">
                  <?php if ($edit_mode): ?>
                    <a href="module1.3.php" class="px-4 py-2 bg-gray-100 rounded-lg">Cancel</a>
                    <button type="submit" name="update_drill" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Update Drill</button>
                  <?php else: ?>
                    <button type="submit" name="add_drill" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Save Drill</button>
                  <?php endif; ?>
                </div>
              </form>

              <div class="mt-4 text-sm text-slate-500">Tip: attach a short agenda or diagram to help participants prepare.</div>
            </section>

            <!-- Drill list -->
            <section class="lg:col-span-2 bg-white p-6 rounded-2xl shadow">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                  <h2 class="text-lg font-semibold">Drill List</h2>
                  <div class="text-sm text-slate-500">Total: <?= (int)$total ?></div>
                </div>
                <div class="flex items-center gap-2">
                  <input id="q" name="q" placeholder="Search title or details" value="<?= esc($search) ?>" class="px-3 py-2 border rounded-lg" oninput="applySearch(this.value)">
                  <a href="?export=csv" class="px-3 py-2 bg-gray-100 rounded text-sm">Export CSV</a>
                </div>
              </div>

              <div class="overflow-auto rounded-lg border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
                    <tr>
                      <th class="px-3 py-2 text-left">Title</th>
                      <th class="px-3 py-2 text-left">Details</th>
                      <th class="px-3 py-2 text-left">Type</th>
                      <th class="px-3 py-2 text-left">File</th>
                      <th class="px-3 py-2 text-left">Date</th>
                      <th class="px-3 py-2 text-left">Created</th>
                      <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="drillTable" class="divide-y">
                    <?php if (count($drills)): foreach ($drills as $d): ?>
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-medium truncate-cell" title="<?= esc($d['title']) ?>"><?= esc($d['title']) ?></td>
                        <td class="px-3 py-2 truncate-cell" title="<?= esc($d['details']) ?>"><?= esc($d['details']) ?></td>
                        <td class="px-3 py-2"><?= esc($d['type']) ?></td>
                        <td class="px-3 py-2"><?php if (!empty($d['file_path'])): ?><a href="<?= esc($d['file_path']) ?>" target="_blank" class="text-indigo-600">Open</a><?php else: ?>—<?php endif; ?></td>
                        <td class="px-3 py-2"><?= esc($d['date']) ?></td>
                        <td class="px-3 py-2"><?= esc($d['created_at']) ?></td>
                        <td class="px-3 py-2 flex gap-3">
                          <a href="?edit=<?= (int)$d['id'] ?>" class="text-indigo-600">Edit</a>
                          <a href="#" onclick="confirmDelete(<?= (int)$d['id'] ?>)" class="text-red-600">Delete</a>
                        </td>
                      </tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="7" class="p-4 text-center text-slate-500">No drills found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div class="mt-4 flex items-center justify-end gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="px-3 py-1 bg-gray-100 rounded">Prev</a><?php endif; ?>
                <div class="text-sm text-slate-500">Page <?= $page ?> / <?= $pages ?></div>
                <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="px-3 py-1 bg-gray-100 rounded">Next</a><?php endif; ?>
              </div>
            </section>

          </div>
        </div>
      </main>
    </div>
  </div>

<script>
  lucide.createIcons();
  function confirmDelete(id){
    if (confirm('Delete this drill? This action cannot be undone.')){
      window.location.href = '?delete='+id;
    }
  }
  // debounce for search
  let searchTimer = null;
  function applySearch(q){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(()=>{ const url = new URL(window.location.href); url.searchParams.set('q', q); url.searchParams.set('page', 1); window.location.href = url.toString(); }, 450);
  }
</script>
</body>
</html>