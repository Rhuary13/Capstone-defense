<?php
// staff_scheduling.php — single-file updated for Staff view to also show Admin drills & lessons
session_start();

// --- Database Connection ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Create table if missing (safety net for schedules) ---
$conn->query("CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  event_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  venue VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- Ensure admin 'drills' table exists (admin-created lessons / scheduled training) ---
$conn->query("CREATE TABLE IF NOT EXISTS drills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  details TEXT NOT NULL,
  `date` DATE NOT NULL,
  `type` VARCHAR(100) NOT NULL DEFAULT 'Drill',
  file_path VARCHAR(512) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- Ensure lessons table exists (optional admin lessons) ---
$conn->query("CREATE TABLE IF NOT EXISTS lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- Handle Form Submission (Schedules) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date  = trim($_POST['event_date']);
    $start_time  = trim($_POST['start_time']);
    $end_time    = trim($_POST['end_time']);
    $venue       = trim($_POST['venue']);

    // Basic server-side validation (keep simple)
    if ($title !== '' && $event_date !== '' && $start_time !== '' && $end_time !== '') {
        $stmt = $conn->prepare("INSERT INTO schedules (title, description, event_date, start_time, end_time, venue) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $title, $description, $event_date, $start_time, $end_time, $venue);
        $stmt->execute();
        $stmt->close();
        // redirect to avoid resubmit
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- Fetch All Schedules (staff-created) ---
$schedules = $conn->query("SELECT * FROM schedules ORDER BY event_date ASC, start_time ASC");

// --- Fetch Admin-created drills (scheduled lessons) so Staff can see them ---
$drills = $conn->query("SELECT id,title,details,`date`,type,file_path,created_at FROM drills ORDER BY `date` ASC");

// --- Fetch Admin-created free lessons (optional) ---
$lessons = $conn->query("SELECT id,title,content,created_at FROM lessons ORDER BY created_at DESC LIMIT 20");

// --- Helpers
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Staff Scheduling — Trainings & Admin Lessons</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    html,body{height:100%}
    .app { display:flex; height:100vh; overflow:hidden; }
    .main-wrap { flex:1; display:flex; flex-direction:column; min-width:0; }
    .main-scroll { flex:1; overflow:auto; min-height:0; padding:1.25rem; background:#f8fafc; }
    .card-scroll { max-height:42vh; overflow:auto; padding-right:8px; }
    .card-scroll::-webkit-scrollbar { width:10px }
    .card-scroll::-webkit-scrollbar-thumb { background-color: rgba(2,6,23,0.06); border-radius: 8px; }
    .truncate-cell{ max-width:22rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  </style>
</head>
<body class="bg-gray-100 font-sans">
  <div class="app">
    <!-- Sidebar (keep your existing sidebar include if present, fallback minimal) -->
    <?php if (file_exists(__DIR__ . '/../sidebar.php')): ?>
      <?php include __DIR__ . '/../sidebar.php'; ?>
    <?php else: ?>
      <aside style="width:16rem;background:#fff;border-right:1px solid #e6edf3;padding:1rem;">
        <div class="font-bold mb-4">Staff Menu</div>
        <nav class="text-sm">
          <div class="mb-2"><a href="dashboard.php">Dashboard</a></div>
          <div class="mb-2"><a href="staff_scheduling.php" class="font-medium">Scheduling</a></div>
          <div class="mb-2"><a href="participants.php">Participants</a></div>
        </nav>
      </aside>
    <?php endif; ?>

    <div class="main-wrap">
      <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Staff Scheduling — Inform Participants</h1>
          <div class="text-sm text-slate-500">Create staff schedules and view admin-posted trainings & lessons</div>
        </div>
        <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'Staff') ?></strong></div>
      </header>

      <main class="main-scroll" role="main">
        <div class="max-w-7xl mx-auto space-y-6">

          <!-- Modernized Form: Add Schedule (UI-only: drop-in replacement) -->
<section class="bg-white p-6 rounded-2xl shadow">
  <div class="flex items-start justify-between mb-4">
    <div>
      <h2 class="text-lg font-semibold">Add New Schedule</h2>
      <p class="text-sm text-slate-500 mt-1">Create a training or event. This will be posted to staff and participants.</p>
    </div>
    <div class="text-xs text-slate-400">Tip: Attach agenda or materials for attendees.</div>
  </div>

  <!-- Client-side validation summary (hidden until needed) -->
  <div id="formErrors" class="hidden mb-4 p-3 rounded-lg bg-rose-50 text-rose-800 border border-rose-100 text-sm"></div>

  <form id="scheduleForm" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4" novalidate>
    <!-- keep server-side flag so backend continues to work -->
    <input type="hidden" name="save_schedule" value="1" />

    <div>
      <label for="title" class="block text-sm font-medium mb-1">Event Title <span class="text-rose-600">*</span></label>
      <input id="title" name="title" type="text" required
             class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-100"
             placeholder="e.g. Community Evacuation Drill — Flood" aria-required="true" />
      <p class="text-xs text-slate-400 mt-1">Short descriptive title.</p>
    </div>

    <div>
      <label for="venue" class="block text-sm font-medium mb-1">Venue</label>
      <input id="venue" name="venue" type="text" class="w-full p-2 border rounded-lg" placeholder="e.g. Barangay Hall / Evacuation Site" />
    </div>

    <div>
      <label for="event_date" class="block text-sm font-medium mb-1">Event Date <span class="text-rose-600">*</span></label>
      <input id="event_date" name="event_date" type="date" required class="w-full p-2 border rounded-lg" aria-required="true" />
    </div>

    <div class="flex gap-3">
      <div class="flex-1">
        <label for="start_time" class="block text-sm font-medium mb-1">Start Time <span class="text-rose-600">*</span></label>
        <input id="start_time" name="start_time" type="time" required class="w-full p-2 border rounded-lg" />
      </div>
      <div class="flex-1">
        <label for="end_time" class="block text-sm font-medium mb-1">End Time <span class="text-rose-600">*</span></label>
        <input id="end_time" name="end_time" type="time" required class="w-full p-2 border rounded-lg" />
      </div>
    </div>

    <div>
      <label for="type" class="block text-sm font-medium mb-1">Category / Disaster Type</label>
      <select id="type" name="type" class="w-full p-2 border rounded-lg">
        <option value="Drill">Drill</option>
        <option value="Flood">Flood</option>
        <option value="Earthquake">Earthquake</option>
        <option value="Fire">Fire</option>
        <option value="Storm">Storm</option>
        <option value="Tsunami">Tsunami</option>
        <option value="Workshop">Workshop</option>
        <option value="Tabletop">Tabletop</option>
        <option value="Simulation">Simulation</option>
      </select>
      <p class="text-xs text-slate-400 mt-1">This helps participants filter trainings by hazard type.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Audience</label>
      <div class="flex items-center gap-3">
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="audience[]" value="participants" checked class="h-4 w-4" />
          <span>Participants</span>
        </label>
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="audience[]" value="staff" checked class="h-4 w-4" />
          <span>Staff</span>
        </label>
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="audience[]" value="public" class="h-4 w-4" />
          <span>Public</span>
        </label>
      </div>
      <p class="text-xs text-slate-400 mt-1">Choose who will see this schedule. Backend should interpret these checkboxes (UI-only here).</p>
    </div>

    <div class="md:col-span-2">
      <label for="description" class="block text-sm font-medium mb-1">Description / Agenda</label>
      <textarea id="description" name="description" rows="4" class="w-full p-2 border rounded-lg" placeholder="Add details, objectives, speaker, or agenda..."></textarea>
    </div>

    <div>
      <label for="file" class="block text-sm font-medium mb-1">Attach file (optional)</label>
      <input id="file" name="file" type="file" accept=".pdf,.ppt,.pptx,.doc,.docx,.jpg,.png" class="w-full" />
      <p class="text-xs text-slate-400 mt-1">PDF / PPT / image for attendees (max recommended 10MB).</p>
    </div>

    <div>
      <label for="timezone" class="block text-sm font-medium mb-1">Timezone</label>
      <select id="timezone" name="timezone" class="w-full p-2 border rounded-lg">
        <option value="Asia/Manila">Asia/Manila (UTC+8)</option>
        <option value="UTC">UTC</option>
        <!-- add others if desired -->
      </select>
      <p class="text-xs text-slate-400 mt-1">Timezone used for this schedule (display only).</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Reminder</label>
      <div class="flex items-center gap-3">
        <select id="reminder" name="reminder" class="p-2 border rounded-lg">
          <option value="none">No reminder</option>
          <option value="1_day">1 day before</option>
          <option value="1_hour">1 hour before</option>
          <option value="15_min">15 minutes before</option>
        </select>
        <button type="button" id="previewBtn" class="px-3 py-2 bg-sky-50 border rounded text-sm">Preview</button>
      </div>
      <p class="text-xs text-slate-400 mt-1">(Reminder delivery requires backend support.)</p>
    </div>

    <!-- preview panel (mobile: full width) -->
    <div class="md:col-span-2">
      <div id="previewCard" class="border rounded-lg p-4 bg-slate-50">
        <div class="flex items-start justify-between">
          <div>
            <div id="previewTitle" class="text-lg font-semibold text-slate-800">— Event title preview —</div>
            <div id="previewMeta" class="text-xs text-slate-500 mt-1">Date • Time • Category</div>
          </div>
          <div id="previewAudience" class="text-xs text-slate-600">Audience: Participants, Staff</div>
        </div>
        <div id="previewDesc" class="mt-3 text-sm text-slate-700">Description preview will appear here as you type.</div>
        <div class="mt-3 flex gap-2">
          <a id="previewFile" class="hidden text-indigo-600 text-sm" href="#" target="_blank">Open attachment</a>
          <span id="previewBadge" class="ml-auto text-xs text-slate-400">Draft</span>
        </div>
      </div>
    </div>

    <div class="md:col-span-2 flex justify-end gap-3 mt-1">
      <button type="reset" id="resetBtn" class="px-4 py-2 bg-gray-100 border rounded-lg">Reset</button>
      <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Save Schedule</button>
    </div>
  </form>
</section>

<script>
  // Client-side: live preview + simple validation (UI only)
  (function(){
    const form = document.getElementById('scheduleForm');
    const title = document.getElementById('title');
    const date = document.getElementById('event_date');
    const start = document.getElementById('start_time');
    const end = document.getElementById('end_time');
    const type = document.getElementById('type');
    const desc = document.getElementById('description');
    const audienceInputs = Array.from(document.querySelectorAll('input[name="audience[]"]'));
    const fileInput = document.getElementById('file');

    const previewTitle = document.getElementById('previewTitle');
    const previewMeta = document.getElementById('previewMeta');
    const previewDesc = document.getElementById('previewDesc');
    const previewAudience = document.getElementById('previewAudience');
    const previewFile = document.getElementById('previewFile');
    const previewBadge = document.getElementById('previewBadge');
    const formErrors = document.getElementById('formErrors');

    function updPreview(){
      previewTitle.textContent = title.value.trim() || '— Event title preview —';
      const d = date.value ? new Date(date.value) : null;
      const dText = d ? d.toLocaleDateString(undefined, { year:'numeric', month:'short', day:'numeric' }) : 'Date';
      const timeText = (start.value ? start.value : '--:--') + (end.value ? ' - ' + end.value : '');
      previewMeta.textContent = `${dText} • ${timeText} • ${type.value}`;
      previewDesc.innerHTML = desc.value ? (desc.value.length>350? desc.value.substr(0,350)+'...' : desc.value) : 'Description preview will appear here as you type.';
      const aud = audienceInputs.filter(i=>i.checked).map(i=>i.value).join(', ') || 'None';
      previewAudience.textContent = 'Audience: ' + aud;
      if (fileInput.files && fileInput.files.length){
        previewFile.textContent = fileInput.files[0].name;
        previewFile.href = '#';
        previewFile.classList.remove('hidden');
      } else {
        previewFile.classList.add('hidden');
      }
      // simple "Draft" vs "Ready" indicator
      previewBadge.textContent = (title.value && date.value && start.value) ? 'Ready to publish' : 'Draft';
    }

    // Attach listeners
    [title, date, start, end, type, desc, fileInput].forEach(el=>{
      if (!el) return;
      el.addEventListener('input', updPreview);
      el.addEventListener('change', updPreview);
    });
    audienceInputs.forEach(a=>a.addEventListener('change', updPreview));
    updPreview();

    // Minimal client validation on submit (server wins)
    form.addEventListener('submit', function(e){
      const errors = [];
      if (!title.value.trim()) errors.push('Title is required.');
      if (!date.value) errors.push('Event date is required.');
      if (!start.value) errors.push('Start time is required.');
      if (!end.value) errors.push('End time is required.');
      // check time order if both filled
      if (start.value && end.value && start.value >= end.value) errors.push('End time must be after start time.');

      if (errors.length){
        e.preventDefault();
        formErrors.innerHTML = '<ul class="list-disc pl-5">' + errors.map(x=>'<li>'+x+'</li>').join('') + '</ul>';
        formErrors.classList.remove('hidden');
        formErrors.focus();
        // scroll to formErrors
        formErrors.scrollIntoView({behavior:'smooth', block:'center'});
      } else {
        // allow submit; form will POST to server
      }
    });

    // reset behavior: clear preview
    document.getElementById('resetBtn').addEventListener('click', function(){
      setTimeout(()=> {
        formErrors.classList.add('hidden');
        updPreview();
      }, 10);
    });

    // Preview button simply focuses the preview (UI)
    document.getElementById('previewBtn').addEventListener('click', function(){
      document.getElementById('previewCard').scrollIntoView({behavior:'smooth', block:'center'});
    });
  })();
</script>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Upcoming staff schedules -->
            <section class="lg:col-span-1 bg-white p-6 rounded-2xl shadow">
              <h3 class="text-md font-semibold mb-3">Upcoming Staff Schedules</h3>
              <div class="card-scroll divide-y">
                <?php if ($schedules && $schedules->num_rows): ?>
                  <?php while ($r = $schedules->fetch_assoc()): ?>
                    <div class="py-3">
                      <div class="flex justify-between items-start gap-3">
                        <div>
                          <div class="font-medium"><?= esc($r['title']) ?></div>
                          <div class="text-xs text-slate-500"><?= date('M d, Y', strtotime($r['event_date'])) ?> • <?= esc(substr($r['start_time'],0,5)) ?> - <?= esc(substr($r['end_time'],0,5)) ?></div>
                        </div>
                        <div class="text-xs text-slate-400"><?= esc($r['venue']) ?></div>
                      </div>
                      <?php if (!empty($r['description'])): ?>
                        <div class="text-sm text-slate-600 mt-2"><?= esc((strlen($r['description'])>180 ? substr($r['description'],0,180).'...' : $r['description'])) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <div class="text-sm text-slate-500">No staff schedules yet.</div>
                <?php endif; ?>
              </div>
            </section>

            <!-- Admin-posted drills (scheduled lessons/training) -->
            <section class="lg:col-span-1 bg-white p-6 rounded-2xl shadow">
              <h3 class="text-md font-semibold mb-3">Admin Posted Trainings</h3>
              <div class="text-xs text-slate-500 mb-3">These are lessons/training scheduled by Admin (visible to staff & participants)</div>
              <div class="card-scroll divide-y">
                <?php if ($drills && $drills->num_rows): ?>
                  <?php while ($d = $drills->fetch_assoc()): ?>
                    <div class="py-3">
                      <div class="flex justify-between items-start gap-3">
                        <div>
                          <div class="font-medium"><?= esc($d['title']) ?></div>
                          <div class="text-xs text-slate-500"><?= esc($d['type']) ?></div>
                        </div>
                        <div class="text-xs text-amber-700"><?= date('M d, Y', strtotime($d['date'])) ?></div>
                      </div>
                      <div class="text-sm text-slate-600 mt-2"><?= esc((strlen($d['details'])>150? substr($d['details'],0,150).'...' : $d['details'])) ?></div>
                      <?php if (!empty($d['file_path'])): ?>
                        <div class="mt-2"><a class="text-indigo-600 text-sm" href="<?= esc($d['file_path']) ?>" target="_blank">Open attachment</a></div>
                      <?php endif; ?>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <div class="text-sm text-slate-500">No admin trainings scheduled.</div>
                <?php endif; ?>
              </div>
            </section>

            <!-- Admin free lessons preview -->
            <section class="lg:col-span-1 bg-white p-6 rounded-2xl shadow">
              <h3 class="text-md font-semibold mb-3">Admin Lessons (Latest)</h3>
              <div class="card-scroll divide-y">
                <?php if ($lessons && $lessons->num_rows): ?>
                  <?php while ($ls = $lessons->fetch_assoc()): ?>
                    <div class="py-3">
                      <div class="flex justify-between items-start gap-3">
                        <div class="truncate-cell font-medium"><?= esc($ls['title']) ?></div>
                        <div class="text-xs text-slate-400"><?= date('M d, Y', strtotime($ls['created_at'])) ?></div>
                      </div>
                      <div class="text-sm text-slate-600 mt-2"><?= esc((strlen($ls['content'])>140? substr($ls['content'],0,140).'...' : $ls['content'])) ?></div>
                      <div class="mt-2"><a href="staff_view_lesson.php?id=<?= (int)$ls['id'] ?>" class="text-sky-600 text-sm">Read full</a></div>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <div class="text-sm text-slate-500">No admin lessons yet.</div>
                <?php endif; ?>
              </div>
            </section>
          </div>

          <!-- Combined tables (detailed) -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Detailed schedule table -->
            <section class="bg-white p-6 rounded-2xl shadow col-span-1">
              <h3 class="text-lg font-semibold mb-4">All Staff Schedules (Detailed)</h3>
              <div class="overflow-auto rounded border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 text-slate-700 text-xs uppercase sticky top-0">
                    <tr>
                      <th class="px-3 py-2 text-left">Title</th>
                      <th class="px-3 py-2 text-left">Date</th>
                      <th class="px-3 py-2 text-left">Time</th>
                      <th class="px-3 py-2 text-left">Venue</th>
                      <th class="px-3 py-2 text-left">Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      // Re-run fetch for table (because earlier card-scroll consumed pointer)
                      $fullSched = $conn->query("SELECT * FROM schedules ORDER BY event_date ASC, start_time ASC");
                      if ($fullSched && $fullSched->num_rows):
                        while ($row = $fullSched->fetch_assoc()):
                    ?>
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-medium"><?= esc($row['title']) ?></td>
                        <td class="px-3 py-2"><?= esc($row['event_date']) ?></td>
                        <td class="px-3 py-2"><?= esc(substr($row['start_time'],0,5)) ?> - <?= esc(substr($row['end_time'],0,5)) ?></td>
                        <td class="px-3 py-2"><?= esc($row['venue']) ?></td>
                        <td class="px-3 py-2"><?= esc((strlen($row['description'])>180? substr($row['description'],0,180).'...' : $row['description'])) ?></td>
                      </tr>
                    <?php
                        endwhile;
                      else:
                    ?>
                      <tr><td colspan="5" class="p-4 text-center text-slate-500">No staff schedules.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </section>

            <!-- Detailed admin drills table -->
            <section class="bg-white p-6 rounded-2xl shadow col-span-1">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Admin Trainings (Detailed)</h3>
                <div class="text-sm text-slate-500"><?= ($drills ? $drills->num_rows : 0) ?> total</div>
              </div>

              <div class="overflow-auto rounded border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 text-slate-700 text-xs uppercase sticky top-0">
                    <tr>
                      <th class="px-3 py-2 text-left">Title</th>
                      <th class="px-3 py-2 text-left">Category</th>
                      <th class="px-3 py-2 text-left">Date</th>
                      <th class="px-3 py-2 text-left">File</th>
                      <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      // Re-query drills for full table
                      $allDrills = $conn->query("SELECT id,title,details,`date`,type,file_path,created_at FROM drills ORDER BY `date` ASC");
                      if ($allDrills && $allDrills->num_rows):
                        while ($dd = $allDrills->fetch_assoc()):
                    ?>
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-medium"><?= esc($dd['title']) ?></td>
                        <td class="px-3 py-2"><?= esc($dd['type']) ?></td>
                        <td class="px-3 py-2"><?= esc($dd['date']) ?></td>
                        <td class="px-3 py-2"><?php if (!empty($dd['file_path'])): ?><a href="<?= esc($dd['file_path']) ?>" class="text-indigo-600" target="_blank">Open</a><?php else: ?>—<?php endif; ?></td>
                        <td class="px-3 py-2">
                          <a href="staff_view_drill.php?id=<?= (int)$dd['id'] ?>" class="text-sky-600">View</a>
                        </td>
                      </tr>
                    <?php
                        endwhile;
                      else:
                    ?>
                      <tr><td colspan="5" class="p-4 text-center text-slate-500">No admin trainings.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </section>
          </div>

        </div>
      </main>
    </div>
  </div>

  <?php $conn->close(); ?>
</body>
</html>
