<?php
// content_structuring.php
// Admin UI — Content Structuring (single file)
// Updated to implement Review, inline Edit, Open Quiz, and Open Attachment functionality.

session_start();

// ------------------ DB connect ------------------
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'training_management';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('DB connect error: ' . htmlspecialchars($conn->connect_error));
}

// ------------------ Admin check ------------------
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// ------------------ CSRF ------------------
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// ------------------ Ensure tables exist (safe) ------------------
$conn->query("
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `disaster_type` VARCHAR(100) DEFAULT NULL,
  `content` TEXT NOT NULL,
  `scheduled_date` DATE DEFAULT NULL,
  `file_path` VARCHAR(512) DEFAULT NULL,
  `published` TINYINT(1) DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lesson_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quiz_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT NOT NULL,
  `question` TEXT NOT NULL,
  `option_a` VARCHAR(255) DEFAULT NULL,
  `option_b` VARCHAR(255) DEFAULT NULL,
  `option_c` VARCHAR(255) DEFAULT NULL,
  `option_d` VARCHAR(255) DEFAULT NULL,
  `correct_option` CHAR(1) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_q_q FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS `module_postings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lesson_id` INT NOT NULL,
  `target` ENUM('participants','staff','all') NOT NULL DEFAULT 'all',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_posting_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ------------------ Helpers ------------------
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function full_file_url($path) {
    // If path is empty, return null. If path is already absolute (http), return as-is.
    if (!$path) return null;
    if (preg_match('#^https?://#i', $path)) return $path;
    // make sure path starts with slash
    if ($path[0] !== '/') $path = '/' . $path;
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $proto . $_SERVER['HTTP_HOST'] . $path;
}

// upload dir
$uploadDir = __DIR__ . '/uploads/lessons/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ------------------ Messages ------------------
$errors = [];
$success = '';

// ------------------ Actions: update lesson (AJAX) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_lesson') {
    // AJAX inline update
    header('Content-Type: application/json; charset=utf-8');
    $resp = ['ok' => false, 'errors' => []];
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $resp['errors'][] = 'Invalid CSRF token.';
        echo json_encode($resp); exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $resp['errors'][] = 'Invalid lesson ID.'; echo json_encode($resp); exit; }
    $title = trim($_POST['title'] ?? '');
    $disaster_type = trim($_POST['disaster_type'] ?? '') ?: null;
    $content = trim($_POST['content'] ?? '');
    $scheduled_date = trim($_POST['scheduled_date'] ?? '') ?: null;
    $target = in_array($_POST['target'] ?? 'all', ['participants','staff','all']) ? $_POST['target'] : 'all';

    if ($title === '') $resp['errors'][] = 'Title required';
    if ($content === '') $resp['errors'][] = 'Content required';
    if (!empty($resp['errors'])) { echo json_encode($resp); exit; }

    // handle optional file replacement (if provided)
    if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $name = preg_replace('/[^A-Za-z0-9.\-_]/','_', basename($_FILES['file']['name']));
        $targetFile = $uploadDir . time() . '_' . $name;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $resp['errors'][] = 'Failed to save uploaded file.';
            echo json_encode($resp); exit;
        }
        $filePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $targetFile);
        $stmt = $conn->prepare("UPDATE lessons SET title=?, disaster_type=?, content=?, scheduled_date=?, file_path=? WHERE id=?");
        $stmt->bind_param('sssssi', $title, $disaster_type, $content, $scheduled_date, $filePath, $id);
    } else {
        $stmt = $conn->prepare("UPDATE lessons SET title=?, disaster_type=?, content=?, scheduled_date=? WHERE id=?");
        $stmt->bind_param('ssssi', $title, $disaster_type, $content, $scheduled_date, $id);
    }

    if ($stmt->execute()) {
        // update module_postings target if provided
        $up = $conn->prepare("UPDATE module_postings SET target=? WHERE lesson_id=?");
        $up->bind_param('si', $target, $id);
        $up->execute();
        $up->close();

        $resp['ok'] = true;
        $resp['message'] = 'Lesson updated.';
    } else {
        $resp['errors'][] = 'Update failed: ' . $stmt->error;
    }
    $stmt->close();
    echo json_encode($resp); exit;
}

