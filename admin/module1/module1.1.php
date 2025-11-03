<?php
// admin_content_expanded.php
// Single-file Admin UI — Expanded Module & Lesson Cards (improved accessibility/readability)
// Database: training_management

session_start();

/* CONFIG */
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "training_management";

$uploadRoot = __DIR__ . '/uploads/';
@mkdir($uploadRoot, 0755, true);
$uploadModules = $uploadRoot . 'modules/'; @mkdir($uploadModules,0755,true);
$uploadLessons = $uploadRoot . 'lessons/'; @mkdir($uploadLessons,0755,true);
$uploadDrills  = $uploadRoot . 'drills/';  @mkdir($uploadDrills,0755,true);

$allowedExtensions = ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png'];
$maxFileSizeMB = 12;

$presetDisasterTypes = [
  "All Disaster Type","Earthquake","Tsunami","Volcanic Eruption","Flood",
  "Typhoon / Hurricane / Cyclone","Tornado","Landslide","Avalanche","Wildfire",
  "Drought","Heatwave","Coldwave / Extreme Cold","Pandemic / Epidemic",
  "Biological Incident","Chemical Spill / Hazmat","Radiological / Nuclear",
  "Power Outage / Blackout","Infrastructure Collapse (structures, bridges)",
  "Mass Casualty Incident","Transport Accident (road, rail, air, maritime)",
  "Oil Spill","Water Contamination","Food Shortage / Famine","Sinkhole",
  "Cyberattack / ICT disruption","Terrorism / Violent Attack","Urban Fire",
  "Industrial Accident","Extreme Storm / Hail","Other"
];

/* DB CONNECT */
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) die("DB connect error: " . htmlspecialchars($conn->connect_error));
$conn->set_charset('utf8mb4');

/* AUTH CHECK (Admin) */
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

/* CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));

/* helpers */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function short($s,$n=220){ $s = strip_tags($s ?? ''); if (mb_strlen($s) <= $n) return $s; return mb_substr($s,0,$n).'…'; }

/* quick counts */
$cntModules = (int)($conn->query("SELECT COUNT(*) AS c FROM training_modules")->fetch_assoc()['c'] ?? 0);
$cntLessons = (int)($conn->query("SELECT COUNT(*) AS c FROM lessons")->fetch_assoc()['c'] ?? 0);
$cntDrills  = (int)($conn->query("SELECT COUNT(*) AS c FROM drills")->fetch_assoc()['c'] ?? 0);

