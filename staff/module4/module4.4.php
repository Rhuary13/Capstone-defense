<?php
session_start();

/**
 * debriefing_materials.php
 * Staff role: Leads debriefing sessions and distributes materials
 * Tech Stack: PHP + MySQL + TailwindCSS + JavaScript
 * DB: simulation_event_planning
 */

// --- Configuration ---
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

// --- Connect to DB ---
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Create table if not exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS debriefing_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$message = "";

// --- Handle File Upload ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload'])) {
    $title = trim($_POST['title']);
    $uploadDir = __DIR__ . "/uploads/debriefing/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES["file"]) && $_FILES["file"]["error"] == 0) {
        $filename = time() . "_" . basename($_FILES["file"]["name"]);
        $targetFile = $uploadDir . $filename;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Allow common doc formats
        $allowed = ["pdf", "doc", "docx", "ppt", "pptx", "txt"];
        if (in_array($fileType, $allowed)) {
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
                $stmt = $conn->prepare("INSERT INTO debriefing_materials (title, file_path) VALUES (?, ?)");
                $dbPath = "uploads/debriefing/" . $filename;
                $stmt->bind_param("ss", $title, $dbPath);
                if ($stmt->execute()) {
                    $message = "✅ Debriefing material uploaded!";
                } else {
                    $message = "❌ Error saving material to database.";
                }
                $stmt->close();
            } else {
                $message = "❌ Failed to upload file.";
            }
        } else {
            $message = "❌ Invalid file type. Allowed: PDF, DOC, DOCX, PPT, PPTX, TXT.";
        }
    } else {
        $message = "❌ No file selected or error in upload.";
    }
}

// --- Fetch Materials ---
$materials = $conn->query("SELECT * FROM debriefing_materials ORDER BY uploaded_at DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debriefing Materials - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">📚 Debriefing Materials</h1>
            <p class="text-gray-600 mb-6">Upload and distribute debriefing materials to participants.</p>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Upload Button -->
            <div class="mb-6">
                <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                    ➕ Upload Material
                </button>
            </div>

            <!-- Materials List -->
            <div class="space-y-4">
                <?php if ($materials->num_rows > 0): ?>
                    <?php while ($row = $materials->fetch_assoc()): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow-sm flex justify-between items-center">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($row['title']) ?></h2>
                                <p class="text-sm text-gray-500">Uploaded: <?= $row['uploaded_at'] ?></p>
                            </div>
                            <div class="flex gap-2">
                                <a href="../<?= $row['file_path'] ?>" target="_blank" 
                                   class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg shadow">
                                    View
                                </a>
                                <button onclick="copyLink('<?= $row['file_path'] ?>')" 
                                        class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded-lg shadow">
                                    Share Link
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No debriefing materials uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-lg">
            <h2 class="text-xl font-bold mb-4">➕ Upload Debriefing Material</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-medium">Title</label>
                    <input type="text" name="title" required
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium">File</label>
                    <input type="file" name="file" required
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-gray-300">Cancel</button>
                    <button type="submit" name="upload"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ESC to close modal
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                document.getElementById("uploadModal").classList.add("hidden");
            }
        });

        // Copy link to clipboard
        function copyLink(path) {
            const fullUrl = window.location.origin + "/" + path;
            navigator.clipboard.writeText(fullUrl).then(() => {
                alert("📋 Link copied: " + fullUrl);
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
