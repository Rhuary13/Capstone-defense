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
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// Process File Upload
// =========================
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['quiz_file'])) {
    $lesson_id = $_POST['lesson_id'] ?? 0;

    if (!empty($_FILES['quiz_file']['name'])) {
        $uploadDir = "../uploads/quiz_files/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['quiz_file']['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['quiz_file']['tmp_name'], $filePath)) {
            $stmt = $conn->prepare("INSERT INTO quiz_files (lesson_id, file_name, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $lesson_id, $fileName, $filePath);
            $stmt->execute();
            $stmt->close();
            $message = "✅ File Uploaded Successfully.";
        } else {
            $message = "❌ File Upload Failed.";
        }
    }
}

// =========================
// Fetch All Built-in Questions (limit 50)
// =========================
$all_questions = [];
$result_all = $conn->query("SELECT * FROM quiz_questions ORDER BY question_id ASC LIMIT 50");
if ($result_all && $result_all->num_rows > 0) {
    while ($row = $result_all->fetch_assoc()) {
        $all_questions[] = $row;
    }
}

// =========================
// Fetch Quiz Result Stats
// =========================
$total = 0; $passed = 0; $failed = 0;
$res_stats = $conn->query("SELECT status, COUNT(*) as cnt FROM quiz_results GROUP BY status");
while ($row = $res_stats->fetch_assoc()) {
    $total += $row['cnt'];
    if ($row['status'] == 'Passed') $passed = $row['cnt'];
    if ($row['status'] == 'Failed') $failed = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Quizzes</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    /* Independent scrolling for main content */
    body {
    height: 100vh;
    display: flex;
      overflow: hidden; /* Prevent body scroll */
    }
    main {
    height: 100vh;
      overflow-y: auto; /* Enable scroll only in main */
    }
</style>
</head>
<body class="bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>
        
<!-- Main Content -->
<main class="flex-1 p-6 bg-gray-50">

    <!-- Quiz Results Analytics -->
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-2xl shadow-lg mb-8">
    <h2 class="text-2xl font-bold text-blue-700 mb-4">📊 Quiz Results Analytics</h2>

    <p class="text-lg text-gray-700">
        ✅ <span class="font-bold"><?= $passed ?>/<?= $total ?></span> Passed
    </p>
    <p class="text-lg text-gray-700">
        ❌ <span class="font-bold"><?= $failed ?>/<?= $total ?></span> Failed
    </p>

    <div class="mt-6 overflow-x-auto">
        <table class="w-full border-collapse border border-gray-200 text-sm">
        <thead>
            <tr class="bg-gray-100">
            <th class="border px-4 py-2">Participant</th>
            <th class="border px-4 py-2">Lesson ID</th>
            <th class="border px-4 py-2">Score</th>
            <th class="border px-4 py-2">Status</th>
            <th class="border px-4 py-2">Date Taken</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $res = $conn->query("
            SELECT qr.*, u.full_name as participant_name 
            FROM quiz_results qr
            LEFT JOIN users u ON qr.participant_id = u.id
            ORDER BY qr.taken_at DESC
            ");
            while ($row = $res->fetch_assoc()):
            ?>
            <tr>
            <td class="border px-4 py-2"><?= htmlspecialchars($row['participant_name'] ?? 'Unknown') ?></td>
            <td class="border px-4 py-2"><?= $row['lesson_id'] ?></td>
            <td class="border px-4 py-2"><?= $row['score'] ?>/<?= $row['total_questions'] ?></td>
            <td class="border px-4 py-2 <?= $row['status']=='Passed'?'text-green-600 font-bold':'text-red-600 font-bold' ?>">
                <?= $row['status'] ?>
            </td>
            <td class="border px-4 py-2"><?= $row['taken_at'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        </table>
    </div>
    </div>

    <!-- Built-in Questions -->
    <div class="max-w-5xl mx-auto bg-white p-6 rounded-2xl shadow-lg mb-8">
    <h2 class="text-2xl font-bold text-gray-700 mb-4">📘 Built-in Quiz Questions (50 Items)</h2>
    <?php if (count($all_questions) > 0): ?>
        <ol class="space-y-4 list-decimal pl-6">
        <?php foreach ($all_questions as $q): ?>
            <li class="p-4 border rounded-lg bg-gray-50">
            <p class="font-semibold"><?= htmlspecialchars($q['question']) ?></p>
            <ul class="ml-4 mt-2 text-sm text-gray-600">
                <li>A. <?= htmlspecialchars($q['option_a']) ?></li>
                <li>B. <?= htmlspecialchars($q['option_b']) ?></li>
                <li>C. <?= htmlspecialchars($q['option_c']) ?></li>
                <li>D. <?= htmlspecialchars($q['option_d']) ?></li>
            </ul>
            <p class="mt-2 text-green-700 font-semibold">✔ Correct: <?= $q['correct_option'] ?></p>
            </li>
        <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p class="text-gray-500">No built-in questions found.</p>
    <?php endif; ?>
    </div>

    <!-- Upload Quiz File -->
    <div class="max-w-4xl mx-auto mb-10 bg-white p-6 rounded-2xl shadow-lg">
    <h1 class="text-3xl font-bold text-blue-700 mb-6">📤 Upload Quiz File</h1>

    <?php if (!empty($message)): ?>
        <div class="p-4 mb-6 bg-green-50 text-green-700 border border-green-200 rounded-lg">
        <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="lesson_id" value="1"> <!-- or dynamic lesson ID -->

        <div>
        <label class="block font-semibold mb-2">Select Quiz File</label>
        <input type="file" name="quiz_file" class="w-full border px-4 py-2 rounded-lg">
        <p class="text-sm text-gray-500 mt-2">Allowed: PDF, DOC, DOCX</p>
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition">
        ✅ Upload File
        </button>
    </form>
    </div>

</main>
</body>
</html>
