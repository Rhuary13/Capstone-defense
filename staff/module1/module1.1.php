<?php
session_start();
// Database connection
$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "training_management"; // <-- use your actual DB name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Security check
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = $conn->real_escape_string($_POST['title'] ?? '');
    $objectives   = $conn->real_escape_string($_POST['objectives'] ?? '');
    $disasterType = $conn->real_escape_string($_POST['disaster_type'] ?? '');

    // Handle file upload
    $upload_dir = __DIR__ . "/uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $uploaded_file = null;
    if (!empty($_FILES['content']['name'])) {
        $filename = time() . "_" . basename($_FILES['content']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['content']['tmp_name'], $target_file)) {
            $uploaded_file = $filename;
        }
    }

    // Save to database (staff entries are marked "pending" for admin review)
    $sql = "INSERT INTO training_modules (title, objectives, disaster_type, file_name, status, created_by) 
            VALUES ('$title', '$objectives', '$disasterType', '$uploaded_file', 'pending', 'staff')";
    if ($conn->query($sql)) {
        $success = true;
    } else {
        $error = "Database Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff | Module Creation & Setup</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
        <div class=" bg-white p-6 rounded-2xl shadow-md">
            <h1 class="text-2xl font-bold text-blue-700 mb-4">Staff | Module Creation & Setup</h1>
            <p class="text-gray-600 mb-6">Assist in creating training modules by uploading content. Submissions will be reviewed by the Admin.</p>

            <!-- Success / Error Messages -->
            <?php if (!empty($success)): ?>
              <div class="p-4 mb-4 bg-green-100 text-green-800 rounded-lg">
                ✅ Module submitted successfully! Waiting for admin review.
              </div>
            <?php elseif (!empty($error)): ?>
              <div class="p-4 mb-4 bg-red-100 text-red-800 rounded-lg">
                ❌ <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
              <!-- Title -->
              <div>
                <label class="block text-sm font-medium mb-1">Module Title</label>
                <input type="text" name="title" required
                      class="w-full px-3 py-2 border rounded-lg focus:ring focus:ring-blue-300">
              </div>

              <!-- Objectives -->
              <div>
                <label class="block text-sm font-medium mb-1">Objectives</label>
                <textarea name="objectives" rows="4" required
                          class="w-full px-3 py-2 border rounded-lg focus:ring focus:ring-blue-300"></textarea>
              </div>

              <!-- Disaster Type -->
              <div>
                <label class="block text-sm font-medium mb-1">Disaster Type</label>
                <select name="disaster_type" required
                        class="w-full px-3 py-2 border rounded-lg focus:ring focus:ring-blue-300">
                  <option value="">-- Select Type --</option>
                  <option value="Earthquake">Earthquake</option>
                  <option value="Flood">Flood</option>
                  <option value="Fire">Fire</option>
                  <option value="Typhoon">Typhoon</option>
                  <option value="Landslide">Landslide</option>
                  <option value="Pandemic">Pandemic</option>
                </select>
              </div>

              <!-- Upload Content -->
              <div>
                <label class="block text-sm font-medium mb-1">Upload Content (PDF, Docs, Images)</label>
                <input type="file" name="content"
                      class="w-full px-3 py-2 border rounded-lg">
              </div>

              <!-- Submit -->
              <div class="pt-4">
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                  Submit to Admin
                </button>
              </div>
            </form>
        </div>
    </main>
</body>
</html>
