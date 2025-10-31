<?php
session_start();

// -------------------------
// Database connection
// -------------------------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// -------------------------
// Security check
// -------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// -------------------------
// Helpers (insights & styles)
// -------------------------
function generateInsight(float $completionRate, float $avgScore): string {
    if ($completionRate === 0 && $avgScore === 0) {
        return "No learner data yet. Consider assigning this module to learners or attaching quizzes/resources.";
    }
    if ($completionRate < 50) {
        return "Low completion rate detected. Consider shortening the module, breaking content into smaller lessons, or adding reminders/notifications.";
    }
    if ($avgScore < 60) {
        return "Low average quiz score. Review the learning materials for clarity, add examples, or update quiz questions to better match learning objectives.";
    }
    if ($avgScore < 75) {
        return "Average performance is moderate. Consider adding interactive activities, knowledge checks, or short videos to reinforce concepts.";
    }
    return "Module performing well. Maintain current approach and periodically review quiz/pass thresholds.";
}
function progressColorClass(float $rate): string {
    if ($rate < 50) return 'text-red-600';
    if ($rate < 75) return 'text-yellow-600';
    return 'text-green-600';
}

// -------------------------
// Decide which completion source exists
// -------------------------
$has_completion_table = false;
$check = $conn->query("SHOW TABLES LIKE 'training_completion'");
if ($check && $check->num_rows > 0) $has_completion_table = true;

// -------------------------
// Fetch modules with completion summary
// -------------------------
$completion_stats = [];
if ($has_completion_table) {
    $sql = "
      SELECT tm.id AS module_id, tm.title, tm.description,
             COUNT(tc.user_id) AS total_learners,
             SUM(CASE WHEN tc.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
             SUM(CASE WHEN tc.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress
      FROM training_modules tm
      LEFT JOIN training_completion tc ON tm.id = tc.module_id
      GROUP BY tm.id
      ORDER BY tm.id ASC
    ";
} else {
    // fallback using quiz_results
    $sql = "
      SELECT tm.id AS module_id, tm.title, tm.description,
             COUNT(DISTINCT qr.participant_id) AS total_learners,
             SUM(CASE WHEN qr.status = 'Passed' THEN 1 ELSE 0 END) AS completed,
             0 AS in_progress
      FROM training_modules tm
      LEFT JOIN quiz_results qr ON tm.id = qr.lesson_id
      GROUP BY tm.id
      ORDER BY tm.id ASC
    ";
}
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $r['total_learners'] = (int)($r['total_learners'] ?? 0);
        $r['completed'] = (int)($r['completed'] ?? 0);
        $r['in_progress'] = (int)($r['in_progress'] ?? 0);
        $completion_stats[] = $r;
    }
}

// -------------------------
// Module quiz effectiveness (avg score + attempts)
// -------------------------
$module_effectiveness = [];
$res2 = $conn->query("
    SELECT tm.id, tm.title,
           ROUND(AVG(qr.score),2) AS avg_score,
           COUNT(qr.participant_id) AS attempts
    FROM training_modules tm
    LEFT JOIN quiz_results qr ON tm.id = qr.lesson_id
    GROUP BY tm.id
    ORDER BY tm.id ASC
");
if ($res2) {
    while ($r = $res2->fetch_assoc()) {
        $r['avg_score'] = $r['avg_score'] !== null ? (float)$r['avg_score'] : 0.0;
        $r['attempts'] = (int)($r['attempts'] ?? 0);
        $module_effectiveness[$r['id']] = $r;
    }
}

// -------------------------
// Overall quiz summary
// -------------------------
$quiz_summary = ['total'=>0, 'passed'=>0, 'failed'=>0, 'avg_score'=>0.0];
$res3 = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN status='Passed' THEN 1 ELSE 0 END) AS passed,
           SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) AS failed,
           ROUND(AVG(score),2) AS avg_score
    FROM quiz_results
