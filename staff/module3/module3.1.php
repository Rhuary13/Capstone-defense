<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; // adjust if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle manual registration by staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_participant'])) {
    $name  = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $pass  = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $conn->query("INSERT INTO participants (full_name, email, phone, password) 
                  VALUES ('$name', '$email', '$phone', '$pass')");
}

// Handle verification update
if (isset($_GET['verify_id'])) {
    $id = intval($_GET['verify_id']);
    $conn->query("UPDATE participants SET status='Verified' WHERE id=$id");
    header("Location: module_registration.php");
    exit();
}

// Fetch participants
$participants = $conn->query("SELECT * FROM participants ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Registration Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Registration Portal</h1>
      <p class="text-sm text-slate-500 mt-1">Assist participants with registration, troubleshooting, and data verification.</p>
    </div>

    <!-- Manual Registration Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Manual Registration</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Full Name</label>
          <input type="text" name="full_name" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input type="email" name="email" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Phone</label>
          <input type="text" name="phone" 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Password</label>
          <input type="password" name="password" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" name="add_participant" 
                  class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Register Participant
          </button>
        </div>
      </form>
    </div>

    <!-- Participant List -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Participants</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">Name</th>
              <th class="p-3 border-b">Email</th>
              <th class="p-3 border-b">Phone</th>
              <th class="p-3 border-b">Status</th>
              <th class="p-3 border-b">Registered</th>
              <th class="p-3 border-b">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($participants->num_rows > 0): ?>
              <?php while ($p = $participants->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?= htmlspecialchars($p['full_name']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($p['email']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($p['phone']); ?></td>
                  <td class="p-3 border-b">
                    <span class="px-2 py-1 text-xs rounded 
                                 <?= $p['status'] === 'Verified' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                      <?= htmlspecialchars($p['status']); ?>
                    </span>
                  </td>
                  <td class="p-3 border-b"><?= date("Y-m-d", strtotime($p['created_at'])); ?></td>
                  <td class="p-3 border-b">
                    <?php if ($p['status'] !== 'Verified'): ?>
                      <a href="?verify_id=<?= $p['id']; ?>" 
                         class="text-indigo-600 hover:underline">Verify</a>
                    <?php else: ?>
                      <span class="text-slate-400">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="p-4 text-center text-slate-500">No participants registered yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
