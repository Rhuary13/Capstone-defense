<?php
session_start();

/**
 * module3.2.php
 * Attendance Tracking Portal
 *
 * - Admin: Monitors all attendance logs (staff & participants).
 * - Staff: Records attendance for participants and views their own logs.
 * - Participant: Checks in/out and views their own logs.
 */

// =========================
// Database Connection & Setup
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
// Create Attendance Table if it doesn't exist
// =========================
$sql_create_table = "
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT(11) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `user_type` ENUM('staff','participant') NOT NULL,
  `check_in` DATETIME DEFAULT NULL,
  `check_out` DATETIME DEFAULT NULL,
  `date` DATE NOT NULL,
  UNIQUE KEY `user_date` (`id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_table)) {
    die("Error creating attendance table: " . $conn->error);
}

// =========================
// Security Check
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'] ?? 'participant';
$user_full_name = $_SESSION['full_name'] ?? 'Guest';
$user_type = $_SESSION['user_type'] ?? 'participant';

$success_message = '';
$error_message = '';

// =========================
// Handle Participant Check In/Out (POST request)
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_type === 'participant') {
    $today = date("Y-m-d");

    if (isset($_POST['check_in'])) {
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE id = ? AND date = ?");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "❌ You have already checked in today.";
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (id, full_name, user_type, check_in, date) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("isss", $user_id, $user_full_name, $user_type, $today);
            if ($stmt->execute()) {
                $success_message = "✅ You have successfully checked in!";
            } else {
                $error_message = "❌ Check-in failed. " . $stmt->error;
            }
        }
        $stmt->close();
    } elseif (isset($_POST['check_out'])) {
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE id = ? AND date = ? AND check_out IS NULL");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE attendance SET check_out = NOW() WHERE id = ? AND date = ?");
            $stmt->bind_param("is", $id, $today);
            if ($stmt->execute()) {
                $success_message = "✅ You have successfully checked out!";
            } else {
                $error_message = "❌ Check-out failed. " . $stmt->error;
            }
        } else {
            $error_message = "❌ You must check in before checking out.";
        }
        $stmt->close();
    }
}

// =========================
// Handle Staff Recording Participant Attendance
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'staff' && isset($_POST['record_participant_attendance'])) {
    $participant_id = trim($_POST['participant_id']);
    $action = $_POST['action'];
    $today = date("Y-m-d");

    if (empty($participant_id)) {
        $error_message = "❌ Please enter a Participant ID.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE id = ? AND user_type = 'participant'");
        $stmt->bind_param("i", $participant_id);
        $stmt->execute();
        $participant_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$participant_data) {
            $error_message = "❌ Participant not found.";
        } else {
            if ($action === 'check_in') {
                $stmt = $conn->prepare("INSERT INTO attendance (id, full_name, user_type, check_in, date) VALUES (?, ?, 'participant', NOW(), ?)");
                $stmt->bind_param("iss", $participant_id, $participant_data['full_name'], $today);
                if ($stmt->execute()) {
                    $success_message = "✅ Participant '" . htmlspecialchars($participant_data['full_name']) . "' checked in successfully.";
                } else {
                    $error_message = "❌ Check-in failed: " . $stmt->error;
                }
            } elseif ($action === 'check_out') {
                $stmt = $conn->prepare("UPDATE attendance SET check_out = NOW() WHERE id = ? AND date = ? AND check_out IS NULL");
                $stmt->bind_param("is", $participant_id, $today);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success_message = "✅ Participant '" . htmlspecialchars($participant_data['full_name']) . "' checked out successfully.";
                } else {
                    $error_message = "❌ Check-out failed. Participant might not have checked in yet.";
                }
            }
            $stmt->close();
        }
    }
}

// =========================
// Fetch Attendance Logs for Display
// =========================
$attendance_logs = [];
if ($user_role === 'admin') {
    $result = $conn->query("SELECT * FROM attendance ORDER BY date DESC, check_in DESC");
    if ($result) {
        $attendance_logs = $result->fetch_all(MYSQLI_ASSOC);
    }
} elseif ($user_type === 'staff' || $user_type === 'participant') {
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE id = ? ORDER BY date DESC, check_in DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Tracking</title>
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
                <i data-lucide="calendar-check" class="w-6 h-6 text-indigo-600"></i>
                Attendance Tracking
            </h1>
        </nav>

        <main class="flex-1 px-6 py-8 mt-16 h-[calc(100vh-4rem)] overflow-y-auto flex justify-center">
            <div class="w-full max-w-5xl">
                <?php if ($success_message): ?>
                    <div class="mb-4 p-4 rounded-lg bg-green-50 text-green-700 border border-green-200">
                        <?= $success_message; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="mb-4 p-4 rounded-lg bg-red-50 text-red-700 border border-red-200">
                        <?= $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i data-lucide="database" class="w-6 h-6 text-indigo-600"></i> Full Attendance Log
                        </h2>
                        <?php if (!empty($attendance_logs)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border-collapse">
                                    <thead>
                                        <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
                                            <th class="py-3 px-4 border-b">Name</th>
                                            <th class="py-3 px-4 border-b">Type</th>
                                            <th class="py-3 px-4 border-b">Date</th>
                                            <th class="py-3 px-4 border-b">Check-in</th>
                                            <th class="py-3 px-4 border-b">Check-out</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_logs as $log): ?>
                                            <tr class="hover:bg-gray-50 text-gray-700 text-sm">
                                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($log['full_name']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= ucwords($log['user_type']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= date('M d, Y', strtotime($log['date'])); ?></td>
                                                <td class="py-3 px-4 border-b"><?= $log['check_in'] ? date('h:i A', strtotime($log['check_in'])) : 'N/A'; ?></td>
                                                <td class="py-3 px-4 border-b"><?= $log['check_out'] ? date('h:i A', strtotime($log['check_out'])) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No attendance records found.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($user_role === 'staff'): ?>
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <i data-lucide="clipboard-pen" class="w-6 h-6 text-green-600"></i> Record Participant Attendance
                            </h2>
                            <p class="text-gray-600 mb-4">Enter the Participant's ID to record their check-in or check-out.</p>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label for="participant_id" class="block text-sm font-medium text-gray-700">Participant ID</label>
                                    <input type="text" id="participant_id" name="participant_id" required
                                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                                </div>
                                <div class="flex gap-4">
                                    <button type="submit" name="record_participant_attendance" value="check_in"
                                        class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i data-lucide="log-in" class="w-5 h-5 mr-2"></i> Check In
                                    </button>
                                    <button type="submit" name="record_participant_attendance" value="check_out"
                                        class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i data-lucide="log-out" class="w-5 h-5 mr-2"></i> Check Out
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <i data-lucide="user-check" class="w-6 h-6 text-gray-600"></i> My Attendance Log
                            </h2>
                            <?php if (!empty($attendance_logs)): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white border-collapse">
                                        <thead>
                                            <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
                                                <th class="py-3 px-4 border-b">Date</th>
                                                <th class="py-3 px-4 border-b">Check-in</th>
                                                <th class="py-3 px-4 border-b">Check-out</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_logs as $log): ?>
                                                <tr class="hover:bg-gray-50 text-gray-700 text-sm">
                                                    <td class="py-3 px-4 border-b"><?= date('M d, Y', strtotime($log['date'])); ?></td>
                                                    <td class="py-3 px-4 border-b"><?= $log['check_in'] ? date('h:i A', strtotime($log['check_in'])) : 'N/A'; ?></td>
                                                    <td class="py-3 px-4 border-b"><?= $log['check_out'] ? date('h:i A', strtotime($log['check_out'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">You have no attendance records yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 text-center">
                            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center justify-center gap-2">
                                <i data-lucide="user" class="w-6 h-6 text-indigo-600"></i> My Attendance
                            </h2>
                            <p class="text-gray-600 mb-4">Click the button to check in for today's training session.</p>
                            <form method="POST" class="flex flex-col sm:flex-row gap-4 justify-center">
                                <button type="submit" name="check_in"
                                    class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i data-lucide="log-in" class="w-5 h-5 mr-2"></i> Check In
                                </button>
                                <button type="submit" name="check_out"
                                    class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i data-lucide="log-out" class="w-5 h-5 mr-2"></i> Check Out
                                </button>
                            </form>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                            <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                <i data-lucide="history" class="w-6 h-6 text-gray-600"></i> My Attendance Log
                            </h2>
                            <?php if (!empty($attendance_logs)): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white border-collapse">
                                        <thead>
                                            <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
                                                <th class="py-3 px-4 border-b">Date</th>
                                                <th class="py-3 px-4 border-b">Check-in</th>
                                                <th class="py-3 px-4 border-b">Check-out</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_logs as $log): ?>
                                                <tr class="hover:bg-gray-50 text-gray-700 text-sm">
                                                    <td class="py-3 px-4 border-b"><?= date('M d, Y', strtotime($log['date'])); ?></td>
                                                    <td class="py-3 px-4 border-b"><?= $log['check_in'] ? date('h:i A', strtotime($log['check_in'])) : 'N/A'; ?></td>
                                                    <td class="py-3 px-4 border-b"><?= $log['check_out'] ? date('h:i A', strtotime($log['check_out'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">You have no attendance records yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>