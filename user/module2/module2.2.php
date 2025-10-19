<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Mock participant login
// =========================
$participant_id   = $_SESSION['user_id'] ?? 1;
$participant_name = $_SESSION['user_name'] ?? "Guest";

// =========================
// Fetch participant role
// =========================
$stmt = $conn->prepare("SELECT * FROM role_assignments WHERE participant_id = ?");
$stmt->bind_param("i", $participant_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

// =========================
// Handle age submission
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['age'])) {
    $age = intval($_POST['age']);

    // Assign role based on age
    if ($age < 30) {
        $role = "First Responder";
    } elseif ($age < 50) {
        $role = "Support Staff";
    } else {
        $role = "Observer";
    }

    // Insert or update record
    $stmt = $conn->prepare("INSERT INTO role_assignments 
        (participant_id, age, participant_name, role, acceptance_status) 
        VALUES (?, ?, ?, ?, 'Pending')
        ON DUPLICATE KEY UPDATE age=VALUES(age), role=VALUES(role)");
    $stmt->bind_param("iiss", $participant_id, $age, $participant_name, $role);
    $stmt->execute();
    $stmt->close();

    header("Location: module2.2.php"); // refresh to show updated role
    exit();
}

// =========================
// Handle acceptance / decline
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $decision = $_POST['decision'];
    $stmt = $conn->prepare("UPDATE role_assignments 
                            SET acceptance_status = ? 
                            WHERE participant_id = ?");
    $stmt->bind_param("si", $decision, $participant_id);
    $stmt->execute();
    $stmt->close();

    header("Location: module2.2.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🎭 Role Assignment</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-2xl shadow-lg">

      <h1 class="text-3xl font-bold text-blue-700 mb-6">🎭 Your Role Assignment</h1>

      <?php if (!$assignment): ?>
        <!-- Step 1: Ask for age -->
        <form method="POST" class="space-y-4">
          <label class="block text-lg font-medium text-gray-700">
            Please enter your age:
          </label>
          <input type="number" name="age" required
                 class="w-full p-3 border rounded-lg focus:ring focus:ring-blue-300">
          <button type="submit" 
                  class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">
            Submit
          </button>
        </form>

      <?php else: ?>
        <!-- Step 2: Show role -->
        <div class="p-4 bg-gray-50 border rounded-xl">
          <p class="text-lg"><strong>Name:</strong> <?= htmlspecialchars($assignment['participant_name']); ?></p>
          <p class="text-lg"><strong>Age:</strong> <?= htmlspecialchars($assignment['age']); ?></p>
          <p class="text-xl font-bold text-green-700 mt-3">
            🎭 Assigned Role: <?= htmlspecialchars($assignment['role']); ?>
          </p>
          <p class="mt-2 text-gray-600">Acceptance Status: 
            <strong><?= htmlspecialchars($assignment['acceptance_status']); ?></strong>
          </p>
        </div>

        <?php if ($assignment['acceptance_status'] === "Pending"): ?>
          <form method="POST" class="mt-4 flex space-x-4">
            <button type="submit" name="decision" value="Accepted"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
              ✅ Accept
            </button>
            <button type="submit" name="decision" value="Declined"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
              ❌ Decline
            </button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
