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
// Create table if missing
// =========================
$conn->query("CREATE TABLE IF NOT EXISTS safety_protocols (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    instructions TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// =========================
// Mock participant login
// =========================
$participant_id = $_SESSION['user_id'] ?? 1;

$message = "";

// =========================
// Handle acknowledgement
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge'])) {
    $message = "✅ Thank you! You have acknowledged the safety protocols.";
}

// =========================
// Fetch latest safety protocol
// =========================
$sql = "SELECT * FROM safety_protocols ORDER BY created_at DESC LIMIT 1";
$result = $conn->query($sql);
$protocol = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🦺 Safety Protocols</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-lg">
      <h1 class="text-3xl font-bold text-red-700 mb-6 text-center">🦺 Safety Protocols</h1>

      <?php if (!empty($message)): ?>
        <div class="p-4 mb-6 bg-green-100 border border-green-300 text-green-800 text-lg rounded-lg text-center">
          <?= $message ?>
        </div>
      <?php endif; ?>

      <?php if ($protocol): ?>
        <div class="mb-6 text-center">
          <h2 class="text-2xl font-semibold text-gray-800 mb-4">
            <?= htmlspecialchars($protocol['title']); ?>
          </h2>
          <p class="text-lg text-gray-700 leading-relaxed whitespace-pre-line">
            <?= nl2br(htmlspecialchars($protocol['instructions'])); ?>
          </p>
        </div>

        <form method="POST" class="text-center">
          <button type="submit" name="acknowledge"
                  class="w-full md:w-auto bg-red-600 text-white text-xl px-8 py-4 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300">
            👍 I Understand
          </button>
        </form>
      <?php else: ?>
        <p class="text-gray-600 text-xl text-center">
          ℹ️ No safety protocols available at the moment. Please check back later.
        </p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
