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

// Handle new role assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $participant = $conn->real_escape_string($_POST['participant_name']);
    $role        = $conn->real_escape_string($_POST['role']);

    $conn->query("INSERT INTO role_assignments (participant_name, role, acceptance_status) 
                  VALUES ('$participant', '$role', 'Pending')");
}

// Handle acceptance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id     = (int) $_POST['id'];
    $status = $conn->real_escape_string($_POST['status']);

    $conn->query("UPDATE role_assignments SET acceptance_status='$status' WHERE id=$id");
}

// Fetch all assignments
$assignments = $conn->query("SELECT * FROM role_assignments ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Role Assignment</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Role Assignment</h1>
      <p class="text-sm text-slate-500 mt-1">Brief participants and confirm role acceptance.</p>
    </div>

    <!-- Add Role Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Assign New Role</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Participant Name</label>
          <input type="text" name="participant_name" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Role</label>
          <input type="text" name="role" required class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" name="assign_role" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Assign Role
          </button>
        </div>
      </form>
    </div>

    <!-- Role Assignments Table -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Assigned Roles</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">Participant</th>
              <th class="p-3 border-b">Role</th>
              <th class="p-3 border-b">Status</th>
              <th class="p-3 border-b">Assigned On</th>
              <th class="p-3 border-b">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($assignments->num_rows > 0): ?>
              <?php while ($a = $assignments->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?= htmlspecialchars($a['participant_name']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($a['role']); ?></td>
                  <td class="p-3 border-b">
                    <span class="px-2 py-1 rounded text-xs 
                      <?= $a['acceptance_status'] === 'Accepted' ? 'bg-green-100 text-green-700' : 
                          ($a['acceptance_status'] === 'Declined' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                      <?= htmlspecialchars($a['acceptance_status']); ?>
                    </span>
                  </td>
                  <td class="p-3 border-b"><?= date("Y-m-d", strtotime($a['created_at'])); ?></td>
                  <td class="p-3 border-b">
                    <form method="POST" class="flex gap-2">
                      <input type="hidden" name="id" value="<?= $a['id']; ?>">
                      <select name="status" class="border rounded-md p-1 text-sm">
                        <option value="Pending"   <?= $a['acceptance_status']==='Pending'?'selected':''; ?>>Pending</option>
                        <option value="Accepted"  <?= $a['acceptance_status']==='Accepted'?'selected':''; ?>>Accepted</option>
                        <option value="Declined"  <?= $a['acceptance_status']==='Declined'?'selected':''; ?>>Declined</option>
                      </select>
                      <button type="submit" name="update_status" class="bg-indigo-600 text-white px-2 py-1 rounded-md text-xs hover:bg-indigo-700">
                        Update
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="p-4 text-center text-slate-500">No roles assigned yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
