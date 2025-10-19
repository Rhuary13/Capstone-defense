<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Deploy scenario
if (isset($_POST['deploy_scenario'])) {
    $id = intval($_POST['scenario_id']);
    $conn->query("UPDATE scenarios SET status='deployed' WHERE id=$id");
}

// Update (customize) scenario
if (isset($_POST['update_scenario'])) {
    $id    = intval($_POST['scenario_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $desc  = $conn->real_escape_string($_POST['description']);
    $conn->query("UPDATE scenarios SET title='$title', description='$desc' WHERE id=$id");
}

// Fetch scenarios
$scenarios = $conn->query("SELECT * FROM scenarios ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scenario Templates</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Open modal with data
    function openCustomize(id, title, desc) {
      document.getElementById('scenario_id').value = id;
      document.getElementById('edit_title').value = title;
      document.getElementById('edit_desc').value = desc;
      document.getElementById('editModal').classList.remove('hidden');
    }
    function closeModal() {
      document.getElementById('editModal').classList.add('hidden');
    }
  </script>
</head>
<body class="h-screen flex bg-slate-50 text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Scenario Templates</h1>
      <p class="text-sm text-slate-500">Staff can customize and deploy training scenarios.</p>
    </div>

    <!-- Scenarios List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if ($scenarios && $scenarios->num_rows > 0): ?>
        <?php while ($row = $scenarios->fetch_assoc()): ?>
          <?php 
            // fallback for status column
            $status = isset($row['status']) && $row['status'] !== '' ? $row['status'] : 'draft'; 
          ?>
          <div class="bg-white shadow rounded-lg p-5 flex flex-col justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-700"><?php echo htmlspecialchars($row['title']); ?></h2>
              <p class="text-sm text-slate-500 mt-2"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
              <span class="inline-block mt-3 px-3 py-1 text-xs rounded-full 
                <?php echo $status=='deployed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                <?php echo ucfirst($status); ?>
              </span>
            </div>
            <div class="mt-4 flex gap-2">
              <button onclick="openCustomize('<?php echo $row['id']; ?>','<?php echo htmlspecialchars($row['title']); ?>','<?php echo htmlspecialchars($row['description']); ?>')" 
                      class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                Customize
              </button>
              <?php if ($status !== 'deployed'): ?>
              <form method="post">
                <input type="hidden" name="scenario_id" value="<?php echo $row['id']; ?>">
                <button type="submit" name="deploy_scenario" 
                        class="px-3 py-1 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                  Deploy
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-slate-500">No scenarios found.</p>
      <?php endif; ?>
    </div>
  </main>

  <!-- Customize Modal -->
  <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
      <h2 class="text-xl font-semibold mb-4">Customize Scenario</h2>
      <form method="post" class="space-y-4">
        <input type="hidden" id="scenario_id" name="scenario_id">
        <div>
          <label class="block text-sm font-medium mb-1">Title</label>
          <input type="text" id="edit_title" name="title" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea id="edit_desc" name="description" rows="4" required
                    class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" onclick="closeModal()" 
                  class="px-4 py-2 bg-slate-300 text-slate-700 rounded-md hover:bg-slate-400">
            Cancel
          </button>
          <button type="submit" name="update_scenario" 
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
