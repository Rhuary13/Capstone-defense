<?php
// participant_content.php
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

// =========================
// Ensure tables exist
// =========================
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

// =========================
// Fetch Lessons
// =========================
$lessons = $conn->query("SELECT * FROM lessons ORDER BY created_at ASC");

// =========================
// Fetch Quiz if lesson selected
// =========================
$quiz = null;
if (isset($_GET['lesson_id'])) {
    $lesson_id = intval($_GET['lesson_id']);
    $quiz = $conn->query("SELECT * FROM quizzes WHERE lesson_id=$lesson_id");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Learning Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900 flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-8 overflow-y-auto">
    <header class="mb-8 text-center">
      <h1 class="text-3xl font-bold text-blue-800">📘 Learning Center</h1>
      <p class="text-lg text-gray-600">Easy lessons and quizzes for all participants</p>
    </header>

    <!-- Lessons List -->
    <section class="mb-10">
      <h2 class="text-2xl font-semibold text-gray-800 mb-4">📖 Lessons</h2>
      <div class="grid gap-4">
        <?php while ($lesson = $lessons->fetch_assoc()): ?>
          <a href="?lesson_id=<?= $lesson['id'] ?>" 
             class="block p-6 bg-white rounded-xl shadow hover:bg-blue-50 transition text-xl">
            <?= htmlspecialchars($lesson['title']) ?>
          </a>
        <?php endwhile; ?>
        <?php if ($lessons->num_rows === 0): ?>
          <p class="text-gray-500 text-lg">No lessons available yet.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- Lesson Content + Quiz -->
    <?php if (isset($_GET['lesson_id'])): ?>
      <?php
        $lesson_id = intval($_GET['lesson_id']);
        $lesson = $conn->query("SELECT * FROM lessons WHERE id=$lesson_id")->fetch_assoc();
      ?>
      <section class="mb-10">
        <h2 class="text-2xl font-bold text-blue-700 mb-4">📌 <?= htmlspecialchars($lesson['title']) ?></h2>
        <div class="bg-white p-6 rounded-lg shadow text-lg leading-relaxed">
          <?= nl2br(htmlspecialchars($lesson['content'])) ?>
        </div>
      </section>

      <section>
        <h3 class="text-xl font-semibold text-gray-800 mb-4">📝 Quiz</h3>
        <?php if ($quiz && $quiz->num_rows > 0): ?>
          <form method="post" class="space-y-6">
            <?php while ($q = $quiz->fetch_assoc()): ?>
              <div class="bg-white p-6 rounded-lg shadow">
                <p class="text-lg font-medium mb-4"><?= htmlspecialchars($q['question']) ?></p>
                <div class="space-y-3 text-lg">
                  <label class="block"><input type="radio" name="q<?= $q['id'] ?>" value="A" class="mr-2"> <?= htmlspecialchars($q['option_a']) ?></label>
                  <label class="block"><input type="radio" name="q<?= $q['id'] ?>" value="B" class="mr-2"> <?= htmlspecialchars($q['option_b']) ?></label>
                  <label class="block"><input type="radio" name="q<?= $q['id'] ?>" value="C" class="mr-2"> <?= htmlspecialchars($q['option_c']) ?></label>
                  <label class="block"><input type="radio" name="q<?= $q['id'] ?>" value="D" class="mr-2"> <?= htmlspecialchars($q['option_d']) ?></label>
                </div>
              </div>
            <?php endwhile; ?>
            <button type="submit" class="bg-green-600 text-white text-xl px-6 py-3 rounded-lg shadow hover:bg-green-700">Submit Answers</button>
          </form>
        <?php else: ?>
          <p class="text-gray-500 text-lg">No quiz available for this lesson.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </div>
</body>
</html>
