<?php
// damage_investigation.php
session_start();

// --- DB Connection ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS damage_investigations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  investigation_notes TEXT,
  investigated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES damage_reports(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS equipment_audits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  audit_reason VARCHAR(255),
  audit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Pending','In Progress','Completed') DEFAULT 'Pending',
  FOREIGN KEY (report_id) REFERENCES damage_reports(id) ON DELETE CASCADE
)");

// --- Handle Investigation Submission ---
if (isset($_POST['investigation_notes'])) {
    $report_id = $_POST['report_id'];
    $notes     = $_POST['investigation_notes'];

    $stmt = $conn->prepare("INSERT INTO damage_investigations (report_id, investigation_notes) VALUES (?, ?)");
    $stmt->bind_param("is", $report_id, $notes);
    $stmt->execute();
    $stmt->close();
}

// --- Handle Audit Trigger ---
if (isset($_POST['trigger_audit'])) {
    $report_id = $_POST['report_id'];
    $reason    = "Triggered due to damage report";

    $stmt = $conn->prepare("INSERT INTO equipment_audits (report_id, audit_reason) VALUES (?, ?)");
    $stmt->bind_param("is", $report_id, $reason);
    $stmt->execute();
    $stmt->close();
}

// Fetch Damage Reports with Investigations + Audits
$reports = $conn->query("
  SELECT dr.*, 
         (SELECT COUNT(*) FROM damage_investigations di WHERE di.report_id=dr.id) AS investigations_count,
         (SELECT COUNT(*) FROM equipment_audits ea WHERE ea.report_id=dr.id) AS audits_count
  FROM damage_reports dr
  ORDER BY dr.incident_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Damage Investigation & Audit</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?> 

  <!-- Main Content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="bg-purple-700 text-white p-4 rounded-lg shadow mb-6 text-xl font-bold">
      Investigations & Equipment Audits
    </header>

    <!-- Reports Table -->
    <div class="bg-white rounded-2xl shadow-md p-6">
      <h2 class="text-lg font-semibold mb-4">Damage Reports for Investigation</h2>
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-gray-200 text-left text-sm">
            <th class="p-2 border">Item</th>
            <th class="p-2 border">Type</th>
            <th class="p-2 border">Date</th>
            <th class="p-2 border">Investigations</th>
            <th class="p-2 border">Audits</th>
            <th class="p-2 border">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $reports->fetch_assoc()) : ?>
            <tr class="hover:bg-gray-50 text-sm">
              <td class="p-2 border"><?php echo htmlspecialchars($row['item_name']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['damage_type']); ?></td>
              <td class="p-2 border"><?php echo htmlspecialchars($row['incident_date']); ?></td>
              <td class="p-2 border text-center"><?php echo $row['investigations_count']; ?></td>
              <td class="p-2 border text-center"><?php echo $row['audits_count']; ?></td>
              <td class="p-2 border">
                <!-- Investigation Form -->
                <form method="POST" class="mb-2">
                  <input type="hidden" name="report_id" value="<?php echo $row['id']; ?>">
                  <textarea name="investigation_notes" placeholder="Add investigation notes..." 
                            class="w-full p-1 border rounded mb-2"></textarea>
                  <button type="submit" 
                          class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                    Save Investigation
                  </button>
                </form>

                <!-- Audit Trigger -->
                <form method="POST">
                  <input type="hidden" name="report_id" value="<?php echo $row['id']; ?>">
                  <button type="submit" name="trigger_audit"
                          class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">
                    Trigger Audit
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>
            