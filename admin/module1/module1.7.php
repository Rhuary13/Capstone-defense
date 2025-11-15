<?php
// admin_records_ui.php
// Modernized UI for Training & Simulation Records (Admin)
// Single-file update. Includes search, filters, CSV export, modal preview,
// independent scrolling area, and polished Tailwind UI.

session_start();

// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// Helpers
// ----------------------
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ----------------------
// CSV Export (current filters)
// ----------------------
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Build same query as below but output CSV
    $where = [];
    $types = '';
    $params = [];

    if ($search !== '') {
        $where[] = "(p.name LIKE ? OR pr.other_reference LIKE ?)";
        $types .= 'ss';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like;
    }
    if ($statusFilter !== '') {
        $where[] = "pr.status = ?";
        $types .= 's';
        $params[] = $statusFilter;
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
      SELECT 
        pr.id AS record_id,
        p.id AS participant_id,
        p.name AS full_name,
        COALESCE(pr.training_module,'-') AS training_module,
        COALESCE(pr.program_name,'-') AS program_name,
        COALESCE(pr.simulation_title,'-') AS simulation_title,
        COALESCE(pr.score, '') AS score,
        COALESCE(pr.status, '') AS status,
        COALESCE(pr.created_at, '') AS created_at
      FROM participant_records pr
      LEFT JOIN participants p ON pr.participant_id = p.id
      $where_sql
      ORDER BY pr.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=training_records.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['record_id','participant_id','full_name','training_module','program_name','simulation_title','score','status','created_at']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['record_id'],
                $row['participant_id'],
                $row['full_name'],
                $row['training_module'],
                $row['program_name'],
                $row['simulation_title'],
                $row['score'],
                $row['status'],
                $row['created_at']
            ]);
        }
        fclose($out);
        exit;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Failed to prepare CSV query.";
        exit;
    }
}

// ----------------------
// Build search/filter query (prepared)
// ----------------------
$where = [];
$types = '';
$params = [];

if ($search !== '') {
    // search participant name or any other reference column (some installs store other reference columns)
    $where[] = "(p.name LIKE ? OR pr.other_reference LIKE ?)";
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}
if ($statusFilter !== '') {
    $where[] = "pr.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Build main query (select common columns; use COALESCE for missing DB data)
$sql = "
    SELECT 
        pr.id AS record_id,
        pr.participant_id,
        COALESCE(p.name, '') AS full_name,
        COALESCE(pr.training_module, '') AS training_module,
        COALESCE(pr.program_name, '') AS program_name,
        COALESCE(pr.simulation_title, '') AS simulation_title,
        COALESCE(pr.score, '') AS score,
        COALESCE(pr.status, '') AS status,
        COALESCE(pr.created_at, '') AS created_at
    FROM participant_records pr
    LEFT JOIN participants p ON pr.participant_id = p.id
    $where_sql
    ORDER BY pr.id DESC
    LIMIT 1000
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Query prepare failed: " . esc($conn->error));
}
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ----------------------
// Summary counts for cards
// ----------------------
$counts = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'failed' => 0];
$countSql = "SELECT 
    COUNT(*) AS total,
    SUM(pr.status = 'completed') AS completed,
    SUM(pr.status = 'in-progress') AS in_progress,
    SUM(pr.status = 'failed') AS failed
  FROM participant_records pr
  LEFT JOIN participants p ON pr.participant_id = p.id
  $where_sql
";
$cstmt = $conn->prepare($countSql);
if ($cstmt) {
    if ($params) $cstmt->bind_param($types, ...$params);
    $cstmt->execute();
    $cres = $cstmt->get_result()->fetch_assoc();
    if ($cres) {
        $counts['total'] = (int)$cres['total'];
        $counts['completed'] = (int)$cres['completed'];
        $counts['in_progress'] = (int)$cres['in_progress'];
        $counts['failed'] = (int)$cres['failed'];
    }
    $cstmt->close();
}

