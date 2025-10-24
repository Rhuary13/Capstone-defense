<?php
session_start();

$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "training_management";

// ✅ Create connection
$conn = new mysqli($host, $user, $pass, $db);

// ✅ Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Security: Admin-only access
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ✅ Handle forms with prepared statements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_staff'])) {
        $stmt = $conn->prepare("INSERT INTO staff (name, role) VALUES (?, ?)");
        $stmt->bind_param("ss", $_POST['name'], $_POST['role']);
        $stmt->execute();
    }

    if (isset($_POST['add_program'])) {
        $stmt = $conn->prepare("INSERT INTO programs (title, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $_POST['title'], $_POST['description']);
        $stmt->execute();
    }

    if (isset($_POST['add_drill'])) {
        $stmt = $conn->prepare("INSERT INTO drills (title, date, details) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['title'], $_POST['date'], $_POST['details']);
        $stmt->execute();
    }

    if (isset($_POST['upload_resource']) && isset($_FILES['resource_file'])) {
        $file = $_FILES['resource_file'];
        $targetDir = __DIR__ . "/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $target = $targetDir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $stmt = $conn->prepare("INSERT INTO resources (file_name, file_path) VALUES (?, ?)");
            $stmt->bind_param("ss", $file['name'], $target);
            $stmt->execute();
        }
    }
}

// ✅ Fetch analytics data from training_management
$trainingLabels = [];
$trainingData   = [];

$sql = "
  SELECT tm.title, 
         ROUND(AVG(CASE WHEN qr.status='Passed' THEN 100 ELSE 0 END),0) as completion_rate
  FROM training_management.training_modules tm
  LEFT JOIN training_management.quiz_results qr ON tm.id = qr.lesson_id
  GROUP BY tm.id
";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $trainingLabels[] = $row['title'];
        $trainingData[]   = (int)$row['completion_rate'];
    }
}

// ✅ Fetch quiz results for modal (⚡ fixed alias for taken_at)
$quizResults = $conn->query("
  SELECT u.username, q.title AS quiz_title, qr.score, qr.taken_at AS date_taken,
         CASE WHEN qr.score >= 50 THEN 'Pass' ELSE 'Fail' END AS result
  FROM training_management.quiz_results qr
  LEFT JOIN users u ON qr.participant_id = u.id
  JOIN training_management.training_modules q ON qr.lesson_id = q.id
  ORDER BY qr.taken_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Disaster Preparedness Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    function openModal(id){document.getElementById(id).classList.remove("hidden");}
    function closeModal(id){document.getElementById(id).classList.add("hidden");}
  </script>
</head>
<body class="h-screen flex bg-gray-100 overflow-hidden">

  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Main -->
  <main class="flex-1 flex flex-col h-screen">
    <!-- Header -->
    <header class="bg-white shadow px-8 py-4 flex justify-between items-center">
      <h1 class="text-2xl font-bold">Admin Dashboard</h1>
      <span class="text-gray-700">Hello, <?= htmlspecialchars($_SESSION['username']); ?></span>
    </header>

    <!-- Content -->
    <section class="p-6 flex-1 overflow-y-auto">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Staff -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center gap-3">
            <i data-lucide="users" class="w-7 h-7 text-blue-600"></i>
            <h2 class="text-xl font-bold">Manage Staff</h2>
          </div>
          <p class="text-gray-500 mt-2">Add, edit, or remove staff accounts.</p>
          <button onclick="openModal('staffModal')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add New</button>
        </div>

        <!-- Programs -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center gap-3">
            <i data-lucide="book-open" class="w-7 h-7 text-blue-600"></i>
            <h2 class="text-xl font-bold">Training Programs</h2>
          </div>
          <p class="text-gray-500 mt-2">Organize and track preparedness training.</p>
          <button onclick="openModal('programModal')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add Program</button>
        </div>

        <!-- Drills -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center gap-3">
            <i data-lucide="activity" class="w-7 h-7 text-blue-600"></i>
            <h2 class="text-xl font-bold">Educational Drills</h2>
          </div>
          <p class="text-gray-500 mt-2">Plan and evaluate simulation drills.</p>
          <button onclick="openModal('drillModal')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Schedule</button>
        </div>

        <!-- Reports -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center gap-3">
            <i data-lucide="bar-chart-3" class="w-7 h-7 text-blue-600"></i>
            <h2 class="text-xl font-bold">Reports</h2>
          </div>
          <p class="text-gray-500 mt-2">Generate and export performance reports.</p>
          <a href="export_report.php" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">View</a>
        </div>

        <!-- Resources -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center gap-3">
            <i data-lucide="folder-open" class="w-7 h-7 text-blue-600"></i>
            <h2 class="text-xl font-bold">Resource Library</h2>
          </div>
          <p class="text-gray-500 mt-2">Upload and manage learning materials.</p>
          <button onclick="openModal('resourceModal')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Upload</button>
        </div>

        <!-- ✅ Quiz Results -->
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center gap-3">
            <i data-lucide="check-circle" class="w-7 h-7 text-blue-600"></i>
            <h2 class="text-xl font-bold">Quiz Results</h2>
          </div>
          <p class="text-gray-500 mt-2">View participants’ pass/fail status.</p>
          <button onclick="openModal('quizResultsModal')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">View Results</button>
        </div>
      </div>

      <!-- Training Progress Chart -->
      <div class="mt-8 bg-white p-6 rounded-xl shadow w-full md:w-1/2">
        <h2 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
          <i data-lucide="line-chart" class="w-5 h-5 text-blue-500"></i>
          Training Program Completion
        </h2>
        <div class="h-56">
          <canvas id="trainingChart"></canvas>
        </div>
      </div>

<script>
  const ctx = document.getElementById("trainingChart").getContext("2d");
  const gradient = ctx.createLinearGradient(0, 0, 0, 200);
  gradient.addColorStop(0, "rgba(59,130,246,0.7)");
  gradient.addColorStop(1, "rgba(59,130,246,0.05)");

  new Chart(ctx, {
    type: "line",
    data: {
      labels: <?= json_encode($trainingLabels) ?>,
      datasets: [{
        label: "Completion (%)",
        data: <?= json_encode($trainingData) ?>,
        backgroundColor: gradient,
        borderColor: "#3b82f6",
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: "#fff",
        pointBorderColor: "#3b82f6",
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: val => val + "%" },
          grid: { color: "#f3f4f6" }
        },
        x: { grid: { display: false } }
      }
    }
  });

  lucide.createIcons();
