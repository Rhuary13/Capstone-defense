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
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// Security check (Staff role only)
// =========================
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// Fetch All Modules
// ----------------------
$modules = $conn->query("SELECT * FROM training_modules ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff - Lesson Modules</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Area -->
  <div class="flex-1 flex flex-col h-screen">

    <!-- Top Navigation -->
    <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="book" class="w-6 h-6 text-blue-600"></i>
            Lesson Modules
        </h1>
    </nav>

    <main class="flex-1 px-6 py-8 mt-16 h-[calc(100vh-4rem)] overflow-y-auto flex justify-center">
      <div class="w-full max-w-7xl">
        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
            <h3 class="text-lg font-semibold mb-4">Available Training Modules</h3>

            <?php if (!$modules || $modules->num_rows === 0): ?>
              <p class="text-gray-600">No modules available yet. Please check back later.</p>
            <?php else: ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Objectives</th>
                            <th class="px-3 py-2">Disaster Type</th>
                            <th class="px-3 py-2">Lesson File</th>
                            <th class="px-3 py-2">Date Added</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($row = $modules->fetch_assoc()): ?>
                            <?php
                                $filename = $row['file_name'] ?? '';
                                $basename = $filename ? basename($filename) : '';
                                $ext = $basename ? strtolower(pathinfo($basename, PATHINFO_EXTENSION)) : '';
                                // Path from staff → admin/uploads
                                $filePath = "../../admin/module1/uploads/" . rawurlencode($basename);
                            ?>
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900"><?= htmlspecialchars($row['title'] ?? '') ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($row['objectives'] ?? '') ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($row['disaster_type'] ?? '') ?></td>
                                <td class="border px-4 py-2 text-center">
                                    <?php if ($basename): ?>
                                        <?php if (in_array($ext, ['pdf','jpg','jpeg','png'])): ?>
                                            <a href="<?= $filePath ?>" 
                                               class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-xs"
                                               target="_blank">📖 View Lesson</a>
                                        <?php elseif (in_array($ext, ['doc','docx'])): ?>
                                            <a href="<?= $filePath ?>" 
                                               class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs"
                                               download>⬇️ Download DOC</a>
                                        <?php else: ?>
                                            <a href="<?= $filePath ?>" 
                                               class="px-3 py-1 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-xs"
                                               download>⬇️ Download</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-500">No file</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2"><?= htmlspecialchars($row['created_at'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

<script>lucide.createIcons();</script>
</body>
</html>
