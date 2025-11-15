<?php
// admin_manage_quizzes.php
// Single-file admin UI — defensive against missing/renamed id columns
// Place into admin/module1 and ensure ../sidebar.php exists

session_start();

// ------------------------
// DB connection
// ------------------------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// ------------------------
// Auth check (admin)
// ------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ------------------------
// Helpers
// ------------------------
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_exists($conn, $table){
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '{$t}'");
    return ($r && $r->num_rows > 0);
}

function column_exists($conn, $table, $column){
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return ($r && $r->num_rows > 0);
}

// Ensure upload folder exists
$uploadDir = __DIR__ . '/../uploads/quiz_files/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ------------------------
// fetch_result endpoint (AJAX used by "View" button)
// ------------------------
if (isset($_GET['fetch_result']) && is_numeric($_GET['fetch_result'])) {
    $id = (int)$_GET['fetch_result'];
    header('Content-Type: application/json');

    if (!table_exists($conn, 'quiz_results')) {
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT qr.*, COALESCE(u.full_name, 'Unknown') AS participant_name
            FROM quiz_results qr
            LEFT JOIN users u ON qr.participant_id = u.id
            WHERE qr.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode($row ?: []);
    } else {
        echo json_encode([]);
    }
    exit;
}

// ------------------------
// CSV export of quiz results
// ------------------------
if (isset($_GET['export']) && $_GET['export'] === 'results_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=quiz_results.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['participant','lesson_id','score','total_questions','status','taken_at']);

    if (table_exists($conn, 'quiz_results')) {
        $sql = "SELECT qr.*, COALESCE(u.full_name, 'Unknown') AS participant_name FROM quiz_results qr LEFT JOIN users u ON qr.participant_id = u.id ORDER BY qr.taken_at DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                fputcsv($out, [
                    $r['participant_name'],
                    $r['lesson_id'],
                    $r['score'],
                    $r['total_questions'],
                    $r['status'],
                    $r['taken_at']
                ]);
            }
            $res->free();
        }
    }
    fclose($out);
    exit;
}

// ------------------------
// Process File Upload
// ------------------------
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['quiz_file'])) {
    $lesson_id = (int)($_POST['lesson_id'] ?? 0);
    if (!empty($_FILES['quiz_file']['name'])) {
        $fileName = time() . "_" . preg_replace('/[^A-Za-z0-9\.\-_]/','_', basename($_FILES['quiz_file']['name']));
        $filePath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['quiz_file']['tmp_name'], $filePath)) {
            // ensure table exists
            if (!table_exists($conn, 'quiz_files')) {
                $conn->query("CREATE TABLE IF NOT EXISTS quiz_files (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    lesson_id INT DEFAULT NULL,
                    file_name VARCHAR(512),
                    file_path VARCHAR(1024),
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }

            $stmt = $conn->prepare("INSERT INTO quiz_files (lesson_id, file_name, file_path) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iss", $lesson_id, $fileName, $filePath);
                if ($stmt->execute()) $message = "✅ File uploaded successfully.";
                else $message = "❌ DB error saving file: " . esc($stmt->error);
                $stmt->close();
            } else {
                $message = "❌ DB prepare error: " . esc($conn->error);
            }
        } else {
            $message = "❌ File upload failed (move_uploaded_file).";
        }
    } else {
        $message = "❌ No file selected.";
    }
}

// ------------------------
// Fetch built-in questions (limit 50) - safe column handling
// ------------------------
$all_questions = [];
if (table_exists($conn, 'quiz_questions')) {
    // prefer ordering by 'id' if present, otherwise fallback to 'question_id' or nothing
    if (column_exists($conn, 'quiz_questions', 'id')) {
        $orderCol = 'id';
    } elseif (column_exists($conn, 'quiz_questions', 'question_id')) {
        $orderCol = 'question_id';
    } else {
        $orderCol = null;
    }

    if ($orderCol) {
        $sql = "SELECT * FROM quiz_questions ORDER BY `{$orderCol}` ASC LIMIT 50";
    } else {
        $sql = "SELECT * FROM quiz_questions LIMIT 50";
    }

    $res_all = $conn->query($sql);
    if ($res_all) {
        while ($row = $res_all->fetch_assoc()) $all_questions[] = $row;
        $res_all->free();
    }
}

// ------------------------
// Fetch quiz result stats (defensive)
// ------------------------
$total = $passed = $failed = 0;
if (table_exists($conn, 'quiz_results')) {
    $res_stats = $conn->query("SELECT status, COUNT(*) as cnt FROM quiz_results GROUP BY status");
    if ($res_stats) {
        while ($r = $res_stats->fetch_assoc()) {
            $total += (int)$r['cnt'];
            if (strcasecmp($r['status'],'passed') === 0) $passed = (int)$r['cnt'];
            if (strcasecmp($r['status'],'failed') === 0) $failed = (int)$r['cnt'];
        }
        $res_stats->free();
    }
}

