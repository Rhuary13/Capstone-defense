<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "training_management";

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

// =========================
// Fetch all modules uploaded by admin
// =========================
$modules = $conn->query("SELECT * FROM training_modules ORDER BY id DESC");

// =========================
// Fetch Notifications for Participant
// =========================
$notifications = [];
// Assuming a 'user_type' is set in the session, e.g., 'participant' or 'staff'
$user_type = $_SESSION['user_type'] ?? 'participant'; 

$stmt = $conn->prepare("SELECT id, title, message, created_at, is_read FROM notifications WHERE recipient_type = 'all' OR recipient_type = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("s", $user_type);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Participant Lessons & Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden">

    <?php include '../sidebar.php'; ?>

    <main class="flex-1 h-screen overflow-y-auto p-6">
        <div class="max-w-5xl mx-auto space-y-8">

            <h1 class="text-3xl font-bold text-blue-700 mb-6 flex items-center gap-2">
                <i data-lucide="book-open" class="w-8 h-8"></i>
                Training Modules
            </h1>

            <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200">
                <div class="flex items-center gap-2 text-xl font-semibold text-gray-800 mb-4">
                    <i data-lucide="bell-ring" class="text-orange-500"></i> My Notifications
                </div>
                <?php if (!empty($notifications)): ?>
                    <ul class="space-y-4">
                        <?php foreach ($notifications as $notification): ?>
                            <li class="p-3 rounded-lg border border-gray-200 shadow-sm
                                <?= $notification['is_read'] ? 'bg-gray-50' : 'bg-yellow-50'; ?>">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($notification['title']); ?></span>
                                    <span class="text-xs text-gray-500"><?= date("M d, Y", strtotime($notification['created_at'])); ?></span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($notification['message'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500 bg-gray-50 rounded-lg">
                        You have no new notifications.
                    </div>
                <?php endif; ?>
            </div>
            

            <div class="space-y-8">
                <?php if ($modules && $modules->num_rows > 0): ?>
                    <?php while ($lesson = $modules->fetch_assoc()): ?>
                        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200">
                            <h2 class="text-2xl font-semibold text-gray-800 mb-2">
                                <?= htmlspecialchars($lesson['title']); ?>
                            </h2>

                            <?php if (!empty($lesson['objectives'])): ?>
                                <p class="text-gray-600 mb-4">
                                    <?= nl2br(htmlspecialchars($lesson['objectives'])); ?>
                                </p>
                            <?php endif; ?>

                            <p class="text-sm text-gray-500 mb-4">
                                <strong>Disaster Type:</strong> <?= htmlspecialchars($lesson['disaster_type']); ?>
                            </p>

                            <?php if (!empty($lesson['file_name'])): 
                                $filePath = "../../admin/module1/uploads/" . htmlspecialchars($lesson['file_name']);
                                $ext = strtolower(pathinfo($lesson['file_name'], PATHINFO_EXTENSION));
                            ?>
                                <div class="mt-4">
                                    <?php if ($ext === 'pdf'): ?>
                                        <iframe src="<?= $filePath ?>" class="w-full h-[600px] border rounded-lg"></iframe>
                                    <?php elseif (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                        <img src="<?= $filePath ?>" alt="Lesson Image" class="w-full rounded-lg shadow">
                                    <?php elseif (in_array($ext, ['doc','docx'])): ?>
                                        <iframe src="https://docs.google.com/viewer?url=<?= urlencode('http://localhost/capstone/admin/module1/uploads/' . $lesson['file_name']) ?>&embedded=true" 
                                            class="w-full h-[600px] border rounded-lg"></iframe>
                                        <p class="mt-2">
                                            <a href="<?= $filePath ?>" class="text-green-600 hover:underline" download>⬇️ Download DOC</a>
                                        </p>
                                    <?php else: ?>
                                        <a href="<?= $filePath ?>" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700" download>
                                            ⬇️ Download Lesson
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500">No lesson file uploaded.</p>
                            <?php endif; ?>

                            <p class="text-xs text-gray-400 mt-4">Uploaded: <?= htmlspecialchars($lesson['created_at'] ?? 'N/A'); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-red-600 font-semibold">❌ No lessons available yet. Please check with the administrator.</p>
                <?php endif; ?>
            </div>

        </div>
    </main>
<script>
    lucide.createIcons();
</script>
</body>
</html>