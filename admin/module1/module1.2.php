<?php
session_start();
// Database connection
$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "training_management"; // <-- use your actual DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Security: require login
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// -------- Export CSV (simple) --------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=training_programs.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','title','definition','scope','goal','format','example','created_at']);
    $res = $conn->query("SELECT id,title,definition,scope,goal,format,example,created_at FROM training_programs ORDER BY created_at DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['id'],$r['title'],$r['definition'],$r['scope'],$r['goal'],$r['format'],$r['example'],$r['created_at']]);
        }
    }
    fclose($out);
    exit;
}

// ---------- Helpers ----------
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ---------- Handle POSTs (Create / Update) with prepared statements ----------
$postErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // create
    if (isset($_POST['add_program'])) {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') $postErrors[] = 'Title is required.';
        if (empty($postErrors)) {
            $stmt = $conn->prepare("INSERT INTO training_programs (title, definition, scope, goal, format, example, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('ssssss', $_POST['title'], $_POST['definition'], $_POST['scope'], $_POST['goal'], $_POST['format'], $_POST['example']);
            $stmt->execute();
            $stmt->close();
            header('Location: program.php?success=1');
            exit;
        }
    }

    // update
    if (isset($_POST['update_program'])) {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if ($id <= 0) $postErrors[] = 'Invalid program id.';
        if ($title === '') $postErrors[] = 'Title is required.';
        if (empty($postErrors)) {
            $stmt = $conn->prepare("UPDATE training_programs SET title=?, definition=?, scope=?, goal=?, format=?, example=? WHERE id=?");
            $stmt->bind_param('ssssssi', $_POST['title'], $_POST['definition'], $_POST['scope'], $_POST['goal'], $_POST['format'], $_POST['example'], $id);
            $stmt->execute();
            $stmt->close();
            header('Location: program.php?updated=1');
            exit;
        }
    }
}

// ---------- Delete via GET (confirm client-side) ----------
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM training_programs WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: program.php?deleted=1');
    exit;
}

// ---------- Fetch (for edit) ----------
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM training_programs WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editData = $result->fetch_assoc();
    $stmt->close();
}

