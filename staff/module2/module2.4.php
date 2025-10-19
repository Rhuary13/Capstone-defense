<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management"; // ✅ same DB as admin
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// Mock staff session (replace later with real session login)
// =========================
$user_role = $_SESSION['role'] ?? 'staff';

// =========================
// Fetch notifications (global + staff-specific)
// =========================
$sql = "SELECT * FROM notifications 
        WHERE recipient_type = 'all' OR recipient_type = 'staff'
        ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🔔 Staff Notifications</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">🔔 Staff Notifications</h1>
      <p class="text-sm text-slate-500 mt-1">View important updates posted by Admin.</p>
    </div>

    <!-- Notifications List -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Recent Notifications</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">Title</th>
              <th class="p-3 border-b">Message</th>
              <th class="p-3 border-b">Type</th>
              <th class="p-3 border-b">Date Sent</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while ($n = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b font-medium"><?= htmlspecialchars($n['title']); ?></td>
                  <td class="p-3 border-b"><?= nl2br(htmlspecialchars($n['message'])); ?></td>
                  <td class="p-3 border-b">
                    <?= $n['recipient_type'] === 'all' ? '🌍 All' : '👨‍💼 Staff'; ?>
                  </td>
                  <td class="p-3 border-b"><?= date("F j, Y, g:i A", strtotime($n['created_at'])); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="p-4 text-center text-slate-500">No notifications available.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