// ------------------------
// Fetch recent quiz results list (defensive)
// ------------------------
$recent_results = [];
if (table_exists($conn, 'quiz_results')) {
    // order safely by taken_at if exists otherwise id if exists
    if (column_exists($conn,'quiz_results','taken_at')) {
        $orderBy = 'qr.taken_at DESC';
    } elseif (column_exists($conn,'quiz_results','id')) {
        $orderBy = 'qr.id DESC';
    } else {
        $orderBy = 'qr.taken_at DESC';
    }
    $sql = "SELECT qr.*, COALESCE(u.full_name, 'Unknown') AS participant_name FROM quiz_results qr LEFT JOIN users u ON qr.participant_id = u.id ORDER BY {$orderBy} LIMIT 200";
    $r = $conn->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) $recent_results[] = $row;
        $r->free();
    }
}

// ------------------------
// Fetch uploaded quiz files (if table exists)
// ------------------------
$quiz_files = [];
if (table_exists($conn, 'quiz_files')) {
    // order by uploaded_at if available, else by id if exists
    if (column_exists($conn, 'quiz_files', 'uploaded_at')) {
        $fres = $conn->query("SELECT * FROM quiz_files ORDER BY uploaded_at DESC LIMIT 100");
    } elseif (column_exists($conn, 'quiz_files', 'id')) {
        $fres = $conn->query("SELECT * FROM quiz_files ORDER BY id DESC LIMIT 100");
    } else {
        $fres = $conn->query("SELECT * FROM quiz_files LIMIT 100");
    }
    if ($fres) {
        while ($frow = $fres->fetch_assoc()) $quiz_files[] = $frow;
        $fres->free();
    }
}

// ------------------------
// Small helper to detect fields for rendering options on questions
// ------------------------
function get_question_text($q) {
    if (isset($q['question'])) return $q['question'];
    if (isset($q['q']) ) return $q['q'];
    if (isset($q['text'])) return $q['text'];
    return '[no question text]';
}

