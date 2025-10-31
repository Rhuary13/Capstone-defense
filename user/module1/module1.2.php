<?php
// participant_content.php (updated)
// Shows lessons (lessons table) and admin-created scheduled drills (drills table)
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ------------------------
// Ensure drills table exists (safe auto-create)
// ------------------------
$createDrills = "
CREATE TABLE IF NOT EXISTS `drills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `details` TEXT NOT NULL,
  `date` DATE NOT NULL,
  `type` VARCHAR(100) NOT NULL DEFAULT 'Drill',
  `file_path` VARCHAR(512) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createDrills);

// ------------------------
// Ensure lessons & quizzes exist (your existing tables)
// ------------------------
$conn->query("CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_option CHAR(1) NOT NULL,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ------------------------
// Helpers
// ------------------------
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ------------------------
// Fetch lessons and drills
// - Lessons: used as self-paced content
// - Drills: admin scheduled trainings (visible to participants & staff)
// ------------------------
$lessons = $conn->query("SELECT * FROM lessons ORDER BY created_at ASC");

// optional filter for drills by type (disaster)
$typeFilter = trim($_GET['type'] ?? '');
$drillsWhere = $typeFilter ? "WHERE `type` = '" . $conn->real_escape_string($typeFilter) . "'" : "";
$drills = $conn->query("SELECT * FROM drills {$drillsWhere} ORDER BY `date` ASC");

// Upcoming drills for sidebar
$upcoming = $conn->query("SELECT id,title,`date`,type FROM drills WHERE `date` >= CURDATE() ORDER BY `date` ASC LIMIT 6");
$upcoming_list = $upcoming ? $upcoming->fetch_all(MYSQLI_ASSOC) : [];

// Distinct types for filter dropdown
$typeRes = $conn->query("SELECT DISTINCT `type` FROM drills ORDER BY `type` ASC");
$types = [];
if ($typeRes) {
    foreach ($typeRes->fetch_all(MYSQLI_ASSOC) as $r) $types[] = $r['type'];
}

// ------------------------
// Determine selected item: lesson_id or drill_id
// ------------------------
$selected_lesson = null;
$selected_quiz = null;
$selected_drill = null;

if (isset($_GET['lesson_id'])) {
    $lesson_id = intval($_GET['lesson_id']);
    $selected_lesson = $conn->query("SELECT * FROM lessons WHERE id=$lesson_id")->fetch_assoc();
    $selected_quiz = $conn->query("SELECT * FROM quizzes WHERE lesson_id=$lesson_id");
} elseif (isset($_GET['drill_id'])) {
    $drill_id = intval($_GET['drill_id']);
    $stmt = $conn->prepare("SELECT * FROM drills WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $drill_id);
    $stmt->execute();
    $selected_drill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Learning Center — Participant</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* independent scroll area to prevent UI overlap */
    .app-scroll {
      max-height: calc(100vh - 120px);
      overflow: auto;
      scroll-behavior: smooth;
      padding-right: 8px;
    }
    .app-scroll::-webkit-scrollbar { width: 10px; }
    .app-scroll::-webkit-scrollbar-thumb {
      background-color: rgba(15,23,42,0.10);
      border-radius: 8px;
      border: 2px solid transparent;
      background-clip: content-box;
    }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .truncate-cell{ max-width:22rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 flex min-h-screen">

  <!-- Sidebar (keeps your existing include) -->
  <?php if (file_exists(__DIR__ . '/../sidebar.php')) include __DIR__ . '/../sidebar.php'; ?>

  <div class="flex-1 p-6">
    <header class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-extrabold text-sky-700">📘 Learning Center</h1>
        <p class="text-sm text-slate-600 mt-1">Lessons, scheduled trainings, and deadlines posted by admin</p>
      </div>

      <div class="flex items-center gap-3">
        <form method="get" class="flex items-center gap-2">
          <select name="type" onchange="this.form.submit()" class="px-3 py-2 border rounded bg-white">
            <option value="">All categories</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= esc($t) ?>" <?= $t === $typeFilter ? 'selected' : '' ?>><?= esc($t) ?></option>
            <?php endforeach; ?>
          </select>
          <a href="participant_content.php" class="px-3 py-2 bg-sky-600 text-white rounded">Reset</a>
        </form>
      </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left column: lessons list (col 1) -->
      <aside class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-800">📖 Lessons</h2>
            <span class="text-xs text-slate-500"><?= $lessons ? $lessons->num_rows : 0 ?> total</span>
          </div>

          <div class="app-scroll space-y-3">
            <?php if ($lessons && $lessons->num_rows > 0): while ($lesson = $lessons->fetch_assoc()): ?>
              <a href="?lesson_id=<?= (int)$lesson['id'] ?>" class="block rounded-xl border border-slate-100 bg-white p-3 hover:bg-sky-50 transition">
                <div class="flex items-center justify-between">
                  <h3 class="font-medium text-slate-800 truncate-cell"><?= esc($lesson['title']) ?></h3>
                  <span class="text-xs text-slate-500"><?= date('M d, Y', strtotime($lesson['created_at'])) ?></span>
                </div>
                <p class="text-sm text-slate-600 mt-2 line-clamp-3"><?= nl2br(esc(substr($lesson['content'],0,300))) ?></p>
              </a>
            <?php endwhile; else: ?>
              <div class="text-sm text-slate-500 p-4">No lessons yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </aside>

      <!-- Middle column: drills list (col 2) -->
      <aside class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-800">📅 Scheduled Trainings</h2>
            <span class="text-xs text-slate-500"><?= $drills ? $drills->num_rows : 0 ?></span>
          </div>

          <div class="app-scroll space-y-3">
            <?php if ($drills && $drills->num_rows > 0): while ($drill = $drills->fetch_assoc()): ?>
              <a href="?drill_id=<?= (int)$drill['id'] ?>" class="block rounded-xl border border-slate-100 bg-white p-3 hover:bg-amber-50 transition">
                <div class="flex items-center justify-between">
                  <h3 class="font-medium text-slate-800 truncate-cell"><?= esc($drill['title']) ?></h3>
                  <div class="text-right">
                    <div class="text-xs text-slate-500"><?= date('M d, Y', strtotime($drill['date'])) ?></div>
                    <div class="text-xs text-amber-700"><?= esc($drill['type']) ?></div>
                  </div>
                </div>
                <p class="text-sm text-slate-600 mt-2 line-clamp-3"><?= nl2br(esc(substr($drill['details'],0,200))) ?></p>
              </a>
            <?php endwhile; else: ?>
              <div class="text-sm text-slate-500 p-4">No scheduled trainings.</div>
            <?php endif; ?>
          </div>
        </div>
      </aside>

      <!-- Right column: content/details (col 3-4) -->
      <main class="lg:col-span-2">
        <?php if ($selected_lesson): ?>
          <!-- SHOW LESSON CONTENT (existing behavior) -->
          <div class="bg-white rounded-2xl border border-slate-200 shadow p-6 mb-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-2xl font-bold text-sky-700"><?= esc($selected_lesson['title']) ?></h2>
                <p class="text-sm text-slate-500 mt-1">Published: <?= date('M d, Y', strtotime($selected_lesson['created_at'])) ?></p>
              </div>
              <div class="hidden md:flex items-center gap-3">
                <a href="#" class="px-3 py-2 text-sm border border-slate-200 rounded-lg">Bookmark</a>
                <a href="#" class="px-3 py-2 text-sm bg-green-600 text-white rounded-lg">Mark as Completed</a>
              </div>
            </div>

            <section class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
              <article class="lg:col-span-2 bg-slate-50 rounded-lg p-5 border border-slate-100 max-h-[60vh] overflow-auto">
                <div class="prose max-w-none text-base text-slate-800">
                  <?= nl2br(esc($selected_lesson['content'])) ?>
                </div>
              </article>

              <aside class="lg:col-span-1 bg-white rounded-lg border border-slate-100 p-4 shadow-sm">
                <h4 class="text-sm font-semibold text-slate-700">Lesson details</h4>
                <ul class="text-sm text-slate-600 mt-3 space-y-2">
                  <li><strong>Duration:</strong> ~15 minutes</li>
                  <li><strong>Type:</strong> Module</li>
                  <li><strong>Uploaded:</strong> <?= date('M d, Y', strtotime($selected_lesson['created_at'])) ?></li>
                </ul>
                <div class="mt-4">
                  <a href="#" class="w-full inline-block text-center px-4 py-2 rounded-lg bg-sky-600 text-white">Start Lesson</a>
                </div>
              </aside>
            </section>
          </div>

          <!-- QUIZ (if exists) -->
          <div class="bg-white rounded-2xl border border-slate-200 shadow p-6">
            <h3 class="text-xl font-semibold text-slate-800 mb-4">📝 Quiz</h3>
            <?php if ($selected_quiz && $selected_quiz->num_rows > 0): ?>
              <form method="post" action="" class="space-y-4">
                <?php while ($q = $selected_quiz->fetch_assoc()): ?>
                  <fieldset class="border border-slate-100 rounded-lg p-4">
                    <legend class="text-lg font-medium text-slate-800"><?= esc($q['question']) ?></legend>
                    <div class="mt-3 space-y-2 text-base">
                      <label class="flex items-center gap-3"><input type="radio" name="q<?= $q['id'] ?>" value="A" /> <span><?= esc($q['option_a']) ?></span></label>
                      <label class="flex items-center gap-3"><input type="radio" name="q<?= $q['id'] ?>" value="B" /> <span><?= esc($q['option_b']) ?></span></label>
                      <label class="flex items-center gap-3"><input type="radio" name="q<?= $q['id'] ?>" value="C" /> <span><?= esc($q['option_c']) ?></span></label>
                      <label class="flex items-center gap-3"><input type="radio" name="q<?= $q['id'] ?>" value="D" /> <span><?= esc($q['option_d']) ?></span></label>
                    </div>
                  </fieldset>
                <?php endwhile; ?>
                <div class="flex items-center gap-3 mt-2">
                  <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-lg">Submit Answers</button>
                </div>
              </form>
            <?php else: ?>
              <div class="text-sm text-slate-500">No quiz available for this lesson.</div>
            <?php endif; ?>
          </div>

        <?php elseif ($selected_drill): ?>
          <!-- SHOW DRILL / SCHEDULED TRAINING (new behavior) -->
          <div class="bg-white rounded-2xl border border-slate-200 shadow p-6 mb-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-2xl font-bold text-amber-700"><?= esc($selected_drill['title']) ?></h2>
                <p class="text-sm text-slate-500 mt-1">Scheduled: <?= date('M d, Y', strtotime($selected_drill['date'])) ?> — Category: <strong><?= esc($selected_drill['type']) ?></strong></p>
              </div>
              <div class="hidden md:flex items-center gap-3">
                <a href="#" class="px-3 py-2 text-sm border border-slate-200 rounded-lg">Save</a>
                <a href="#" class="px-3 py-2 text-sm bg-sky-600 text-white rounded-lg">I will attend</a>
              </div>
            </div>

            <section class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
              <article class="lg:col-span-2 bg-slate-50 rounded-lg p-5 border border-slate-100 max-h-[60vh] overflow-auto">
                <div class="prose max-w-none text-base text-slate-800">
                  <?= nl2br(esc($selected_drill['details'])) ?>
                </div>
                <?php if (!empty($selected_drill['file_path'])): ?>
                  <div class="mt-4">
                    <a href="<?= esc($selected_drill['file_path']) ?>" target="_blank" class="text-indigo-600">Open attached file</a>
                  </div>
                <?php endif; ?>
              </article>

              <aside class="lg:col-span-1 bg-white rounded-lg border border-slate-100 p-4 shadow-sm">
                <h4 class="text-sm font-semibold text-slate-700">Training details</h4>
                <ul class="text-sm text-slate-600 mt-3 space-y-2">
                  <li><strong>Scheduled date:</strong> <?= date('M d, Y', strtotime($selected_drill['date'])) ?></li>
                  <li><strong>Category:</strong> <?= esc($selected_drill['type']) ?></li>
                  <li><strong>Posted:</strong> <?= date('M d, Y', strtotime($selected_drill['created_at'])) ?></li>
                </ul>
                <div class="mt-4">
                  <a href="#" class="w-full inline-block text-center px-4 py-2 rounded-lg bg-amber-600 text-white">Acknowledge / Sign-up</a>
                </div>
                <div class="text-xs text-slate-400 mt-3">This scheduled training is posted to Participants & Staff.</div>
              </aside>
            </section>
          </div>

        <?php else: ?>
          <div class="bg-white rounded-2xl border border-slate-200 shadow p-6">
            <h2 class="text-xl font-semibold text-slate-800">Welcome</h2>
            <p class="text-sm text-slate-600 mt-2">Select a lesson or scheduled training from the lists to view details. Scheduled trainings include a training date/deadline and category (disaster type).</p>
          </div>
        <?php endif; ?>
      </main>

    </div>
  </div>
</body>
</html>
