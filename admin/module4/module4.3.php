<?php
session_start();

// -----------------------------
// Simple role check (Admin-only)
// -----------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Forbidden: Admins only.";
    exit;
}

// -----------------------------
// DB Connection (PDO)
// -----------------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'simulation_event_planning';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo "DB Connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

// -----------------------------
// Create table if not exists (simple schema)
// -----------------------------
$pdo->exec("
CREATE TABLE IF NOT EXISTS injects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    exercise_id INT DEFAULT NULL,
    decision_points JSON DEFAULT NULL,
    schedule_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// -----------------------------
// CSRF token helper
// -----------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_token'];

// -----------------------------
// Helper: sanitize (server-side)
// -----------------------------
function s($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -----------------------------
// Handle POST actions: create, update, delete
// -----------------------------
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';

            if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $exercise_id = $_POST['exercise_id'] ?: null;

            // decision_points posted as JSON string from client
            $decision_points_json = $_POST['decision_points'] ?? '[]';

            // ---- validate schedule_json ----
            $schedule_json = trim($_POST['schedule_json'] ?? '');
            if ($schedule_json === '') {
                $schedule_json = null;
            } else {
                json_decode($schedule_json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "Schedule JSON is invalid.";
                    $schedule_json = null;
                }
            }

            if ($title === '' || $description === '') $errors[] = "Title and description required.";

            if (!$errors) {
                $stmt = $pdo->prepare("INSERT INTO injects (title, description, exercise_id, decision_points, schedule_json) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $exercise_id, $decision_points_json, $schedule_json]);
                $success = "Inject created.";
            }

        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $exercise_id = $_POST['exercise_id'] ?: null;
            $decision_points_json = $_POST['decision_points'] ?? '[]';

            // ---- validate schedule_json ----
            $schedule_json = trim($_POST['schedule_json'] ?? '');
            if ($schedule_json === '') {
                $schedule_json = null;
            } else {
                json_decode($schedule_json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "Schedule JSON is invalid.";
                    $schedule_json = null;
                }
            }

            if ($id <= 0) $errors[] = "Invalid ID.";
            if ($title === '' || $description === '') $errors[] = "Title and description required.";

            if (!$errors) {
                $stmt = $pdo->prepare("UPDATE injects 
                  SET title = ?, description = ?, exercise_id = ?, decision_points = ?, schedule_json = ? 
                  WHERE id = ?");
                $stmt->execute([$title, $description, $exercise_id, $decision_points_json, $schedule_json, $id]);
                $success = "Inject updated.";
            }

            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) $errors[] = "Invalid ID to delete.";

                if (!$errors) {
                    $stmt = $pdo->prepare("DELETE FROM injects WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Inject deleted.";
                }

            } else {
                $errors[] = "Unknown action.";
            }
        }
}


// -----------------------------
// Fetch current injects
// -----------------------------
$stmt = $pdo->query("SELECT * FROM injects ORDER BY created_at DESC");
$injects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Minimal list of exercises (for linking) - in real app replace with exercises table
// -----------------------------
$exercises = [
    ['id' => 1, 'name' => 'Flood Response Drill'],
    ['id' => 2, 'name' => 'Earthquake Tabletop'],
    ['id' => 3, 'name' => 'Mass Casualty Simulation']
];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Injects & Decision Points</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small helper so long sidebar won't cause horizontal scroll */
    html,body { height:100%; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

  <!-- Sidebar (your sidebar.php likely contains its own <aside> element) -->
  <?php include '../sidebar.php'; ?>

  <!-- Main -->
  <main class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">

      <header class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Injects & Decision Points — Admin</h1>
        <div class="text-sm text-gray-600">Role: Admin</div>
      </header>

      <?php if ($errors): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded">
          <ul class="list-disc pl-5">
            <?php foreach ($errors as $e): ?>
              <li><?= s($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded">
          <?= s($success) ?>
        </div>
      <?php endif; ?>

      <!-- ================= -->
      <!-- Form + Preview + List (single grid, no nested grids) -->
      <!-- ================= -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Create/Edit -->
        <div class="bg-white p-5 rounded shadow">
          <h2 class="text-lg font-medium mb-3">Create Inject</h2>

          <form id="injectForm" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= s($CSRF) ?>">
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="id" id="injectId" value="">

            <label class="block mb-2 text-sm">Title</label>
            <input required name="title" id="title" class="w-full mb-3 p-2 border rounded" placeholder="Short title">

            <label class="block mb-2 text-sm">Description</label>
            <textarea required name="description" id="description" rows="4" class="w-full mb-3 p-2 border rounded" placeholder="Describe the inject and intent"></textarea>

            <label class="block mb-2 text-sm">Link to Exercise (optional)</label>
            <select name="exercise_id" id="exercise_id" class="w-full mb-3 p-2 border rounded">
              <option value="">-- none --</option>
              <?php foreach ($exercises as $ex): ?>
                <option value="<?= s($ex['id']) ?>"><?= s($ex['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <!-- Decision Points builder -->
            <div class="mb-3">
              <label class="block mb-2 text-sm">Decision Points</label>
              <div id="decisionBuilder" class="space-y-2">
                <!-- JS will manage list -->
              </div>
              <button type="button" id="addDecisionBtn" class="mt-2 inline-block px-3 py-1 bg-blue-600 text-white rounded text-sm">+ Add Decision Point</button>
            </div>

            <!-- Schedule JSON freeform (simple) -->
            <div class="mb-3">
              <label class="block mb-2 text-sm">Schedule (JSON, optional)</label>
              <input name="schedule_json" id="schedule_json" class="w-full p-2 border rounded" placeholder='{"offset_minutes":10}'>
              <p class="text-xs text-gray-500 mt-1">Example: <code>{"offset_minutes":10}</code> (trigger 10 mins into exercise)</p>
            </div>

            <!-- Hidden JSON fields to submit -->
            <input type="hidden" name="decision_points" id="decision_points_input">

            <div class="flex gap-2">
              <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Save Inject</button>
              <button type="button" id="resetForm" class="px-4 py-2 bg-gray-200 rounded">Reset</button>
              <button type="button" id="switchToEdit" class="hidden px-4 py-2 bg-yellow-500 text-white rounded">Switch to Edit</button>
            </div>
          </form>

          <hr class="my-4">

          <!-- Live Preview (moved here for two-column design on smaller screens) -->
          <div>
            <h3 class="text-sm font-medium mb-2">Live Preview</h3>
            <div id="livePreview" class="p-3 border rounded bg-gray-50">
              <div class="text-sm text-gray-500">No preview yet — start typing title/description or add decision points.</div>
            </div>

            <div class="mt-3">
              <button id="simulateBtn" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Simulate Trigger</button>
              <span class="ml-2 text-xs text-gray-500">Simulate how a decision point prompt will appear to participants.</span>
            </div>
          </div>
        </div>

        <!-- Live Preview (on wide screens this is the middle column; we duplicated preview inside left for narrow screens) -->
        <div class="bg-white p-5 rounded shadow hidden md:block">
          <h3 class="text-lg font-medium mb-3">Live Preview</h3>
          <div id="livePreviewCenter" class="p-3 border rounded bg-gray-50">
            <div class="text-sm text-gray-500">No preview yet — start typing title/description or add decision points.</div>
          </div>
          <div class="mt-3">
            <button id="simulateBtnCenter" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Simulate Trigger</button>
            <span class="ml-2 text-xs text-gray-500">Simulate how a decision point prompt will appear to participants.</span>
          </div>
        </div>

        <!-- Injects List -->
        <div class="bg-white p-5 rounded shadow">
          <!-- Existing Injects -->
        <h2 class="text-2xl font-bold mb-6">Existing Injects</h2>

        <div class="w-full bg-gray-50 p-6 rounded-xl shadow">
            <div class="space-y-6">
                <?php if ($injects): ?>
                    <?php foreach ($injects as $inj): ?>
                        <div class="border border-gray-200 bg-white rounded-lg p-5 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                <?= s($inj['title']); ?>
                            </h3>
                            <p class="text-sm text-gray-600 mb-1"><strong>ID:</strong> <?= s($inj['id']); ?></p>

                            <p class="text-sm text-gray-600 mb-2">
                                <strong>Exercise:</strong>
                                <?php
                                    $exercise = array_values(array_filter($exercises, fn($e) => $e['id'] == $inj['exercise_id']));
                                    echo $exercise ? s($exercise[0]['name']) : '—';
                                ?>
                            </p>

                            <p class="text-sm text-gray-700 mb-3"><?= nl2br(s($inj['description'])); ?></p>

                            <?php if (!empty($inj['decision_points'])): ?>
                                <?php $dp = json_decode($inj['decision_points'], true); ?>
                                <?php if ($dp && is_array($dp)): ?>
                                    <div class="mb-3">
                                        <p class="text-sm font-semibold text-gray-700">Decision Points:</p>
                                        <ul class="list-disc list-inside text-sm text-gray-600">
                                            <?php foreach ($dp as $point): ?>
                                                <li>
                                                    <?= s($point['prompt'] ?? ''); ?>
                                                    — <em><?= s($point['type'] ?? ''); ?></em>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="flex gap-2">
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= s($inj['id']); ?>">
                                    <button type="submit"
                                        class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                                        Delete
                                    </button>
                                </form>
                                <button onclick="editInject(<?= (int)$inj['id']; ?>)"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Edit
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500">No injects yet.</p>
                <?php endif; ?>
            </div>
        </div>


      </div> <!-- end grid -->

    </div>
  </main>

  <!-- Modal for simulation -->
  <div id="modal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative bg-white rounded-lg p-6 max-w-xl w-full z-10">
      <div class="flex justify-between items-start">
        <h3 class="text-lg font-semibold" id="modalTitle">Simulation</h3>
        <button id="closeModal" class="text-gray-500">✕</button>
      </div>
      <div id="modalBody" class="mt-3 text-sm text-gray-700"></div>
      <div class="mt-4 text-right">
        <button id="modalOkay" class="px-4 py-2 bg-green-600 text-white rounded">OK</button>
      </div>
    </div>
  </div>

<script>
// -----------------------------
// Client-side: decision builder & live preview
// -----------------------------
const decisionBuilder = document.getElementById('decisionBuilder');
const addDecisionBtn = document.getElementById('addDecisionBtn');
const livePreview = document.getElementById('livePreview');
const livePreviewCenter = document.getElementById('livePreviewCenter');
const decisionPointsInput = document.getElementById('decision_points_input');
const injectForm = document.getElementById('injectForm');
const simulateBtn = document.getElementById('simulateBtn');
const simulateBtnCenter = document.getElementById('simulateBtnCenter');

let decisions = [];

function renderDecisionBuilder() {
  if (!decisionBuilder) return;
  decisionBuilder.innerHTML = '';
  decisions.forEach((d, i) => {
    const wrapper = document.createElement('div');
    wrapper.className = "p-2 border rounded flex gap-2 items-start bg-gray-50";

    const left = document.createElement('div');
    left.className = "flex-1";

    const label = document.createElement('input');
    label.className = "w-full p-1 mb-1 border rounded";
    label.placeholder = "Decision label (e.g., 'Evacuate now?')";
    label.value = d.label || '';
    label.oninput = (e) => { decisions[i].label = e.target.value; sync(); };

    const type = document.createElement('select');
    type.className = "w-full p-1 mb-1 border rounded";
    type.innerHTML = `<option value="single_choice">Single choice</option>
                      <option value="multiple_choice">Multiple choice</option>
                      <option value="acknowledgement">Acknowledgement (info)</option>`;
    type.value = d.type || 'single_choice';
    type.onchange = (e) => { decisions[i].type = e.target.value; sync(); };

    const optionsInput = document.createElement('input');
    optionsInput.className = "w-full p-1 mb-1 border rounded";
    optionsInput.placeholder = "Options (comma-separated, only for choice types)";
    optionsInput.value = (d.options || []).join(', ');
    optionsInput.oninput = (e) => { decisions[i].options = e.target.value.split(',').map(s=>s.trim()).filter(Boolean); sync(); };

    const timeout = document.createElement('input');
    timeout.type = 'number';
    timeout.min = 0;
    timeout.className = "w-full p-1 mb-1 border rounded";
    timeout.placeholder = "Timeout seconds (optional)";
    timeout.value = d.timeout || '';
    timeout.oninput = (e) => { decisions[i].timeout = e.target.value ? parseInt(e.target.value) : null; sync(); };

    left.appendChild(label);
    left.appendChild(type);
    left.appendChild(optionsInput);
    left.appendChild(timeout);

    const right = document.createElement('div');
    right.className = "flex flex-col gap-2";

    const upBtn = document.createElement('button');
    upBtn.type = 'button';
    upBtn.className = "px-2 py-1 bg-gray-200 rounded text-xs";
    upBtn.textContent = "↑";
    upBtn.onclick = () => { if (i>0) { const t = decisions[i-1]; decisions[i-1]=decisions[i]; decisions[i]=t; renderDecisionBuilder(); sync(); } };

    const downBtn = upBtn.cloneNode(true);
    downBtn.textContent = "↓";
    downBtn.onclick = () => { if (i < decisions.length-1) { const t = decisions[i+1]; decisions[i+1]=decisions[i]; decisions[i]=t; renderDecisionBuilder(); sync(); } };

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = "px-2 py-1 bg-red-400 text-white rounded text-xs";
    removeBtn.textContent = "Remove";
    removeBtn.onclick = () => { decisions.splice(i,1); renderDecisionBuilder(); sync(); };

    right.appendChild(upBtn);
    right.appendChild(downBtn);
    right.appendChild(removeBtn);

    wrapper.appendChild(left);
    wrapper.appendChild(right);
    decisionBuilder.appendChild(wrapper);
  });

  if (decisions.length === 0) {
    decisionBuilder.innerHTML = '<div class="text-xs text-gray-500">No decision points added.</div>';
  }
  renderPreview();
}

if (addDecisionBtn) {
  addDecisionBtn.addEventListener('click', () => {
    decisions.push({ label: '', type: 'single_choice', options: [], timeout: null });
    renderDecisionBuilder();
  });
}

// Sync decisionPoints into hidden input and preview
function sync() {
  if (decisionPointsInput) decisionPointsInput.value = JSON.stringify(decisions);
  renderPreview();
}

function renderPreview() {
  const title = document.getElementById('title') ? document.getElementById('title').value : '';
  const desc = document.getElementById('description') ? document.getElementById('description').value : '';
  let html = `<div class="mb-2"><strong>${escapeHtml(title || '— Title will appear here —')}</strong></div>`;
  html += `<div class="mb-3 text-sm text-gray-600">${escapeHtml(desc || '— Description will appear here —')}</div>`;

  if (decisions.length > 0) {
    html += '<div><strong>Decision Points Preview:</strong><div class="mt-2 space-y-2">';
    decisions.forEach((d, idx) => {
      html += `<div class="p-2 border rounded bg-white">
                <div class="text-sm font-medium">#${idx+1} ${escapeHtml(d.label || '(no label)')}</div>
                <div class="text-xs text-gray-500">Type: ${escapeHtml(d.type)}${d.timeout ? ' — timeout: '+escapeHtml(String(d.timeout))+'s' : ''}</div>`;
      if (d.options && d.options.length) {
        html += '<ul class="list-disc pl-5 text-xs mt-1">';
        d.options.forEach(opt => { html += `<li>${escapeHtml(opt)}</li>`; });
        html += '</ul>';
      }
      html += `</div>`;
    });
    html += '</div></div>';
  } else {
    html += '<div class="text-xs text-gray-500">No decision points configured.</div>';
  }

  if (livePreview) livePreview.innerHTML = html;
  if (livePreviewCenter) livePreviewCenter.innerHTML = html;
}

function escapeHtml(s) {
  return String(s || '').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
}

// on form submit: ensure decision_points hidden field is populated
if (injectForm) {
  injectForm.addEventListener('submit', (e) => {
    if (decisionPointsInput) decisionPointsInput.value = JSON.stringify(decisions);
    // allow submit to continue
  });
}

// Reset button
const resetBtn = document.getElementById('resetForm');
if (resetBtn) {
  resetBtn.addEventListener('click', () => {
    if (injectForm) injectForm.reset();
    decisions = [];
    renderDecisionBuilder();
    const fa = document.getElementById('formAction');
    const iid = document.getElementById('injectId');
    if (fa) fa.value = 'create';
    if (iid) iid.value = '';
  });
}

// -----------------------------
// Edit existing inject: populate form
// -----------------------------
document.querySelectorAll('.editBtn').forEach(btn=>{
  btn.addEventListener('click', (ev)=>{
    const inj = JSON.parse(ev.currentTarget.getAttribute('data-inject'));
    if (document.getElementById('title')) document.getElementById('title').value = inj.title || '';
    if (document.getElementById('description')) document.getElementById('description').value = inj.description || '';
    if (document.getElementById('exercise_id')) document.getElementById('exercise_id').value = inj.exercise_id || '';
    if (document.getElementById('schedule_json')) document.getElementById('schedule_json').value = inj.schedule_json || '';
    if (document.getElementById('formAction')) document.getElementById('formAction').value = 'update';
    if (document.getElementById('injectId')) document.getElementById('injectId').value = inj.id;
    // load decision points JSON
    try {
      decisions = inj.decision_points ? JSON.parse(inj.decision_points) : [];
    } catch (err) {
      decisions = [];
    }
    renderDecisionBuilder();
    // scroll to form
    window.scrollTo({top:0, behavior:'smooth'});
  });
});

// -----------------------------
// Simulation modal logic
// -----------------------------
const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const modalBody = document.getElementById('modalBody');
const closeModal = document.getElementById('closeModal');
const modalOkay = document.getElementById('modalOkay');

function showModal(title, html) {
  if (!modal) return;
  if (modalTitle) modalTitle.textContent = title;
  if (modalBody) modalBody.innerHTML = html;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}
function hideModal() {
  if (!modal) return;
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

if (closeModal) closeModal.addEventListener('click', hideModal);
if (modalOkay) modalOkay.addEventListener('click', hideModal);

if (simulateBtn) simulateBtn.addEventListener('click', () => {
  // Build preview from form (for immediate simulation)
  const title = document.getElementById('title') ? document.getElementById('title').value : '';
  const desc = document.getElementById('description') ? document.getElementById('description').value : '';
  let html = `<div class="mb-2"><strong>${escapeHtml(title || 'Untitled Inject')}</strong></div>`;
  html += `<div class="mb-3 text-sm">${escapeHtml(desc || '')}</div>`;
  if (decisions.length === 0) {
    html += '<div class="text-xs text-gray-500">No decision points to simulate.</div>';
  } else {
    decisions.forEach((d, i) => {
      html += `<div class="mb-3 p-3 border rounded">
                <div class="font-medium">Decision ${i+1}: ${escapeHtml(d.label || '(no label)')}</div>
                <div class="text-xs text-gray-600 mb-2">Type: ${escapeHtml(d.type)}</div>`;
      if (d.type.indexOf('choice') !== -1) {
        html += '<div class="space-y-2">';
        (d.options||[]).forEach(opt => {
          html += `<div><button class="px-3 py-1 border rounded text-sm">${escapeHtml(opt)}</button></div>`;
        });
        html += '</div>';
      } else {
        html += `<div class="text-sm text-gray-700">[${escapeHtml(d.type)} prompt shown to participants]</div>`;
      }
      html += '</div>';
    });
  }
  showModal('Simulation Preview', html);
});

document.querySelectorAll('.simulateSingleBtn').forEach(btn=>{
  btn.addEventListener('click', (ev)=>{
    const inj = JSON.parse(ev.currentTarget.getAttribute('data-inject'));
    let dp = [];
    try { dp = inj.decision_points ? JSON.parse(inj.decision_points) : []; } catch(e){}
    let html = `<div class="mb-2"><strong>${escapeHtml(inj.title)}</strong></div>`;
    html += `<div class="mb-3 text-sm">${escapeHtml(inj.description)}</div>`;
    if (!dp.length) html += '<div class="text-xs text-gray-500">No decision points.</div>';
    else {
      dp.forEach((d, i) => {
        html += `<div class="mb-3 p-3 border rounded"><div class="font-medium">Decision ${i+1}: ${escapeHtml(d.label||'(no label)')}</div>`;
        if (d.options && d.options.length) {
          html += '<div class="mt-2 space-y-2">';
          d.options.forEach(opt => html += `<div><button class="px-3 py-1 border rounded text-sm">${escapeHtml(opt)}</button></div>`);
          html += '</div>';
        } else {
          html += `<div class="text-sm text-gray-700">[${escapeHtml(d.type || 'unknown')} shown]</div>`;
        }
        if (d.timeout) html += `<div class="text-xs text-gray-400 mt-2">Timeout: ${escapeHtml(String(d.timeout))}s</div>`;
        html += '</div>';
      })
    }
    showModal('Inject Simulation — ' + inj.title, html);
  });
});

renderDecisionBuilder(); // initial
</script>
</body>
</html>