function get_option($q, $label) {
    $keys = [
        "option_{$label}", strtolower($label), strtoupper($label), "opt_{$label}"
    ];
    foreach ($keys as $k) {
        if (isset($q[$k]) && $q[$k] !== null && $q[$k] !== '') return $q[$k];
    }
    return '';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin — Manage Quizzes</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden;background:#f8fafc}
    .main {flex:1;display:flex;flex-direction:column;min-width:0}
    .main-scroll{flex:1;overflow:auto;padding:1.25rem}
    .card-scroll{max-height:48vh;overflow:auto;padding-right:8px}
    .modal-backdrop{background:rgba(2,6,23,0.45)}
    .main-scroll::-webkit-scrollbar{width:10px}
    .main-scroll::-webkit-scrollbar-thumb{background-color:rgba(2,6,23,0.06);border-radius:8px}
  </style>
</head>
<body class="font-sans text-slate-800">

<div class="app">
  <?php include '../sidebar.php'; ?>

  <div class="main">
    <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold">Manage Quizzes</h1>
        <div class="text-sm text-slate-500">Upload quiz files, review built-in questions and monitor results</div>
      </div>

      <div class="flex items-center gap-3">
        <a href="?export=results_csv" class="px-3 py-2 bg-gray-100 rounded-lg text-sm">Export Results CSV</a>
        <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'Admin') ?></strong></div>
      </div>
    </header>

    <main class="main-scroll">
      <div class="max-w-6xl mx-auto space-y-6">
        <!-- Analytics -->
        <section class="bg-white p-6 rounded-2xl shadow grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
          <div>
            <h2 class="text-lg font-semibold">Quiz Results</h2>
            <p class="text-sm text-slate-500">Overview of recent quiz attempts</p>
          </div>
          <div class="flex items-center gap-6">
            <div class="p-4 bg-slate-50 rounded-lg">
              <div class="text-sm text-slate-500">Total attempts</div>
              <div class="text-2xl font-bold"><?= (int)$total ?></div>
            </div>
            <div class="p-4 bg-emerald-50 rounded-lg">
              <div class="text-sm text-emerald-700">Passed</div>
              <div class="text-2xl font-bold text-emerald-700"><?= (int)$passed ?></div>
            </div>
            <div class="p-4 bg-rose-50 rounded-lg">
              <div class="text-sm text-rose-700">Failed</div>
              <div class="text-2xl font-bold text-rose-700"><?= (int)$failed ?></div>
            </div>
          </div>
          <div class="text-right hidden md:block">
            <div class="text-sm text-slate-500">Quick Actions</div>
            <div class="mt-2 flex justify-end gap-2">
              <a href="#upload" class="px-3 py-2 rounded bg-indigo-600 text-white">Upload Quiz File</a>
              <button onclick="document.getElementById('questionsList').scrollIntoView({behavior:'smooth'})" class="px-3 py-2 rounded bg-slate-100">View Built-in Questions</button>
            </div>
          </div>
        </section>

        <!-- Recent results table -->
        <section class="bg-white p-4 rounded-2xl shadow">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold">Recent Quiz Attempts</h3>
            <div class="text-sm text-slate-500">Most recent attempts (up to 200)</div>
          </div>

          <div class="overflow-auto card-scroll">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
                <tr>
                  <th class="px-3 py-2 text-left">Participant</th>
                  <th class="px-3 py-2 text-left">Lesson ID</th>
                  <th class="px-3 py-2 text-left">Score</th>
                  <th class="px-3 py-2 text-left">Status</th>
                  <th class="px-3 py-2 text-left">Taken At</th>
                  <th class="px-3 py-2 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($recent_results) > 0): foreach ($recent_results as $r): ?>
                  <tr class="border-b hover:bg-gray-50">
                    <td class="px-3 py-2 font-medium"><?= esc($r['participant_name'] ?? 'Unknown') ?></td>
                    <td class="px-3 py-2"><?= esc($r['lesson_id'] ?? '-') ?></td>
                    <td class="px-3 py-2"><?= esc($r['score'] ?? '-') ?> / <?= esc($r['total_questions'] ?? '-') ?></td>
                    <td class="px-3 py-2 <?= (strtolower($r['status'] ?? '') === 'passed') ? 'text-emerald-600 font-semibold' : 'text-rose-600 font-semibold' ?>"><?= esc($r['status'] ?? '-') ?></td>
                    <td class="px-3 py-2"><?= esc($r['taken_at'] ?? '-') ?></td>
                    <td class="px-3 py-2">
                      <div class="flex gap-2">
                        <button class="px-2 py-1 bg-slate-100 rounded view-result-btn" data-id="<?= (int)($r['id'] ?? 0) ?>">View</button>
                        <a href="mailto:?subject=Quiz%20Result&body=See%20result%20for%20<?= rawurlencode($r['participant_name'] ?? '') ?>" class="px-2 py-1 bg-gray-100 rounded">Email</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="6" class="p-4 text-center text-slate-500">No quiz attempts recorded yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Built-in questions -->
        <section id="questionsList" class="bg-white p-6 rounded-2xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Built-in Quiz Questions (up to 50)</h3>
            <div class="text-sm text-slate-500"><?= count($all_questions) ?> items</div>
          </div>

          <?php if (count($all_questions) > 0): ?>
            <ol class="space-y-4 list-decimal pl-6">
              <?php foreach ($all_questions as $q):
                $qText = get_question_text($q);
                $a = get_option($q,'a');
                $b = get_option($q,'b');
                $c = get_option($q,'c');
                $d = get_option($q,'d');
                $correct = $q['correct_option'] ?? ($q['answer'] ?? '');
              ?>
                <li class="p-4 border rounded-lg bg-gray-50">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <p class="font-medium text-slate-800"><?= esc($qText) ?></p>
                      <ul class="mt-2 text-sm text-slate-600">
                        <?php if ($a !== ''): ?><li>A. <?= esc($a) ?></li><?php endif; ?>
                        <?php if ($b !== ''): ?><li>B. <?= esc($b) ?></li><?php endif; ?>
                        <?php if ($c !== ''): ?><li>C. <?= esc($c) ?></li><?php endif; ?>
                        <?php if ($d !== ''): ?><li>D. <?= esc($d) ?></li><?php endif; ?>
                      </ul>
                      <p class="mt-2 text-sm text-emerald-700 font-semibold">Correct: <?= esc($correct) ?></p>
                    </div>
                    <div class="text-right">
                      <button class="px-3 py-1 bg-slate-100 rounded view-question-btn" data-q='<?= esc(json_encode($q, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Preview</button>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <p class="text-slate-500">No built-in questions found. Add questions in the builder or import one.</p>
          <?php endif; ?>
        </section>

        <!-- Upload Quiz File -->
        <section id="upload" class="bg-white p-6 rounded-2xl shadow max-w-3xl mx-auto">
          <h3 class="text-lg font-semibold mb-2">Upload Quiz File</h3>
          <p class="text-sm text-slate-500 mb-4">Attach files (PDF, DOC, DOCX). Files are saved to <code>/uploads/quiz_files</code>.</p>

          <?php if ($message): ?>
            <div class="p-3 mb-4 rounded <?= strpos($message,'✅') === 0 ? 'bg-emerald-50 border border-emerald-100 text-emerald-800' : 'bg-rose-50 border border-rose-100 text-rose-800' ?>">
              <?= esc($message) ?>
            </div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
              <label class="text-sm font-medium block mb-1">Lesson ID (optional)</label>
              <input type="number" name="lesson_id" class="w-full px-3 py-2 border rounded" placeholder="Enter lesson ID (if applicable)" />
            </div>
            <div>
              <label class="text-sm font-medium block mb-1">Select file</label>
              <input type="file" name="quiz_file" class="block w-full" accept=".pdf,.doc,.docx" />
            </div>
            <div>
              <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Upload File</button>
            </div>
          </form>

          <?php if (count($quiz_files) > 0): ?>
            <div class="mt-6">
              <h4 class="text-sm font-semibold mb-2">Recent Uploads</h4>
              <ul class="space-y-2 text-sm">
                <?php foreach ($quiz_files as $f): ?>
                  <li class="flex items-center justify-between bg-slate-50 p-2 rounded">
                    <div>
                      <div class="font-medium"><?= esc($f['file_name']) ?></div>
                      <div class="text-xs text-slate-500">Lesson: <?= esc($f['lesson_id'] ?: '-') ?> • Uploaded: <?= esc($f['uploaded_at'] ?? '') ?></div>
                    </div>
                    <div class="flex gap-2">
                      <a href="<?= esc($f['file_path']) ?>" target="_blank" class="px-2 py-1 rounded bg-sky-50 text-sky-700">Open</a>
                      <a href="<?= esc($f['file_path']) ?>" download class="px-2 py-1 rounded bg-gray-100">Download</a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </section>

      </div>
    </main>
  </div>
