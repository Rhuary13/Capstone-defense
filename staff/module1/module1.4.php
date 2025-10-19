<?php
// staff_records.php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "training_management"; // <-- use your actual DB name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Create Table if Missing ---
$conn->query("CREATE TABLE IF NOT EXISTS records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  participant_name VARCHAR(255) NOT NULL,
  module VARCHAR(255) NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  validity_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $participant_name = $_POST['participant_name'];
    $module           = $_POST['module'];
    $score            = $_POST['score'];
    $validity_date    = $_POST['validity_date'];

    $stmt = $conn->prepare("INSERT INTO records (participant_name, module, score, validity_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $participant_name, $module, $score, $validity_date);
    $stmt->execute();
    $stmt->close();
}

// --- Fetch All Records ---
$result = $conn->query("SELECT * FROM records ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Records</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?> 

  <!-- Main Content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="bg-purple-700 text-white p-4 rounded-lg shadow mb-6 text-xl font-bold">
      Records Management - Staff
    </header>

    <!-- Add Record Form -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Add New Record</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
          <label class="block text-sm font-medium mb-1">Participant Name</label>
          <input type="text" name="participant_name" required class="w-full p-2 border rounded-lg focus:ring focus:ring-purple-300">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Module</label>
          <input type="text" name="module" required class="w-full p-2 border rounded-lg focus:ring focus:ring-purple-300">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Score</label>
          <input type="number" step="0.01" name="score" required class="w-full p-2 border rounded-lg focus:ring focus:ring-purple-300">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Validity Date</label>
          <input type="date" name="validity_date" required class="w-full p-2 border rounded-lg focus:ring focus:ring-purple-300">
        </div>

        <div class="md:col-span-2 flex justify-end">
          <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg shadow">
            Save Record
          </button>
        </div>
      </form>
    </div>

    <!-- Records List -->
    <div class="bg-white rounded-2xl shadow-md p-6">
      <h2 class="text-lg font-semibold mb-4">Participant Records</h2>
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-gray-200 text-left text-sm">
            <th class="p-2 border">Name</th>
            <th class="p-2 border">Module</th>
            <th class="p-2 border">Score</th>
            <th class="p-2 border">Validity</th>
            <th class="p-2 border">Date Added</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()) : ?>
            <tr class="hover:bg-gray-50 text-sm">
              <td class="p-2 border font-medium"><?php echo htmlspecialchars($row['participant_name']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['module']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['score']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['validity_date']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>
