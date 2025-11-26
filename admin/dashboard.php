<?php
session_start();

$host = "localhost";
$user = "root";
$pass = ""; // adjust if needed
$db   = "training_management";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Security: admin-only
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ---------- helpers ----------
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function js_e($s){ return str_replace("</","<\/", json_encode($s)); }
function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    return ($res && $res->num_rows > 0);
}
function safe_count(mysqli $conn, string $table, string $where = ''): int {
    if (!table_exists($conn, $table)) return 0;
    $sql = "SELECT COUNT(*) AS c FROM `{$table}`" . ($where ? " WHERE {$where}" : "");
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}
function build_target_if_exists($filename) {
    $fsCandidate = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($fsCandidate)) {
        $scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($scriptDir === '') $scriptDir = '/';
        return ($scriptDir === '/' ? '' : $scriptDir) . '/' . $filename;
    }
    $fsRoot = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($fsRoot)) {
        return '/' . $filename;
    }
    return '';
}

// ---------- Process POSTs (with PRG) ----------
$didPost = false;
$postErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_staff']) && table_exists($conn, 'staff')) {
        $name = trim((string)($_POST['name'] ?? ''));
        $role = trim((string)($_POST['role'] ?? ''));
        if ($name === '' || $role === '') {
            $postErrors[] = "Name and role are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO staff (name, role) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $role);
            $stmt->execute();
            $stmt->close();
            $didPost = true;
        }
    }

    if (isset($_POST['add_program']) && table_exists($conn, 'programs')) {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        if ($title === '' || $description === '') {
            $postErrors[] = "Program title and description are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO programs (title, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $title, $description);
            $stmt->execute();
            $stmt->close();
            $didPost = true;
        }
    }

    if (isset($_POST['add_drill']) && table_exists($conn, 'drills')) {
        $title = trim((string)($_POST['title'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        if ($title === '' || $date === '' || $details === '') {
            $postErrors[] = "Drill title, date, and details are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO drills (title, date, details) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $date, $details);
            $stmt->execute();
            $stmt->close();
            $didPost = true;
        }
    }

    if (isset($_POST['upload_resource']) && isset($_FILES['resource_file']) && table_exists($conn, 'resources')) {
        $file = $_FILES['resource_file'];
        if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
            $targetDir = __DIR__ . "/uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $base = basename($file['name']);
            $base = preg_replace('/[^A-Za-z0-9.\-_ ]/', '_', $base);
            $target = $targetDir . $base;
            if (file_exists($target)) {
                $target = $targetDir . time() . '_' . $base;
            }
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $stmt = $conn->prepare("INSERT INTO resources (file_name, file_path) VALUES (?, ?)");
                $stmt->bind_param("ss", $base, $target);
                $stmt->execute();
                $stmt->close();
                $didPost = true;
            } else {
                $postErrors[] = "Failed to move uploaded file.";
            }
        } else {
            $postErrors[] = "Upload error or no file selected.";
        }
    }

    if ($didPost && empty($postErrors)) {
        $redirect = $_SERVER['PHP_SELF'];
        header("Location: " . $redirect);
        exit;
    }
}

// ---------- Queries (guarded) ----------
$k_staff = safe_count($conn, 'staff');
$k_programs = safe_count($conn, 'programs');
$k_drills = safe_count($conn, 'drills');
$k_modules = safe_count($conn, 'training_modules');
$k_resources = safe_count($conn, 'resources');
$k_participants = safe_count($conn, 'users', "role IN ('participant','user')");

$passRate = 0; $avgScore = 0;
if (table_exists($conn, 'quiz_results')) {
    $pr = $conn->query("SELECT AVG(CASE WHEN score>=50 THEN 1 ELSE 0 END)*100 AS pass_rate, AVG(score) AS avg_score FROM quiz_results");
    if ($pr) { $r = $pr->fetch_assoc(); $passRate = round($r['pass_rate'] ?? 0); $avgScore = round($r['avg_score'] ?? 0); }
}

