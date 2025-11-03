<?php
// module1.2_topic_categorization.php
// Topic Categorization — Admin (single file)
// Database: training_management

session_start();

/* ---------------------------
   CONFIG
   --------------------------- */
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'training_management';

/* ---------------------------
   Require admin
   --------------------------- */
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

/* ---------------------------
   DB CONNECT
   --------------------------- */
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('DB connect error: ' . htmlspecialchars($conn->connect_error));
}
$conn->set_charset('utf8mb4');

/* ---------------------------
   CSRF token
   --------------------------- */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

/* ---------------------------
   Ensure required tables exist
   --------------------------- */
$conn->query("
CREATE TABLE IF NOT EXISTS `topic_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL UNIQUE,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `visibility` ENUM('public','private') DEFAULT 'public',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS `module_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_table` VARCHAR(100) NOT NULL,
  `module_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `assigned_by` INT DEFAULT NULL,
  `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_module_cat (module_table, module_id, category_id),
  CONSTRAINT fk_module_categories_category FOREIGN KEY (category_id) REFERENCES topic_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ---------------------------
   Add optional category_id columns (non-destructive)
   --------------------------- */
function ensure_column($conn, $table, $column_sql, $column_name) {
    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    if (!$res || $res->num_rows === 0) return;
    $safeColumn = $conn->real_escape_string($column_name);
    $colRes = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN {$column_sql}");
    }
}
ensure_column($conn, 'training_modules', "category_id INT DEFAULT NULL", 'category_id');
ensure_column($conn, 'lessons', "category_id INT DEFAULT NULL", 'category_id');

/* ---------------------------
   Helpers
   --------------------------- */
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function slugify($s){
    $s = preg_replace('/[^\p{L}\p{N}\-]+/u','-', mb_strtolower(trim($s)));
    $s = preg_replace('/-+/','-',$s);
    return trim($s,'-');
}

/* ---------------------------
   AJAX endpoint handlers
   --------------------------- */
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['action'];

    // enforce CSRF for POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !hash_equals($CSRF, $_POST['csrf_token']))) {
        echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']);
        exit;
    }

    // create_category
    if ($action === 'create_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $visibility = in_array($_POST['visibility'] ?? 'public', ['public','private']) ? $_POST['visibility'] : 'public';
        if ($name === '') { echo json_encode(['success'=>false,'error'=>'Name required']); exit; }
        $slug = slugify($name);
        $base = $slug; $i = 1;
        while (true) {
            $stmt = $conn->prepare("SELECT id FROM topic_categories WHERE slug = ? LIMIT 1");
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r->num_rows === 0) { $stmt->close(); break; }
            $stmt->close();
            $slug = $base . '-' . $i; $i++;
        }
        $stmt = $conn->prepare("INSERT INTO topic_categories (name, slug, description, visibility, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $user = (int)($_SESSION['id'] ?? 0);
        $stmt->bind_param('sssis', $name, $slug, $desc, $visibility, $user);
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success'=>(bool)$ok,'id'=>$id]); exit;
    }

    // update_category
    if ($action === 'update_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $visibility = in_array($_POST['visibility'] ?? 'public', ['public','private']) ? $_POST['visibility'] : 'public';
        if ($id <= 0 || $name === '') { echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }
        $slug = slugify($name);
        $base = $slug; $i = 1;
        while (true) {
            $stmt = $conn->prepare("SELECT id FROM topic_categories WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->bind_param('si', $slug, $id);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r->num_rows === 0) { $stmt->close(); break; }
            $stmt->close();
            $slug = $base . '-' . $i; $i++;
        }
        $stmt = $conn->prepare("UPDATE topic_categories SET name=?, slug=?, description=?, visibility=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ssssi', $name, $slug, $desc, $visibility, $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>(bool)$ok]); exit;
    }

    // delete_category
    if ($action === 'delete_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }
        $stmt = $conn->prepare("DELETE FROM topic_categories WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>(bool)$ok]); exit;
    }

    // list_categories
    if ($action === 'list_categories') {
        $res = $conn->query("SELECT id,name,slug,description,visibility,created_at,updated_at,
            (SELECT COUNT(1) FROM module_categories mc WHERE mc.category_id = topic_categories.id) AS assigned_count
            FROM topic_categories ORDER BY name ASC");
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }

    // list_modules (for assign modal) - prefer training_modules else lessons
    if ($action === 'list_modules') {
        $modules = [];
        $hasTraining = $conn->query("SHOW TABLES LIKE 'training_modules'")->num_rows ?? 0;
        if ($hasTraining) {
            $res = $conn->query("SELECT id, title, disaster_type, created_at FROM training_modules ORDER BY id DESC LIMIT 1000");
            while ($r = $res->fetch_assoc()) { $r['module_table'] = 'training_modules'; $modules[] = $r; }
        }
        $hasLessons = $conn->query("SHOW TABLES LIKE 'lessons'")->num_rows ?? 0;
        if ($hasLessons) {
            $res = $conn->query("SELECT id, title, disaster_type, created_at FROM lessons ORDER BY id DESC LIMIT 1000");
            while ($r = $res->fetch_assoc()) { $r['module_table'] = 'lessons'; $modules[] = $r; }
        }
        echo json_encode(['success'=>true,'data'=>$modules]); exit;
    }

    // assign_modules (POST)
    if ($action === 'assign_modules' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $module_table = $_POST['module_table'] ?? '';
        $module_ids = $_POST['module_ids'] ?? [];
        if (!is_array($module_ids)) $module_ids = [$module_ids];
        if ($category_id <=0 || !$module_table || empty($module_ids)) { echo json_encode(['success'=>false,'error'=>'Bad input']); exit; }
        $user = (int)($_SESSION['id'] ?? 0);
        $stmt = $conn->prepare("INSERT IGNORE INTO module_categories (module_table, module_id, category_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($module_ids as $mid) {
            $mid = (int)$mid; if ($mid <= 0) continue;
            $stmt->bind_param('siii', $module_table, $mid, $category_id, $user);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['success'=>true]); exit;
    }

    // unassign_module (POST)
    if ($action === 'unassign_module' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Bad id']); exit; }
        $stmt = $conn->prepare("DELETE FROM module_categories WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>(bool)$ok]); exit;
    }

    // get_assigned (GET)
    if ($action === 'get_assigned') {
        $category_id = (int)($_GET['category_id'] ?? 0);
        if ($category_id <= 0) { echo json_encode(['success'=>false,'error'=>'Bad id']); exit; }
        $stmt = $conn->prepare("
            SELECT mc.id as mapping_id, mc.module_table, mc.module_id, COALESCE(m.title, l.title) AS title, COALESCE(m.disaster_type, l.disaster_type) AS disaster_type, mc.assigned_at
            FROM module_categories mc
            LEFT JOIN training_modules m ON (mc.module_table='training_modules' AND mc.module_id = m.id)
            LEFT JOIN lessons l ON (mc.module_table='lessons' AND mc.module_id = l.id)
            WHERE mc.category_id = ?
            ORDER BY mc.assigned_at DESC
        ");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

/* ---------------------------
   Page render: initial data
   --------------------------- */
$totCat = intval($conn->query("SELECT COUNT(*) AS c FROM topic_categories")->fetch_assoc()['c'] ?? 0);

$catRes = $conn->query("
    SELECT c.id, c.name, c.slug, c.visibility, c.created_at, c.description,
      (SELECT COUNT(1) FROM module_categories mc WHERE mc.category_id = c.id) AS assigned_count
    FROM topic_categories c
    ORDER BY c.name ASC
");

$cntModules = 0;
if ($conn->query("SHOW TABLES LIKE 'training_modules'")->num_rows) {
    $cntModules = intval($conn->query("SELECT COUNT(*) AS c FROM training_modules")->fetch_assoc()['c'] ?? 0);
} elseif ($conn->query("SHOW TABLES LIKE 'lessons'")->num_rows) {
    $cntModules = intval($conn->query("SELECT COUNT(*) AS c FROM lessons")->fetch_assoc()['c'] ?? 0);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Topic Categorization — Admin</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide icons + Alpine (for small interactions if needed) -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>

  <style>
    /* main independent scrolling */
    html,body{height:100%}
    .app-root{display:flex;height:100vh;min-height:0}
    .main-panel{flex:1;display:flex;flex-direction:column;min-height:0}
    header.app-header{height:4rem;min-height:4rem}
    main.scrollable{flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:1.5rem}
    .truncate-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    /* accessible focus */
    :focus { outline: 3px solid rgba(56,189,248,0.15); outline-offset: 2px; }
  </style>
</head>
<body class="bg-slate-50 text-slate-800">

  <div class="app-root">
    <!-- Sidebar (include) -->
    <?php include '../sidebar.php'; ?>

    <!-- Main panel -->
    <div class="main-panel w-full">
      <!-- Header (sticky within main panel) -->
      <header class="app-header bg-white border-b border-slate-200 flex items-center px-6 sticky top-0 z-30">
        <div class="flex-1">
          <h1 class="text-lg font-semibold">Topic Categorization</h1>
          <p class="text-sm text-slate-500">Group lessons and modules by disaster type or training level for easy navigation.</p>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-sm text-slate-600">Role: Admin</div>
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-600 to-sky-500 text-white flex items-center justify-center font-semibold">
            <?= esc(strtoupper(substr($_SESSION['name'] ?? ($_SESSION['username'] ?? 'AD'),0,2))) ?>
          </div>
        </div>
      </header>

      <!-- Independent scrollable main -->
      <main class="scrollable">
        <div class="max-w-7xl mx-auto">

          <!-- Controls -->
          <div class="mb-6 grid grid-cols-1 lg:grid-cols-3 gap-4 items-center">
            <div class="col-span-2">
              <div class="flex gap-3 items-center">
                <input id="searchInput" type="search" placeholder="Search categories or modules..." class="w-full md:w-96 px-3 py-2 border rounded-md bg-white text-sm" />
                <select id="visibilityFilter" class="px-3 py-2 border rounded-md bg-white text-sm">
                  <option value="">All visibility</option>
                  <option value="public">Public</option>
                  <option value="private">Private</option>
                </select>
                <button id="searchBtn" class="px-4 py-2 bg-sky-600 text-white rounded-md">Search</button>
                <div class="text-sm text-slate-500 ml-4">Categories: <strong id="catCount"><?= $totCat ?></strong> • Modules: <strong id="modCount"><?= $cntModules ?></strong></div>
              </div>
            </div>

            <div class="flex justify-end">
              <button id="openCreateBtn" class="px-4 py-2 bg-emerald-600 text-white rounded-md flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Category
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Create / Edit Form -->
            <section class="lg:col-span-1">
              <div class="bg-white p-6 rounded-2xl shadow-sm border">
                <div class="flex items-center justify-between mb-4">
                  <h2 id="formTitle" class="text-lg font-medium text-slate-800">Create Category</h2>
                </div>

                <form id="categoryForm" class="space-y-3">
                  <input type="hidden" name="csrf_token" value="<?= esc($CSRF) ?>">
                  <input type="hidden" id="catId" name="id" value="">
                  <div>
                    <label class="block text-sm font-medium">Name</label>
                    <input id="catName" name="name" required class="w-full px-3 py-2 border rounded-md bg-white text-sm" placeholder="e.g. Flood Response — Level 1" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium">Visibility</label>
                    <select id="catVisibility" name="visibility" class="w-full px-3 py-2 border rounded-md text-sm">
                      <option value="public">Public</option>
                      <option value="private">Private</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium">Description (optional)</label>
                    <textarea id="catDesc" name="description" rows="3" class="w-full px-3 py-2 border rounded-md text-sm" placeholder="Short description to show on category cards."></textarea>
                  </div>

                  <div class="flex items-center gap-3">
                    <button id="saveCatBtn" type="submit" class="px-4 py-2 bg-sky-600 text-white rounded-md">Save Category</button>
                    <button id="cancelEditBtn" type="button" class="px-3 py-2 bg-gray-100 rounded-md hidden">Cancel Edit</button>
                  </div>

                  <div class="mt-3 text-xs text-slate-500">
                    Tip: Include level or phase in the name (e.g., "Earthquake — Awareness") to make categories meaningful to learners.
                  </div>
                </form>
              </div>
            </section>

            <!-- Right: Categories Grid -->
            <section class="lg:col-span-2">
              <div class="bg-white p-4 rounded-2xl shadow-sm border">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h3 class="text-lg font-medium text-slate-800">Categories</h3>
                    <p class="text-sm text-slate-500">Manage topic categories, assign multiple modules, and update classifications.</p>
                  </div>
                  <div class="flex items-center gap-3">
                    <button id="refreshBtn" class="px-3 py-2 border rounded-md text-sm">Refresh</button>
                  </div>
                </div>

                <div id="categoriesGrid" class="grid gap-4">
                  <?php while ($c = $catRes->fetch_assoc()): ?>
                    <div class="p-4 border rounded-lg bg-white flex items-start justify-between">
                      <div class="w-2/3">
                        <div class="flex items-center gap-3">
                          <div class="w-12 h-12 rounded-md bg-slate-100 flex items-center justify-center text-slate-700 font-semibold text-lg"><?= esc(mb_substr($c['name'],0,2)) ?></div>
                          <div>
                            <div class="text-md font-semibold"><?= esc($c['name']) ?></div>
                            <div class="text-xs text-slate-500"><?= esc($c['slug']) ?> • <?= esc($c['visibility']) ?></div>
                          </div>
                        </div>
                        <div class="mt-3 text-sm text-slate-700 truncate-2"><?= esc($c['description'] ?? '') ?></div>
                      </div>

                      <div class="w-1/3 text-right flex flex-col items-end gap-2">
                        <div class="text-sm text-slate-500">Assigned: <strong><?= (int)$c['assigned_count'] ?></strong></div>

                        <div class="flex gap-2">
                          <button class="assignBtn px-3 py-1 text-sm bg-sky-600 text-white rounded-md" data-id="<?= (int)$c['id'] ?>">Assign</button>
                          <button class="editBtn px-3 py-1 text-sm border rounded-md" data-id="<?= (int)$c['id'] ?>" data-name="<?= esc($c['name']) ?>" data-desc="<?= esc($c['description']) ?>" data-vis="<?= esc($c['visibility']) ?>">Edit</button>
                          <button class="delBtn px-3 py-1 text-sm bg-rose-50 text-rose-700 rounded-md" data-id="<?= (int)$c['id'] ?>">Delete</button>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                </div>

                <div class="mt-4 flex items-center justify-between">
                  <div class="text-sm text-slate-500">Tip: Use the Assign button to map modules to categories for easy filtering on learner-facing pages.</div>
                  <div class="text-sm text-slate-500">Showing <span id="visibleCats"><?= $totCat ?></span> categories</div>
                </div>
              </div>
            </section>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Assign Modal -->
  <div id="assignModal" class="fixed inset-0 hidden items-center justify-center z-50 px-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeAssignModal()"></div>
    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl z-10 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h3 id="assignTitle" class="text-lg font-semibold">Assign Modules</h3>
        <button onclick="closeAssignModal()" class="text-slate-600">Close</button>
      </div>
      <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-medium">Search Modules</label>
          <input id="moduleSearch" class="w-full px-3 py-2 border rounded-md" placeholder="Filter modules by title or type..." />
          <div class="mt-3 text-sm text-slate-500">Select modules and click "Assign selected".</div>
          <div class="mt-3 overflow-auto max-h-72 border rounded-md p-2" id="modulesList">
            <div class="text-sm text-slate-500">Loading modules...</div>
          </div>
        </div>

        <div>
          <label class="text-sm font-medium">Selected for Category</label>
          <div class="mt-2 p-3 border rounded-md min-h-[6rem]" id="selectedList">
            <div class="text-sm text-slate-500">No modules selected.</div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button class="px-3 py-2 bg-gray-100 rounded-md" onclick="clearSelection()">Clear</button>
            <button id="assignSelectedBtn" class="px-4 py-2 bg-emerald-600 text-white rounded-md">Assign selected</button>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
  lucide.createIcons();

  const API = location.pathname;
  const CSRF = '<?= esc($CSRF) ?>';

  document.addEventListener('DOMContentLoaded', () => {
    // elements
    const form = document.getElementById('categoryForm');
    const catId = document.getElementById('catId');
    const catName = document.getElementById('catName');
    const catDesc = document.getElementById('catDesc');
    const catVisibility = document.getElementById('catVisibility');
    const saveBtn = document.getElementById('saveCatBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const openCreateBtn = document.getElementById('openCreateBtn');
    const categoriesGrid = document.getElementById('categoriesGrid');
    const refreshBtn = document.getElementById('refreshBtn');

    // create/update category
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      saveBtn.disabled = true;
      const id = catId.value ? catId.value : '';
      const action = id ? 'update_category' : 'create_category';
      const fd = new FormData();
      fd.append('action', action);
      fd.append('csrf_token', CSRF);
      if (id) fd.append('id', id);
      fd.append('name', catName.value.trim());
      fd.append('description', catDesc.value.trim());
      fd.append('visibility', catVisibility.value);
      try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
          resetForm();
          await loadCategories();
          alert('Saved.');
        } else {
          alert(json.error || 'Save failed.');
        }
      } catch (err) {
        alert('Request failed.');
      } finally {
        saveBtn.disabled = false;
      }
    });

    // delegate edit/delete/assign
    categoriesGrid.addEventListener('click', (ev) => {
      const btn = ev.target.closest('button, a');
      if (!btn) return;
      if (btn.classList.contains('editBtn')) {
        catId.value = btn.dataset.id;
        catName.value = btn.dataset.name || '';
        catDesc.value = btn.dataset.desc || '';
        catVisibility.value = btn.dataset.vis || 'public';
        document.getElementById('formTitle').textContent = 'Edit Category';
        cancelEditBtn.classList.remove('hidden');
        saveBtn.textContent = 'Update Category';
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else if (btn.classList.contains('delBtn')) {
        const id = btn.dataset.id;
        if (!confirm('Delete this category? This will remove assignments but not modules.')) return;
        const fd = new FormData(); fd.append('action','delete_category'); fd.append('csrf_token', CSRF); fd.append('id', id);
        fetch(API, { method:'POST', body: fd }).then(r=>r.json()).then(j=>{ if (j.success) loadCategories(); else alert(j.error||'Delete failed'); });
      } else if (btn.classList.contains('assignBtn')) {
        const id = btn.dataset.id;
        openAssignModal(id);
      }
    });

    cancelEditBtn.addEventListener('click', (e) => { e.preventDefault(); resetForm(); });

    openCreateBtn.addEventListener('click', (e) => { resetForm(); window.scrollTo({ top: 0, behavior: 'smooth' }); });

    refreshBtn.addEventListener('click', () => loadCategories());
    document.getElementById('searchBtn').addEventListener('click', () => loadCategories());

    // load categories (AJAX)
    async function loadCategories() {
      const q = document.getElementById('searchInput').value.trim().toLowerCase();
      const vis = document.getElementById('visibilityFilter').value;
      const r = await fetch(API + '?action=list_categories');
      const j = await r.json();
      if (!j.success) { alert('Failed to load categories'); return; }
      const data = j.data || [];
      const grid = document.getElementById('categoriesGrid');
      grid.innerHTML = '';
      const filtered = data.filter(c => {
        const s = (c.name + ' ' + (c.description||'') + ' ' + (c.visibility||'')).toLowerCase();
        if (q && !s.includes(q)) return false;
        if (vis && c.visibility !== vis) return false;
        return true;
      });
      document.getElementById('visibleCats').textContent = filtered.length;
      filtered.forEach(c => {
        const node = document.createElement('div');
        node.className = 'p-4 border rounded-lg bg-white flex items-start justify-between';
        node.innerHTML = `
          <div class="w-2/3">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-md bg-slate-100 flex items-center justify-center text-slate-700 font-semibold text-lg">${escapeHtml(c.name.slice(0,2))}</div>
              <div>
                <div class="text-md font-semibold">${escapeHtml(c.name)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(c.slug)} • ${escapeHtml(c.visibility)}</div>
              </div>
            </div>
            <div class="mt-3 text-sm text-slate-700 truncate-2">${escapeHtml(c.description || '')}</div>
          </div>
          <div class="w-1/3 text-right flex flex-col items-end gap-2">
            <div class="text-sm text-slate-500">Assigned: <strong>${escapeHtml(c.assigned_count || 0)}</strong></div>
            <div class="flex gap-2">
              <button class="assignBtn px-3 py-1 text-sm bg-sky-600 text-white rounded-md" data-id="${c.id}">Assign</button>
              <button class="editBtn px-3 py-1 text-sm border rounded-md" data-id="${c.id}" data-name="${escapeHtml(c.name)}" data-desc="${escapeHtml(c.description)}" data-vis="${escapeHtml(c.visibility)}">Edit</button>
              <button class="delBtn px-3 py-1 text-sm bg-rose-50 text-rose-700 rounded-md" data-id="${c.id}">Delete</button>
            </div>
          </div>`;
        grid.appendChild(node);
      });
    }

    function resetForm(){
      catId.value = '';
      catName.value = '';
      catDesc.value = '';
      catVisibility.value = 'public';
      document.getElementById('formTitle').textContent = 'Create Category';
      cancelEditBtn.classList.add('hidden');
      saveBtn.textContent = 'Save Category';
    }

    function escapeHtml(s=''){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    /* -------- Assign modal logic -------- */
    let currentCategoryForAssign = null;
    let modulesCache = [];
    let selectedModules = new Map();

    async function openAssignModal(categoryId){
      currentCategoryForAssign = categoryId;
      document.getElementById('assignTitle').textContent = 'Assign Modules — Category #' + categoryId;
      const modal = document.getElementById('assignModal');
      modal.classList.remove('hidden'); modal.classList.add('flex');
      await loadModules();
      await loadAssigned(categoryId);
    }
    window.openAssignModal = openAssignModal;

    function closeAssignModal(){
      document.getElementById('assignModal').classList.add('hidden');
      document.getElementById('assignModal').classList.remove('flex');
      modulesCache = []; selectedModules.clear();
      document.getElementById('modulesList').innerHTML = '';
      document.getElementById('selectedList').innerHTML = '<div class="text-sm text-slate-500">No modules selected.</div>';
    }
    window.closeAssignModal = closeAssignModal;

    async function loadModules(){
      const modulesListEl = document.getElementById('modulesList');
      modulesListEl.innerHTML = '<div class="text-sm text-slate-500">Loading modules...</div>';
      const res = await fetch(API + '?action=list_modules');
      const json = await res.json();
      if (!json.success) { modulesListEl.innerHTML = '<div class="text-sm text-rose-600">Failed to load modules.</div>'; return; }
      modulesCache = json.data || [];
      renderModules(modulesCache);
      document.getElementById('moduleSearch').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        const filtered = modulesCache.filter(m => (m.title + ' ' + (m.disaster_type||'')).toLowerCase().includes(q));
        renderModules(filtered);
      });
    }

    function renderModules(list){
      const el = document.getElementById('modulesList');
      if (!list.length) { el.innerHTML = '<div class="text-sm text-slate-500">No modules found.</div>'; return; }
      el.innerHTML = '';
      list.forEach(m => {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between gap-2 p-2 hover:bg-slate-50 rounded';
        row.innerHTML = `
          <div>
            <div class="font-medium">${escapeHtml(m.title)}</div>
            <div class="text-xs text-slate-500">${escapeHtml(m.disaster_type||'')} • <span class="text-xs text-slate-400">${escapeHtml(m.module_table||'')}</span></div>
          </div>
          <div>
            <input type="checkbox" data-id="${m.id}" data-table="${escapeHtml(m.module_table)}" class="moduleCheckbox" ${selectedModules.has((m.module_table||'')+'|'+m.id)?'checked':''} />
          </div>
        `;
        el.appendChild(row);
      });
      // attach change handlers
      el.querySelectorAll('.moduleCheckbox').forEach(cb => cb.addEventListener('change', (ev) => {
        const id = ev.target.dataset.id; const table = ev.target.dataset.table;
        const key = table + '|' + id;
        if (ev.target.checked) {
          const meta = modulesCache.find(x => String(x.id) === String(id) && x.module_table === table) || {};
          selectedModules.set(key, { module_table: table, module_id: id, title: meta.title || ('#'+id) });
        } else {
          selectedModules.delete(key);
        }
        renderSelected();
      }));
    }

    function renderSelected(){
      const sel = document.getElementById('selectedList');
      if (!selectedModules.size) { sel.innerHTML = '<div class="text-sm text-slate-500">No modules selected.</div>'; return; }
      sel.innerHTML = '';
      Array.from(selectedModules.values()).forEach(m => {
        const d = document.createElement('div');
        d.className = 'flex items-center justify-between gap-2 py-1';
        d.innerHTML = `<div class="text-sm">${escapeHtml(m.title)} <div class="text-xs text-slate-400">${escapeHtml(m.module_table)}</div></div>
                       <button class="text-xs text-rose-600 removeSelBtn" data-key="${m.module_table}|${m.module_id}">Remove</button>`;
        sel.appendChild(d);
      });
      sel.querySelectorAll('.removeSelBtn').forEach(b => b.addEventListener('click', (ev) => {
        const k = ev.target.dataset.key; selectedModules.delete(k);
        const [table, id] = k.split('|');
        const cb = document.querySelector(`.moduleCheckbox[data-id="${id}"][data-table="${table}"]`);
        if (cb) cb.checked = false;
        renderSelected();
      }));
    }

    document.getElementById('assignSelectedBtn').addEventListener('click', async () => {
      if (!currentCategoryForAssign) return alert('No category selected');
      if (!selectedModules.size) return alert('Select at least one module to assign');
      // ensure all selected are same module_table for simplicity
      const tables = Array.from(new Set(Array.from(selectedModules.values()).map(x=>x.module_table)));
      if (tables.length > 1) {
        if (!confirm('Selected modules come from multiple tables. Assign anyway (will use the first table)?')) {
          return;
        }
      }
      const module_table = tables[0];
      const fd = new FormData();
      fd.append('action','assign_modules');
      fd.append('csrf_token', CSRF);
      fd.append('category_id', currentCategoryForAssign);
      fd.append('module_table', module_table);
      selectedModules.forEach(m => fd.append('module_ids[]', m.module_id));
      try {
        const res = await fetch(API, { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
          alert('Assigned');
          closeAssignModal();
          await loadCategories();
        } else alert(json.error || 'Assign failed');
      } catch (err) {
        alert('Request failed');
      }
    });

    async function loadAssigned(categoryId){
      const res = await fetch(API + '?action=get_assigned&category_id=' + encodeURIComponent(categoryId));
      const j = await res.json();
      if (!j.success) return;
      selectedModules.clear();
      const mappings = j.data || [];
      mappings.forEach(m => {
        const key = m.module_table + '|' + m.module_id;
        selectedModules.set(key, { module_table: m.module_table, module_id: m.module_id, title: m.title || ('#'+m.module_id) });
      });
      // check checkboxes if available
      document.querySelectorAll('.moduleCheckbox').forEach(cb => {
        const key = cb.dataset.table + '|' + cb.dataset.id;
        cb.checked = selectedModules.has(key);
      });
      renderSelected();
    }

    window.clearSelection = () => {
      selectedModules.clear();
      renderSelected();
      document.querySelectorAll('.moduleCheckbox').forEach(cb => cb.checked = false);
    };

    // initial
    loadCategories();
  });
</script>

</body>
</html>