</script>
    </section>
  </main>

  <!-- =================== Enhanced Uniform Modals =================== -->
<style>
  .modal-box {
    background: #fff;
    border-radius: 0.75rem;
    padding: 1.5rem;
    width: 100%;
    max-width: 700px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.25);
    animation: fadeIn 0.2s ease-in-out;
  }
  .modal-btn {
    @apply px-4 py-2 rounded text-white font-medium transition;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }
</style>

<!-- Staff Modal -->
<div id="staffModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
  <div class="modal-box">
    <h2 class="font-bold text-xl mb-4 text-blue-700">👥 Add Staff</h2>
    <form method="POST">
      <input type="text" name="name" placeholder="Name" class="w-full p-2 mb-2 border rounded">
      <input type="text" name="role" placeholder="Role" class="w-full p-2 mb-2 border rounded">
      <div class="flex justify-end gap-2 mt-3 border-t pt-3">
        <button type="button" onclick="closeModal('staffModal')" class="modal-btn bg-gray-500 hover:bg-gray-600">Cancel</button>
        <button type="submit" name="add_staff" class="modal-btn bg-blue-600 hover:bg-blue-700">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Program Modal -->
<div id="programModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
  <div class="modal-box">
    <h2 class="font-bold text-xl mb-4 text-green-700">📘 Add Training Program</h2>
    <form method="POST">
      <input type="text" name="title" placeholder="Program Title" class="w-full p-2 mb-2 border rounded">
      <textarea name="description" placeholder="Description" class="w-full p-2 mb-2 border rounded"></textarea>
      <div class="flex justify-end gap-2 mt-3 border-t pt-3">
        <button type="button" onclick="closeModal('programModal')" class="modal-btn bg-gray-500 hover:bg-gray-600">Cancel</button>
        <button type="submit" name="add_program" class="modal-btn bg-green-600 hover:bg-green-700">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Drill Modal -->
<div id="drillModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
  <div class="modal-box">
    <h2 class="font-bold text-xl mb-4 text-yellow-700">📅 Schedule Drill</h2>
    <form method="POST">
      <input type="text" name="title" placeholder="Drill Title" class="w-full p-2 mb-2 border rounded">
      <input type="date" name="date" class="w-full p-2 mb-2 border rounded">
      <textarea name="details" placeholder="Details" class="w-full p-2 mb-2 border rounded"></textarea>
      <div class="flex justify-end gap-2 mt-3 border-t pt-3">
        <button type="button" onclick="closeModal('drillModal')" class="modal-btn bg-gray-500 hover:bg-gray-600">Cancel</button>
        <button type="submit" name="add_drill" class="modal-btn bg-yellow-600 hover:bg-yellow-700">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Resource Modal -->
<div id="resourceModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
  <div class="modal-box">
    <h2 class="font-bold text-xl mb-4 text-teal-700">📂 Upload Resource</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="resource_file" class="w-full p-2 mb-2 border rounded">
      <div class="flex justify-end gap-2 mt-3 border-t pt-3">
        <button type="button" onclick="closeModal('resourceModal')" class="modal-btn bg-gray-500 hover:bg-gray-600">Cancel</button>
        <button type="submit" name="upload_resource" class="modal-btn bg-teal-600 hover:bg-teal-700">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- Quiz Results Modal -->
<div id="quizResultsModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
  <div class="modal-box max-w-4xl w-full max-h-[80vh] overflow-y-auto">
    <h2 class="font-bold text-xl mb-4 text-purple-700">📊 Quiz Participants Results</h2>
    <table class="w-full border-collapse text-sm">
      <thead>
        <tr class="bg-gray-200 text-left">
          <th class="p-2 border">Username</th>
          <th class="p-2 border">Quiz</th>
          <th class="p-2 border">Score</th>
          <th class="p-2 border">Result</th>
          <th class="p-2 border">Date Taken</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($quizResults && $quizResults->num_rows > 0): ?>
          <?php while($row = $quizResults->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50">
            <td class="p-2 border"><?= htmlspecialchars($row['username']) ?></td>
            <td class="p-2 border"><?= htmlspecialchars($row['quiz_title']) ?></td>
            <td class="p-2 border"><?= htmlspecialchars($row['score']) ?>%</td>
            <td class="p-2 border font-bold <?= $row['result'] === 'Pass' ? 'text-green-600' : 'text-red-600' ?>">
              <?= htmlspecialchars($row['result']) ?>
            </td>
            <td class="p-2 border"><?= htmlspecialchars($row['date_taken']) ?></td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="p-4 text-center text-gray-500">No quiz results yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div class="flex justify-end mt-4 pt-3 border-t">
      <button onclick="closeModal('quizResultsModal')" class="modal-btn bg-gray-500 hover:bg-gray-600">Close</button>
    </div>
  </div>
</div>

</body>
</html>
