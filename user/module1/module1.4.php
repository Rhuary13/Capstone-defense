<?php
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
// Security check
// =========================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$lesson_id = $_GET['lesson_id'] ?? 5;

// =========================
// Validate lesson_id exists in training_modules
// =========================
$checkLesson = $conn->prepare("SELECT id FROM training_modules WHERE id = ?");
$checkLesson->bind_param("i", $lesson_id);
$checkLesson->execute();
$checkLesson->store_result();

if ($checkLesson->num_rows === 0) {
    $stmt = $conn->prepare("INSERT IGNORE INTO training_modules 
        (id, title, objectives, disaster_type, created_at, updated_at) 
        VALUES (?, ?, 'Auto-created objectives', 'General', NOW(), NOW())");
    $autoTitle = "Lesson " . $lesson_id;
    $stmt->bind_param("is", $lesson_id, $autoTitle);
    $stmt->execute();
    $stmt->close();
}
$checkLesson->close();

// =========================
// Fetch quiz questions
// =========================
$questions = $conn->query("SELECT * FROM quiz_questions WHERE lesson_id = $lesson_id");

if (!$questions || $questions->num_rows === 0) {
    $fallback = $conn->query("SELECT DISTINCT lesson_id FROM quiz_questions LIMIT 1");
    if ($fallback && $fallback->num_rows > 0) {
        $row = $fallback->fetch_assoc();
        $lesson_id = $row['lesson_id'];

        $checkLesson = $conn->prepare("SELECT id FROM training_modules WHERE id = ?");
        $checkLesson->bind_param("i", $lesson_id);
        $checkLesson->execute();
        $checkLesson->store_result();
        if ($checkLesson->num_rows === 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO training_modules 
                (id, title, objectives, disaster_type, created_at, updated_at) 
                VALUES (?, ?, 'Auto-created objectives', 'General', NOW(), NOW())");
            $autoTitle = "Lesson " . $lesson_id;
            $stmt->bind_param("is", $lesson_id, $autoTitle);
            $stmt->execute();
            $stmt->close();
        }
        $checkLesson->close();

        $questions = $conn->query("SELECT * FROM quiz_questions WHERE lesson_id = $lesson_id");
    }
}

// =========================
// Fetch last quiz result
// =========================
$result_message = "";
$last_result = $conn->prepare("SELECT score, total_questions, status 
                               FROM quiz_results 
                               WHERE participant_id = ? AND lesson_id = ? 
                               ORDER BY taken_at DESC LIMIT 1");
$last_result->bind_param("ii", $user_id, $lesson_id);
$last_result->execute();
$last_result->bind_result($last_score, $last_total, $last_status);
$last_result->fetch();
$last_result->close();

if (!empty($last_status)) {
    $percent = ($last_total > 0) ? round(($last_score / $last_total) * 100, 2) : 0;
    $result_message = "Your latest result: <strong>$last_score / $last_total</strong> ($percent%). 
                      <span class='" . ($last_status == "Passed" ? "text-green-600" : "text-red-600") . "'>$last_status</span>";
}

// =========================
// Process quiz submission
// =========================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $questions && $questions->num_rows > 0) {
    $score = 0;
    $total = $questions->num_rows;

    $questions->data_seek(0);

    while ($q = $questions->fetch_assoc()) {
        $qid     = $q['question_id'];
        $correct = $q['correct_option'];
        $answer  = $_POST["q$qid"] ?? '';

        if ($answer === $correct) {
            $score++;
        }
    }

    $percentage = ($total > 0) ? ($score / $total) * 100 : 0;
    $status = ($percentage >= 70) ? "Passed" : "Failed";

    $check = $conn->prepare("SELECT result_id FROM quiz_results WHERE participant_id = ? AND lesson_id = ?");
    $check->bind_param("ii", $user_id, $lesson_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE quiz_results 
                                SET score = ?, total_questions = ?, status = ?, taken_at = NOW()
                                WHERE participant_id = ? AND lesson_id = ?");
        $stmt->bind_param("iissi", $score, $total, $status, $user_id, $lesson_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO quiz_results 
                                (participant_id, lesson_id, score, total_questions, status, taken_at) 
                                VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiiss", $user_id, $lesson_id, $score, $total, $status);
    }
    $stmt->execute();
    $stmt->close();
    $check->close();

    $result_message = "You scored <strong>$score / $total</strong> (" . round($percentage, 2) . "%). Result: 
                      <span class='" . ($status == "Passed" ? "text-green-600" : "text-red-600") . "'>$status</span>";
}

// =========================
// Fetch ALL quiz history (Records)
// =========================
$records = $conn->prepare("SELECT qm.title, qr.score, qr.total_questions, qr.status, qr.taken_at 
                           FROM quiz_results qr
                           JOIN training_modules qm ON qr.lesson_id = qm.id
                           WHERE qr.participant_id = ?
                           ORDER BY qr.taken_at DESC");
$records->bind_param("i", $user_id);
$records->execute();
$history = $records->get_result();
$records->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Test & Review</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto h-screen">
    
    <!-- Records Section -->
    <div class="max-w mx-8 bg-white mt-8 p-6 rounded-2xl shadow-lg">
      <h2 class="text-2xl font-bold text-blue-700 mb-4">📊 Your Records</h2>
      <?php if ($history && $history->num_rows > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-blue-600 text-white">
              <tr>
                <th class="p-3">Module</th>
                <th class="p-3 text-center">Score</th>
                <th class="p-3 text-center">Status</th>
                <th class="p-3 text-center">Date Taken</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $history->fetch_assoc()): ?>
                <tr class="border-b hover:bg-gray-50">
                  <td class="p-3 font-semibold"><?= htmlspecialchars($row['title']); ?></td>
                  <td class="p-3 text-center"><?= $row['score'] ?>/<?= $row['total_questions'] ?></td>
                  <td class="p-3 text-center <?= $row['status'] == 'Passed' ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $row['status'] ?>
                  </td>
                  <td class="p-3 text-center"><?= date("F d, Y h:i A", strtotime($row['taken_at'])) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-gray-600">No completion records found yet.</p>
      <?php endif; ?>
    </div>
  
  
    <div class="max-w-4xl mx-auto bg-white mt-8 p-6 rounded-2xl shadow-lg">
      <h1 class="text-3xl font-bold text-blue-700 mb-6">📝 Test & Review</h1>

      <?php if (!empty($result_message)): ?>
        <div class="p-4 mb-6 bg-gray-50 border rounded-lg">
          <?= $result_message ?>
        </div>
        <a href="module1.1.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">⬅️ Back to Lessons</a>

      <?php elseif ($questions && $questions->num_rows > 0): ?>
        <form method="POST" class="space-y-6">
          <?php 
          $num = 1;
          $questions->data_seek(0);
          while ($q = $questions->fetch_assoc()): ?>
            <div class="p-4 border rounded-lg shadow-sm bg-gray-50">
              <p class="font-semibold mb-3">Q<?= $num++; ?>: <?= htmlspecialchars($q['question']); ?></p>
              <?php foreach (['A','B','C','D'] as $opt): ?>
                <label class="block mb-2">
                  <input type="radio" name="q<?= $q['question_id']; ?>" value="<?= $opt ?>" class="mr-2" required>
                  <?= $opt ?>) <?= htmlspecialchars($q["option_" . strtolower($opt)]); ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endwhile; ?>
          <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700">✅ Submit Answers</button>
        </form>

      <?php else: ?>
        <p class="text-red-600 font-semibold">❌ No quiz available for this lesson yet. Please check back later.</p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