</div>

<!-- Modal: view question -->
<div id="questionModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="absolute inset-0 bg-black/40"></div>
  <div class="relative bg-white rounded-2xl shadow-lg w-11/12 max-w-2xl z-10 overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold" id="qModalTitle">Question Preview</h3>
      <button id="qModalClose" class="text-slate-600">&times;</button>
    </div>
    <div class="p-4 max-h-[60vh] overflow-auto" id="qModalBody">Loading…</div>
    <div class="p-4 border-t text-sm text-slate-500">Use this to inspect the question object & options.</div>
  </div>
</div>

<script>
  lucide.createIcons();

  // view question modal
  const qModal = document.getElementById('questionModal');
  const qModalBody = document.getElementById('qModalBody');
  const qModalClose = document.getElementById('qModalClose');
  document.querySelectorAll('.view-question-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const raw = btn.getAttribute('data-q');
      try {
        const obj = JSON.parse(raw);
        qModalBody.innerHTML = `<pre class="whitespace-pre-wrap text-sm">${escapeHtml(JSON.stringify(obj, null, 2))}</pre>`;
        qModal.classList.remove('hidden'); qModal.classList.add('flex');
      } catch(e) {
        qModalBody.innerHTML = '<div class="text-rose-600">Unable to parse question data.</div>';
        qModal.classList.remove('hidden'); qModal.classList.add('flex');
      }
    });
  });
  qModalClose.addEventListener('click', ()=>{ qModal.classList.remove('flex'); qModal.classList.add('hidden'); });

  // view result button (simple viewer)
  document.querySelectorAll('.view-result-btn').forEach(b=>{
    b.addEventListener('click', ()=>{
      const id = b.dataset.id;
      if (!id || id === '0') { alert('No record id available.'); return; }
      fetch('?fetch_result='+encodeURIComponent(id))
        .then(r=>r.json()).then(data=>{
          if (!data || Object.keys(data).length===0) {
            alert('Record not found.');
            return;
          }
          const details = [
            ['Participant', data.participant_name || 'Unknown'],
            ['Lesson ID', data.lesson_id || '-'],
            ['Score', (data.score||'-') + ' / ' + (data.total_questions||'-')],
            ['Status', data.status || '-'],
            ['Taken at', data.taken_at || '-'],
            ['Notes', data.answers || data.meta || '-']
          ];
          let html = '<div style="font-family:system-ui, sans-serif;padding:16px;">';
          details.forEach(d=> html += `<div style="margin-bottom:8px;"><strong>${escapeHtml(d[0])}:</strong> ${escapeHtml(d[1])}</div>`);
          html += '</div>';
          const w = window.open('', '_blank', 'width=600,height=500');
          w.document.write(html);
        }).catch(err=>{
          alert('Failed to load result details.');
        });
    });
  });

  function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str).replace(/[&<>"'`=\/]/g, function(s){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[s]; });
  }
</script>

</body>
</html>
