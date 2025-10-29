<?php
// module1.2.php — Content Structuring (single-file, admin)
// Assumes lessons table columns: id, title, content, created_at

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

// ------------------ Helpers ------------------
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
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

// ------------------ Messages ------------------
$errors = [];
$success = [];

// ------------------ POST: Add Lesson ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO lessons (title, content, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param('ss', $title, $content);
            if ($stmt->execute()) {
                $success[] = 'Lesson created successfully.';
            } else {
                $errors[] = 'DB error (insert lesson): ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ------------------ GET: Delete Lesson ------------------
if (isset($_GET['delete_lesson'])) {
    $id = (int)$_GET['delete_lesson'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM lessons WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) $success[] = 'Lesson deleted.';
        else $errors[] = 'Failed to delete: ' . $stmt->error;
        $stmt->close();
        // redirect to remove query param
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// ------------------ Fetch lessons for listing ------------------
// Use created_at ordering; fallback to id if needed
$lessons_q = $conn->query("SELECT id, title, content, created_at FROM lessons ORDER BY created_at DESC");
if (!$lessons_q) {
    $lessons_q = $conn->query("SELECT id, title, content, created_at FROM lessons ORDER BY id DESC");
}

// ------------------ Fetch quizzes safely (if table exists) ------------------
$quizzes_q = false;
if (table_exists($conn, 'quizzes')) {
    if (column_exists($conn, 'quizzes', 'created_at')) {
        $quizzes_q = $conn->query("SELECT q.*, l.title AS lesson_title FROM quizzes q LEFT JOIN lessons l ON q.lesson_id = l.id ORDER BY q.created_at DESC");
    } else {
        $quizzes_q = $conn->query("SELECT q.*, l.title AS lesson_title FROM quizzes q LEFT JOIN lessons l ON q.lesson_id = l.id ORDER BY q.id DESC");
    }
}

// Example SQL for quizzes if missing (shown in UI)
$example_sql = <<<SQL
-- Example SQL to create quizzes & questions:
CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question TEXT NOT NULL,
  option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT,
  correct CHAR(1) NOT NULL
);
SQL;

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Content Structuring — Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html,body{height:100%}
    .app{display:flex;height:100vh;overflow:hidden}
    .main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
    .main-scroll{flex:1;overflow:auto;min-height:0;padding:1.5rem;background:#f8fafc}
    .truncate{max-width:18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  </style>
</head>
<body class="font-sans text-slate-800">

<div class="app">
  <!-- Sidebar include -->
  <?php include '../sidebar.php'; ?>

  <div class="main-wrap">
    <header class="bg-white border-b h-16 flex items-center justify-between px-6">
      <div>
        <h1 class="text-lg font-semibold">Content Structuring</h1>
        <div class="text-sm text-slate-500">Create lessons and manage quizzes</div>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-sm text-slate-700">Signed in as <strong><?= esc($_SESSION['username'] ?? 'Admin') ?></strong></div>
      </div>
    </header>

    <main class="main-scroll" role="main">
      <div class="max-w-6xl mx-auto">

        <!-- messages -->
        <?php foreach ($errors as $er): ?>
          <div class="mb-3 p-3 rounded bg-rose-50 border border-rose-100 text-rose-800"><?= esc($er) ?></div>
        <?php endforeach; ?>
        <?php foreach ($success as $s): ?>
          <div class="mb-3 p-3 rounded bg-green-50 border border-green-100 text-green-800"><?= esc($s) ?></div>
        <?php endforeach; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Lesson form -->
          <section class="bg-white p-6 rounded-2xl shadow">
            <h2 class="text-lg font-semibold mb-3">Create Lesson</h2>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= esc($CSRF) ?>">
              <div class="mb-3">
                <label class="text-sm font-medium">Title</label>
                <input name="title" required class="mt-1 w-full px-3 py-2 border rounded" />
              </div>
              <div class="mb-3">
                <label class="text-sm font-medium">Content</label>
                <textarea name="content" rows="6" class="mt-1 w-full px-3 py-2 border rounded"></textarea>
              </div>
              <div class="flex justify-end">
                <button type="submit" name="add_lesson" class="px-4 py-2 bg-indigo-600 text-white rounded">Save Lesson</button>
              </div>
            </form>
            <div class="text-xs text-slate-400 mt-3">Note: your lessons table stores the lesson body in <code>content</code>.</div>
          </section>

          <!-- placeholder quizzes panel -->
          <section class="bg-white p-6 rounded-2xl shadow">
            <h2 class="text-lg font-semibold mb-3">Quizzes</h2>
            <?php if ($quizzes_q !== false): ?>
              <p class="text-sm text-slate-500 mb-3">Recent quizzes</p>
              <div class="overflow-auto rounded border border-slate-100">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
                    <tr>
                      <th class="px-3 py-2 text-left">Quiz</th>
                      <th class="px-3 py-2 text-left">Lesson</th>
                      <th class="px-3 py-2 text-left">Created</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($q = $quizzes_q->fetch_assoc()): ?>
                      <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2"><?= esc($q['title'] ?? 'Untitled') ?></td>
                        <td class="px-3 py-2 truncate"><?= esc($q['lesson_title'] ?? '-') ?></td>
                        <td class="px-3 py-2"><?= esc($q['created_at'] ?? $q['id']) ?></td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-sm text-slate-500">No quizzes table found. Create <code>quizzes</code> and <code>quiz_questions</code> to enable quizzes.</div>
              <pre class="mt-3 p-2 bg-white text-xs rounded border"><?= esc($example_sql) ?></pre>
            <?php endif; ?>
          </section>

        </div>

        <div class="mt-6 bg-white p-6 rounded-2xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Lessons</h3>
            <div class="text-sm text-slate-500">Total: <?= ($lessons_q ? $lessons_q->num_rows : 0) ?></div>
          </div>

          <div class="overflow-auto rounded border border-slate-100">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-slate-700 text-xs uppercase">
                <tr>
                  <th class="px-3 py-2 text-left">Title</th>
                  <th class="px-3 py-2 text-left">Content (preview)</th>
                  <th class="px-3 py-2 text-left">Created</th>
                  <th class="px-3 py-2 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($lessons_q && $lessons_q->num_rows): while ($row = $lessons_q->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="px-3 py-2 font-medium"><?= esc($row['title']) ?></td>
                  <td class="px-3 py-2 truncate"><?= esc(substr(strip_tags($row['content']), 0, 180)) ?></td>
                  <td class="px-3 py-2"><?= esc($row['created_at'] ?? '') ?></td>
                  <td class="px-3 py-2 flex gap-3">
                    <a class="text-indigo-600" href="edit_lesson.php?id=<?= (int)$row['id'] ?>">Edit</a>
                    <a class="text-red-600" href="#" onclick="if(confirm('Delete this lesson?')) window.location='?delete_lesson=<?= (int)$row['id'] ?>'">Delete</a>
                  </td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="4" class="p-4 text-center text-slate-500">No lessons yet.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>