// ---------- Fetch all programs (with simple pagination) ----------
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$totalR = $conn->query("SELECT COUNT(*) AS c FROM training_programs");
$total = 0;
if ($totalR) { $total = (int)$totalR->fetch_assoc()['c']; }
$pages = max(1, (int)ceil($total / $perPage));
$stmt = $conn->prepare("SELECT id,title,definition,scope,goal,format,example,created_at FROM training_programs ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$programs = $stmt->get_result();

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Training Programs — Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x" defer></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden}
    .main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
    .main-scroll{flex:1;overflow:auto;min-height:0;padding:1.5rem}
    /* subtle table wrapping */
    .table-cell-truncate{max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  </style>
</head>
<body class="bg-slate-50 font-sans">

  <div class="app">
    <!-- Sidebar include if exists -->
    <?php if (file_exists(__DIR__ . '/../sidebar.php')): ?>
      <?php include __DIR__ . '/../sidebar.php'; ?>
    <?php else: ?>
      <aside style="width:16rem;background:#fff;border-right:1px solid #edf2f7;padding:1rem;"> 
        <div class="font-bold text-lg">Admin</div>
      </aside>
    <?php endif; ?>

    <div class="main-wrap">
      <header class="bg-white border-b h-16 flex items-center justify-between px-6">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">Training Programs</h1>
          <div class="text-sm text-slate-500">Manage training programs and curriculum</div>
        </div>
        <div class="flex items-center gap-3">
          <a href="?export=csv" class="px-3 py-2 bg-gray-100 rounded text-sm">Export CSV</a>
          <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'User') ?></strong></div>
        </div>
      </header>

      <main class="main-scroll" role="main">
        <div class="max-w-6xl mx-auto">
          <?php if (!empty($postErrors)): ?>
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-100">
              <strong>Errors:</strong>
              <ul class="mt-1">
                <?php foreach ($postErrors as $pe): ?><li><?= esc($pe) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form panel -->
            <section class="bg-white p-6 rounded-2xl shadow">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold"><?= $editData ? 'Edit Program' : 'Add Program' ?></h2>
                <?php if ($editData): ?>
                  <a href="program.php" class="text-sm text-slate-500">New</a>
                <?php endif; ?>
              </div>

              <form method="POST" class="space-y-3">
                <input type="hidden" name="id" value="<?= esc($editData['id'] ?? '') ?>">

                <div>
                  <label class="text-sm font-medium">Title</label>
                  <input name="title" required value="<?= esc($editData['title'] ?? '') ?>" class="mt-1 w-full px-3 py-2 border rounded-lg" />
                </div>

                <div>
                  <label class="text-sm font-medium">Definition</label>
                  <textarea name="definition" rows="3" class="mt-1 w-full px-3 py-2 border rounded-lg"><?= esc($editData['definition'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-medium">Scope</label>
                    <textarea name="scope" rows="2" class="mt-1 w-full px-3 py-2 border rounded-lg"><?= esc($editData['scope'] ?? '') ?></textarea>
                  </div>
                  <div>
                    <label class="text-sm font-medium">Goal</label>
                    <textarea name="goal" rows="2" class="mt-1 w-full px-3 py-2 border rounded-lg"><?= esc($editData['goal'] ?? '') ?></textarea>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-medium">Format</label>
                    <input name="format" value="<?= esc($editData['format'] ?? '') ?>" class="mt-1 w-full px-3 py-2 border rounded-lg" />
                  </div>
                  <div>
                    <label class="text-sm font-medium">Example</label>
                    <input name="example" value="<?= esc($editData['example'] ?? '') ?>" class="mt-1 w-full px-3 py-2 border rounded-lg" />
                  </div>
                </div>

                <div class="flex gap-2 justify-end">
                  <?php if ($editData): ?>
                    <button type="submit" name="update_program" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Update Program</button>
                    <a href="program.php" class="px-4 py-2 bg-gray-100 rounded-lg">Cancel</a>
                  <?php else: ?>
                    <button type="submit" name="add_program" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Save Program</button>
                  <?php endif; ?>
                </div>
              </form>

              <div class="mt-6 text-sm text-slate-500">
                <strong>Tip:</strong> Use concise titles and a short definition to keep lists readable.
              </div>
            </section>

            <!-- Programs list (spans 2 columns on large screens) -->
            <section class="lg:col-span-2 bg-white p-6 rounded-2xl shadow">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Existing Programs</h2>
                <div class="flex items-center gap-3">
                  <input id="search" placeholder="Search title..." class="px-3 py-2 border rounded-lg text-sm" oninput="filterTable(this.value)">
                  <label class="text-sm text-slate-500">Showing <?= $programs->num_rows ?> of <?= $total ?></label>
                </div>
              </div>

              <div class="overflow-auto rounded-lg border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
                    <tr>
                      <th class="px-3 py-2 text-left">Title</th>
                      <th class="px-3 py-2 text-left">Definition</th>
                      <th class="px-3 py-2 text-left">Scope</th>
                      <th class="px-3 py-2 text-left">Goal</th>
                      <th class="px-3 py-2 text-left">Format</th>
                      <th class="px-3 py-2 text-left">Created</th>
                      <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="programTable" class="divide-y">
                    <?php while ($row = $programs->fetch_assoc()): ?>
                      <tr>
                        <td class="px-3 py-2 table-cell-truncate" title="<?= esc($row['title']) ?>"><?= esc($row['title']) ?></td>
                        <td class="px-3 py-2 table-cell-truncate" title="<?= esc($row['definition']) ?>"><?= esc($row['definition']) ?></td>
                        <td class="px-3 py-2 table-cell-truncate" title="<?= esc($row['scope']) ?>"><?= esc($row['scope']) ?></td>
                        <td class="px-3 py-2 table-cell-truncate" title="<?= esc($row['goal']) ?>"><?= esc($row['goal']) ?></td>
                        <td class="px-3 py-2 table-cell-truncate" title="<?= esc($row['format']) ?>"><?= esc($row['format']) ?></td>
                        <td class="px-3 py-2"><?= esc($row['created_at']) ?></td>
                        <td class="px-3 py-2 flex gap-3">
                          <a href="?edit=<?= (int)$row['id'] ?>" class="text-indigo-600 hover:underline">Edit</a>
                          <a href="#" onclick="confirmDelete(<?= (int)$row['id'] ?>)" class="text-red-600 hover:underline">Delete</a>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div class="mt-4 flex items-center justify-end gap-2">
                <?php if ($page > 1): ?>
                  <a href="?page=<?= $page-1 ?>" class="px-3 py-1 bg-gray-100 rounded">Prev</a>
                <?php endif; ?>
                <div class="text-sm text-slate-500">Page <?= $page ?> / <?= $pages ?></div>
                <?php if ($page < $pages): ?>
                  <a href="?page=<?= $page+1 ?>" class="px-3 py-1 bg-gray-100 rounded">Next</a>
                <?php endif; ?>
              </div>

            </section>

          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Delete confirmation modal (Alpine) -->
  <div x-data="{}" x-init="() => {}">
    <template x-teleport="body">
      <div id="confirmDelete" x-show="false" style="display:none"></div>
    </template>
  </div>

  <script>
    lucide.createIcons();
    function confirmDelete(id){
      if (confirm('Delete this program? This action cannot be undone.')){
        window.location.href = '?delete='+id;
      }
    }

    // client-side simple filter (title + definition)
    function filterTable(q){
      q = q.trim().toLowerCase();
      const rows = document.querySelectorAll('#programTable tr');
      rows.forEach(r=>{
        const title = r.children[0].innerText.toLowerCase();
        const def = r.children[1].innerText.toLowerCase();
        if (!q || title.includes(q) || def.includes(q)) r.style.display = '';
        else r.style.display = 'none';
      });
    }
  </script>
</body>
</html>