$trainingLabels = []; $trainingData = [];
if (table_exists($conn, 'training_modules')) {
    $s = $conn->query("
      SELECT tm.id, tm.title, ROUND(AVG(CASE WHEN qr.score>=50 THEN 100 ELSE 0 END),0) AS completion
      FROM training_modules tm
      LEFT JOIN quiz_results qr ON tm.id = qr.lesson_id
      GROUP BY tm.id
      ORDER BY tm.id DESC
    ");
    if ($s) {
        while ($row = $s->fetch_assoc()) {
            $trainingLabels[] = $row['title'];
            $trainingData[] = (int)$row['completion'];
        }
    }
}

$recentQuizzes = null;
if (table_exists($conn, 'quiz_results')) {
    $recentQuizzes = $conn->query("
      SELECT qr.id, u.username, tm.title AS quiz_title, qr.score, qr.taken_at, 
             CASE WHEN qr.score>=50 THEN 'Pass' ELSE 'Fail' END AS result
      FROM quiz_results qr
      LEFT JOIN users u ON qr.participant_id = u.id
      LEFT JOIN training_modules tm ON qr.lesson_id = tm.id
      ORDER BY qr.taken_at DESC
      LIMIT 10
    ");
}

$topPerformers = null;
if (table_exists($conn, 'quiz_results') && table_exists($conn, 'users')) {
    $topPerformers = $conn->query("
      SELECT u.id, u.username, ROUND(AVG(qr.score),1) AS avg_score, COUNT(qr.id) AS attempts
      FROM quiz_results qr
      JOIN users u ON qr.participant_id = u.id
      GROUP BY u.id
      ORDER BY avg_score DESC, attempts DESC
      LIMIT 6
    ");
}

$modules = null;
if (table_exists($conn, 'training_modules')) {
    $modules = $conn->query("SELECT id, title, disaster_type, file_name, created_at FROM training_modules ORDER BY created_at DESC LIMIT 8");
}

$moduleTarget = build_target_if_exists('user/module1/module1.1.php');
$participantsTarget = build_target_if_exists('participants.php');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard — Disaster Preparedness</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    :root{ --muted:#6b7280; }
    html,body{height:100%}
    .main-scroll{height:calc(100vh - 4rem); overflow-y:auto; -webkit-overflow-scrolling:touch; padding-bottom:3rem;}
    /* Minimal cards with visible border */
    .card-min {
      background: #fff;
      border: 1px solid #e6e9ef; /* visible line around card */
      border-radius: 12px;
      padding: 1.25rem;
      display:flex;
      align-items:center;
      gap:1rem;
      cursor:pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      min-height:76px; /* more clickable area */
    }
    .card-min:focus { outline: none; box-shadow: 0 0 0 6px rgba(37,99,235,0.08); border-color:#c7dbff; transform: translateY(-3px); }
    .card-min:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(2,6,23,0.06); border-color:#dfe7fb; }
    .card-min .kpi-icon { width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px; flex-shrink:0; }
    .card-min .label{ color:var(--muted); font-size:0.9rem; }
    .card-min .value{ font-size:1.5rem; font-weight:700; color:#0f172a; }
    .card-min .extra{ color:#94a3b8; font-size:0.85rem; }

    /* Modal styles (unchanged detailed ones) */
    .modal-backdrop { background: rgba(2,6,23,0.55); position:absolute; inset:0; }
    .modal-wrapper { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; padding:1.25rem; }
    .modal-panel { background: linear-gradient(180deg,#fff,#fbfdff); border-radius:14px; width:100%; max-width:920px; box-shadow:0 40px 80px rgba(2,6,23,0.16); transform:translateY(8px) scale(.995); opacity:0; transition: transform .18s ease, opacity .18s ease; overflow:hidden; }
    .modal-open .modal-panel{ transform: translateY(0) scale(1); opacity:1; }
    .modal-head{ display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1.25rem 1.5rem; border-bottom:1px solid #eef2f7; }
    .modal-title{ font-size:1.125rem; font-weight:700; color:#0f172a; }
    .modal-sub{ color:var(--muted); font-size:0.9rem; }
    .modal-body{ padding:1.25rem 1.5rem; max-height:60vh; overflow:auto; }
    .modal-foot{ padding:0.75rem 1.5rem; border-top:1px solid #eef2f7; display:flex; justify-content:flex-end; gap:0.5rem; }

    /* Inputs + buttons */
    .form-row{ display:flex; flex-direction:column; gap:.375rem; margin-bottom:.75rem; }
    input, textarea { padding:.75rem .9rem; border-radius:10px; border:1px solid #e6e9ee; }
    .btn{ padding:.6rem .95rem; border-radius:.6rem; font-weight:700; cursor:pointer; border:none; display:inline-flex; gap:.5rem; align-items:center; }
    .btn-muted{ background:#f3f4f6; color:#0f172a; }
    .btn-primary{ background:#2563eb; color:white; }
    .helper{ font-size:0.85rem; color:var(--muted); }

    /* responsive small tweaks */
    @media (min-width:1024px){ .kpi-grid{ grid-template-columns: repeat(6,1fr); } }
  </style>
</head>
<body class="min-h-screen bg-slate-50 font-sans flex">

  <?php
    $sidebarPath = __DIR__ . '/sidebar.php';
    if (file_exists($sidebarPath)) include $sidebarPath;
    else {
      echo '<div style="width:16rem;background:#fff;box-shadow:inset -1px 0 0 rgba(0,0,0,0.03);padding:1rem;position:fixed;min-height:100vh"><div class="text-lg font-bold">Admin</div></div>';
      echo '<style>main{margin-left:16rem}</style>';
    }
  ?>

  <div class="flex-1 flex flex-col"<?php if (!file_exists($sidebarPath)) echo ' style="margin-left:16rem"'; ?>>

    <header class="bg-white border-b h-16 flex items-center justify-between px-6 sticky top-0 z-30">
      <div class="flex items-center gap-4">
        <h1 class="text-lg font-semibold text-slate-800">Admin Dashboard</h1>
        <div class="text-sm text-slate-500">Disaster Preparedness • Overview</div>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-sm text-slate-700">Hello, <strong><?= e($_SESSION['username'] ?? 'Admin') ?></strong></div>
        <button class="p-2 rounded-md border bg-white" title="Profile"><i data-lucide="user" class="w-5 h-5"></i></button>
      </div>
    </header>

    <main class="main-scroll p-6">

      <?php if (!empty($postErrors)): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-100 text-red-800">
          <strong>Submission error:</strong>
          <ul class="mt-1"><?php foreach ($postErrors as $pe): ?><li><?= e($pe) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <!-- Minimal KPI grid with visible border line around each card -->
      <div class="grid gap-4 kpi-grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div role="button" tabindex="0" data-action="open-modal" data-target="staffModal" class="card-min" aria-label="Manage staff">
          <div class="kpi-icon bg-blue-50"><i data-lucide="users" class="w-5 h-5 text-blue-600"></i></div>
          <div style="flex:1">
            <div class="label">Staff</div>
            <div class="value"><?= e($k_staff) ?></div>
          </div>
        </div>

        <div role="button" tabindex="0" data-action="open-modal" data-target="programModal" class="card-min" aria-label="Manage programs">
          <div class="kpi-icon bg-green-50"><i data-lucide="book-open" class="w-5 h-5 text-green-600"></i></div>
          <div style="flex:1">
            <div class="label">Programs</div>
            <div class="value"><?= e($k_programs) ?></div>
          </div>
        </div>

        <div role="button" tabindex="0" data-action="open-modal" data-target="drillModal" class="card-min" aria-label="Schedule drills">
          <div class="kpi-icon bg-yellow-50"><i data-lucide="activity" class="w-5 h-5 text-yellow-600"></i></div>
          <div style="flex:1">
            <div class="label">Drills</div>
            <div class="value"><?= e($k_drills) ?></div>
          </div>
          <div class="extra">Simulate</div>
        </div>

        <div role="button" tabindex="0"
             data-action="<?= $moduleTarget !== '' ? 'navigate' : 'alert-missing' ?>"
             data-target="<?= $moduleTarget !== '' ? e($moduleTarget) : 'user/module1/module1.1.php' ?>"
             class="card-min" aria-label="Manage modules">
          <div class="kpi-icon bg-indigo-50"><i data-lucide="layers" class="w-5 h-5 text-indigo-600"></i></div>
          <div style="flex:1">
            <div class="label">Modules</div>
            <div class="value"><?= e($k_modules) ?></div>
          </div>
          <div class="extra">Manage</div>
        </div>

        <div role="button" tabindex="0" data-action="open-modal" data-target="resourceModal" class="card-min" aria-label="Upload resources">
          <div class="kpi-icon bg-teal-50"><i data-lucide="folder-open" class="w-5 h-5 text-teal-600"></i></div>
          <div style="flex:1">
            <div class="label">Resources</div>
            <div class="value"><?= e($k_resources) ?></div>
          </div>
          <div class="extra">Upload</div>
        </div>

        <div role="button" tabindex="0"
             data-action="<?= $participantsTarget !== '' ? 'navigate' : 'alert-missing' ?>"
             data-target="<?= $participantsTarget !== '' ? e($participantsTarget) : 'participants.php' ?>"
             class="card-min" aria-label="View participants">
          <div class="kpi-icon bg-slate-50"><i data-lucide="users" class="w-5 h-5 text-slate-600"></i></div>
          <div style="flex:1">
            <div class="label">Participants</div>
            <div class="value"><?= e($k_participants) ?></div>
          </div>
          <div class="extra">List</div>
        </div>
      </div>

      <!-- Rest of dashboard unchanged (metrics, charts, recent etc.) -->
      <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg card-shadow">
          <div class="flex items-center justify-between">
            <div>
              <div class="helper">Overall Quiz Pass Rate</div>
              <div class="text-3xl font-bold"><?= e($passRate) ?>%</div>
            </div>
            <div class="rounded-md p-3 bg-indigo-50"><i data-lucide="check-circle" class="w-6 h-6 text-indigo-600"></i></div>
          </div>
          <div class="mt-3 helper">Average score: <strong><?= e($avgScore) ?>%</strong></div>
          <div class="mt-4 flex gap-3">
            <button type="button" class="btn btn-primary" data-action="open-modal" data-target="quizResultsModal">View Results</button>
            <a class="btn btn-muted" href="export_report.php">Export</a>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg card-shadow">
          <h3 class="text-lg font-semibold">Quick Actions</h3>
          <div class="mt-3 flex flex-wrap gap-3">
            <button class="px-3 py-2 bg-blue-600 text-white rounded" data-action="open-modal" data-target="staffModal">Add Staff</button>
            <button class="px-3 py-2 bg-green-600 text-white rounded" data-action="open-modal" data-target="programModal">Add Program</button>
            <button class="px-3 py-2 bg-yellow-600 text-white rounded" data-action="open-modal" data-target="drillModal">Schedule Drill</button>
            <button class="px-3 py-2 bg-teal-600 text-white rounded" data-action="open-modal" data-target="resourceModal">Upload Resource</button>
            <a class="px-3 py-2 bg-slate-100 border rounded" href="<?= e($moduleTarget !== '' ? $moduleTarget : 'module1.1.php') ?>">Manage Modules</a>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg card-shadow">
          <h3 class="text-lg font-semibold">Top Performers</h3>
          <div class="mt-3 space-y-3 text-sm">
            <?php if ($topPerformers && $topPerformers->num_rows): while ($tp = $topPerformers->fetch_assoc()): ?>
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-medium"><?= e($tp['username']) ?></div>
                  <div class="helper">Attempts: <?= e($tp['attempts']) ?></div>
                </div>
                <div class="text-slate-700 font-semibold"><?= e($tp['avg_score']) ?>%</div>
              </div>
            <?php endwhile; else: ?>
              <div class="text-slate-400">No performers yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg card-shadow lg:col-span-2">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold">Training Completion</h3>
            <div class="helper">By module</div>
          </div>
          <div style="height:360px"><canvas id="trainingChart"></canvas></div>
        </div>

        <div class="bg-white p-6 rounded-lg card-shadow">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold">Recent Modules</h3>
            <a href="<?= e($moduleTarget !== '' ? $moduleTarget : 'module1.1.php') ?>" class="text-sky-600 text-sm">Manage</a>
          </div>
          <ul class="text-sm space-y-3">
            <?php if ($modules && $modules->num_rows): while ($m = $modules->fetch_assoc()): ?>
              <li class="flex items-start justify-between">
                <div>
                  <div class="font-medium"><?= e($m['title']) ?></div>
                  <div class="helper"><?= e($m['disaster_type']) ?> • <?= e($m['created_at']) ?></div>
                </div>
                <div><a href="<?= e($moduleTarget !== '' ? $moduleTarget . '?edit=' . (int)$m['id'] : 'module1.1.php?edit=' . (int)$m['id']) ?>" class="text-sky-600">Edit</a></div>
              </li>
            <?php endwhile; else: ?>
              <li class="text-slate-400">No modules yet.</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <div class="mt-6 bg-white p-6 rounded-lg card-shadow">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold">Recent Quiz Attempts</h3>
          <div class="helper">Latest 10 attempts</div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
              <tr>
                <th class="p-2 text-left">User</th>
                <th class="p-2 text-left">Quiz</th>
                <th class="p-2 text-left">Score</th>
                <th class="p-2 text-left">Result</th>
                <th class="p-2 text-left">Taken</th>
                <th class="p-2 text-left">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recentQuizzes && $recentQuizzes->num_rows): while ($q = $recentQuizzes->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-2"><?= e($q['username']) ?></td>
                  <td class="p-2"><?= e($q['quiz_title']) ?></td>
                  <td class="p-2"><?= e($q['score']) ?>%</td>
                  <td class="p-2 font-semibold <?= $q['result']=='Pass' ? 'text-green-600' : 'text-red-600' ?>"><?= e($q['result']) ?></td>
                  <td class="p-2"><?= e($q['taken_at']) ?></td>
                  <td class="p-2"><button class="text-indigo-600" onclick="openDetails(<?= (int)$q['id'] ?>)">Details</button></td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td class="p-4 text-center text-slate-400" colspan="6">No quiz attempts yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function(){ lucide.createIcons(); });

    (function(){
      const ctx = document.getElementById('trainingChart');
      if (!ctx) return;
      const c = ctx.getContext('2d');
      const gradient = c.createLinearGradient(0,0,0,360);
      gradient.addColorStop(0,'rgba(59,130,246,0.8)');
      gradient.addColorStop(1,'rgba(59,130,246,0.06)');
      new Chart(c, {
        type:'bar',
        data:{ labels: <?= js_e($trainingLabels) ?>, datasets:[{ label:'Completion %', data: <?= js_e($trainingData) ?>, backgroundColor: gradient, borderColor:'#2563eb', borderWidth:1 }]},
        options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback: v => v + '%' } } }, plugins:{ legend:{ display:false } } }
      });
    })();

    function openDetails(id){ alert('Open details for quiz ID: ' + id); }

    function preventBodyScroll(toggle){ document.body.style.overflow = toggle ? 'hidden' : ''; }
    function openModal(id){
      const wrapper = document.getElementById(id);
      if (!wrapper) return;
      wrapper.classList.remove('hidden'); wrapper.classList.add('modal-open'); wrapper.setAttribute('aria-hidden','false');
      const panel = wrapper.querySelector('.modal-panel'); if (panel) panel.classList.add('modal-open');
      preventBodyScroll(true);
      setTimeout(()=> { const closeBtn = wrapper.querySelector('[data-close]'); (closeBtn || panel) && ((closeBtn || panel).focus && (closeBtn || panel).focus()); }, 120);
      const onKey = (e) => { if (e.key === 'Escape') closeModal(id); };
      wrapper.__onKey = onKey; document.addEventListener('keydown', onKey);
    }
    function closeModal(id){
      const wrapper = document.getElementById(id); if (!wrapper) return;
      wrapper.classList.add('hidden'); wrapper.classList.remove('modal-open'); wrapper.setAttribute('aria-hidden','true');
      const panel = wrapper.querySelector('.modal-panel'); if (panel) panel.classList.remove('modal-open');
      preventBodyScroll(false);
      if (wrapper.__onKey) { document.removeEventListener('keydown', wrapper.__onKey); wrapper.__onKey = null; }
    }

    (function attach(){
      document.querySelectorAll('[data-action]').forEach(function(el){
        if (el.__attached) return; el.__attached = true;
        el.addEventListener('click', function(){
          const action = el.getAttribute('data-action'), target = el.getAttribute('data-target') || '';
          if (action === 'open-modal' && target) openModal(target);
          else if (action === 'navigate' && target) window.location.href = target;
          else if (action === 'alert-missing') alert('Target page not found on server: ' + target);
        });
        el.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); } });
      });

      document.addEventListener('click', function(ev){
        if (ev.target.classList && ev.target.classList.contains('modal-backdrop')) {
          const wrapper = ev.target.closest('.modal-wrapper'); wrapper && wrapper.id && closeModal(wrapper.id);
        }
        const c = ev.target.closest && ev.target.closest('[data-close]');
        if (c) { const wrapper = c.closest('.modal-wrapper'); wrapper && wrapper.id && closeModal(wrapper.id); }
      });

      document.querySelectorAll('form').forEach(function(form){
        form.addEventListener('submit', function(e){
          const btn = form.querySelector('button[type="submit"]');
          if (btn) { btn.disabled = true; btn.classList.add('opacity-70'); }
        });
      });
    })();
  </script>

  <!-- MODALS (kept detailed) -->
  <div id="staffModal" class="hidden modal-wrapper" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-panel" role="document">
      <div class="modal-head">
        <div class="flex items-center gap-4">
          <div class="p-2 rounded-lg bg-blue-50"><i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i></div>
          <div>
            <div class="modal-title">Add Staff</div>
            <div class="modal-sub">Create a new staff account — visible in staff lists</div>
          </div>
        </div>
        <div><button data-close class="btn btn-muted" aria-label="Close"><i data-lucide="x" class="w-4 h-4"></i></button></div>
      </div>
      <div class="modal-body">
        <form id="staffForm" method="POST" novalidate>
          <div class="form-row"><label for="staff-name">Full name</label><input id="staff-name" name="name" type="text" required placeholder="e.g., John Doe"></div>
          <div class="form-row"><label for="staff-role">Role / title</label><input id="staff-role" name="role" type="text" required placeholder="e.g., Trainer"></div>
        </form>
      </div>
      <div class="modal-foot">
        <button data-close class="btn btn-muted">Cancel</button>
        <button type="submit" form="staffForm" name="add_staff" class="btn btn-primary">Create staff</button>
      </div>
    </div>
  </div>

  <div id="programModal" class="hidden modal-wrapper" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-panel">
      <div class="modal-head">
        <div class="flex items-center gap-4">
          <div class="p-2 rounded-lg bg-green-50"><i data-lucide="book" class="w-5 h-5 text-green-600"></i></div>
          <div>
            <div class="modal-title">Add Training Program</div>
            <div class="modal-sub">Create a new program with title and description</div>
          </div>
        </div>
        <div><button data-close class="btn btn-muted" aria-label="Close"><i data-lucide="x" class="w-4 h-4"></i></button></div>
      </div>
      <div class="modal-body">
        <form id="programForm" method="POST" novalidate>
          <div class="form-row"><label for="program-title">Program title</label><input id="program-title" name="title" type="text" required></div>
          <div class="form-row"><label for="program-desc">Description</label><textarea id="program-desc" name="description" rows="5" required></textarea></div>
        </form>
      </div>
      <div class="modal-foot">
        <button data-close class="btn btn-muted">Cancel</button>
        <button type="submit" form="programForm" name="add_program" class="btn btn-primary">Save Program</button>
      </div>
    </div>
  </div>

  <div id="drillModal" class="hidden modal-wrapper" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-panel">
      <div class="modal-head">
        <div class="flex items-center gap-4">
          <div class="p-2 rounded-lg bg-yellow-50"><i data-lucide="clock" class="w-5 h-5 text-yellow-600"></i></div>
          <div>
            <div class="modal-title">Schedule Drill</div>
            <div class="modal-sub">Plan a simulation drill with date and details</div>
          </div>
        </div>
        <div><button data-close class="btn btn-muted" aria-label="Close"><i data-lucide="x" class="w-4 h-4"></i></button></div>
      </div>
      <div class="modal-body">
        <form id="drillForm" method="POST" novalidate>
          <div class="form-row"><label for="drill-title">Drill title</label><input id="drill-title" name="title" type="text" required></div>
          <div class="form-row"><label for="drill-date">Date</label><input id="drill-date" name="date" type="date" required></div>
          <div class="form-row"><label for="drill-details">Details</label><textarea id="drill-details" name="details" rows="4" required></textarea></div>
        </form>
      </div>
      <div class="modal-foot">
        <button data-close class="btn btn-muted">Cancel</button>
        <button type="submit" form="drillForm" name="add_drill" class="btn btn-primary">Schedule</button>
      </div>
    </div>
  </div>

  <div id="resourceModal" class="hidden modal-wrapper" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-panel">
      <div class="modal-head">
        <div class="flex items-center gap-4">
          <div class="p-2 rounded-lg bg-teal-50"><i data-lucide="upload" class="w-5 h-5 text-teal-600"></i></div>
          <div>
            <div class="modal-title">Upload Resource</div>
            <div class="modal-sub">Add a file to the resources library</div>
          </div>
        </div>
        <div><button data-close class="btn btn-muted" aria-label="Close"><i data-lucide="x" class="w-4 h-4"></i></button></div>
      </div>
      <div class="modal-body">
        <form id="resourceForm" method="POST" enctype="multipart/form-data" novalidate>
          <div class="form-row"><label for="resource-file">Choose file</label><input id="resource-file" name="resource_file" type="file" required></div>
        </form>
      </div>
      <div class="modal-foot">
        <button data-close class="btn btn-muted">Cancel</button>
        <button type="submit" form="resourceForm" name="upload_resource" class="btn btn-primary">Upload</button>
      </div>
    </div>
  </div>

  <div id="quizResultsModal" class="hidden modal-wrapper" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-panel">
      <div class="modal-head">
        <div class="flex items-center gap-4">
          <div class="p-2 rounded-lg bg-indigo-50"><i data-lucide="bar-chart" class="w-5 h-5 text-indigo-600"></i></div>
          <div>
            <div class="modal-title">Quiz Results</div>
            <div class="modal-sub">Recent participant quiz results (latest)</div>
          </div>
        </div>
        <div><button data-close class="btn btn-muted" aria-label="Close"><i data-lucide="x" class="w-4 h-4"></i></button></div>
      </div>
      <div class="modal-body">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-700">
              <tr><th class="p-2">User</th><th class="p-2">Quiz</th><th class="p-2">Score</th><th class="p-2">Result</th><th class="p-2">Taken</th></tr>
            </thead>
            <tbody>
              <?php
                if (table_exists($conn, 'quiz_results')) {
                  $qr2 = $conn->query("
                    SELECT u.username, tm.title AS quiz_title, qr.score, qr.taken_at,
                           CASE WHEN qr.score>=50 THEN 'Pass' ELSE 'Fail' END AS result
                    FROM quiz_results qr
                    LEFT JOIN users u ON qr.participant_id = u.id
                    LEFT JOIN training_modules tm ON qr.lesson_id = tm.id
                    ORDER BY qr.taken_at DESC
                    LIMIT 200
                  ");
                  if ($qr2 && $qr2->num_rows) {
                    while ($r = $qr2->fetch_assoc()) {
                      echo '<tr class="hover:bg-slate-50">';
                      echo '<td class="p-2">'.e($r['username']).'</td>';
                      echo '<td class="p-2">'.e($r['quiz_title']).'</td>';
                      echo '<td class="p-2">'.e($r['score']).'%</td>';
                      echo '<td class="p-2 font-semibold '.($r['result']=='Pass'?'text-green-600':'text-red-600').'">'.e($r['result']).'</td>';
                      echo '<td class="p-2">'.e($r['taken_at']).'</td>';
                      echo '</tr>';
                    }
                  } else {
                    echo '<tr><td class="p-4 text-center text-slate-400" colspan="5">No results yet.</td></tr>';
                  }
                } else {
                  echo '<tr><td class="p-4 text-center text-slate-400" colspan="5">Quiz results table not found.</td></tr>';
                }
              ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-foot">
        <button data-close class="btn btn-muted">Close</button>
      </div>
    </div>
  </div>

</body>
</html>
