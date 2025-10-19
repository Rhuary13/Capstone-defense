<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management"; // ✅ same as admin
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Mock participant login (replace with real session later)
// =========================
$participant_id = $_SESSION['user_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'participant'; // should be 'participant'

// =========================
// Mark notification as read
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notif_id);
    $stmt->execute();
    $stmt->close();
}

// =========================
// Fetch notifications for participants
// - show global (all)
// - show participant-only
// =========================
$sql = "SELECT * FROM notifications 
        WHERE recipient_type = 'all' OR recipient_type = 'participant'
        ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🔔 Notifications</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-6 overflow-y-auto">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-lg">
      <h1 class="text-3xl font-bold text-blue-700 mb-6">🔔 Notifications</h1>

      <?php if ($result && $result->num_rows > 0): ?>
        <div class="space-y-6">
          <?php while ($row = $result->fetch_assoc()): ?>
            <div class="p-6 border rounded-xl shadow-sm <?= $row['is_read'] ? 'bg-gray-50' : 'bg-yellow-50'; ?>">
              <h2 class="text-2xl font-semibold text-gray-800 mb-2">
                <?= htmlspecialchars($row['title']); ?>
              </h2>
              <p class="text-lg text-gray-700 mb-4 leading-relaxed">
                <?= nl2br(htmlspecialchars($row['message'])); ?>
              </p>
              <p class="text-sm text-gray-500 mb-4">
                📅 <?= date("F j, Y, g:i A", strtotime($row['created_at'])); ?>
                <?php if ($row['recipient_type'] === 'all'): ?>
                  <span class="ml-2 text-blue-600 font-semibold">(🌍 Global Admin Announcement)</span>
                <?php endif; ?>
              </p>

              <?php if (!$row['is_read']): ?>
                <form method="POST" class="inline-block">
                  <input type="hidden" name="notif_id" value="<?= $row['id']; ?>">
                  <button type="submit" 
                          class="bg-blue-600 text-white px-5 py-2 rounded-lg text-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    ✅ Mark as Read
                  </button>
                </form>
              <?php else: ?>
                <span class="text-green-700 font-bold text-lg">✔️ Read</span>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p class="text-red-600 text-xl font-semibold">❌ No notifications available at the moment.</p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