");
if ($res3 && ($row = $res3->fetch_assoc())) {
    $quiz_summary['total'] = (int)$row['total'];
    $quiz_summary['passed'] = (int)$row['passed'];
    $quiz_summary['failed'] = (int)$row['failed'];
    $quiz_summary['avg_score'] = $row['avg_score'] !== null ? (float)$row['avg_score'] : 0.0;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin — Module Analytics</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root{--card-radius:.9rem}
    body { background:#f8fafc; margin:0; min-height:100vh; }
    /* main independent scroll + transitions for layout adjustments */
    main { height: 100vh; overflow: hidden; display: flex; flex-direction: column; transition: margin-left .18s ease, padding-top .18s ease; position: relative; z-index:1; }
    .content { overflow: auto; padding: 1.25rem; box-sizing: border-box; max-height: 100vh; }
    .modules-grid { gap: 1.25rem; }
    .module-card { border-radius: var(--card-radius); transition: transform .12s ease, box-shadow .12s ease; }
    .module-card:hover { transform: translateY(-6px); box-shadow: 0 10px 28px rgba(2,6,23,0.06); }
    /* table/list container independent scroll */
    .list-scroll { max-height: 56vh; overflow: auto; }
    .radial { font-weight:700; font-size:.95rem; display:flex; align-items:center; justify-content:center; width:72px; height:72px; border-radius:999px; background:rgba(0,0,0,0.03); }
    .small { font-size:.85rem; }

    /* aggressive safety: if sidebar overlaps, these help preserve content visibility */
    #adminMain { background: transparent; }
    @media (max-width: 1024px) {
      main { margin-left: 0 !important; padding-top: 0 !important; }
      .content { padding: 1rem; }
    }
  </style>
</head>
<body class="font-sans text-gray-800">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main -->
  <main class="flex-1 flex flex-col" id="adminMain">
    <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-sky-700">📋 Module Completion & Effectiveness</h1>
        <p class="text-sm text-gray-600 mt-1">Use these insights to improve content and learning flow.</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-sm text-gray-600">Admin: <span class="font-medium"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin' ?></span></div>
        <div class="text-sm text-gray-400"><?= date('F j, Y, g:i A') ?></div>
      </div>
    </header>

    <div class="content" id="adminContent">
      <section class="max-w-7xl mx-auto">

        <!-- top summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white p-4 rounded-lg module-card">
            <p class="text-xs text-gray-500">Total Quiz Attempts</p>
            <p class="text-2xl font-bold text-sky-600"><?= number_format($quiz_summary['total']) ?></p>
          </div>
          <div class="bg-white p-4 rounded-lg module-card">
            <p class="text-xs text-gray-500">Passed</p>
            <p class="text-2xl font-bold text-green-600"><?= number_format($quiz_summary['passed']) ?></p>
          </div>
          <div class="bg-white p-4 rounded-lg module-card">
            <p class="text-xs text-gray-500">Failed</p>
            <p class="text-2xl font-bold text-red-600"><?= number_format($quiz_summary['failed']) ?></p>
          </div>
          <div class="bg-white p-4 rounded-lg module-card">
            <p class="text-xs text-gray-500">Avg Quiz Score</p>
            <p class="text-2xl font-bold text-purple-600"><?= round($quiz_summary['avg_score'],2) ?>%</p>
          </div>
        </div>

        <!-- controls -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
          <div class="flex items-center gap-3 w-full md:w-2/3">
            <input id="search" oninput="filterModules()" placeholder="Search modules or type id..." class="w-full md:w-1/2 border rounded px-3 py-2 focus:outline-none" />
            <select id="sort" onchange="sortModules()" class="border rounded px-3 py-2">
              <option value="id_asc">ID ↑</option>
              <option value="id_desc">ID ↓</option>
              <option value="title_asc">Title A→Z</option>
              <option value="title_desc">Title Z→A</option>
              <option value="completion_desc">Completion % ↓</option>
            </select>
            <button onclick="exportCSV()" class="ml-2 px-3 py-2 rounded bg-sky-600 text-white">Export CSV</button>
          </div>
          <div class="text-sm text-gray-500">Modules: <strong><?= number_format(count($completion_stats)) ?></strong></div>
        </div>

        <!-- modules grid + list scroll (independent) -->
        <div class="list-scroll">
          <div id="modulesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 modules-grid">
            <?php if (empty($completion_stats)): ?>
              <div class="col-span-full bg-white p-6 rounded-lg text-center text-gray-600">No training modules found. Please add training modules.</div>
            <?php else: ?>
              <?php foreach ($completion_stats as $m):
                $total = (int)$m['total_learners'];
                $completed = (int)$m['completed'];
                $in_progress = (int)$m['in_progress'];
                $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

                $eff = $module_effectiveness[$m['module_id']] ?? ['avg_score' => 0.0, 'attempts' => 0];
                $avgScore = (float)($eff['avg_score'] ?? 0.0);
                $attempts = (int)($eff['attempts'] ?? 0);

                $insight = generateInsight($completionRate, $avgScore);
                $colorClass = progressColorClass($completionRate);
              ?>
              <article class="bg-white p-5 module-card rounded-lg" data-title="<?= htmlspecialchars(strtolower($m['title'])) ?>" data-id="<?= intval($m['module_id']) ?>" data-completion="<?= $completionRate ?>">
                <div class="flex gap-4">
                  <div class="radial <?= $colorClass ?>">
                    <?= $completionRate ?>%
                  </div>

                  <div class="flex-1">
                    <h3 class="text-lg font-semibold"><?= htmlspecialchars($m['title']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1">
                      <strong><?= $completed ?></strong> completed &middot; <strong><?= $in_progress ?></strong> in progress &middot; <strong><?= $total ?></strong> learners
                    </p>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                      <div class="p-3 bg-gray-50 rounded border">
                        <p class="text-xs text-gray-500">Avg Quiz Score</p>
                        <p class="text-lg font-bold text-purple-600"><?= round($avgScore,2) ?>%</p>
                        <p class="text-xs text-gray-400"><?= number_format($attempts) ?> attempts</p>
                      </div>
                      <div class="p-3 bg-gray-50 rounded border">
                        <p class="text-xs text-gray-500">Status</p>
                        <?php
                          if ($completionRate === 0.0 && $avgScore === 0.0) {
                            $statusLabel = "<span class='text-gray-500 font-semibold'>No data</span>";
                          } elseif ($completionRate < 50 || $avgScore < 60) {
                            $statusLabel = "<span class='text-red-600 font-semibold'>Needs Improvement</span>";
                          } elseif ($avgScore < 75) {
                            $statusLabel = "<span class='text-yellow-600 font-semibold'>Moderate</span>";
                          } else {
                            $statusLabel = "<span class='text-green-600 font-semibold'>Good</span>";
                          }
                        ?>
                        <div class="mt-1"><?= $statusLabel ?></div>
                      </div>
                    </div>

                    <div class="mt-3 bg-gray-50 p-3 rounded border-l-4 border-sky-400">
                      <p class="text-sm text-gray-700"><?= htmlspecialchars($insight) ?></p>
                    </div>

                    <div class="mt-3 flex gap-2">
                      <a href="../admin/module_edit.php?module_id=<?= urlencode($m['module_id']) ?>" class="px-3 py-1 rounded bg-sky-600 text-white text-sm">Edit</a>
                      <a href="../admin/module_resources.php?module_id=<?= urlencode($m['module_id']) ?>" class="px-3 py-1 rounded border bg-white text-sm">Resources</a>
                      <button onclick="previewModule(<?= intval($m['module_id']) ?>)" class="px-3 py-1 rounded border bg-white text-sm">Preview</button>
                    </div>
                  </div>
                </div>
              </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </section>
    </div>
  </main>

  <!-- Modal preview -->
  <div id="previewModal" class="fixed inset-0 hidden z-50 items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg w-11/12 md:w-2/3 p-5 max-h-[80vh] overflow-auto">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold">Module Preview</h3>
        <button onclick="closePreview()" class="text-gray-600">✕</button>
      </div>
      <div id="previewContent" class="text-sm text-gray-700"></div>
    </div>
  </div>

<script>
  // client helpers
  function filterModules() {
    const q = document.getElementById('search').value.trim().toLowerCase();
    document.querySelectorAll('#modulesGrid article[data-title]').forEach(a => {
      const title = a.getAttribute('data-title') || '';
      const id = a.getAttribute('data-id') || '';
      if (!q || title.includes(q) || id.includes(q)) a.style.display = '';
      else a.style.display = 'none';
    });
  }

  function sortModules() {
    const mode = document.getElementById('sort').value;
    const container = document.getElementById('modulesGrid');
    const nodes = Array.from(container.children);
    let sorted = nodes.slice();
    sorted.sort((A,B) => {
      const aId = parseInt(A.dataset.id||0,10), bId = parseInt(B.dataset.id||0,10);
      const aTitle = (A.dataset.title||''), bTitle = (B.dataset.title||'');
      const aComp = parseFloat(A.dataset.completion||0), bComp = parseFloat(B.dataset.completion||0);
      if (mode === 'id_asc') return aId - bId;
      if (mode === 'id_desc') return bId - aId;
      if (mode === 'title_asc') return aTitle.localeCompare(bTitle);
      if (mode === 'title_desc') return bTitle.localeCompare(aTitle);
      if (mode === 'completion_desc') return bComp - aComp;
      return 0;
    });
    sorted.forEach(n => container.appendChild(n));
  }

  function previewModule(id) {
    const node = document.querySelector('#modulesGrid article[data-id="'+id+'"]');
    if (!node) return;
    const title = node.querySelector('h3').innerText;
    const desc = node.querySelector('.mt-3 p') ? node.querySelector('.mt-3 p').innerText : '';
    const content = "<h4 class='font-semibold mb-2'>"+escapeHtml(title)+"</h4><div class='text-sm whitespace-pre-wrap'>"+escapeHtml(desc)+"</div>";
    document.getElementById('previewContent').innerHTML = content;
    document.getElementById('previewModal').classList.remove('hidden');
    document.getElementById('previewModal').classList.add('flex');
  }
  function closePreview() {
    document.getElementById('previewModal').classList.add('hidden');
    document.getElementById('previewModal').classList.remove('flex');
  }
  function escapeHtml(s){ return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'); }

  // Export visible modules to CSV
  function exportCSV(){
    const rows = [['Module ID','Title','Completion %','Completed','In Progress','Total Learners','Avg Score','Attempts']];
    document.querySelectorAll('#modulesGrid article[data-id]').forEach(a=>{
      if (a.style.display === 'none') return; // skip filtered out
      const id = a.dataset.id||'';
      const title = a.querySelector('h3') ? a.querySelector('h3').innerText.trim() : '';
      const comp = a.dataset.completion || '';
      const avg = a.querySelector('.text-lg.font-bold') ? a.querySelector('.text-lg.font-bold').innerText : '';
      const attempts = a.querySelector('.text-xs.text-gray-400') ? a.querySelector('.text-xs.text-gray-400').innerText : '';
      rows.push([id, title, comp, '', '', '', avg, attempts]);
    });
    const csv = rows.map(r => r.map(c => '"'+String(c).replace(/"/g,'""')+'"').join(',')).join("\n");
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'module_analytics.csv'; document.body.appendChild(a); a.click();
    URL.revokeObjectURL(url); a.remove();
  }

  // -------------------------
  // Aggressive content-only sidebar fix
  // -------------------------
  (function() {
    // selectors to attempt
    const selectors = ['aside', '#sidebar', '.sidebar', '.sidenav', 'nav[role="navigation"]', 'nav', '[data-sidebar]'];

    function numericZIndex(el) {
      const z = window.getComputedStyle(el).zIndex;
      const zNum = parseInt(z, 10);
      return Number.isFinite(zNum) ? zNum : 0;
    }
    function rectsOverlap(a, b) {
      return !(a.right <= b.left || a.left >= b.right || a.bottom <= b.top || a.top >= b.bottom);
    }

    // Try many heuristics to find the sidebar element
    function findSidebarCandidate() {
      for (const sel of selectors) {
        const candidates = Array.from(document.querySelectorAll(sel));
        for (const el of candidates) {
          const rect = el.getBoundingClientRect();
          if (rect.width < 40 || rect.height < 40) continue;
          // visible?
          const style = window.getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;
          return el;
        }
      }
      // If not found, try to find element that contains links/menus (common in sidebars)
      const linky = Array.from(document.querySelectorAll('nav a, aside a, .menu a'));
      if (linky.length > 4) {
        // pick ancestor of first link that is sizable
        let el = linky[0].closest('nav, aside, div, section');
        if (el) return el;
      }
      return null;
    }

    function adjustLayoutAggressive() {
      const main = document.getElementById('adminMain');
      if (!main) return;

      // reset first (so repeated calls are stable)
      main.style.marginLeft = '';
      main.style.paddingTop = '';

      const sidebar = findSidebarCandidate();
      if (!sidebar) return;

      const rectS = sidebar.getBoundingClientRect();
      const rectM = main.getBoundingClientRect();

      // if the sidebar overlaps or has higher z-index than main, force adjustments
      const sidebarZ = numericZIndex(sidebar);
      const mainZ = numericZIndex(main);
      const overlap = rectsOverlap(rectS, rectM);
      const zAbove = sidebarZ > mainZ;

      // Safety: don't push content off-screen on narrow screens
      const narrow = window.innerWidth <= 1024;

      // Gap to add beyond measured size
      const gap = 12;

      if (overlap || zAbove) {
        // If sidebar sits on top near page top => push content down
        if (rectS.top <= rectM.top + 10 && rectS.bottom > rectM.top + 4) {
          const extra = Math.max(0, Math.ceil(rectS.bottom - rectM.top) + gap);
          main.style.paddingTop = extra + 'px';
        }

        // If sidebar is left and extends into main => push content right
        if (rectS.left <= rectM.left + 10 && rectS.right > rectM.left + 4 && !narrow) {
          const extra = Math.max(0, Math.ceil(rectS.right - rectM.left) + gap);
          main.style.marginLeft = extra + 'px';
        }

        // As fallback: if sidebar covers the left half of the screen, assign a larger margin
        if (!narrow && rectS.width > window.innerWidth * 0.18 && rectS.right > window.innerWidth * 0.18) {
          main.style.marginLeft = Math.ceil(rectS.right) + gap + 'px';
        }
      } else {
        // If no overlap and z-order ok, ensure no forced margins
        main.style.marginLeft = '';
        main.style.paddingTop = '';
      }

      // Force a repaint/layout
      main.getBoundingClientRect();
    }

    // Run multiple times (initial + after potential sidebar script loads)
    function runWithRetries() {
      adjustLayoutAggressive();
      // re-run a few times to catch late-render sidebars
      setTimeout(adjustLayoutAggressive, 200);
      setTimeout(adjustLayoutAggressive, 600);
      setTimeout(adjustLayoutAggressive, 1200);
    }

    window.addEventListener('load', runWithRetries);
    window.addEventListener('resize', adjustLayoutAggressive);

    // watch for sidebar being injected or changed
    const observer = new MutationObserver(() => adjustLayoutAggressive());
    observer.observe(document.body, { childList: true, subtree: true });

    // Expose for manual re-call if needed
    window.adjustAdminContentLayout = adjustLayoutAggressive;
  })();
</script>
</body>
</html>
