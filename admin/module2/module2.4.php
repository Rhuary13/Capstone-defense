<?php
session_start();

/**
 * module2.4.php
 * Admin interface: Notification System
 *
 * This file handles the creation of system-wide notifications by the admin
 * and displays them to the relevant stakeholders (staff and participants).
 *
 * Key features:
 * - Admin sends alerts, reminders, and updates.
 * - Staff & Participants receive notifications based on their user type.
 */

// =========================
// Database Connection & Setup
// =========================
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "training_management"; // Using the database from your previous code

// Connect to MySQL
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}

// =========================
// Security Check
// =========================
// Note: This assumes you have 'user_id', 'role', and 'user_type' set in your session after login.
// 'role' should be 'admin' for the admin module.
// 'user_type' can be 'staff' or 'participant'
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// Database Table Creation for Notifications
// =========================
// This table will store all notifications sent by the admin.
$sql_create_table = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('all', 'staff', 'participant') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";
if (!$conn->query($sql_create_table)) {
    die("Error creating notifications table: " . $conn->error);
}

// =========================
// Handle Notification Creation by Admin
// =========================
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $recipient_type = $_POST['recipient_type']; // 'all', 'staff', or 'participant'
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (!empty($title) && !empty($message) && !empty($recipient_type)) {
        $stmt = $conn->prepare("INSERT INTO notifications (recipient_type, title, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $recipient_type, $title, $message);
        if ($stmt->execute()) {
            $success = "✅ Notification sent successfully to all " . ucwords($recipient_type) . ".";
        } else {
            $error = "❌ Failed to send notification. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "❌ Please fill in all fields.";
    }
}

// =========================
// Fetch Notifications for Logged-In Admin
// =========================
// This section fetches the notifications to be displayed on the admin's own dashboard.
$admin_notifications = [];
$stmt = $conn->prepare("SELECT id, recipient_type, title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admin_notifications[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Notification Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .sidebar { min-height: 100vh; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <?php include '../sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-screen">

        <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="bell-ring" class="w-6 h-6 text-indigo-600"></i>
                Notification Center
            </h1>
        </nav>

        <main class="flex-1 px-6 py-8 mt-16 h-[calc(100vh-4rem)] overflow-y-auto flex justify-center">
            <div class="w-full max-w-7xl">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-4">
                            <i data-lucide="mail-plus" class="w-5 h-5 text-indigo-600"></i>
                            Send New Notification
                        </h3>

                        <?php if ($success): ?>
                            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="recipient_type" class="block text-sm font-medium text-gray-700">Send To:</label>
                                <select id="recipient_type" name="recipient_type" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="all">All Stakeholders</option>
                                    <option value="staff">Staff Only</option>
                                    <option value="participant">Participants Only</option>
                                </select>
                            </div>

                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700">Title:</label>
                                <input type="text" id="title" name="title" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="message" class="block text-sm font-medium text-gray-700">Message:</label>
                                <textarea id="message" name="message" rows="4" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                            </div>

                            <div class="flex items-center justify-end">
                                <button type="submit" name="send_notification"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i data-lucide="send" class="w-4 h-4 mr-2"></i>
                                    Send Notification
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-4">
                            <i data-lucide="history" class="w-5 h-5 text-gray-600"></i>
                            Recent Notifications Sent
                        </h3>
                        <ul class="space-y-3">
                            <?php if (!empty($admin_notifications)): ?>
                                <?php foreach ($admin_notifications as $n): ?>
                                    <li class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-indigo-700"><?= htmlspecialchars($n['title']); ?></span>
                                            <span class="text-xs text-gray-500"><?= date("M d, Y g:i A", strtotime($n['created_at'])); ?></span>
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($n['message'])); ?></p>
                                        <span class="inline-block mt-2 px-2 py-1 text-xs font-semibold rounded-full
                                            <?php
                                            switch ($n['recipient_type']) {
                                                case 'all': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'staff': echo 'bg-green-100 text-green-800'; break;
                                                case 'participant': echo 'bg-yellow-100 text-yellow-800'; break;
                                            }
                                            ?>">
                                            To: <?= ucwords($n['recipient_type']); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No notifications have been sent yet.</p>
                            <?php endif; ?>
                        </ul>
                    </div>

                </div>
            </div>
        </main>
    </div>

<script>
    lucide.createIcons();
</script>
</body>
</html>