/* fetch collections (limit reasonable) */
$modules = $conn->query("SELECT id,title,disaster_type,file_name,created_at,description,objectives FROM training_modules ORDER BY id DESC LIMIT 40");
$lessons = $conn->query("SELECT l.id,l.title,l.disaster_type,l.scheduled_date,l.file_path,l.created_at,l.content, p.target, l.published
                         FROM lessons l LEFT JOIN module_postings p ON p.lesson_id = l.id
                         ORDER BY l.created_at DESC LIMIT 40");
$drills = $conn->query("SELECT id,title,`date`,type,file_path,created_at FROM drills ORDER BY `date` DESC LIMIT 20");

/* trainers list */
$trainers = [];
$qr = $conn->query("SELECT id, email, CONCAT(COALESCE(name,''),' (',email,')') AS label FROM users WHERE role='trainer' ORDER BY email ASC");
if ($qr) while ($r=$qr->fetch_assoc()) $trainers[] = $r;

/* merge types */
$typesFromDb = [];
$rt = $conn->query("SELECT DISTINCT disaster_type FROM lessons WHERE disaster_type IS NOT NULL AND disaster_type <> ''");
while ($tr = $rt->fetch_assoc()) if ($tr['disaster_type']) $typesFromDb[] = $tr['disaster_type'];
$mergedTypes = array_unique(array_merge($presetDisasterTypes, $typesFromDb));
if (($k=array_search('All Disaster Type',$mergedTypes))!==false) unset($mergedTypes[$k]);
$typesArr = array_values(array_merge(['All Disaster Type'], $mergedTypes));

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Expanded Cards UI</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden;background:#f8fafc}
    .main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
    main.scrollable{flex:1;overflow:auto;padding:1.5rem;-webkit-overflow-scrolling:touch}
    /* LARGER CARD STYLES */
    .big-card { background: linear-gradient(180deg,#fff,#fbfdff); border:1px solid rgba(2,6,23,0.04); border-radius:14px; padding:18px; box-shadow:0 8px 24px rgba(2,6,23,0.04); }
    .big-title { font-size:1.05rem; font-weight:700; line-height:1.25; }
    .big-meta { font-size:0.94rem; color:#475569; }
    .big-desc { font-size:0.95rem; color:#0f172a; line-height:1.6; margin-top:0.5rem; }
    .card-actions button { font-size:0.92rem; padding:.5rem .75rem; }
    .card-grid { display:grid; grid-template-columns:1fr; gap:14px; }
    @media(min-width:1024px){
      .card-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
      .left-col { max-width:420px; }
    }
    /* ensure content area scrolls comfortably */
    .card-scroll { max-height:58vh; overflow:auto; padding-right:8px; }
    .modal-backdrop { background: rgba(2,6,23,0.55); }
    .expand-body { white-space:pre-wrap; line-height:1.6; font-size:0.98rem; color:#0f172a; }
  </style>
</head>
<body class="font-sans text-slate-800">

<div class="app">
  <?php include '../sidebar.php'; ?>

  <div class="main-wrap">
    <header class="bg-white border-b sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold">Content Management</h1>
          <p class="text-sm text-slate-500">Larger cards & readable content for modules and lessons.</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="text-sm text-slate-600">Role: Admin</div>
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-600 to-sky-500 text-white flex items-center justify-center"><?= e(strtoupper(substr($_SESSION['name'] ?? ($_SESSION['username'] ?? 'AD'),0,2))) ?></div>
        </div>
      </div>
    </header>

    <main class="scrollable">
      <div class="max-w-7xl mx-auto space-y-6">
        <!-- KPI row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="big-card">
            <div class="big-meta">Modules</div>
            <div class="big-title text-2xl"><?= e($cntModules) ?></div>
            <div class="text-sm text-slate-500 mt-2">Structured course units. Click a module card to edit or view details.</div>
          </div>
          <div class="big-card">
            <div class="big-meta">Lessons</div>
            <div class="big-title text-2xl"><?= e($cntLessons) ?></div>
            <div class="text-sm text-slate-500 mt-2">Full lesson content, schedules and targets.</div>
          </div>
          <div class="big-card">
            <div class="big-meta">Drills</div>
            <div class="big-title text-2xl"><?= e($cntDrills) ?></div>
            <div class="text-sm text-slate-500 mt-2">Planned drills and events. Export CSV for reporting.</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- forms column (left) -->
          <aside class="left-col space-y-4">
            <div class="big-card">
              <h2 class="text-lg font-semibold">Quick Create</h2>
              <p class="text-sm text-slate-500 mt-1">Create a module, lesson or drill quickly. Use the forms below to add content.</p>
              <!-- Small forms — submit via your existing endpoints -->
              <div class="mt-4 space-y-3">
                <form id="quickLesson" enctype="multipart/form-data">
                  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                  <input type="hidden" name="action" value="lesson_create">
                  <label class="text-sm font-medium">Title</label>
                  <input name="title" required class="mt-1 w-full px-3 py-2 border rounded-md" placeholder="Lesson title">
                  <label class="text-sm font-medium mt-2">Short content</label>
                  <textarea name="content" rows="4" class="mt-1 w-full px-3 py-2 border rounded-md" placeholder="Short summary or step-by-step..."></textarea>
                  <div class="flex gap-2 justify-end mt-3">
                    <button type="submit" class="px-3 py-2 bg-emerald-600 text-white rounded-md">Create</button>
                  </div>
                </form>
              </div>
            </div>

            <div class="big-card">
              <h3 class="text-base font-semibold">Create Drill</h3>
              <form id="quickDrill" class="mt-3">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input name="title" placeholder="Drill title" class="w-full px-3 py-2 border rounded-md" />
                <div class="grid grid-cols-2 gap-2 mt-2">
                  <input type="date" name="date" class="px-3 py-2 border rounded-md" />
                  <input name="type" placeholder="Type (Drill/Workshop)" class="px-3 py-2 border rounded-md" />
                </div>
                <div class="flex justify-end mt-3">
                  <button type="submit" class="px-3 py-2 bg-indigo-600 text-white rounded-md">Publish Drill</button>
                </div>
              </form>
            </div>
          </aside>

          <!-- center+right: cards -->
          <section class="lg:col-span-2 space-y-6">
            <!-- MODULES area -->
            <div class="big-card">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="text-lg font-semibold">Modules</h3>
                  <div class="text-sm text-slate-500">Larger, readable cards — expand to view full content.</div>
                </div>
                <div class="text-sm text-slate-500"><?= (int)$cntModules ?> total</div>
              </div>

              <div class="card-grid card-scroll">
                <?php if ($modules && $modules->num_rows): while ($m = $modules->fetch_assoc()): ?>
                  <article class="big-card" style="display:flex;gap:14px;align-items:flex-start;">
                    <div style="min-width:82px;flex:0 0 82px;">
                      <div class="w-20 h-20 rounded-lg bg-indigo-50 flex items-center justify-center">
                        <i data-lucide="folder" class="w-6 h-6 text-indigo-600"></i>
                      </div>
                    </div>

                    <div style="flex:1 1 auto;">
                      <div class="big-title"><?= e($m['title']) ?></div>
                      <div class="big-meta mt-1"><?= e($m['disaster_type'] ?? '—') ?> • <?= e(date('M d, Y', strtotime($m['created_at'] ?? 'now'))) ?></div>
                      <div class="big-desc mt-3"><?= e(strlen($m['objectives'] ? $m['objectives'] : ($m['description'] ?? '')) ? short($m['objectives'] ?: $m['description'], 700) : 'No description') ?></div>

                      <div class="mt-4 flex items-center gap-2 card-actions">
                        <?php if (!empty($m['file_name'])): ?>
                          <a class="inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm bg-sky-50 text-sky-700" href="<?= e('uploads/modules/'. $m['file_name']) ?>" target="_blank"><i data-lucide="download" class="w-4 h-4"></i>Resource</a>
                        <?php else: ?>
                          <span class="px-3 py-2 text-sm bg-slate-50 rounded text-slate-600">No resource</span>
                        <?php endif; ?>

                        <button class="px-3 py-2 bg-slate-50 rounded text-sm" onclick="openModuleModal(<?= (int)$m['id'] ?>)">Expand</button>
                        <button class="px-3 py-2 bg-amber-50 rounded text-sm" onclick="editModule(<?= (int)$m['id'] ?>)">Edit</button>
                      </div>
                    </div>
                  </article>
                <?php endwhile; else: ?>
                  <div class="p-4 text-slate-500">No modules found.</div>
                <?php endif; ?>
              </div>
            </div>

            <!-- LESSONS area -->
            <div class="big-card">
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="text-lg font-semibold">Lessons</h3>
                  <div class="text-sm text-slate-500">Full content is visible in cards — use Expand to review attachments and quizzes.</div>
                </div>
                <div class="text-sm text-slate-500"><?= (int)$cntLessons ?> total</div>
              </div>

              <div class="card-grid card-scroll">
                <?php if ($lessons && $lessons->num_rows): while ($l = $lessons->fetch_assoc()): ?>
                  <article class="big-card" style="display:flex;gap:14px;align-items:flex-start;">
                    <div style="min-width:82px;flex:0 0 82px;">
                      <div class="w-20 h-20 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <i data-lucide="book-open" class="w-6 h-6 text-emerald-600"></i>
                      </div>
                    </div>

                    <div style="flex:1 1 auto;">
                      <div class="big-title"><?= e($l['title']) ?></div>

                      <div class="big-meta mt-1">
                        <?= e($l['disaster_type'] ?? '—') ?> • Scheduled: <?= e($l['scheduled_date'] ?? '—') ?> • Target: <?= e($l['target'] ?? 'All') ?>
                      </div>

                      <div class="big-desc mt-3"><?= e(short($l['content'] ?? '', 700)) ?></div>

                      <div class="mt-4 flex items-center gap-2 card-actions">
                        <?php if (!empty($l['file_path'])): ?>
                          <a class="inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm bg-sky-50 text-sky-700" href="<?= e($l['file_path']) ?>" target="_blank"><i data-lucide="paperclip" class="w-4 h-4"></i>Attachment</a>
                        <?php else: ?>
                          <span class="px-3 py-2 text-sm bg-slate-50 rounded">No file</span>
                        <?php endif; ?>

                        <button class="px-3 py-2 bg-slate-50 rounded text-sm" onclick="previewLesson(<?= (int)$l['id'] ?>)">Expand</button>
                        <button class="px-3 py-2 bg-sky-600 text-white rounded text-sm" onclick="openQuiz(<?= (int)$l['id'] ?>)">Quiz</button>
                        <button class="px-3 py-2 bg-rose-50 text-rose-700 rounded text-sm" onclick="deleteLesson(<?= (int)$l['id'] ?>)">Delete</button>
                      </div>
                    </div>
                  </article>
                <?php endwhile; else: ?>
                  <div class="p-4 text-slate-500">No lessons available.</div>
                <?php endif; ?>
              </div>
            </div>

            <!-- DRILLS table smaller -->
            <div class="big-card">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Upcoming Drills</h3>
                <a href="module1.3.php?export=csv" class="text-sm text-slate-500">Export CSV</a>
              </div>
              <div class="overflow-auto">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 sticky top-0">
                    <tr>
                      <th class="px-3 py-2 text-left">Title</th>
                      <th class="px-3 py-2 text-left">Type</th>
                      <th class="px-3 py-2 text-left">Date</th>
                      <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($drills && $drills->num_rows): while ($d = $drills->fetch_assoc()): ?>
                      <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-3"><?= e($d['title']) ?></td>
                        <td class="px-3 py-3"><?= e($d['type']) ?></td>
                        <td class="px-3 py-3"><?= e($d['date']) ?></td>
                        <td class="px-3 py-3">
                          <div class="flex gap-2">
                            <button class="px-2 py-1 bg-slate-50 rounded" onclick="editDrill(<?= (int)$d['id'] ?>)">Edit</button>
                            <button class="px-2 py-1 bg-rose-50 text-rose-700 rounded" onclick="deleteDrill(<?= (int)$d['id'] ?>)">Delete</button>
                          </div>
                        </td>
                      </tr>
                    <?php endwhile; else: ?>
                      <tr><td colspan="4" class="p-4 text-slate-500">No drills scheduled.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </section>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- MODULE Expand Modal -->
<div id="moduleModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="modal-backdrop absolute inset-0"></div>
  <div class="relative bg-white rounded-xl shadow-lg w-11/12 max-w-4xl z-20 overflow-auto" style="max-height:88vh;">
    <div class="p-4 border-b flex items-start justify-between">
      <div>
        <h3 id="modTitle" class="text-xl font-semibold"></h3>
        <div id="modMeta" class="text-sm text-slate-500 mt-1"></div>
      </div>
      <div class="flex items-center gap-2">
        <button class="px-3 py-2 bg-gray-100 rounded" onclick="closeModuleModal()">Close</button>
      </div>
    </div>
    <div class="p-6">
      <div id="modBody" class="expand-body"></div>
      <div id="modFile" class="mt-4"></div>
    </div>
  </div>
</div>

<!-- LESSON Expand Modal (reuse preview from before) -->
<div id="lessonModal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="modal-backdrop absolute inset-0"></div>
  <div class="relative bg-white rounded-xl shadow-lg w-11/12 max-w-4xl z-20 overflow-auto" style="max-height:88vh;">
    <div class="p-4 border-b flex items-start justify-between">
      <div>
        <h3 id="lsTitle" class="text-xl font-semibold"></h3>
        <div id="lsMeta" class="text-sm text-slate-500 mt-1"></div>
      </div>
      <div class="flex items-center gap-2">
        <button class="px-3 py-2 bg-amber-50 rounded" onclick="approveLesson()">Approve</button>
        <button class="px-3 py-2 bg-gray-100 rounded" onclick="closeLessonModal()">Close</button>
      </div>
    </div>
    <div class="p-6">
      <div id="lsBody" class="expand-body"></div>
      <div id="lsFile" class="mt-4"></div>
    </div>
  </div>
</div>

<script>lucide.createIcons();</script>
<script>
const API = location.pathname;
const CSRF = "<?= e($_SESSION['csrf']) ?>";

/* Utility to open/close module modal by fetching server-side data */
async function openModuleModal(id){
  try {
    const r = await fetch('?fetch_module=' + encodeURIComponent(id));
    const data = await r.json();
    if (!data || !data.id) return alert('Module not found');
    document.getElementById('modTitle').textContent = data.title || 'Module';
    document.getElementById('modMeta').textContent = (data.disaster_type||'') + ' • ' + (data.created_at||'');
    document.getElementById('modBody').textContent = data.objectives || data.description || 'No description';
    const modFile = document.getElementById('modFile'); modFile.innerHTML = '';
    if (data.file_name) {
      const a = document.createElement('a'); a.href = 'uploads/modules/' + data.file_name; a.target='_blank'; a.textContent = 'Open Resource'; a.className='text-sky-600';
      modFile.appendChild(a);
    }
    const m = document.getElementById('moduleModal'); m.classList.remove('hidden'); m.classList.add('flex');
  } catch (err) {
    alert('Failed to load module.');
  }
}
function closeModuleModal(){ const m=document.getElementById('moduleModal'); m.classList.add('hidden'); m.classList.remove('flex'); }

/* Preview lesson (full content) */
async function previewLesson(id){
  try {
    const r = await fetch('?fetch_lesson=' + encodeURIComponent(id));
    const data = await r.json();
    if (!data || !data.id) return alert('Lesson not found');
    document.getElementById('lsTitle').textContent = data.title || 'Lesson';
    document.getElementById('lsMeta').textContent = (data.disaster_type||'') + ' • ' + (data.target||'All');
    document.getElementById('lsBody').textContent = data.content || 'No content';
    const fileBox = document.getElementById('lsFile'); fileBox.innerHTML = '';
    if (data.file_path) {
      const ext = (data.file_path.split('.').pop()||'').toLowerCase();
      if (['jpg','jpeg','png'].includes(ext)) {
        const img = document.createElement('img'); img.src = data.file_path; img.className='max-w-full rounded-md';
        fileBox.appendChild(img);
      } else if (ext === 'pdf') {
        const iframe = document.createElement('iframe'); iframe.src = data.file_path; iframe.style.width='100%'; iframe.style.height='520px'; iframe.className='rounded-md border';
        fileBox.appendChild(iframe);
      } else {
        const a = document.createElement('a'); a.href = data.file_path; a.target = '_blank'; a.textContent = 'Open attachment'; a.className='text-sky-600';
        fileBox.appendChild(a);
      }
    }
    document.getElementById('lessonModal').classList.remove('hidden');
    document.getElementById('lessonModal').classList.add('flex');
    document.getElementById('lessonModal').dataset.lessonId = id;
  } catch (err) { alert('Failed to load lesson preview'); }
}
function closeLessonModal(){ const m=document.getElementById('lessonModal'); m.classList.add('hidden'); m.classList.remove('flex'); m.dataset.lessonId=''; }

/* Open quiz (simplified — uses your endpoint) */
async function openQuiz(lessonId){
  try {
    const r = await fetch('?fetch_quiz=' + encodeURIComponent(lessonId));
    const payload = await r.json();
    if (!payload || payload.error) return alert(payload.error || 'No quiz');
    // build a small inline view — for brevity use browser alert or open new window
    let out = 'Quiz: ' + (payload.quiz.title || '') + '\\n\\n';
    payload.questions.forEach((q, i) => {
      out += (i+1) + '. ' + (q.question || '') + '\\n';
    });
    alert(out);
  } catch (err) { alert('Failed to open quiz'); }
}

/* Approve lesson (publish) */
async function approveLesson(){
  const modal = document.getElementById('lessonModal');
  const id = modal.dataset.lessonId;
  if (!id) return alert('No lesson selected');
  if (!confirm('Approve and publish this lesson?')) return;
  const fd = new FormData(); fd.append('action','approve'); fd.append('lesson_id', id);
  const res = await fetch(API, { method:'POST', body: fd }); const j = await res.json();
  if (j.success) { alert('Published'); location.reload(); } else alert(j.error || 'Approve failed');
}

/* Delete helpers — call your endpoints (ensure they exist) */
async function deleteLesson(id){
  if (!confirm('Delete lesson? This is irreversible.')) return;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id', id); fd.append('csrf', CSRF);
  const res = await fetch(API, { method:'POST', body: fd }); const j = await res.json();
  if (j.success) location.reload(); else alert(j.error || 'Delete failed');
}
async function deleteDrill(id){
  if (!confirm('Delete drill?')) return;
  const fd = new FormData(); fd.append('action','delete_drill'); fd.append('id', id); fd.append('csrf', CSRF);
  const res = await fetch(API, { method:'POST', body: fd }); const j = await res.json();
  if (j.success) location.reload(); else alert(j.error || 'Delete failed');
}

/* Edit module (populate quick form) */
async function editModule(id){
  try {
    const r = await fetch('?fetch_module=' + encodeURIComponent(id));
    const data = await r.json();
    if (!data || !data.id) return alert('Module not found');
    // populate the quick form for modules (we didn't implement a dedicated module form in this file)
    // For now open module modal to signal user to use Edit in the full editor
    openModuleModal(id);
  } catch (err) { alert('Failed to load module for edit'); }
}

/* small helpers for quick forms (submit to server endpoints) */
document.getElementById('quickLesson').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd = new FormData(this);
  // map to your server endpoint — action 'create' or 'create_lesson' depending on backend. Adjust as needed.
  fd.append('action','create'); // adjust if your endpoint expects create
  const res = await fetch(API, { method:'POST', body: fd });
  const j = await res.json();
  if (j.success) { alert('Lesson created'); location.reload(); } else alert(j.error || 'Create failed');
});
document.getElementById('quickDrill').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('action','create_drill'); // adjust to your server
  const res = await fetch(API, { method:'POST', body: fd });
  const j = await res.json();
  if (j.success) { alert('Drill published'); location.reload(); } else alert(j.error || 'Publish failed');
});

/* fetch handlers for modal endpoints — you may need to add server-side ?fetch_module and ?fetch_lesson endpoints; if they already exist, good */
</script>
</body>
</html>
