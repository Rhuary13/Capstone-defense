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
// Security check   
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// ADD Module
// ----------------------
if (isset($_POST['add_module'])) {
    $title        = trim($_POST['title'] ?? '');
    $objectives   = trim($_POST['objectives'] ?? '');
    $disasterType = trim($_POST['disaster_type'] ?? '');
    $uploaded_file = null;

    // File upload
    if (!empty($_FILES['content']['name'])) {
    $allowed_extensions = ['pdf','doc','docx','jpg','jpeg','png'];
    $file_ext = strtolower(pathinfo($_FILES['content']['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        die("❌ Invalid file type. Only PDF, DOC, DOCX, JPG, PNG are allowed.");
    }

    $upload_dir = __DIR__ . "/uploads/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = time() . "_" . basename($_FILES['content']['name']);
    $target_file = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['content']['tmp_name'], $target_file)) {
        $uploaded_file = $filename;
    }
}


    $stmt = $conn->prepare("INSERT INTO training_modules (title, objectives, disaster_type, file_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $objectives, $disasterType, $uploaded_file);
    $stmt->execute();
    $stmt->close();

    header("Location: module1.1.php?success=1");
    exit;
}

// ----------------------
// DELETE Module
// ----------------------
if (isset($_GET['delete'])) {
    $id = (int) ($_GET['delete'] ?? 0);
    if ($id > 0) {
        $conn->query("DELETE FROM training_modules WHERE id=$id");
    }
    header("Location: module1.1.php?deleted=1");
    exit;
}

// ----------------------
// EDIT Module (Fetch Data)
// ----------------------
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int) ($_GET['edit'] ?? 0);
    $result = $conn->query("SELECT * FROM training_modules WHERE id=$id");
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
}

// ----------------------
// UPDATE Module
// ----------------------
if (isset($_POST['update_module'])) {
    $id           = (int) ($_POST['id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $objectives   = trim($_POST['objectives'] ?? '');
    $disasterType = trim($_POST['disaster_type'] ?? '');
    $uploaded_file = $_POST['existing_file'] ?? null;

    // File upload
    if (!empty($_FILES['content']['name'])) {
        $upload_dir = __DIR__ . "/uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $filename = time() . "_" . basename($_FILES['content']['name']);
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['content']['tmp_name'], $target_file)) {
            $uploaded_file = $filename;
        }
    }

    $stmt = $conn->prepare("UPDATE training_modules 
                            SET title=?, objectives=?, disaster_type=?, file_name=? 
                            WHERE id=?");
    $stmt->bind_param("ssssi", $title, $objectives, $disasterType, $uploaded_file, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: module1.1.php?updated=1");
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
  <title>Module Creation & Setup</title>
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
            <i data-lucide="folder-plus" class="w-6 h-6 text-blue-600"></i>
            Training Modules
        </h1>
    </nav>

    <main class="flex-1 px-6 py-8 mt-16 h-[calc(100vh-4rem)] overflow-y-auto flex justify-center">
    <div class="w-full max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Add/Update Module Form -->
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-4">
                    <i data-lucide="<?= $editData ? 'pencil' : 'plus-circle' ?>" class="w-5 h-5 text-blue-600"></i>
                    <?= $editData ? 'Edit Module' : 'Add Module' ?>
                </h3>

                <!-- Alerts -->
                <?php if (isset($_GET['success'])): ?>
                <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">✅ Module added successfully!</div>
                <?php elseif (isset($_GET['updated'])): ?>
                <div class="mb-4 p-3 rounded-lg bg-blue-50 text-blue-700 border border-blue-200">✏️ Module updated successfully!</div>
                <?php elseif (isset($_GET['deleted'])): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200">🗑️ Module deleted successfully!</div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id'] ?? '') ?>">
                    <input type="hidden" name="existing_file" value="<?= htmlspecialchars($editData['file_name'] ?? '') ?>">

                    <div>
                        <label class="block text-sm font-medium">Module Title</label>
                        <input type="text" name="title" required
                               value="<?= htmlspecialchars($editData['title'] ?? '') ?>"
                               class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Objectives</label>
                        <textarea name="objectives" rows="3" required
                                  class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($editData['objectives'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Disaster Type</label>
                        <select name="disaster_type" required
                                class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                          <option value="">-- Select Type --</option>
                          <?php
                          $types = ["All Disaster Type","Earthquake","Flood","Fire","Typhoon","Landslide","Pandemic"];
                          $selectedType = $editData['disaster_type'] ?? '';
                          foreach ($types as $type) {
                              $sel = ($selectedType === $type) ? "selected" : "";
                              echo "<option value='".htmlspecialchars($type)."' $sel>$type</option>";
                          }
                          ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Upload Content (PDF, Docs, Images)</label>
                        <input type="file" name="content"
                               class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <?php if (!empty($editData['file_name'])): ?>
                          <p class="text-xs text-gray-600 mt-1">Current: <?= htmlspecialchars($editData['file_name']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-2 mt-4">
                        <?php if ($editData): ?>
                            <button type="submit" name="update_module"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update</button>
                            <a href="module1.1.php"
                               class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_module"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Existing Modules -->
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h3 class="text-lg font-semibold mb-4">Existing Modules</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
                            <tr>
                                <th class="px-3 py-2">Title</th>
                                <th class="px-3 py-2">Objectives</th>
                                <th class="px-3 py-2">Disaster Type</th>
                                <th class="px-3 py-2">File</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php while ($row = $modules->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['title'] ?? ''); ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['objectives'] ?? ''); ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['disaster_type'] ?? ''); ?></td>
                                    <td class="px-3 py-2">
                                        <?php if (!empty($row['file_name'])): 
                                        $filePath = "uploads/" . htmlspecialchars($row['file_name']);
                                        $ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                                    ?>
                                        <?php if (in_array($ext, ['pdf','jpg','jpeg','png'])): ?>
                                            <a href="<?= $filePath ?>" target="_blank" class="text-blue-600 hover:underline">View</a>
                                        <?php elseif (in_array($ext, ['doc','docx'])): ?>
                                            <a href="https://docs.google.com/viewer?url=<?= urlencode('http://localhost/capstone/admin/module1/' . $filePath) ?>&embedded=true"
                                            target="_blank" class="text-green-600 hover:underline">View in Docs</a>
                                        <?php else: ?>
                                            <a href="<?= $filePath ?>" download class="text-gray-600 hover:underline">Download</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-500">None</span>
                                    <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
                                    <td class="px-3 py-2 flex gap-3">
                                        <a href="?edit=<?= (int)($row['id'] ?? 0); ?>" class="text-blue-600 hover:underline">Edit</a>
                                        <a href="?delete=<?= (int)($row['id'] ?? 0); ?>" onclick="return confirm('Delete this module?')" class="text-red-600 hover:underline">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    </main>
  </div>

<script>lucide.createIcons();</script>
</body>
</html>
