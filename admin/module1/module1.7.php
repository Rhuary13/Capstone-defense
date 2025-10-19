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
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// Helper functions
// =========================
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
    if ($rate < 50) return 'text-red-500';
    if ($rate < 75) return 'text-yellow-500';
    return 'text-green-500';
}

// =========================
// Fetch Training Completion per module
// =========================
$completion_stats = [];
$check = $conn->query("SHOW TABLES LIKE 'training_completion'");
if ($check && $check->num_rows > 0) {
    $sql = "
      SELECT tm.id AS module_id, tm.title,
             COUNT(tc.user_id) AS total_learners,
             SUM(CASE WHEN tc.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
             SUM(CASE WHEN tc.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress
      FROM training_modules tm
      LEFT JOIN training_completion tc ON tm.id = tc.module_id
      GROUP BY tm.id
      ORDER BY tm.id ASC
    ";
} else {
    $sql = "
      SELECT tm.id AS module_id, tm.title,
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

// =========================
// Module quiz effectiveness
// =========================
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

// =========================
// Overall quiz summary
// =========================
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin — Module Analytics</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.6.0/dist/full.css" rel="stylesheet" />
  <style>
    .page-wrap { padding: 1.5rem; }
    .module-card { transition: transform .12s ease, box-shadow .12s ease; }
    .module-card:hover { transform: translateY(-6px); box-shadow: 0 10px 30px rgba(2,6,23,0.08); }
    .radial-wrap { min-width: 6rem; }
    .radial-progress { font-weight: 700; font-size: 0.95rem; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main -->
  <main class="flex-1 page-wrap">
    <div class="max-w-7xl mx-auto">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-sky-700">📋 Module Completion & Effectiveness</h1>
        <p class="text-sm text-gray-600 mt-1">Analyze training effectiveness. Use data to improve program.</p>
      </header>

      <!-- Summary row -->
      <section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-sm text-gray-500">Total Quiz Attempts</p>
          <p class="text-2xl font-bold text-sky-600"><?= number_format($quiz_summary['total']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-sm text-gray-500">Passed</p>
          <p class="text-2xl font-bold text-green-600"><?= number_format($quiz_summary['passed']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-sm text-gray-500">Failed</p>
          <p class="text-2xl font-bold text-red-600"><?= number_format($quiz_summary['failed']) ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm">
          <p class="text-sm text-gray-500">Avg Quiz Score</p>
          <p class="text-2xl font-bold text-purple-600"><?= round($quiz_summary['avg_score'],2) ?>%</p>
        </div>
      </section>

      <!-- Modules -->
      <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($completion_stats)): ?>
          <div class="col-span-full bg-white p-6 rounded-lg shadow-sm text-center text-gray-600">
            No training modules found. Please add training modules.
          </div>
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
          <article class="bg-white p-6 rounded-2xl module-card shadow-sm">
            <div class="flex items-start gap-4">
              <div class="radial-wrap">
                <div class="radial-progress <?= $colorClass ?>" style="--value:<?= $completionRate ?>; --size:6rem; --thickness:0.6rem;">
                  <?= $completionRate ?>%
                </div>
              </div>

              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($m['title']) ?></h3>
                <p class="text-sm text-gray-500 mt-1">
                  <strong><?= $completed ?></strong> completed &middot; <strong><?= $in_progress ?></strong> in progress &middot; <strong><?= $total ?></strong> learners
                </p>

                <div class="mt-3 grid grid-cols-2 gap-3">
                  <div class="p-3 bg-gray-50 rounded-lg border">
                    <p class="text-xs text-gray-500">Avg Quiz Score</p>
                    <p class="text-lg font-bold text-purple-600"><?= round($avgScore,2) ?>%</p>
                    <p class="text-xs text-gray-400"><?= number_format($attempts) ?> attempts</p>
                  </div>
                  <div class="p-3 bg-gray-50 rounded-lg border">
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

                <div class="mt-4 bg-gray-50 p-3 rounded-lg border-l-4 border-sky-400">
                  <p class="text-sm text-gray-700"><?= htmlspecialchars($insight) ?></p>
                </div>

                <div class="mt-3 flex gap-2">
                  <a href="../admin/module_edit.php?module_id=<?= urlencode($m['module_id']) ?>" class="px-3 py-1 rounded bg-sky-600 text-white text-sm">Edit Module</a>
                  <a href="../admin/module_resources.php?module_id=<?= urlencode($m['module_id']) ?>" class="px-3 py-1 rounded bg-indigo-50 text-indigo-700 text-sm border">Manage Resources</a>
                </div>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