// ----------------------
// AJAX: fetch single record details for modal
// ----------------------
if (isset($_GET['fetch_record']) && is_numeric($_GET['fetch_record'])) {
    $id = (int)$_GET['fetch_record'];
    $q = $conn->prepare("
        SELECT pr.*, p.name AS participant_name
        FROM participant_records pr
        LEFT JOIN participants p ON pr.participant_id = p.id
        WHERE pr.id = ? LIMIT 1
    ");
    $q->bind_param('i', $id);
    $q->execute();
    $data = $q->get_result()->fetch_assoc();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data ?: []);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Training & Simulation Records — Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden;background:#f1f5f9}
    .main{flex:1;display:flex;flex-direction:column;min-width:0}
    /* independent scrolling for main area below header */
    .main-scroll{flex:1;overflow:auto;padding:1.25rem}
    .card-scroll{max-height:56vh;overflow:auto}
    .sticky-head thead th { position: sticky; top: 0; background: white; z-index: 10; }
    /* small scrollbar styling */
    .main-scroll::-webkit-scrollbar{width:10px}
    .main-scroll::-webkit-scrollbar-thumb{background-color:rgba(2,6,23,0.06);border-radius:8px}
  </style>
</head>
<body class="font-sans text-slate-800">

<div class="app">
  <!-- Sidebar -->
  <?php include "../sidebar.php"; ?>

  <div class="main">
    <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Training & Simulation Records</h1>
        <div class="text-sm text-slate-500">Overview of participants' training, simulations and scores</div>
      </div>
      <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2" id="filterForm">
          <input type="search" name="q" value="<?= esc($search) ?>" placeholder="Search participant..." class="px-3 py-2 border rounded-lg w-64" />
          <select name="status" class="px-3 py-2 border rounded-lg">
            <option value="">All statuses</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="in-progress" <?= $statusFilter === 'in-progress' ? 'selected' : '' ?>>In-progress</option>
            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
          </select>
          <button type="submit" class="px-3 py-2 bg-sky-600 text-white rounded-lg">Apply</button>
          <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="px-3 py-2 bg-gray-100 rounded-lg text-sm">Export CSV</a>
        </form>
      </div>
    </header>

    <main class="main-scroll">
      <div class="max-w-7xl mx-auto space-y-6">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white p-4 rounded-2xl shadow flex items-center justify-between">
            <div>
              <div class="text-sm text-slate-500">Total Records</div>
              <div class="text-2xl font-bold"><?= esc($counts['total']) ?></div>
            </div>
            <div><i data-lucide="file-text" class="w-8 h-8 text-sky-600"></i></div>
          </div>

          <div class="bg-white p-4 rounded-2xl shadow flex items-center justify-between">
            <div>
              <div class="text-sm text-slate-500">Completed</div>
              <div class="text-2xl font-bold text-emerald-600"><?= esc($counts['completed']) ?></div>
            </div>
            <div><i data-lucide="check-circle" class="w-8 h-8 text-emerald-600"></i></div>
          </div>

          <div class="bg-white p-4 rounded-2xl shadow flex items-center justify-between">
            <div>
              <div class="text-sm text-slate-500">In progress</div>
              <div class="text-2xl font-bold text-amber-500"><?= esc($counts['in_progress']) ?></div>
            </div>
            <div><i data-lucide="clock" class="w-8 h-8 text-amber-500"></i></div>
          </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow overflow-hidden">
          <div class="p-4 border-b flex items-center justify-between">
            <div class="font-semibold text-lg">Participant Records</div>
            <div class="text-sm text-slate-500">Showing up to 1000 results</div>
          </div>

          <div class="p-4 overflow-auto card-scroll">
            <table class="min-w-full text-sm sticky-head">
              <thead>
                <tr class="bg-slate-50 text-slate-700 text-xs uppercase">
                  <th class="p-3 text-left">Participant</th>
                  <th class="p-3 text-left">Training Module</th>
                  <th class="p-3 text-left">Program</th>
                  <th class="p-3 text-left">Simulation</th>
                  <th class="p-3 text-left">Score</th>
                  <th class="p-3 text-left">Status</th>
                  <th class="p-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($records)): ?>
                  <?php foreach ($records as $rec): ?>
                    <?php
                      $status = $rec['status'] ?? '';
                      $statusClass = $status === 'completed' ? 'bg-emerald-600' : ($status === 'in-progress' ? 'bg-amber-500' : ($status === 'failed' ? 'bg-rose-600' : 'bg-slate-400'));
                      $scoreText = ($rec['score'] === '' || $rec['score'] === null) ? '-' : esc($rec['score']) . '%';
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                      <td class="p-3 font-medium text-slate-800"><?= esc($rec['full_name'] ?: '—') ?></td>
                      <td class="p-3"><?= esc($rec['training_module'] ?: '-') ?></td>
                      <td class="p-3"><?= esc($rec['program_name'] ?: '-') ?></td>
                      <td class="p-3"><?= esc($rec['simulation_title'] ?: '-') ?></td>
                      <td class="p-3"><?= $scoreText ?></td>
                      <td class="p-3">
                        <span class="px-2 py-1 rounded text-white <?= $statusClass ?>"><?= esc(ucfirst($status ?: 'unknown')) ?></span>
                      </td>
                      <td class="p-3">
                        <div class="flex items-center gap-2">
                          <button class="px-3 py-1 rounded bg-sky-50 text-sky-700 view-btn" data-id="<?= (int)$rec['record_id'] ?>">View</button>
                          <a href="mailto:?subject=Record%20for%20<?= rawurlencode($rec['full_name'] ?: '') ?>" class="px-3 py-1 rounded bg-gray-100">Email</a>
                          <a href="?export=csv&<?= http_build_query(['q'=>$search,'status'=>$statusFilter]) ?>" class="px-3 py-1 rounded bg-gray-100">Export</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="7" class="text-center p-6 text-slate-500">No records found for the current filters.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- Modal for record details -->
<div id="recordModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative bg-white rounded-2xl shadow-lg w-11/12 max-w-2xl z-10 overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between">
      <h3 id="recordModalTitle" class="text-lg font-semibold">Record Details</h3>
      <button id="recordModalClose" class="text-slate-600">&times;</button>
    </div>
    <div class="p-4 max-h-[60vh] overflow-auto" id="recordModalBody">
      <div class="text-slate-500">Loading…</div>
    </div>
    <div class="p-4 border-t text-sm text-slate-500">
      <div id="recordModalFooter">Actions: export / email / contact participant</div>
    </div>
  </div>
</div>

<script>
  lucide.createIcons();

  // Modal logic
  const modal = document.getElementById('recordModal');
  const modalBody = document.getElementById('recordModalBody');
  const modalTitle = document.getElementById('recordModalTitle');
  const modalClose = document.getElementById('recordModalClose');

  function openModalWithData(data) {
    modalTitle.textContent = data.participant_name ? (data.participant_name + ' — Record') : 'Record Details';
    modalBody.innerHTML = `
      <div class="space-y-3">
        <div><strong>Record ID:</strong> ${data.id ?? '-'}</div>
        <div><strong>Participant:</strong> ${escapeHtml(data.participant_name ?? data.full_name ?? '-')}</div>
        <div><strong>Training Module:</strong> ${escapeHtml(data.training_module ?? '-')}</div>
        <div><strong>Program:</strong> ${escapeHtml(data.program_name ?? '-')}</div>
        <div><strong>Simulation:</strong> ${escapeHtml(data.simulation_title ?? '-')}</div>
        <div><strong>Score:</strong> ${data.score !== null ? escapeHtml(data.score + '%') : '-'}</div>
        <div><strong>Status:</strong> ${escapeHtml(data.status ?? '-')}</div>
        <div><strong>Notes:</strong><div class="mt-1 text-sm text-slate-600">${escapeHtml(data.notes ?? data.description ?? '-')}</div></div>
        <div class="text-xs text-slate-400">Created: ${escapeHtml(data.created_at ?? '-')}</div>
      </div>
    `;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeModal() {
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    modalBody.innerHTML = '';
  }

  modalClose.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  function escapeHtml(str){
    if (!str) return '';
    return String(str).replace(/[&<>"'`=\/]/g, function(s){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[s]; });
  }

  // Attach click handlers for view buttons
  document.querySelectorAll('.view-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.dataset.id;
      modalBody.innerHTML = '<div class="text-slate-500">Loading…</div>';
      fetch('?fetch_record=' + encodeURIComponent(id))
        .then(r => {
          if (!r.ok) throw new Error('Network response was not ok');
          return r.json();
        })
        .then(data => {
          if (!data || Object.keys(data).length === 0) {
            modalBody.innerHTML = '<div class="text-rose-600">Record not found.</div>';
          } else {
            openModalWithData(data);
          }
        })
        .catch(err => {
          modalBody.innerHTML = '<div class="text-rose-600">Failed to load record.</div>';
          console.error(err);
        });
    });
  });

  // preserve filters when clicking export link in row
  document.querySelectorAll('a[href*="export=csv"]').forEach(a=>{
    // links are fine — no extra JS needed
  });

</script>
</body>
</html>
