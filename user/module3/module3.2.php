<?php
// attendance.php

session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management"; // match admin DB

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Mock participant session (remove when real login exists)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; 
    $_SESSION['full_name'] = "John Doe"; 
    $_SESSION['role'] = "participant"; 
    $_SESSION['user_type'] = "participant";
}

$user_id = $_SESSION['user_id'] ?? 0; // default 0 if not logged in
$user_role = $_SESSION['role'] ?? 'participant';

// Prevent undefined array key warnings
$user_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Guest';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'participant';

$message = "";

// Handle Check In / Check Out
if ($_SERVER["REQUEST_METHOD"] === "POST" && $user_type === "participant") {
    $today = date("Y-m-d");

    if (isset($_POST['check_in'])) {
        // prevent duplicate check-ins
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id=? AND date=?");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "❌ Already checked in today.";
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (user_id, full_name, user_type, check_in, date) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("isss", $user_id, $user_full_name, $user_type, $today);
            if ($stmt->execute()) {
                $message = "✅ Successfully Checked In!";
            } else {
                $message = "❌ Check-in failed: " . $stmt->error;
            }
        }
        $stmt->close();

    } elseif (isset($_POST['check_out'])) {
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id=? AND date=? AND check_out IS NULL");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE attendance SET check_out = NOW() WHERE user_id=? AND date=?");
            $stmt->bind_param("is", $user_id, $today);
            if ($stmt->execute()) {
                $message = "✅ Successfully Checked Out!";
            } else {
                $message = "❌ Check-out failed: " . $stmt->error;
            }
        } else {
            $message = "❌ Must check in before checking out.";
        }
        $stmt->close();
    }
}

// Get last log
$stmt = $conn->prepare("SELECT check_in, check_out, date FROM attendance WHERE user_id=? ORDER BY date DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$last_action = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Participant Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100 text-gray-800">

    <?php include '../sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6">
        <div class="max-w-xl mx-auto bg-white shadow-xl rounded-2xl p-8 text-center">
            <h1 class="text-3xl font-bold mb-6 text-blue-700">My Attendance</h1>

            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg bg-green-100 text-green-700 text-lg font-semibold">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mb-6">
                <p class="text-xl">Last Record:</p>
                <?php if ($last_action): ?>
                    <p class="mt-2 text-2xl font-bold text-gray-700">
                        <?= $last_action['check_in'] ? "Checked in: " . date("M d, Y h:i A", strtotime($last_action['check_in'])) : "" ?>
                        <?= $last_action['check_out'] ? "<br>Checked out: " . date("M d, Y h:i A", strtotime($last_action['check_out'])) : "" ?>
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-gray-600">No records yet.</p>
                <?php endif; ?>
            </div>

            <form method="post" class="flex flex-col gap-4">
                <button type="submit" name="check_in"
                        class="w-full py-4 text-2xl bg-green-500 hover:bg-green-600 text-white rounded-2xl shadow-lg">
                    ✅ Check In
                </button>
                <button type="submit" name="check_out"
                        class="w-full py-4 text-2xl bg-red-500 hover:bg-red-600 text-white rounded-2xl shadow-lg">
                    ❌ Check Out
                </button>
            </form>
        </div>
    </main>
</body>
</html>
