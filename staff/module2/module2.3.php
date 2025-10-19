<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; // adjust db name if needed

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle new protocol submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_protocol'])) {
    $protocol_name = $conn->real_escape_string($_POST['protocol_name']);
    $description   = $conn->real_escape_string($_POST['description']);

    $conn->query("INSERT INTO safety_protocols (protocol_name, description, compliance_status) 
                  VALUES ('$protocol_name', '$description', 'Pending')");
}

// Handle compliance status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id     = (int) $_POST['id'];
    $status = $conn->real_escape_string($_POST['status']);

    $conn->query("UPDATE safety_protocols SET compliance_status='$status' WHERE id=$id");
}

// Fetch all protocols
$protocols = $conn->query("SELECT * FROM safety_protocols ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Safety Protocols</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Safety Protocols</h1>
      <p class="text-sm text-slate-500 mt-1">Implement safety measures and monitor compliance.</p>
    </div>

    <!-- Add Protocol Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Add New Safety Protocol</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Protocol Name</label>
          <input type="text" name="protocol_name" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea name="description" rows="3" 
                    class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200"></textarea>
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" name="add_protocol" 
                  class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Add Protocol
          </button>
        </div>
      </form>
    </div>

    <!-- Safety Protocols Table -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Safety Protocols List</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">Protocol</th>
              <th class="p-3 border-b">Description</th>
              <th class="p-3 border-b">Compliance</th>
              <th class="p-3 border-b">Created</th>
              <th class="p-3 border-b">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($protocols->num_rows > 0): ?>
              <?php while ($p = $protocols->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?= htmlspecialchars($p['protocol_name']); ?></td>
                  <td class="p-3 border-b"><?= htmlspecialchars($p['description']); ?></td>
                  <td class="p-3 border-b">
                    <span class="px-2 py-1 rounded text-xs 
                      <?= $p['compliance_status'] === 'Compliant' ? 'bg-green-100 text-green-700' : 
                          ($p['compliance_status'] === 'Non-Compliant' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                      <?= htmlspecialchars($p['compliance_status']); ?>
                    </span>
                  </td>
                  <td class="p-3 border-b"><?= date("Y-m-d", strtotime($p['created_at'])); ?></td>
                  <td class="p-3 border-b">
                    <form method="POST" class="flex gap-2">
                      <input type="hidden" name="id" value="<?= $p['id']; ?>">
                      <select name="status" class="border rounded-md p-1 text-sm">
                        <option value="Pending" <?= $p['compliance_status']==='Pending'?'selected':''; ?>>Pending</option>
                        <option value="Compliant" <?= $p['compliance_status']==='Compliant'?'selected':''; ?>>Compliant</option>
                        <option value="Non-Compliant" <?= $p['compliance_status']==='Non-Compliant'?'selected':''; ?>>Non-Compliant</option>
                      </select>
                      <button type="submit" name="update_status" 
                              class="bg-indigo-600 text-white px-2 py-1 rounded-md text-xs hover:bg-indigo-700">
                        Update
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="p-4 text-center text-slate-500">No safety protocols added yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
