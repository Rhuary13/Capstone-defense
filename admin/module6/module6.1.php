<?php
session_start();

/**
 * module3.1.php
 * Registration Portal
 *
 * This file serves two purposes:
 * 1. Participant Registration Form: Allows participants to register for the training.
 * 2. Admin Registration Management: Allows the admin to view and manage registered participants.
 */

// =========================
// Database Connection
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
// Create Registrations Table if it doesn't exist
// =========================
$sql_create_table = "
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `contact_number` VARCHAR(50) NOT NULL,
  `disaster_type` VARCHAR(100) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($sql_create_table)) {
    die("Error creating registrations table: " . $conn->error);
}

// =========================
// Security Check
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? 'participant'; // Default to 'participant' if not set

$success_message = '';
$error_message = '';

// =========================
// Handle Participant Registration (POST request)
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'participant') {
    // Collect form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $disaster_type = trim($_POST['disaster_type']);
    $location = trim($_POST['location']);

    // Basic validation
    if (empty($full_name) || empty($email) || empty($contact_number) || empty($disaster_type) || empty($location)) {
        $error_message = "❌ All fields are required.";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO registrations (full_name, email, contact_number, disaster_type, location) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $email, $contact_number, $disaster_type, $location);

        if ($stmt->execute()) {
            $success_message = "✅ Registration successful! Thank you for signing up.";
            // Clear form fields
            $_POST = array();
        } else {
            // Check for duplicate email error
            if ($conn->errno == 1062) {
                $error_message = "❌ This email is already registered.";
            } else {
                $error_message = "❌ Registration failed. Please try again later.";
            }
        }
        $stmt->close();
    }
}

// =========================
// Fetch Registered Participants for Admin View
// =========================
$registered_participants = [];
if ($user_role === 'admin') {
    $result = $conn->query("SELECT * FROM registrations ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $registered_participants[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Portal</title>
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
                <i data-lucide="clipboard-list" class="w-6 h-6 text-green-600"></i>
                Registration Portal
            </h1>
        </nav>

        <main class="flex-1 px-6 py-8 mt-16 h-[calc(100vh-4rem)] overflow-y-auto flex justify-center">
            <div class="w-full max-w-5xl">
                <?php if ($user_role === 'admin'): ?>
                    <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <i data-lucide="users" class="w-6 h-6 text-green-600"></i> Registered Participants
                        </h2>

                        <?php if (!empty($registered_participants)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border-collapse">
                                    <thead>
                                        <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
                                            <th class="py-3 px-4 border-b">Name</th>
                                            <th class="py-3 px-4 border-b">Email</th>
                                            <th class="py-3 px-4 border-b">Contact</th>
                                            <th class="py-3 px-4 border-b">Training Type</th>
                                            <th class="py-3 px-4 border-b">Location</th>
                                            <th class="py-3 px-4 border-b">Registered On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registered_participants as $participant): ?>
                                            <tr class="hover:bg-gray-50 text-gray-700 text-sm">
                                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($participant['full_name']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($participant['email']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($participant['contact_number']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($participant['disaster_type']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($participant['location']); ?></td>
                                                <td class="py-3 px-4 border-b"><?= date('M d, Y h:i A', strtotime($participant['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No participants have registered yet.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-100">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                            Register for Training & Simulation
                        </h2>
                        <p class="text-gray-600 text-center mb-8">Please fill in the form to secure your spot in the Disaster Preparedness Training & Simulation.</p>
                        
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

                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" id="full_name" name="full_name" required
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" id="email" name="email" required
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number" required
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="disaster_type" class="block text-sm font-medium text-gray-700">Disaster Preparedness Training</label>
                                <select id="disaster_type" name="disaster_type" required
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select a training type</option>
                                    <option value="Earthquake">Earthquake Preparedness</option>
                                    <option value="Flood">Flood Preparedness</option>
                                    <option value="Typhoon">Typhoon Preparedness</option>
                                    <option value="Fire">Fire Safety</option>
                                </select>
                            </div>
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700">Location (e.g., City, Province)</label>
                                <input type="text" id="location" name="location" required
                                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div class="flex justify-center">
                                <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                                    Register for Event
                                </button>
                            </div>
                        </form>
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