// ------------------ Actions: update quiz question (AJAX) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_question') {
    header('Content-Type: application/json; charset=utf-8');
    $resp = ['ok'=>false, 'errors'=>[]];
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $resp['errors'][] = 'Invalid CSRF token.'; echo json_encode($resp); exit;
    }
    $qid = (int)($_POST['question_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $a = trim($_POST['option_a'] ?? ''); $b = trim($_POST['option_b'] ?? ''); $c = trim($_POST['option_c'] ?? ''); $d = trim($_POST['option_d'] ?? '');
    $corr = strtoupper(trim($_POST['correct_option'] ?? ''));
    if ($qid <= 0) { $resp['errors'][] = 'Invalid question id'; echo json_encode($resp); exit; }
    if ($question === '') { $resp['errors'][] = 'Question required'; echo json_encode($resp); exit; }
    if (!in_array($corr, ['A','B','C','D',''])) $corr = null;
    $stmt = $conn->prepare("UPDATE quiz_questions SET question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=?");
    $stmt->bind_param('ssssssi', $question, $a, $b, $c, $d, $corr, $qid);
    if ($stmt->execute()) { $resp['ok'] = true; $resp['message'] = 'Question updated.'; } else { $resp['errors'][] = $stmt->error; }
    $stmt->close();
    echo json_encode($resp); exit;
}

// ------------------ POST: create lesson (auto-create quiz + posting) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_lesson']) && !isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $disaster_type = trim($_POST['disaster_type'] ?? '') ?: null;
        $content = trim($_POST['content'] ?? '');
        $scheduled_date = trim($_POST['scheduled_date'] ?? '') ?: null;
        $target = in_array($_POST['target'] ?? 'all', ['participants','staff','all']) ? $_POST['target'] : 'all';

        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';

        // optional file upload
        $filePath = null;
        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $name = preg_replace('/[^A-Za-z0-9.\-_]/','_', basename($_FILES['file']['name']));
            $targetFile = $uploadDir . time() . '_' . $name;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                $filePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $targetFile);
            } else {
                $errors[] = 'Failed to save uploaded file.';
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO lessons (title, disaster_type, content, scheduled_date, file_path, published, created_by, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
            $userId = (int)($_SESSION['id'] ?? 0);
            if ($stmt === false) {
                $errors[] = 'Prepare failed: ' . $conn->error;
            } else {
                $stmt->bind_param('sssssi', $title, $disaster_type, $content, $scheduled_date, $filePath, $userId);
                if ($stmt->execute()) {
                    $lesson_id = $stmt->insert_id;
                    $success = 'Lesson created successfully.';

                    // auto-create quiz row linked to lesson
                    $qTitle = 'Auto Quiz — ' . $title;
                    $qstmt = $conn->prepare("INSERT INTO quizzes (lesson_id, title, created_at) VALUES (?, ?, NOW())");
                    if ($qstmt) {
                        $qstmt->bind_param('is', $lesson_id, $qTitle);
                        $qstmt->execute();
                        $quiz_id = $qstmt->insert_id;
                        $qstmt->close();

                        // create a placeholder question for the admin to edit
                        $placeholderQ = 'Placeholder question — edit this question to make it specific to the lesson.';
                        $optA = 'Option A (edit)'; $optB = 'Option B (edit)'; $optC = 'Option C (edit)'; $optD = 'Option D (edit)';
                        $pq = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_option, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        if ($pq) {
                            $dummyCorrect = 'A';
                            $pq->bind_param('issssss', $quiz_id, $placeholderQ, $optA, $optB, $optC, $optD, $dummyCorrect);
                            $pq->execute();
                            $pq->close();
                        }
                    }

                    // create a module_posting record
                    $pstmt = $conn->prepare("INSERT INTO module_postings (lesson_id, target, created_at) VALUES (?, ?, NOW())");
                    if ($pstmt) {
                        $pstmt->bind_param('is', $lesson_id, $target);
                        $pstmt->execute();
                        $pstmt->close();
                    }
                } else {
                    $errors[] = 'DB error (insert lesson): ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// ------------------ Delete lesson (with cleanup) ------------------
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // fetch file
    $r = $conn->prepare("SELECT file_path FROM lessons WHERE id=? LIMIT 1");
    $r->bind_param('i', $id);
    $r->execute();
    $res = $r->get_result()->fetch_assoc();
    $r->close();
    if (!empty($res['file_path'])) {
        $fp = $_SERVER['DOCUMENT_ROOT'] . $res['file_path'];
        if (file_exists($fp)) @unlink($fp);
    }
    $d = $conn->prepare("DELETE FROM lessons WHERE id=?");
    $d->bind_param('i', $id);
    if ($d->execute()) {
        $success = 'Lesson deleted.';
    } else {
        $errors[] = 'Failed to delete lesson.';
    }
    $d->close();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ------------------ Fetch lessons for display ------------------
$lessons_q = $conn->query("SELECT l.*, p.target FROM lessons l LEFT JOIN module_postings p ON p.lesson_id = l.id ORDER BY l.created_at DESC");

// ------------------ AJAX: fetch single lesson (AJAX preview) ------------------
if (isset($_GET['fetch_lesson']) && is_numeric($_GET['fetch_lesson'])) {
    $id = (int)$_GET['fetch_lesson'];
    $stmt = $conn->prepare("SELECT id,title,disaster_type,content,scheduled_date,file_path,created_at FROM lessons WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode($data ?: []);
    exit;
}

// ------------------ AJAX: fetch quiz + questions for lesson ------------------
if (isset($_GET['fetch_quiz']) && is_numeric($_GET['fetch_quiz'])) {
    header('Content-Type: application/json; charset=utf-8');
    $lesson_id = (int)$_GET['fetch_quiz'];

    // ensure lesson exists
    $chk = $conn->prepare("SELECT id,title FROM lessons WHERE id=? LIMIT 1");
    $chk->bind_param('i', $lesson_id);
    $chk->execute();
    $lesson = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$lesson) {
        echo json_encode(['error' => 'Lesson not found', 'quiz' => null, 'questions' => []]);
        exit;
    }

    // try to get existing quiz
    $q = $conn->prepare("SELECT id,title,created_at FROM quizzes WHERE lesson_id=? LIMIT 1");
    $q->bind_param('i', $lesson_id);
    $q->execute();
    $quiz = $q->get_result()->fetch_assoc();
    $q->close();

    // if no quiz exists, create one and a placeholder question (so frontend always has something)
    if (!$quiz) {
        $qTitle = 'Auto Quiz — ' . ($lesson['title'] ?? 'Lesson');
        $ins = $conn->prepare("INSERT INTO quizzes (lesson_id, title, created_at) VALUES (?, ?, NOW())");
        if ($ins === false) {
            echo json_encode(['error' => 'Failed to create quiz: ' . $conn->error, 'quiz' => null, 'questions' => []]);
            exit;
        }
        $ins->bind_param('is', $lesson_id, $qTitle);
        if (!$ins->execute()) {
            $ins->close();
            echo json_encode(['error' => 'Failed to create quiz execute: ' . $ins->error, 'quiz' => null, 'questions' => []]);
            exit;
        }
        $quiz_id = $ins->insert_id;
        $ins->close();

        // create placeholder question
        $placeholderQ = 'Placeholder question — edit this to match the lesson.';
        $optA = 'Option A (edit)'; $optB = 'Option B (edit)'; $optC = 'Option C (edit)'; $optD = 'Option D (edit)';
        $pq = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_option, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($pq) {
            $dummyCorrect = 'A';
            $pq->bind_param('issssss', $quiz_id, $placeholderQ, $optA, $optB, $optC, $optD, $dummyCorrect);
            $pq->execute();
            $pq->close();
        }

        // now fetch the quiz we just created
        $q = $conn->prepare("SELECT id,title,created_at FROM quizzes WHERE id=? LIMIT 1");
        $q->bind_param('i', $quiz_id);
        $q->execute();
        $quiz = $q->get_result()->fetch_assoc();
        $q->close();
    }

    // fetch questions for the quiz (may be empty)
    $questions = [];
    if ($quiz && isset($quiz['id'])) {
        $qq = $conn->prepare("SELECT id,question,option_a,option_b,option_c,option_d,correct_option FROM quiz_questions WHERE quiz_id=? ORDER BY id ASC");
        $qq->bind_param('i', $quiz['id']);
        $qq->execute();
        $res = $qq->get_result();
        while ($row = $res->fetch_assoc()) $questions[] = $row;
        $qq->close();
    }

    echo json_encode(['error' => null, 'quiz' => $quiz, 'questions' => $questions]);
    exit;
}


?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Content Structuring — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden;background:#f8fafc}
    .main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
    .main-scroll{flex:1;overflow:auto;min-height:0;padding:1.25rem}
    .list-scroll{max-height:60vh;overflow:auto;padding-right:8px}
    .modal-backdrop{background:rgba(2,6,23,0.45)}
    .truncate{max-width:28rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .list-scroll::-webkit-scrollbar{width:10px}
    .list-scroll::-webkit-scrollbar-thumb{background-color:rgba(2,6,23,0.06);border-radius:8px}
    .prose { line-height:1.5; color:#0f172a; }
    .field-label { font-size:.9rem; color:#334155; }
  </style>
</head>
<body class="font-sans text-slate-800">

<div class="app">
  <!-- include your sidebar.php as requested -->
  <?php include '../sidebar.php'; ?>

  <div class="main-wrap">
    <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold">Content Structuring — Admin</h1>
        <div class="text-sm text-slate-500">Organize lessons and automatically create quizzes for participants.</div>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'Admin') ?></strong></div>
      </div>
    </header>

    <main class="main-scroll">
      <div class="max-w-6xl mx-auto space-y-6">

        <!-- messages -->
        <?php if (!empty($errors)): ?>
          <div class="p-3 rounded bg-rose-50 border border-rose-100 text-rose-800">
            <strong>Errors:</strong>
            <ul class="mt-1 ml-4"><?php foreach($errors as $er) echo "<li>".esc($er)."</li>"; ?></ul>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="p-3 rounded bg-emerald-50 border border-emerald-100 text-emerald-800"><?= esc($success) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Create form -->
          <section class="bg-white p-6 rounded-2xl shadow">
            <h2 class="text-lg font-semibold mb-2">Create Structured Lesson</h2>
            <p class="text-sm text-slate-500 mb-4">When saved, a quiz will be automatically created and the lesson posted to modules.</p>

            <form method="POST" enctype="multipart/form-data" id="createForm">
              <input type="hidden" name="csrf_token" value="<?= esc($CSRF) ?>">
              <div class="mb-3">
                <label class="field-label">Title</label>
                <input name="title" required class="mt-1 w-full px-3 py-2 border rounded-lg" />
              </div>
              <div class="mb-3">
                <label class="field-label">Disaster Type</label>
                <select name="disaster_type" class="mt-1 w-full px-3 py-2 border rounded-lg">
                  <option value="">— Select —</option>
                  <option>Flood</option><option>Earthquake</option><option>Fire</option><option>Storm</option>
                  <option>Workshop</option><option>Drill</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="field-label">Scheduled Date (optional)</label>
                <input type="date" name="scheduled_date" class="mt-1 px-3 py-2 border rounded-lg w-full" />
              </div>

              <div class="mb-3">
                <label class="field-label">Content</label>
                <textarea name="content" rows="6" class="mt-1 w-full px-3 py-2 border rounded-lg"></textarea>
              </div>

              <div class="mb-3">
                <label class="field-label">Attach file (optional)</label>
                <input type="file" name="file" class="mt-1 w-full" />
              </div>

              <div class="mb-3">
                <label class="field-label">Publish target</label>
                <select name="target" class="mt-1 px-3 py-2 border rounded-lg">
                  <option value="all">Participants & Staff</option>
                  <option value="participants">Participants</option>
                  <option value="staff">Staff</option>
                </select>
              </div>

              <div class="flex justify-end gap-2">
                <button type="reset" class="px-4 py-2 bg-gray-100 rounded-lg">Reset</button>
                <button type="submit" name="create_lesson" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Save & Create Quiz</button>
              </div>
            </form>
          </section>

          <!-- Lessons list -->
          <section class="bg-white p-6 rounded-2xl shadow lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold">Lessons</h3>
                <div class="text-sm text-slate-500">Click Review to open lesson details, edit inline, open attachment, or open the generated quiz.</div>
              </div>
              <div class="flex items-center gap-3">
                <input id="search" placeholder="Search lessons..." class="px-3 py-2 border rounded-lg" />
                <button onclick="applySearch()" class="px-3 py-2 bg-sky-600 text-white rounded-lg">Search</button>
              </div>
            </div>

            <div class="list-scroll grid gap-4" id="lessonsContainer">
              <?php if ($lessons_q && $lessons_q->num_rows): while ($l = $lessons_q->fetch_assoc()): ?>
                <div class="p-4 border rounded-lg flex items-start justify-between bg-white">
                  <div>
                    <a href="javascript:void(0)" class="block text-lg font-semibold text-slate-800 lesson-link" data-id="<?= (int)$l['id'] ?>"><?= esc($l['title']) ?></a>
                    <div class="text-xs text-slate-500 mt-1">
                      <?= esc($l['disaster_type'] ?? 'Uncategorized') ?> • Posted: <?= date('M d, Y', strtotime($l['created_at'])) ?>
                      <?php if (!empty($l['scheduled_date'])): ?> • Scheduled: <?= date('M d, Y', strtotime($l['scheduled_date'])) ?><?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-600 mt-2 truncate"><?= esc(substr(strip_tags($l['content']),0,240)) ?><?= (strlen(strip_tags($l['content']))>240? '...' : '') ?></p>
                  </div>

                  <div class="flex flex-col items-end gap-2">
                    <div class="text-xs text-slate-500">Target: <strong><?= esc($l['target'] ?? 'all') ?></strong></div>
                    <div class="flex gap-2">
                      <button class="px-3 py-1 text-sm bg-slate-50 rounded border review-btn" data-id="<?= (int)$l['id'] ?>">Review</button>
                      <a href="?delete=<?= (int)$l['id'] ?>" onclick="return confirm('Delete this lesson?')" class="px-3 py-1 text-sm bg-rose-50 rounded border text-rose-700">Delete</a>
                    </div>
                  </div>
                </div>
              <?php endwhile; else: ?>
                <div class="p-6 text-center text-slate-500 bg-white rounded-lg">No lessons yet. Use the left panel to create one.</div>
              <?php endif; ?>
            </div>
          </section>

        </div>

      </div>
    </main>
  </div>
</div>

<!-- Modal (preview + edit + quiz) -->
<div id="modal" class="fixed inset-0 hidden items-center justify-center z-50">
  <div class="modal-backdrop absolute inset-0"></div>
  <div class="relative bg-white rounded-2xl shadow-lg w-11/12 max-w-4xl z-10 overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between gap-4">
      <div>
        <h3 id="modalTitle" class="text-lg font-semibold">Lesson Preview</h3>
        <div id="modalSubtitle" class="text-xs text-slate-500"></div>
      </div>
      <div class="flex items-center gap-2">
        <button id="modalClose" class="text-slate-500 hover:text-slate-700">&times;</button>
      </div>
    </div>

    <div class="p-4 max-h-[62vh] overflow-auto grid grid-cols-1 lg:grid-cols-3 gap-4" id="modalBody">
      <!-- Left/main: preview or edit area (col-span 2) -->
      <div class="lg:col-span-2" id="modalMain">
        <div id="previewContent" class="prose text-slate-800"></div>

        <!-- Inline edit form (hidden until edit clicked) -->
        <form id="editForm" class="space-y-3 mt-2 hidden" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_lesson" />
          <input type="hidden" name="id" id="edit_id" value="" />
          <input type="hidden" name="csrf_token" value="<?= esc($CSRF) ?>">
          <div>
            <label class="field-label">Title</label>
            <input name="title" id="edit_title" class="mt-1 w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="field-label">Disaster Type</label>
            <input name="disaster_type" id="edit_disaster_type" class="mt-1 w-full px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="field-label">Scheduled Date</label>
            <input type="date" name="scheduled_date" id="edit_scheduled_date" class="mt-1 px-3 py-2 border rounded-lg" />
          </div>
          <div>
            <label class="field-label">Content</label>
            <textarea name="content" id="edit_content" rows="6" class="mt-1 w-full px-3 py-2 border rounded-lg"></textarea>
          </div>
          <div>
            <label class="field-label">Replace Attachment (optional)</label>
            <input type="file" name="file" id="edit_file" class="mt-1" />
            <div id="currentFile" class="text-sm text-slate-500 mt-1"></div>
          </div>
          <div>
            <label class="field-label">Publish target</label>
            <select name="target" id="edit_target" class="mt-1 px-3 py-2 border rounded-lg">
              <option value="all">Participants & Staff</option>
              <option value="participants">Participants</option>
              <option value="staff">Staff</option>
            </select>
          </div>

          <div class="flex justify-end gap-2 mt-2">
            <button type="button" id="cancelEdit" class="px-3 py-2 bg-gray-100 rounded">Cancel</button>
            <button type="submit" id="saveEdit" class="px-4 py-2 bg-indigo-600 text-white rounded">Save changes</button>
          </div>
        </form>

      </div>

      <!-- Right sidebar: meta + actions -->
      <aside class="lg:col-span-1 bg-slate-50 p-3 rounded-lg">
        <div id="metaBlock" class="text-sm text-slate-700 space-y-2">
          <div><strong>Scheduled:</strong> <span id="metaDate">—</span></div>
          <div><strong>Type:</strong> <span id="metaType">—</span></div>
          <div><strong>Created:</strong> <span id="metaCreated">—</span></div>
          <div><strong>Target:</strong> <span id="metaTarget">—</span></div>
          <div id="attachmentArea" class="mt-2"></div>
        </div>

        <div class="mt-4 flex flex-col gap-2">
          <button id="btnEditInline" class="px-3 py-2 bg-slate-100 rounded-lg">Edit Inline</button>
          <button id="btnOpenQuiz" class="px-3 py-2 bg-indigo-600 text-white rounded-lg">Open Quiz</button>
          <button id="btnCloseModal" class="px-3 py-2 bg-gray-100 rounded-lg">Close</button>
        </div>
      </aside>
    </div>

    <!-- Quiz area (hidden/shown when Open Quiz clicked) -->
    <div id="quizArea" class="p-4 border-t hidden">
      <h4 class="font-semibold">Quiz — <span id="quizTitle">—</span></h4>
      <div id="quizQuestions" class="space-y-4 mt-3"></div>
      <div class="mt-3 flex gap-2 justify-end">
        <button id="btnCloseQuiz" class="px-3 py-2 bg-gray-100 rounded">Close Quiz</button>
      </div>
    </div>

  </div>
</div>

<script>
  lucide.createIcons();

  // Basic search function on list
  function applySearch() {
    const q = document.getElementById('search').value.trim().toLowerCase();
    document.querySelectorAll('#lessonsContainer > div').forEach(el => {
      const text = el.innerText.toLowerCase();
      el.style.display = text.includes(q) ? '' : 'none';
    });
  }

  // Modal elements
  const modal = document.getElementById('modal');
  const modalTitle = document.getElementById('modalTitle');
  const modalSubtitle = document.getElementById('modalSubtitle');
  const previewContent = document.getElementById('previewContent');
  const metaDate = document.getElementById('metaDate');
  const metaType = document.getElementById('metaType');
  const metaCreated = document.getElementById('metaCreated');
  const metaTarget = document.getElementById('metaTarget');
  const attachmentArea = document.getElementById('attachmentArea');
  const modalBody = document.getElementById('modalBody');

  const editForm = document.getElementById('editForm');
  const editFormElems = {
    id: document.getElementById('edit_id'),
    title: document.getElementById('edit_title'),
    disaster_type: document.getElementById('edit_disaster_type'),
    scheduled_date: document.getElementById('edit_scheduled_date'),
    content: document.getElementById('edit_content'),
    file: document.getElementById('edit_file'),
    target: document.getElementById('edit_target'),
    currentFile: document.getElementById('currentFile')
  };
  const btnEditInline = document.getElementById('btnEditInline');
  const btnOpenQuiz = document.getElementById('btnOpenQuiz');
  const quizArea = document.getElementById('quizArea');
  const quizTitleEl = document.getElementById('quizTitle');
  const quizQuestionsEl = document.getElementById('quizQuestions');

  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('btnCloseModal').addEventListener('click', closeModal);

  function openModalWithData(data) {
    modalTitle.textContent = data.title || 'Lesson Preview';
    modalSubtitle.textContent = data.disaster_type ? data.disaster_type : '';
    previewContent.innerHTML = (data.content || '').replace(/\n/g,'<br>');
    metaDate.textContent = data.scheduled_date ? new Date(data.scheduled_date).toLocaleDateString() : '—';
    metaType.textContent = data.disaster_type || '—';
    metaCreated.textContent = data.created_at ? new Date(data.created_at).toLocaleString() : '—';
    metaTarget.textContent = data.target || 'all';
    // attachment
    attachmentArea.innerHTML = '';
    if (data.file_path) {
      // ensure link uses proper host if stored relative
      let url = data.file_path;
      if (!/^https?:\/\//i.test(url) && url[0] !== '/') {
        url = '/' + url;
      }
      const a = document.createElement('a');
      a.href = url;
      a.target = '_blank';
      a.className = 'text-indigo-600 underline';
      a.textContent = 'Open attachment';
      attachmentArea.appendChild(a);
    }

    // hide edit form, quiz area
    editForm.classList.add('hidden');
    quizArea.classList.add('hidden');
    // populate edit fields for inline edit readiness
    editFormElems.id.value = data.id || '';
    editFormElems.title.value = data.title || '';
    editFormElems.disaster_type.value = data.disaster_type || '';
    editFormElems.scheduled_date.value = data.scheduled_date || '';
    editFormElems.content.value = data.content || '';
    editFormElems.target.value = data.target || 'all';
    editFormElems.currentFile.innerHTML = data.file_path ? `<a href="${(data.file_path[0]==='/'?data.file_path:('/'+data.file_path))}" target="_blank" class="text-indigo-600">Current attachment</a>` : 'No attachment';

    // store current lesson id on buttons
    btnOpenQuiz.dataset.lessonId = data.id;
    btnEditInline.dataset.lessonId = data.id;

    modal.classList.remove('hidden'); modal.classList.add('flex');
  }

  function closeModal() {
    modal.classList.remove('flex'); modal.classList.add('hidden');
  }

  // attach click listeners to review buttons and lesson links
  document.querySelectorAll('.lesson-link, .review-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.dataset.id;
      fetch('?fetch_lesson=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(data => {
          // augment data with posting target by reading element? We'll fetch it from server via lessons list (we included p.target in initial query).
          // For simplicity, ensure server returned created_at etc.
          // Also fetch posting target by making a small request? but list included target in dataset; instead we'll set target='all' if missing
          if (!data.target) data.target = 'all';
          openModalWithData(data);
        })
        .catch(err => {
          alert('Failed to load preview.');
        });
    });
  });

  // Edit inline toggle
  btnEditInline.addEventListener('click', function(){
    // toggle edit form visibility
    if (editForm.classList.contains('hidden')) {
      editForm.classList.remove('hidden');
      previewContent.classList.add('hidden');
    } else {
      editForm.classList.add('hidden');
      previewContent.classList.remove('hidden');
    }
  });

  // Cancel edit
  document.getElementById('cancelEdit').addEventListener('click', function(e){
    e.preventDefault();
    editForm.classList.add('hidden');
    previewContent.classList.remove('hidden');
  });

  // Save edit via AJAX
  editForm.addEventListener('submit', function(e){
    e.preventDefault();
    const id = editFormElems.id.value;
    const formData = new FormData(editForm);
    // append csrf token is already present, action=update_lesson included
    fetch(window.location.pathname, {
      method: 'POST',
      body: formData
    }).then(r=>r.json()).then(json=>{
      if (json.ok) {
        alert(json.message || 'Updated');
        location.reload(); // simplest: reload so list updates; could update DOM instead
      } else {
        alert('Update failed: ' + (json.errors ? json.errors.join('; ') : 'unknown'));
      }
    }).catch(err=>{
      alert('Update request failed.');
    });
  });

  // Open Quiz (fetch and show)
  btnOpenQuiz.addEventListener('click', function(){
    const lessonId = this.dataset.lessonId;
    if (!lessonId) return alert('Lesson id missing');
    fetch('?fetch_quiz=' + encodeURIComponent(lessonId))
      .then(r => r.json())
      .then(payload => {
        if (!payload.quiz) return alert('No quiz found for this lesson.');
        // show quiz area and populate
        quizTitleEl.textContent = payload.quiz.title || 'Quiz';
        quizQuestionsEl.innerHTML = '';
        payload.questions.forEach(q => {
          const wrapper = document.createElement('div');
          wrapper.className = 'p-3 border rounded-lg bg-white';
          wrapper.innerHTML = `
            <div class="text-sm font-medium mb-2">Q: <input data-qid="${q.id}" class="w-full border px-2 py-1" value="${escapeHtml(q.question)}" /></div>
            <div class="grid gap-2">
              <input data-qid="${q.id}" data-field="option_a" class="border px-2 py-1" value="${escapeHtml(q.option_a||'')}" />
              <input data-qid="${q.id}" data-field="option_b" class="border px-2 py-1" value="${escapeHtml(q.option_b||'')}" />
              <input data-qid="${q.id}" data-field="option_c" class="border px-2 py-1" value="${escapeHtml(q.option_c||'')}" />
              <input data-qid="${q.id}" data-field="option_d" class="border px-2 py-1" value="${escapeHtml(q.option_d||'')}" />
              <div class="flex items-center gap-2 mt-2">
                <label class="text-xs">Correct:</label>
                <select data-qid="${q.id}" data-field="correct_option" class="border px-2 py-1">
                  <option value="">—</option>
                  <option value="A"${q.correct_option==='A'?' selected':''}>A</option>
                  <option value="B"${q.correct_option==='B'?' selected':''}>B</option>
                  <option value="C"${q.correct_option==='C'?' selected':''}>C</option>
                  <option value="D"${q.correct_option==='D'?' selected':''}>D</option>
                </select>
                <button data-qid="${q.id}" class="save-question px-2 py-1 bg-sky-600 text-white rounded ml-auto">Save</button>
              </div>
            </div>
          `;
          quizQuestionsEl.appendChild(wrapper);
        });
        quizArea.classList.remove('hidden');
        // scroll modal to show quiz
        quizArea.scrollIntoView({behavior:'smooth'});
        // attach save handlers
        document.querySelectorAll('.save-question').forEach(btn=>{
          btn.addEventListener('click', function(){
            const qid = this.dataset.qid;
            // find inputs
            const qInput = document.querySelector(`input[data-qid="${qid}"]`);
            const opts = {};
            ['option_a','option_b','option_c','option_d'].forEach((f,i)=>{
              const el = document.querySelector(`input[data-qid="${qid}"][data-field="${f}"]`);
              // if not found search within wrapper via data-field attr
              if (el) opts[f] = el.value;
              else {
                const alt = document.querySelector(`[data-qid="${qid}"][data-field="${f}"]`);
                opts[f] = alt ? alt.value : '';
              }
            });
            const qTextEl = document.querySelector(`input[data-qid="${qid}"]`);
            const qText = qTextEl ? qTextEl.value : '';
            const corr = document.querySelector(`select[data-qid="${qid}"][data-field="correct_option"]`).value;
            // send AJAX to update_question
            const fd = new FormData();
            fd.append('action','update_question');
            fd.append('csrf_token','<?= esc($CSRF) ?>');
            fd.append('question_id', qid);
            fd.append('question', qText);
            fd.append('option_a', opts.option_a || '');
            fd.append('option_b', opts.option_b || '');
            fd.append('option_c', opts.option_c || '');
            fd.append('option_d', opts.option_d || '');
            fd.append('correct_option', corr || '');
            fetch(window.location.pathname, { method: 'POST', body: fd })
              .then(r=>r.json()).then(j=>{
                if (j.ok) { alert('Question saved'); } else { alert('Save failed: ' + (j.errors? j.errors.join('; ') : '')) }
              }).catch(()=> alert('Save request failed'));
          });
        });
      }).catch(err=> { alert('Failed to load quiz'); });
  });

  document.getElementById('btnCloseQuiz').addEventListener('click', function(){ quizArea.classList.add('hidden'); });

  // utility: escape for input value
  function escapeHtml(s){ if (s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // close modal on backdrop click
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  // wire review buttons newly created dynamically? already bound above on initial render.
  // If you generate list dynamically later, re-run attaching logic.

</script>

</body>
</html>
